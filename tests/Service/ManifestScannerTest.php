<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Domain\Command\Dependency\CheckDependencyVulnerabilitiesCommand;
use App\Domain\Command\Package\GetPackageVersionCommand;
use App\Entity\Dependency;
use App\Entity\DependencyManager;
use App\Entity\Manifest;
use App\Entity\Package;
use App\Entity\Project;
use App\Entity\Vendor;
use App\Repository\DependencyRepository;
use App\Repository\PackageRepository;
use App\Repository\VendorRepository;
use App\Service\DependencyManager\ComposerDependencyManager;
use App\Service\DependencyManager\DependencyManagerResolver;
use App\Service\ManifestScanner;
use App\Service\PackageRegistry\PackageRegistryInterface;
use App\Service\VCS\GitTree;
use App\Service\VCS\VCSInterface;
use App\Service\VCS\VCSProject;
use App\Service\VCS\VCSResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ManifestScannerTest extends TestCase
{
    public function testScanIsSkippedWhenManifestHasNoLockfile(): void
    {
        $manifest = new Manifest($this->project(), new DependencyManager('Composer'), 'composer.json', null);

        $vcs = $this->createMock(VCSInterface::class);
        $vcs->expects(self::never())->method('getFileContent');

        $scanner = $this->scanner($vcs);

        $scanner->scan($manifest);
    }

    public function testScanIsSkippedWhenFileContentIsMissing(): void
    {
        $manifest = new Manifest($this->project(), new DependencyManager('Composer'), 'composer.json', 'composer.lock');

        $vcs = $this->createStub(VCSInterface::class);
        $vcs->method('supports')->willReturn(true);
        $vcs->method('getFileContent')->willReturn(null);

        $dependencyRepository = $this->createMock(DependencyRepository::class);
        $dependencyRepository->expects(self::never())->method('findOneBy');

        $scanner = $this->scanner($vcs, dependencyRepository: $dependencyRepository);

        $scanner->scan($manifest);
    }

    public function testScanCreatesVendorPackageAndDependencyOnFirstRun(): void
    {
        $manifest = new Manifest($this->project(), new DependencyManager('Composer'), 'composer.json', 'composer.lock');

        $manifestJson = json_encode(['require' => ['symfony/console' => '^6.4']]);
        $lockJson = json_encode(['packages' => [['name' => 'symfony/console', 'version' => 'v6.4.18']]]);

        $vcs = $this->stubVcs($manifestJson, $lockJson);

        $vendorRepository = $this->createStub(VendorRepository::class);
        $vendorRepository->method('findOneBy')->willReturn(null);

        $packageRepository = $this->createStub(PackageRepository::class);
        $packageRepository->method('findOneBy')->willReturn(null);

        $dependencyRepository = $this->createStub(DependencyRepository::class);
        $dependencyRepository->method('findOneBy')->willReturn(null);

        $persisted = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;

            // Simulate Doctrine assigning the auto-generated id on persist.
            if ($entity instanceof Package) {
                (new \ReflectionProperty(Package::class, 'id'))->setValue($entity, 42);
            } elseif ($entity instanceof Dependency) {
                (new \ReflectionProperty(Dependency::class, 'id'))->setValue($entity, 99);
            }
        });

        $dispatched = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $command) use (&$dispatched): Envelope {
                $dispatched[] = $command;

                return new Envelope($command);
            });

        $scanner = $this->scanner($vcs, $vendorRepository, $packageRepository, $dependencyRepository, $entityManager, $bus);

        $scanner->scan($manifest);

        self::assertInstanceOf(Vendor::class, $persisted[0]);
        self::assertSame('symfony', $persisted[0]->getName());

        self::assertInstanceOf(Package::class, $persisted[1]);
        self::assertSame('console', $persisted[1]->getName());

        self::assertInstanceOf(Dependency::class, $persisted[2]);
        self::assertSame('^6.4', $persisted[2]->getConstraint());
        self::assertSame('v6.4.18', $persisted[2]->getLockedVersion());
        self::assertSame('require', $persisted[2]->getDependencyType());

        self::assertInstanceOf(GetPackageVersionCommand::class, $dispatched[0]);
        self::assertSame(42, $dispatched[0]->packageId);

        self::assertInstanceOf(CheckDependencyVulnerabilitiesCommand::class, $dispatched[1]);
        self::assertSame(99, $dispatched[1]->dependencyId);
    }

    public function testScanUpdatesAnExistingDependencyInstead(): void
    {
        $manifest = new Manifest($this->project(), new DependencyManager('Composer'), 'composer.json', 'composer.lock');

        $manifestJson = json_encode(['require' => ['symfony/console' => '^6.4']]);
        $lockJson = json_encode(['packages' => [['name' => 'symfony/console', 'version' => 'v6.4.19']]]);

        $vcs = $this->stubVcs($manifestJson, $lockJson);

        $vendor = new Vendor('symfony', $manifest->getDependencyManager());
        $vendorRepository = $this->createStub(VendorRepository::class);
        $vendorRepository->method('findOneBy')->willReturn($vendor);

        $package = new Package('console', $vendor);
        (new \ReflectionProperty(Package::class, 'id'))->setValue($package, 7);
        $packageRepository = $this->createStub(PackageRepository::class);
        $packageRepository->method('findOneBy')->willReturn($package);

        $existingDependency = new Dependency($manifest, $package, '^6.3', 'v6.3.0', 'require');
        (new \ReflectionProperty(Dependency::class, 'id'))->setValue($existingDependency, 123);
        $dependencyRepository = $this->createStub(DependencyRepository::class);
        $dependencyRepository->method('findOneBy')->willReturn($existingDependency);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $dispatched = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $command) use (&$dispatched): Envelope {
                $dispatched[] = $command;

                return new Envelope($command);
            });

        $scanner = $this->scanner($vcs, $vendorRepository, $packageRepository, $dependencyRepository, $entityManager, $bus);

        $scanner->scan($manifest);

        self::assertSame('^6.4', $existingDependency->getConstraint());
        self::assertSame('v6.4.19', $existingDependency->getLockedVersion());

        self::assertInstanceOf(GetPackageVersionCommand::class, $dispatched[0]);
        self::assertSame(7, $dispatched[0]->packageId);

        self::assertInstanceOf(CheckDependencyVulnerabilitiesCommand::class, $dispatched[1]);
        self::assertSame(123, $dispatched[1]->dependencyId);
    }

    private function project(): Project
    {
        return new Project('my-project', 'git@github.com:acme/my-project.git');
    }

    private function stubVcs(string $manifestContent, ?string $lockContent): VCSInterface
    {
        return new class($manifestContent, $lockContent) implements VCSInterface {
            public function __construct(
                private readonly string $manifestContent,
                private readonly ?string $lockContent,
            ) {
            }

            public function supports(string $sshLink): bool
            {
                return true;
            }

            public function getVCSInfo(string $projectPath): VCSProject
            {
                throw new \LogicException('Not needed for this test.');
            }

            public function getTree(string $sshLink): GitTree
            {
                throw new \LogicException('Not needed for this test.');
            }

            public function getFileContent(string $sshLink, string $path): ?string
            {
                return str_ends_with($path, '.lock') ? $this->lockContent : $this->manifestContent;
            }
        };
    }

    private function scanner(
        VCSInterface $vcs,
        ?VendorRepository $vendorRepository = null,
        ?PackageRepository $packageRepository = null,
        ?DependencyRepository $dependencyRepository = null,
        ?EntityManagerInterface $entityManager = null,
        ?MessageBusInterface $bus = null,
    ): ManifestScanner {
        return new ManifestScanner(
            new VCSResolver([$vcs]),
            new DependencyManagerResolver([new ComposerDependencyManager($this->createStub(PackageRegistryInterface::class))]),
            $vendorRepository ?? $this->createStub(VendorRepository::class),
            $packageRepository ?? $this->createStub(PackageRepository::class),
            $dependencyRepository ?? $this->createStub(DependencyRepository::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $bus ?? $this->createStub(MessageBusInterface::class),
        );
    }
}
