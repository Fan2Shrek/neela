<?php

declare(strict_types=1);

namespace App\Tests\Service\VCS\Client;

use App\Service\VCS\Client\Exception\GitHubApiException;
use App\Service\VCS\Client\Exception\RepositoryAccessDeniedException;
use App\Service\VCS\Client\Exception\RepositoryNotFoundException;
use App\Service\VCS\Client\GithubVCS;
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
}
