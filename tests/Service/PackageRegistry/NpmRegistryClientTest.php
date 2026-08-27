<?php

declare(strict_types=1);

namespace App\Tests\Service\PackageRegistry;

use App\Enum\Stability;
use App\Service\PackageRegistry\Exception\PackageRegistryException;
use App\Service\PackageRegistry\NpmRegistryClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NpmRegistryClientTest extends TestCase
{
    public function testGetVersionsParsesTheAbbreviatedResponseAndSkipsPreReleases(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'name' => 'react',
                'versions' => [
                    '18.2.0' => ['name' => 'react', 'version' => '18.2.0', 'engines' => ['node' => '>=0.10.0']],
                    '19.0.0-rc.1' => ['name' => 'react', 'version' => '19.0.0-rc.1'],
                    '19.3.0-canary-a1124489-20260826' => ['name' => 'react', 'version' => '19.3.0-canary-a1124489-20260826'],
                    '19.2.8' => ['name' => 'react', 'version' => '19.2.8'],
                ],
            ])),
        ]);

        $versions = (new NpmRegistryClient($httpClient))->getVersions('react', 'react');

        self::assertCount(2, $versions);

        $byVersion = [];
        foreach ($versions as $version) {
            $byVersion[$version->version] = $version;
        }

        self::assertArrayHasKey('18.2.0', $byVersion);
        self::assertArrayHasKey('19.2.8', $byVersion);
        self::assertArrayNotHasKey('19.0.0-rc.1', $byVersion);
        self::assertArrayNotHasKey('19.3.0-canary-a1124489-20260826', $byVersion);

        self::assertSame('18.2.0', $byVersion['18.2.0']->normalizedVersion);
        self::assertSame('>=0.10.0', $byVersion['18.2.0']->runtimeConstraint);
        self::assertSame(Stability::STABLE, $byVersion['18.2.0']->stability);
        self::assertNull($byVersion['18.2.0']->releasedAt);
    }

    public function testGetVersionsRequestsTheUnscopedPackageUrl(): void
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl) {
            $requestedUrl = $url;

            return new MockResponse(json_encode(['versions' => []]));
        });

        (new NpmRegistryClient($httpClient))->getVersions('lodash', 'lodash');

        self::assertSame('https://registry.npmjs.org/lodash', $requestedUrl);
    }

    public function testGetVersionsRequestsTheScopedPackageUrl(): void
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl) {
            $requestedUrl = $url;

            return new MockResponse(json_encode(['versions' => []]));
        });

        (new NpmRegistryClient($httpClient))->getVersions('@babel', 'core');

        self::assertSame('https://registry.npmjs.org/@babel/core', $requestedUrl);
    }

    public function testUnknownPackageReturnsEmptyArray(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Not Found', ['http_code' => 404]),
        ]);

        self::assertSame([], (new NpmRegistryClient($httpClient))->getVersions('acme', 'missing'));
    }

    public function testTransportFailureThrowsDedicatedException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Internal Server Error', ['http_code' => 500]),
        ]);

        $this->expectException(PackageRegistryException::class);

        (new NpmRegistryClient($httpClient))->getVersions('react', 'react');
    }
}
