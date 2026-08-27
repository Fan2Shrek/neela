<?php

declare(strict_types=1);

namespace App\Tests\Service\VCS\Client;

use App\Service\VCS\Client\Exception\GitHubApiException;
use App\Service\VCS\Client\Exception\RepositoryAccessDeniedException;
use App\Service\VCS\Client\Exception\RepositoryNotFoundException;
use App\Entity\AppSettings;
use App\Repository\AppSettingsRepository;
use App\Service\VCS\Client\GithubVCS;
use App\Service\VCS\VCSProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GithubVCSTest extends TestCase
{
    public function testGetTreeReturnsOnlyBlobEntries(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['default_branch' => 'main'])),
            new MockResponse(json_encode([
                'truncated' => false,
                'tree' => [
                    ['path' => 'composer.json', 'type' => 'blob'],
                    ['path' => 'src', 'type' => 'tree'],
                    ['path' => 'app/front/package.json', 'type' => 'blob'],
                ],
            ])),
        ]);

        $tree = ($this->client($httpClient))->getTree('git@github.com:acme/my-project.git');

        self::assertFalse($tree->truncated);
        self::assertCount(2, $tree->entries);
        self::assertSame('composer.json', $tree->entries[0]->path);
        self::assertSame('app/front/package.json', $tree->entries[1]->path);
    }

    public function testHttpsUrlFormatIsSupported(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['default_branch' => 'main'])),
            new MockResponse(json_encode(['truncated' => false, 'tree' => []])),
        ]);

        $tree = ($this->client($httpClient))->getTree('https://github.com/acme/my-project.git');

        self::assertSame([], $tree->entries);
    }

    public function testTruncatedTreeIsReportedExplicitly(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['default_branch' => 'main'])),
            new MockResponse(json_encode(['truncated' => true, 'tree' => []])),
        ]);

        $tree = ($this->client($httpClient))->getTree('git@github.com:acme/big-repo.git');

        self::assertTrue($tree->truncated);
    }

    public function testEmptyRepositoryReturnsEmptyTreeWithoutError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['default_branch' => 'main'])),
            new MockResponse(json_encode(['message' => 'Git Repository is empty.']), ['http_code' => 409]),
        ]);

        $tree = ($this->client($httpClient))->getTree('git@github.com:acme/empty-repo.git');

        self::assertSame([], $tree->entries);
        self::assertFalse($tree->truncated);
    }

    public function testRepositoryNotFoundThrowsDedicatedException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Not Found']), ['http_code' => 404]),
        ]);

        $this->expectException(RepositoryNotFoundException::class);

        ($this->client($httpClient))->getTree('git@github.com:acme/missing.git');
    }

    public function testUnauthorizedAccessThrowsDedicatedException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Bad credentials']), ['http_code' => 401]),
        ]);

        $this->expectException(RepositoryAccessDeniedException::class);

        ($this->client($httpClient))->getTree('git@github.com:acme/private.git');
    }

    public function testForbiddenAccessThrowsDedicatedException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Forbidden']), ['http_code' => 403]),
        ]);

        $this->expectException(RepositoryAccessDeniedException::class);

        ($this->client($httpClient))->getTree('git@github.com:acme/forbidden.git');
    }

    public function testUnexpectedApiErrorThrowsGenericException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Internal Server Error']), ['http_code' => 500]),
        ]);

        $this->expectException(GitHubApiException::class);

        ($this->client($httpClient))->getTree('git@github.com:acme/broken.git');
    }

    public function testGetVCSInfoParsesOwnerAndRepoFromSshLink(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['name' => 'my-project', 'owner' => ['login' => 'acme']])),
        ]);

        $info = ($this->client($httpClient))->getVCSInfo('git@github.com:acme/my-project.git');

        self::assertEquals(new VCSProject(name: 'my-project', owner: 'acme'), $info);
    }

    public function testGetVCSInfoRequestsTheCorrectRepoPath(): void
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl) {
            $requestedUrl = $url;

            return new MockResponse(json_encode(['name' => 'my-project', 'owner' => ['login' => 'acme']]));
        });

        ($this->client($httpClient))->getVCSInfo('git@github.com:acme/my-project.git');

        self::assertSame('https://api.github.com/repos/acme/my-project', $requestedUrl);
    }

    public function testGetVCSInfoWithMissingRepositoryThrowsDedicatedException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Not Found']), ['http_code' => 404]),
        ]);

        $this->expectException(RepositoryNotFoundException::class);

        ($this->client($httpClient))->getVCSInfo('git@github.com:acme/missing.git');
    }

    public function testGetFileContentDecodesBase64Content(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['default_branch' => 'main'])),
            new MockResponse(json_encode([
                'encoding' => 'base64',
                'content' => base64_encode('{"require":{"symfony/console":"^6.4"}}'),
            ])),
        ]);

        $content = ($this->client($httpClient))->getFileContent('git@github.com:acme/my-project.git', 'composer.json');

        self::assertSame('{"require":{"symfony/console":"^6.4"}}', $content);
    }

    public function testGetFileContentRequestsTheEncodedPathAtTheDefaultBranch(): void
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl) {
            if (str_contains($url, '/contents/')) {
                $requestedUrl = $url;

                return new MockResponse(json_encode(['encoding' => 'base64', 'content' => base64_encode('{}')]));
            }

            return new MockResponse(json_encode(['default_branch' => 'main']));
        });

        ($this->client($httpClient))->getFileContent('git@github.com:acme/my-project.git', 'app/back/composer.json');

        self::assertStringContainsString('/repos/acme/my-project/contents/app/back/composer.json', $requestedUrl);
        self::assertStringContainsString('ref=main', $requestedUrl);
    }

    public function testGetFileContentReturnsNullWhenFileIsMissing(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['default_branch' => 'main'])),
            new MockResponse(json_encode(['message' => 'Not Found']), ['http_code' => 404]),
        ]);

        $content = ($this->client($httpClient))->getFileContent('git@github.com:acme/my-project.git', 'composer.lock');

        self::assertNull($content);
    }

    public function testAuthorizationHeaderIsSentWhenAGithubTokenIsConfigured(): void
    {
        $sentAuthorization = null;
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$sentAuthorization) {
            foreach ($options['headers'] as $header) {
                if (str_starts_with($header, 'Authorization:')) {
                    $sentAuthorization = $header;
                }
            }

            return new MockResponse(json_encode(['name' => 'my-project', 'owner' => ['login' => 'acme']]));
        });

        $this->client($httpClient, 'ghp_secret')->getVCSInfo('git@github.com:acme/my-project.git');

        self::assertSame('Authorization: Bearer ghp_secret', $sentAuthorization);
    }

    public function testNoAuthorizationHeaderIsSentWithoutAConfiguredToken(): void
    {
        $sentHeaders = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$sentHeaders) {
            $sentHeaders = $options['headers'];

            return new MockResponse(json_encode(['name' => 'my-project', 'owner' => ['login' => 'acme']]));
        });

        $this->client($httpClient)->getVCSInfo('git@github.com:acme/my-project.git');

        foreach ($sentHeaders as $header) {
            self::assertStringNotContainsString('Authorization:', $header);
        }
    }

    public function testDiscoverRepositoriesReturnsOrgReposWhenTheAccountIsAnOrganization(): void
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls) {
            $requestedUrls[] = $url;

            return new MockResponse(json_encode([
                ['full_name' => 'acme/api', 'ssh_url' => 'git@github.com:acme/api.git', 'private' => true, 'fork' => false],
                ['full_name' => 'acme/website', 'ssh_url' => 'git@github.com:acme/website.git', 'private' => false, 'fork' => false],
            ]));
        });

        $repositories = ($this->client($httpClient))->discoverRepositories('acme');

        self::assertCount(2, $repositories);
        self::assertSame('acme/api', $repositories[0]->name);
        self::assertSame('git@github.com:acme/api.git', $repositories[0]->sshLink);
        self::assertTrue($repositories[0]->private);
        self::assertFalse($repositories[1]->private);
        self::assertStringContainsString('/orgs/acme/repos', $requestedUrls[0]);
    }

    public function testDiscoverRepositoriesFallsBackToUserWhenTheAccountIsNotAnOrganization(): void
    {
        $requestedUrls = [];
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrls) {
            $requestedUrls[] = $url;

            if (str_contains($url, '/orgs/')) {
                return new MockResponse(json_encode(['message' => 'Not Found']), ['http_code' => 404]);
            }

            return new MockResponse(json_encode([
                ['full_name' => 'Fan2Shrek/kard', 'ssh_url' => 'git@github.com:Fan2Shrek/kard.git', 'private' => false, 'fork' => false],
            ]));
        });

        $repositories = ($this->client($httpClient))->discoverRepositories('Fan2Shrek');

        self::assertCount(1, $repositories);
        self::assertSame('Fan2Shrek/kard', $repositories[0]->name);
        self::assertStringContainsString('/orgs/Fan2Shrek/repos', $requestedUrls[0]);
        self::assertStringContainsString('/users/Fan2Shrek/repos', $requestedUrls[1]);
    }

    public function testDiscoverRepositoriesExcludesForks(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                ['full_name' => 'acme/original', 'ssh_url' => 'git@github.com:acme/original.git', 'private' => false, 'fork' => false],
                ['full_name' => 'acme/a-fork', 'ssh_url' => 'git@github.com:acme/a-fork.git', 'private' => false, 'fork' => true],
            ])),
        ]);

        $repositories = ($this->client($httpClient))->discoverRepositories('acme');

        self::assertCount(1, $repositories);
        self::assertSame('acme/original', $repositories[0]->name);
    }

    public function testDiscoverRepositoriesThrowsWhenNeitherOrgNorUserExists(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Not Found']), ['http_code' => 404]),
            new MockResponse(json_encode(['message' => 'Not Found']), ['http_code' => 404]),
        ]);

        $this->expectException(RepositoryNotFoundException::class);

        ($this->client($httpClient))->discoverRepositories('does-not-exist');
    }

    private function client(HttpClientInterface $httpClient, ?string $githubToken = null): GithubVCS
    {
        $settings = new AppSettings();
        $settings->setGithubToken($githubToken);

        $appSettingsRepository = $this->createStub(AppSettingsRepository::class);
        $appSettingsRepository->method('get')->willReturn($settings);

        return new GithubVCS($httpClient, $appSettingsRepository);
    }
}
