<?php

declare(strict_types=1);

namespace App\Tests\Service\EndOfLife;

use App\Service\EndOfLife\EndOfLifeDateClient;
use App\Service\EndOfLife\Exception\EndOfLifeClientException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EndOfLifeDateClientTest extends TestCase
{
    public function testGetCyclesParsesTheApiResponse(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                [
                    'cycle' => '7.4',
                    'latest' => '7.4.17',
                    'lts' => true,
                    'releaseDate' => '2025-11-27',
                    'eol' => '2029-11-30',
                ],
                [
                    'cycle' => '7.3',
                    'latest' => '7.3.11',
                    'lts' => false,
                    'releaseDate' => '2025-05-29',
                    'eol' => false,
                ],
            ])),
        ]);

        $cycles = (new EndOfLifeDateClient($httpClient))->getCycles('symfony');

        self::assertCount(2, $cycles);

        self::assertSame('7.4', $cycles[0]->cycle);
        self::assertSame('7.4.17', $cycles[0]->latestVersion);
        self::assertTrue($cycles[0]->isLts);
        self::assertSame('2025-11-27', $cycles[0]->releaseDate->format('Y-m-d'));
        self::assertSame('2029-11-30', $cycles[0]->eolDate->format('Y-m-d'));

        self::assertFalse($cycles[1]->isLts);
        self::assertNull($cycles[1]->eolDate);
    }

    public function testGetCyclesRequestsTheCorrectUrl(): void
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(function (string $method, string $url) use (&$requestedUrl) {
            $requestedUrl = $url;

            return new MockResponse(json_encode([]));
        });

        (new EndOfLifeDateClient($httpClient))->getCycles('symfony');

        self::assertSame('https://endoflife.date/api/symfony.json', $requestedUrl);
    }

    public function testUnknownProductReturnsEmptyArray(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('Not Found', ['http_code' => 404]),
        ]);

        self::assertSame([], (new EndOfLifeDateClient($httpClient))->getCycles('unknown-product'));
    }

    public function testTransportFailureThrowsDedicatedException(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['message' => 'Internal Server Error']), ['http_code' => 500]),
        ]);

        $this->expectException(EndOfLifeClientException::class);

        (new EndOfLifeDateClient($httpClient))->getCycles('symfony');
    }
}
