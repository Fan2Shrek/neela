<?php

declare(strict_types=1);

namespace App\Service\VCS\Client;

use App\Service\VCS\Client\Exception\GitHubApiException;
use App\Service\VCS\Client\Exception\RepositoryAccessDeniedException;
use App\Service\VCS\Client\Exception\RepositoryNotFoundException;
use App\Service\VCS\GitTree;
use App\Service\VCS\GitTreeEntry;
use App\Service\VCS\VCSInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class GithubVCS implements VCSInterface
{
    private const API_BASE_URL = 'https://api.github.com';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $githubToken = '',
    ) {
    }

    public function supports(string $sshLink): bool
    {
        return str_contains($sshLink, 'github.com');
    }

    public function getVCSInfo(string $projectPath): array
    {
        throw new \Exception('Not implemented');
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

    private function getDefaultBranch(string $owner, string $repo): string
    {
        $response = $this->request('GET', \sprintf('/repos/%s/%s', $owner, $repo));

        $data = $this->decode($response, $owner, $repo);

        return $data['default_branch']
            ?? throw new GitHubApiException(\sprintf('Unable to determine the default branch of "%s/%s".', $owner, $repo));
    }

    private function request(string $method, string $path, array $options = []): ResponseInterface
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        if ('' !== $this->githubToken) {
            $headers['Authorization'] = 'Bearer '.$this->githubToken;
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
