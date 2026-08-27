<?php

declare(strict_types=1);

namespace App\Tests\Service\PackageRegistry;

use App\Enum\Stability;
use App\Service\PackageRegistry\Exception\PackageRegistryException;
use App\Service\PackageRegistry\PackagistClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PackagistClientTest extends TestCase
{
    public function testGetVersionsParsesTheP2Response(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'packages' => [
                    'symfony/console' => [
                        [
                            'version' => 'v6.4.19',
                            'version_normalized' => '6.4.19.0',
                            'time' => '2024-08-14T09:38:32+00:00',
                            'require' => ['php' => '>=8.1'],
                        ],
                        [
                            'version' => 'v6.4.18',
                            'version_normalized' => '6.4.18.0',
                            'time' => '2024-07-01T00:00:00+00:00',
                        ],
                        [
                            'version' => 'v6.5.0-beta1',
                            'version_normalized' => '6.5.0.0-beta1',
                            'time' => '2024-08-01T00:00:00+00:00',
                        ],
                    ],
                ],
            ])),
        ]);

        $versions = (new PackagistClient($httpClient))->getVersions('symfony', 'console');

        self::assertCount(3, $versions);
        self::assertSame('v6.4.19', $versions[0]->version);
        self::assertSame('6.4.19.0', $versions[0]->normalizedVersion);
        self::assertSame('2024-08-14T09:38:32+00:00', $versions[0]->releasedAt->format('c'));
        self::assertSame('>=8.1', $versions[0]->runtimeConstraint);
        self::assertSame(Stability::STABLE, $versions[0]->stability);

        self::assertSame('v6.4.18', $versions[1]->version);
        self::assertNull($versions[1]->runtimeConstraint);
        self::assertSame(Stability::STABLE, $versions[1]->stability);

        self::assertSame(Stability::BETA, $versions[2]->stability);
    }

    public function testGetVersionsRequestsTheCorrectUrl(): void
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl) {
            $requestedUrl = $url;

            return new MockResponse(json_encode(['packages' => []]));
        });

        (new PackagistClient($httpClient))->getVersions('symfony', 'console');

        self::assertSame('https://repo.packagist.org/p2/symfony/console.json', $requestedUrl);
    }

    public function testUnknownPackageReturnsEmptyArray(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Not Found']), ['http_code' => 404]),
        ]);

        $versions = (new PackagistClient($httpClient))->getVersions('acme', 'missing');

        self::assertSame([], $versions);
    }

    public function testTransportFailureThrowsDedicatedException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Internal Server Error']), ['http_code' => 500]),
        ]);

        $this->expectException(PackageRegistryException::class);

        (new PackagistClient($httpClient))->getVersions('symfony', 'console');
    }
}
