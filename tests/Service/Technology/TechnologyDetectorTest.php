<?php

declare(strict_types=1);

namespace App\Tests\Service\Technology;

use App\Entity\Dependency;
use App\Entity\DependencyManager;
use App\Entity\Manifest;
use App\Entity\Package;
use App\Entity\Project;
use App\Entity\Vendor;
use App\Enum\Technology;
use App\Service\Technology\TechnologyDetector;
use PHPUnit\Framework\TestCase;

final class TechnologyDetectorTest extends TestCase
{
    private TechnologyDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new TechnologyDetector();
    }

    public function testDetectsSymfonyFromTheFrameworkBundle(): void
    {
        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        $composer = new DependencyManager('Composer');
        $manifest = new Manifest($project, $composer, 'composer.json', 'composer.lock');
        $vendor = new Vendor('symfony', $composer);

        $dependencies = [
            new Dependency($manifest, new Package('console', $vendor), '^7.3', 'v7.3.3', 'require'),
            new Dependency($manifest, new Package('framework-bundle', $vendor), '^7.3', 'v7.3.3', 'require'),
        ];

        $detected = $this->detector->detect($dependencies);

        self::assertNotNull($detected);
        self::assertSame(Technology::SYMFONY, $detected->technology);
        self::assertSame('v7.3.3', $detected->dependency->getLockedVersion());
    }

    public function testReturnsNullWhenNoSignalPackageIsPresent(): void
    {
        $project = new Project('my-project', 'git@github.com:acme/my-project.git');
        $composer = new DependencyManager('Composer');
        $manifest = new Manifest($project, $composer, 'composer.json', 'composer.lock');
        $vendor = new Vendor('psr', $composer);

        $dependencies = [
            new Dependency($manifest, new Package('log', $vendor), '^3.0', 'v3.0.0', 'require'),
        ];

        self::assertNull($this->detector->detect($dependencies));
    }
}
