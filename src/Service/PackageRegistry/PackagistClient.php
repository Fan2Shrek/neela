<?php

declare(strict_types=1);

namespace App\Service\PackageRegistry;

use App\Enum\Stability;
use App\Service\PackageRegistry\Exception\PackageRegistryException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PackagistClient implements PackageRegistryInterface
{
    private const API_BASE_URL = 'https://repo.packagist.org';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getVersions(string $vendor, string $name): array
    {
        $packageKey = \sprintf('%s/%s', $vendor, $name);

        try {
            $response = $this->httpClient->request('GET', \sprintf('%s/p2/%s.json', self::API_BASE_URL, $packageKey));
            $statusCode = $response->getStatusCode();

            if (404 === $statusCode) {
                // Not every registered package is necessarily on Packagist (private packages, typos, ...).
                return [];
            }

            if ($statusCode >= 400) {
                throw new PackageRegistryException(\sprintf('Packagist returned status code %d for "%s".', $statusCode, $packageKey));
            }

            $data = $response->toArray(false);
        } catch (HttpExceptionInterface $exception) {
            throw new PackageRegistryException(\sprintf('Unable to fetch versions for "%s" from Packagist: %s', $packageKey, $exception->getMessage()), previous: $exception);
        }

        $versions = [];
        foreach ($data['packages'][$packageKey] ?? [] as $release) {
            if (!isset($release['version'], $release['version_normalized'])) {
                continue;
            }

            $versions[] = new PackageVersionData(
                version: $release['version'],
                normalizedVersion: $release['version_normalized'],
                releasedAt: isset($release['time']) ? new \DateTimeImmutable($release['time']) : null,
                // Packagist's "require.php" is Composer's flavor of a runtime constraint.
                runtimeConstraint: $release['require']['php'] ?? null,
                stability: Stability::fromVersionString($release['version']),
            );
        }

        return $versions;
    }
}
