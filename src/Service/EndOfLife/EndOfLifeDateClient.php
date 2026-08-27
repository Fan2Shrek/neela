<?php

declare(strict_types=1);

namespace App\Service\EndOfLife;

use App\Service\EndOfLife\Exception\EndOfLifeClientException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class EndOfLifeDateClient implements EndOfLifeClientInterface
{
    private const API_BASE_URL = 'https://endoflife.date/api';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getCycles(string $productSlug): array
    {
        try {
            $response = $this->httpClient->request('GET', \sprintf('%s/%s.json', self::API_BASE_URL, $productSlug));
            $statusCode = $response->getStatusCode();

            if (404 === $statusCode) {
                // Product slug not (or no longer) tracked by endoflife.date.
                return [];
            }

            if ($statusCode >= 400) {
                throw new EndOfLifeClientException(\sprintf('endoflife.date returned status code %d for "%s".', $statusCode, $productSlug));
            }

            $data = $response->toArray(false);
        } catch (HttpExceptionInterface $exception) {
            throw new EndOfLifeClientException(\sprintf('Unable to fetch support data for "%s" from endoflife.date: %s', $productSlug, $exception->getMessage()), previous: $exception);
        }

        $cycles = [];
        foreach ($data as $cycle) {
            if (!isset($cycle['cycle'], $cycle['latest'])) {
                continue;
            }

            $cycles[] = new EndOfLifeCycleData(
                cycle: (string) $cycle['cycle'],
                latestVersion: (string) $cycle['latest'],
                isLts: \is_string($cycle['lts'] ?? false) || true === ($cycle['lts'] ?? false),
                releaseDate: $this->parseDate($cycle['releaseDate'] ?? null),
                eolDate: $this->parseDate($cycle['eol'] ?? null),
            );
        }

        return $cycles;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            // endoflife.date uses `false` for "not applicable" / "not yet announced".
            return null;
        }

        return new \DateTimeImmutable($value);
    }
}
