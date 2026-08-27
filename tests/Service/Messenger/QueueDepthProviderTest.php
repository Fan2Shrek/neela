<?php

declare(strict_types=1);

namespace App\Tests\Service\Messenger;

use App\Service\Messenger\QueueDepthProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class QueueDepthProviderTest extends TestCase
{
    public function testReturnsTheMessageCountWhenTheTransportSupportsIt(): void
    {
        $asyncTransport = $this->createStub(CountableTransport::class);
        $asyncTransport->method('getMessageCount')->willReturn(3);

        $provider = new QueueDepthProvider($asyncTransport, $this->createStub(TransportInterface::class));

        self::assertSame(3, $provider->getAsyncQueueDepth());
    }

    public function testReturnsNullWhenTheTransportCannotReportACount(): void
    {
        $provider = new QueueDepthProvider(
            $this->createStub(TransportInterface::class),
            $this->createStub(TransportInterface::class),
        );

        self::assertNull($provider->getAsyncQueueDepth());
        self::assertNull($provider->getFailedQueueDepth());
    }

    public function testAsyncAndFailedDepthsAreReadFromTheirOwnTransport(): void
    {
        $asyncTransport = $this->createStub(CountableTransport::class);
        $asyncTransport->method('getMessageCount')->willReturn(2);

        $failedTransport = $this->createStub(CountableTransport::class);
        $failedTransport->method('getMessageCount')->willReturn(5);

        $provider = new QueueDepthProvider($asyncTransport, $failedTransport);

        self::assertSame(2, $provider->getAsyncQueueDepth());
        self::assertSame(5, $provider->getFailedQueueDepth());
    }
}

interface CountableTransport extends TransportInterface, MessageCountAwareInterface
{
}
