<?php

declare(strict_types=1);

namespace App\Service\VCS\Client;

use App\Repository\AppSettingsRepository;
use App\Service\VCS\Client\Exception\GitHubApiException;
use App\Service\VCS\Client\Exception\RepositoryAccessDeniedException;
use App\Service\VCS\Client\Exception\RepositoryNotFoundException;
use App\Service\VCS\GitTree;
use App\Service\VCS\GitTreeEntry;
use App\Service\VCS\VCSInterface;
use App\Service\VCS\VCSProject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class GithubVCS implements VCSInterface
{
    private const API_BASE_URL = 'https://api.github.com';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AppSettingsRepository $appSettingsRepository,
        // Falls back to the env var when nothing is configured from the settings page,
        // so a self-hosted install can still set it once at deploy time if preferred.
        #[Autowire('%env(GITHUB_TOKEN)%')]
        private readonly string $fallbackGithubToken = '',
    ) {
    }

    public function supports(string $sshLink): bool
    {
        return str_contains($sshLink, 'github.com');
    }

    public function getVCSInfo(string $projectPath): VCSProject
    {
        [$owner, $repo] = $this->parseOwnerAndRepo($projectPath);

        $response = $this->request('GET', \sprintf('/repos/%s/%s', $owner, $repo));

        $data = $this->decode($response, $owner, $repo);

        return new VCSProject(
            name: $data['name'] ?? throw new GitHubApiException(\sprintf('Unable to determine the name of "%s/%s".', $owner, $repo)),
            owner: $data['owner']['login'] ?? throw new GitHubApiException(\sprintf('Unable to determine the owner of "%s/%s".', $owner, $repo)),
        );
    }

    public function getTree(string $sshLink): GitTree
    {
        [$owner, $repo] = $this->parseOwnerAndRepo($sshLink);

        $defaultBranch = $this->getDefaultBranch($owner, $repo);

        $response = $this->request('GET', \sprintf('/repos/%s/%s/git/trees/%s', $owner, $repo, rawurlencode($defaultBranch)), [
            'query' => ['recursive' => 1],
        ]);

        if (409 === $response->getStatusCode()) {
            // Empty repository: nothing to discover, not an error.
            return new GitTree([], false);
        }

        $data = $this->decode($response, $owner, $repo);

        $entries = [];
        foreach ($data['tree'] ?? [] as $item) {
            if (($item['type'] ?? null) !== 'blob') {
                continue;
            }

            $entries[] = new GitTreeEntry($item['path'], $item['type']);
        }

        return new GitTree($entries, (bool) ($data['truncated'] ?? false));
    }

    public function getFileContent(string $sshLink, string $path): ?string
    {
        [$owner, $repo] = $this->parseOwnerAndRepo($sshLink);

        $defaultBranch = $this->getDefaultBranch($owner, $repo);

        $encodedPath = implode('/', array_map(rawurlencode(...), explode('/', ltrim($path, '/'))));

        $response = $this->request('GET', \sprintf('/repos/%s/%s/contents/%s', $owner, $repo, $encodedPath), [
            'query' => ['ref' => $defaultBranch],
        ]);

        if (404 === $response->getStatusCode()) {
            return null;
        }

        $data = $this->decode($response, $owner, $repo);

        if ('base64' !== ($data['encoding'] ?? null) || !isset($data['content'])) {
            throw new GitHubApiException(\sprintf('Unexpected content encoding for "%s/%s/%s".', $owner, $repo, $path));
        }

        $decoded = base64_decode(str_replace("\n", '', $data['content']), true);

        if (false === $decoded) {
            throw new GitHubApiException(\sprintf('Unable to decode the content of "%s/%s/%s".', $owner, $repo, $path));
        }

        return $decoded;
    }

    private function getDefaultBranch(string $owner, string $repo): string
    {
        $response = $this->request('GET', \sprintf('/repos/%s/%s', $owner, $repo));

        $data = $this->decode($response, $owner, $repo);

        return $data['default_branch']
            ?? throw new GitHubApiException(\sprintf('Unable to determine the default branch of "%s/%s".', $owner, $repo));
    }

    /**
     * Read fresh on every request rather than cached at construction time: this
     * service can live inside a long-running worker process, and a token saved from
     * the settings page must take effect on the very next scan, not after a restart.
     */
    private function resolveGithubToken(): string
    {
        $token = $this->appSettingsRepository->get()->getGithubToken();

        return (null !== $token && '' !== $token) ? $token : $this->fallbackGithubToken;
    }

    private function request(string $method, string $path, array $options = []): ResponseInterface
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        $token = $this->resolveGithubToken();

        if ('' !== $token) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        try {
            $response = $this->httpClient->request($method, self::API_BASE_URL.$path, [...$options, 'headers' => $headers]);
            // Force the request to be sent now so transport errors surface here.
            $response->getStatusCode();

            return $response;
        } catch (HttpExceptionInterface $exception) {
            throw new GitHubApiException(\sprintf('GitHub API request "%s %s" failed: %s', $method, $path, $exception->getMessage()), previous: $exception);
        }
    }

    private function decode(ResponseInterface $response, string $owner, string $repo): array
    {
        $statusCode = $response->getStatusCode();

        if (404 === $statusCode) {
            throw new RepositoryNotFoundException(\sprintf('Repository "%s/%s" was not found.', $owner, $repo));
        }

        if (\in_array($statusCode, [401, 403], true)) {
            throw new RepositoryAccessDeniedException(\sprintf('Access to repository "%s/%s" was denied.', $owner, $repo));
        }

        if ($statusCode >= 400) {
            throw new GitHubApiException(\sprintf('GitHub API returned status code %d for "%s/%s".', $statusCode, $owner, $repo));
        }

        try {
            return $response->toArray(false);
        } catch (HttpExceptionInterface $exception) {
            throw new GitHubApiException(\sprintf('Unable to decode the GitHub API response for "%s/%s": %s', $owner, $repo, $exception->getMessage()), previous: $exception);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseOwnerAndRepo(string $sshLink): array
    {
        if (!preg_match('#github\.com[:/]+(?<owner>[^/]+)/(?<repo>[^/]+?)(?:\.git)?/?$#', $sshLink, $matches)) {
            throw new GitHubApiException(\sprintf('Unable to parse a GitHub owner/repo from "%s".', $sshLink));
        }

        return [$matches['owner'], $matches['repo']];
    }
}
