<?php

declare(strict_types=1);

namespace App\Domain\Command\Dependency;

use App\Entity\Vulnerability;
use App\Repository\DependencyRepository;
use App\Repository\VulnerabilityRepository;
use App\Service\VulnerabilityRegistry\VulnerabilityRegistryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CheckDependencyVulnerabilitiesHandler
{
    public function __construct(
        private readonly DependencyRepository $dependencyRepository,
        private readonly VulnerabilityRepository $vulnerabilityRepository,
        private readonly VulnerabilityRegistryInterface $vulnerabilityRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(CheckDependencyVulnerabilitiesCommand $command): void
    {
        $dependency = $this->dependencyRepository->find($command->dependencyId)
            ?? throw new \RuntimeException(\sprintf('Dependency "%d" not found.', $command->dependencyId));

        $package = $dependency->getPackage();
        $dependencyManagerName = $dependency->getManifest()->getDependencyManager()->getName();

        try {
            $vulnerabilities = $this->vulnerabilityRegistry->getVulnerabilities(
                $dependencyManagerName,
                $package->getVendor()->getName(),
                $package->getName(),
                $dependency->getLockedVersion(),
            );
        } catch (\Throwable $exception) {
            // One dependency's registry lookup failing (network, rate-limit, ...) must not
            // block the rest of the scan.
            $this->logger->warning('Unable to fetch vulnerabilities for dependency "{dependencyId}": {message}', [
                'dependencyId' => $command->dependencyId,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return;
        }

        foreach ($vulnerabilities as $data) {
            $existing = $this->vulnerabilityRepository->findOneByPackageExternalIdAndVersion($package, $data->externalId, $dependency->getLockedVersion());

            if (null === $existing) {
                $this->entityManager->persist(new Vulnerability(
                    $package,
                    $data->externalId,
                    $dependency->getLockedVersion(),
                    $data->summary,
                    $data->severity,
                    $data->url,
                ));
            } else {
                $existing->setSummary($data->summary);
                $existing->setSeverity($data->severity);
                $existing->setUrl($data->url);
            }
        }

        $this->entityManager->flush();
    }
}
