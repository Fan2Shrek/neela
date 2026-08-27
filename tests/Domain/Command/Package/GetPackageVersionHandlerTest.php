<?php

declare(strict_types=1);

namespace App\Tests\Domain\Command\Package;

use App\Domain\Command\Package\GetPackageVersionCommand;
use App\Domain\Command\Package\GetPackageVersionHandler;
use App\Entity\DependencyManager;
use App\Entity\Package;
use App\Entity\Vendor;
use App\Entity\Version;
use App\Repository\PackageRepository;
use App\Repository\VersionRepository;
use App\Service\DependencyManager\DependencyManagerInterface;
use App\Service\DependencyManager\DependencyManagerResolver;
use App\Service\PackageRegistry\PackageRegistryInterface;
use App\Service\PackageRegistry\PackageVersionData;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class GetPackageVersionHandlerTest extends TestCase
{
    public function testMissingPackageThrows(): void
    {
        $packageRepository = $this->createStub(PackageRepository::class);
        $packageRepository->method('find')->willReturn(null);

        $handler = new GetPackageVersionHandler(
            $packageRepository,
            $this->createStub(VersionRepository::class),
            new DependencyManagerResolver([]),
            $this->createStub(EntityManagerInterface::class),
        );

        $this->expectException(\RuntimeException::class);

        $handler(new GetPackageVersionCommand(1));
    }

    public function testDoesNothingWhenTheDependencyManagerHasNoRegistry(): void
    {
        $package = $this->package('npm');

        $packageRepository = $this->createStub(PackageRepository::class);
        $packageRepository->method('find')->willReturn($package);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $handler = new GetPackageVersionHandler(
            $packageRepository,
            $this->createStub(VersionRepository::class),
            new DependencyManagerResolver([$this->dependencyManager('npm', null)]),
            $entityManager,
        );

        $handler(new GetPackageVersionCommand(1));
    }

    public function testCreatesNewVersionsAndUpdatesExistingOnes(): void
    {
        $package = $this->package('Composer');

        $packageRepository = $this->createStub(PackageRepository::class);
        $packageRepository->method('find')->willReturn($package);

        $existingVersion = new Version($package, 'v6.4.18', '6.4.18.0');

        $versionRepository = $this->createStub(VersionRepository::class);
        $versionRepository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria) => '6.4.18.0' === $criteria['normalizedVersion'] ? $existingVersion : null,
        );

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->expects(self::once())->method('flush');

        $registry = $this->createStub(PackageRegistryInterface::class);
        $registry->method('getVersions')->willReturn([
            new PackageVersionData('v6.4.19', '6.4.19.0', new \DateTimeImmutable('2024-08-14'), '>=8.1'),
            new PackageVersionData('v6.4.18', '6.4.18.0', new \DateTimeImmutable('2024-07-01'), '>=8.1'),
        ]);

        $handler = new GetPackageVersionHandler(
            $packageRepository,
            $versionRepository,
            new DependencyManagerResolver([$this->dependencyManager('Composer', $registry)]),
            $entityManager,
        );

        $handler(new GetPackageVersionCommand(1));

        self::assertCount(1, $persisted);
        self::assertInstanceOf(Version::class, $persisted[0]);
        self::assertSame('6.4.19.0', $persisted[0]->getNormalizedVersion());

        self::assertSame('v6.4.18', $existingVersion->getVersion());
        self::assertSame('>=8.1', $existingVersion->getRuntimeConstraint());
    }

    private function package(string $dependencyManagerName): Package
    {
        $dependencyManager = new DependencyManager($dependencyManagerName);
        $vendor = new Vendor('symfony', $dependencyManager);

        return new Package('console', $vendor);
    }

    private function dependencyManager(string $name, ?PackageRegistryInterface $registry): DependencyManagerInterface
    {
        $dependencyManager = $this->createStub(DependencyManagerInterface::class);
        $dependencyManager->method('getName')->willReturn($name);
        $dependencyManager->method('getRegistry')->willReturn($registry);

        return $dependencyManager;
    }
}
