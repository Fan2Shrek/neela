<?php

declare(strict_types=1);

namespace App\Domain\Command\Package;

use App\Repository\PackageRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class FetchAllPackageHandler
{
    public function __construct(
        private PackageRepository $packageRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(FetchAllPackageCommand $command): void
    {
        foreach ($this->packageRepository->iterateAllIds() as $packageId) {
            try {
                $this->messageBus->dispatch(new GetPackageVersionCommand($packageId));
            } catch (\Throwable $exception) {
                // One package's registry failing (network, rate-limit, ...) must not
                // abort the refresh of every other package in the catalog.
                $this->logger->warning('Unable to fetch versions for package "{packageId}": {message}', [
                    'packageId' => $packageId,
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);
            }
        }
    }
}
