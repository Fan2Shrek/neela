<?php

declare(strict_types=1);

namespace App\Tests\Service\VCS\Client;

use App\Service\VCS\Client\Exception\GitHubApiException;
use App\Service\VCS\Client\Exception\RepositoryAccessDeniedException;
use App\Service\VCS\Client\Exception\RepositoryNotFoundException;
use App\Service\VCS\Client\GithubVCS;
use App\Service\VCS\VCSProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

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

        $tree = (new GithubVCS($httpClient))->getTree('git@github.com:acme/my-project.git');

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

        $tree = (new GithubVCS($httpClient))->getTree('https://github.com/acme/my-project.git');

        self::assertSame([], $tree->entries);
    }

    public function testTruncatedTreeIsReportedExplicitly(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['default_branch' => 'main'])),
            new MockResponse(json_encode(['truncated' => true, 'tree' => []])),
        ]);

        $tree = (new GithubVCS($httpClient))->getTree('git@github.com:acme/big-repo.git');

        self::assertTrue($tree->truncated);
    }

    public function testEmptyRepositoryReturnsEmptyTreeWithoutError(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['default_branch' => 'main'])),
            new MockResponse(json_encode(['message' => 'Git Repository is empty.']), ['http_code' => 409]),
        ]);

        $tree = (new GithubVCS($httpClient))->getTree('git@github.com:acme/empty-repo.git');

        self::assertSame([], $tree->entries);
        self::assertFalse($tree->truncated);
    }

    public function testRepositoryNotFoundThrowsDedicatedException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Not Found']), ['http_code' => 404]),
        ]);

        $this->expectException(RepositoryNotFoundException::class);

        (new GithubVCS($httpClient))->getTree('git@github.com:acme/missing.git');
    }

    public function testUnauthorizedAccessThrowsDedicatedException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Bad credentials']), ['http_code' => 401]),
        ]);

        $this->expectException(RepositoryAccessDeniedException::class);

        (new GithubVCS($httpClient))->getTree('git@github.com:acme/private.git');
    }

    public function testForbiddenAccessThrowsDedicatedException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Forbidden']), ['http_code' => 403]),
        ]);

        $this->expectException(RepositoryAccessDeniedException::class);

        (new GithubVCS($httpClient))->getTree('git@github.com:acme/forbidden.git');
    }

    public function testUnexpectedApiErrorThrowsGenericException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Internal Server Error']), ['http_code' => 500]),
        ]);

        $this->expectException(GitHubApiException::class);

        (new GithubVCS($httpClient))->getTree('git@github.com:acme/broken.git');
    }

    public function testGetVCSInfoParsesOwnerAndRepoFromSshLink(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['name' => 'my-project', 'owner' => ['login' => 'acme']])),
        ]);

        $info = (new GithubVCS($httpClient))->getVCSInfo('git@github.com:acme/my-project.git');

        self::assertEquals(new VCSProject(name: 'my-project', owner: 'acme'), $info);
    }

    public function testGetVCSInfoRequestsTheCorrectRepoPath(): void
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl) {
            $requestedUrl = $url;

            return new MockResponse(json_encode(['name' => 'my-project', 'owner' => ['login' => 'acme']]));
        });

        (new GithubVCS($httpClient))->getVCSInfo('git@github.com:acme/my-project.git');

        self::assertSame('https://api.github.com/repos/acme/my-project', $requestedUrl);
    }

    public function testGetVCSInfoWithMissingRepositoryThrowsDedicatedException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Not Found']), ['http_code' => 404]),
        ]);

        $this->expectException(RepositoryNotFoundException::class);

        (new GithubVCS($httpClient))->getVCSInfo('git@github.com:acme/missing.git');
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

        $content = (new GithubVCS($httpClient))->getFileContent('git@github.com:acme/my-project.git', 'composer.json');

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

        (new GithubVCS($httpClient))->getFileContent('git@github.com:acme/my-project.git', 'app/back/composer.json');

        self::assertStringContainsString('/repos/acme/my-project/contents/app/back/composer.json', $requestedUrl);
        self::assertStringContainsString('ref=main', $requestedUrl);
    }

    public function testGetFileContentReturnsNullWhenFileIsMissing(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['default_branch' => 'main'])),
            new MockResponse(json_encode(['message' => 'Not Found']), ['http_code' => 404]),
        ]);

        $content = (new GithubVCS($httpClient))->getFileContent('git@github.com:acme/my-project.git', 'composer.lock');

        self::assertNull($content);
    }
}
