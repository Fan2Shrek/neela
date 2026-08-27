<?php

declare(strict_types=1);

namespace App\Tests\Domain\Command\Dependency;

use App\Domain\Command\Dependency\CheckDependencyVulnerabilitiesCommand;
use App\Domain\Command\Dependency\CheckDependencyVulnerabilitiesHandler;
use App\Entity\Dependency;
use App\Entity\DependencyManager;
use App\Entity\Manifest;
use App\Entity\Package;
use App\Entity\Project;
use App\Entity\Vendor;
use App\Entity\Vulnerability;
use App\Repository\DependencyRepository;
use App\Repository\VulnerabilityRepository;
use App\Service\VulnerabilityRegistry\VulnerabilityData;
use App\Service\VulnerabilityRegistry\VulnerabilityRegistryInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CheckDependencyVulnerabilitiesHandlerTest extends TestCase
{
    public function testPersistsNewVulnerabilitiesFoundForTheLockedVersion(): void
    {
        $dependency = $this->dependencyFixture();

        $dependencyRepository = $this->createStub(DependencyRepository::class);
        $dependencyRepository->method('find')->willReturn($dependency);

        $vulnerabilityRepository = $this->createStub(VulnerabilityRepository::class);
        $vulnerabilityRepository->method('findOneByPackageExternalIdAndVersion')->willReturn(null);

        $registry = $this->createStub(VulnerabilityRegistryInterface::class);
        $registry->method('getVulnerabilities')->willReturn([
            new VulnerabilityData('GHSA-xxxx', 'A summary', 'HIGH', 'https://osv.dev/vulnerability/GHSA-xxxx'),
        ]);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->expects(self::once())->method('flush');

        $handler = new CheckDependencyVulnerabilitiesHandler($dependencyRepository, $vulnerabilityRepository, $registry, $entityManager, new NullLogger());

        $handler(new CheckDependencyVulnerabilitiesCommand(1));

        self::assertCount(1, $persisted);
        self::assertInstanceOf(Vulnerability::class, $persisted[0]);
        self::assertSame('GHSA-xxxx', $persisted[0]->getExternalId());
        self::assertSame('v6.4.18', $persisted[0]->getAffectedVersion());
    }

    public function testDoesNotDuplicateAnAlreadyKnownVulnerability(): void
    {
        $dependency = $this->dependencyFixture();
        $existing = new Vulnerability($dependency->getPackage(), 'GHSA-xxxx', 'v6.4.18', 'old summary', 'LOW', null);

        $dependencyRepository = $this->createStub(DependencyRepository::class);
        $dependencyRepository->method('find')->willReturn($dependency);

        $vulnerabilityRepository = $this->createStub(VulnerabilityRepository::class);
        $vulnerabilityRepository->method('findOneByPackageExternalIdAndVersion')->willReturn($existing);

        $registry = $this->createStub(VulnerabilityRegistryInterface::class);
        $registry->method('getVulnerabilities')->willReturn([
            new VulnerabilityData('GHSA-xxxx', 'updated summary', 'CRITICAL', 'https://osv.dev/vulnerability/GHSA-xxxx'),
        ]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $handler = new CheckDependencyVulnerabilitiesHandler($dependencyRepository, $vulnerabilityRepository, $registry, $entityManager, new NullLogger());

        $handler(new CheckDependencyVulnerabilitiesCommand(1));

        self::assertSame('CRITICAL', $existing->getSeverity());
        self::assertSame('updated summary', $existing->getSummary());
    }

    public function testRegistryFailureIsLoggedAndDoesNotThrow(): void
    {
        $dependency = $this->dependencyFixture();

        $dependencyRepository = $this->createStub(DependencyRepository::class);
        $dependencyRepository->method('find')->willReturn($dependency);

        $registry = $this->createStub(VulnerabilityRegistryInterface::class);
        $registry->method('getVulnerabilities')->willThrowException(new \RuntimeException('boom'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        $handler = new CheckDependencyVulnerabilitiesHandler($dependencyRepository, $this->createStub(VulnerabilityRepository::class), $registry, $entityManager, new NullLogger());

        $handler(new CheckDependencyVulnerabilitiesCommand(1));
    }

    private function dependencyFixture(): Dependency
    {
        $dependencyManager = new DependencyManager('Composer');
        $vendor = new Vendor('symfony', $dependencyManager);
        $package = new Package('console', $vendor);
        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        $manifest = new Manifest($project, $dependencyManager, 'composer.json', 'composer.lock');

        return new Dependency($manifest, $package, '^6.4', 'v6.4.18', 'require');
    }
}
