<?php

declare(strict_types=1);

namespace App\Service\PackageRegistry;

use App\Enum\Stability;
use App\Service\PackageRegistry\Exception\PackageRegistryException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NpmRegistryClient implements PackageRegistryInterface
{
    private const REGISTRY_BASE_URL = 'https://registry.npmjs.org';

    // Even the abbreviated document is one JSON object per published version: packages
    // with a long tail of releases (react's daily canary builds go back years) can reach
    // several MB — which then balloons further once json_decode'd, enough to exhaust a
    // typical 128M worker. Abort rather than let a single package OOM the whole worker.
    private const MAX_RESPONSE_BYTES = 4_000_000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getVersions(string $vendor, string $name): array
    {
        // Unscoped packages store the same value in both fields (see
        // NpmDependencyManager); scoped ones ("@babel/core") need both segments back.
        $packageName = str_starts_with($vendor, '@') ? \sprintf('%s/%s', $vendor, $name) : $name;

        try {
            $response = $this->httpClient->request('GET', \sprintf('%s/%s', self::REGISTRY_BASE_URL, $packageName), [
                // The abbreviated "install" document drops readme/maintainers/changelog
                // per release. Some packages (react, with years of daily canary builds)
                // run into tens of megabytes on the full metadata document otherwise.
                'headers' => ['Accept' => 'application/vnd.npm.install-v1+json'],
                'on_progress' => function (int $downloadedBytes) use ($packageName): void {
                    if ($downloadedBytes > self::MAX_RESPONSE_BYTES) {
                        throw new PackageRegistryException(\sprintf('Response for "%s" exceeded %d bytes; aborted to avoid memory exhaustion.', $packageName, self::MAX_RESPONSE_BYTES));
                    }
                },
            ]);
            $statusCode = $response->getStatusCode();

            if (404 === $statusCode) {
                // Not every discovered dependency is necessarily on the public registry
                // (private packages, typos, ...).
                return [];
            }

            if ($statusCode >= 400) {
                throw new PackageRegistryException(\sprintf('npm registry returned status code %d for "%s".', $statusCode, $packageName));
            }

            $data = $response->toArray(false);
        } catch (HttpExceptionInterface $exception) {
            throw new PackageRegistryException(\sprintf('Unable to fetch versions for "%s" from the npm registry: %s', $packageName, $exception->getMessage()), previous: $exception);
        }

        $versions = [];
        foreach ($data['versions'] ?? [] as $version => $release) {
            $stability = Stability::fromVersionString((string) $version);

            if ($stability->isPreRelease()) {
                // We only ever compare against stable releases (see PackageUpdateChecker);
                // skip the rest instead of storing years of throwaway pre-release noise.
                continue;
            }

            $versions[] = new PackageVersionData(
                version: (string) $version,
                normalizedVersion: (string) $version,
                releasedAt: null,
                runtimeConstraint: $release['engines']['node'] ?? null,
                stability: $stability,
            );
        }

        return $versions;
    }
}
