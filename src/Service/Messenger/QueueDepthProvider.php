<?php

declare(strict_types=1);

namespace App\Service\Messenger;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class QueueDepthProvider
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.async')]
        private readonly TransportInterface $asyncTransport,
        #[Autowire(service: 'messenger.transport.failed')]
        private readonly TransportInterface $failedTransport,
    ) {
    }

    public function getAsyncQueueDepth(): ?int
    {
        return $this->count($this->asyncTransport);
    }

    public function getFailedQueueDepth(): ?int
    {
        return $this->count($this->failedTransport);
    }

    /**
     * Null when the transport can't report a count at all (some self-hosted
     * MESSENGER_TRANSPORT_DSN values), as opposed to a real, empty queue (0).
     */
    private function count(TransportInterface $transport): ?int
    {
        return $transport instanceof MessageCountAwareInterface
            ? $transport->getMessageCount()
            : null;
    }
}
