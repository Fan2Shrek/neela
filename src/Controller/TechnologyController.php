<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Dependency;
use App\Entity\TechnologyReleaseCycle;
use App\Enum\Technology;
use App\Enum\TechnologySupportStatus;
use App\Repository\DependencyRepository;
use App\Repository\PackageRepository;
use App\Repository\TechnologyReleaseCycleRepository;
use App\Service\Technology\TechnologySupportEvaluator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TechnologyController extends AbstractController
{
    public function __construct(
        private readonly PackageRepository $packageRepository,
        private readonly DependencyRepository $dependencyRepository,
        private readonly TechnologyReleaseCycleRepository $technologyReleaseCycleRepository,
        private readonly TechnologySupportEvaluator $technologySupportEvaluator,
    ) {
    }

    #[Route('/technologies', name: 'app_technology_index', methods: ['GET'])]
    public function index(): Response
    {
        // Package-signal-based technologies only for now: PHP (and other future runtimes)
        // are detected from a manifest's own platform requirement, not a dependency, and
        // aren't wired into this dependency-driven catalog yet — see ManifestScanner and
        // ProjectController's manifest rows for where PHP is actually surfaced today.
        $catalogTechnologies = array_filter(Technology::cases(), static fn (Technology $t): bool => null !== $t->getSignalPackage());

        $statusRank = static fn (string $status): int => match ($status) {
            TechnologySupportStatus::END_OF_LIFE->value => 3,
            TechnologySupportStatus::OUTDATED->value => 2,
            TechnologySupportStatus::UNKNOWN->value => 1,
            default => 0,
        };

        $rows = array_map(function (Technology $technology) use ($statusRank): array {
            $dependencies = $this->findDependencies($technology);

            $projectIds = [];
            $statuses = [];
            foreach ($dependencies as $dependency) {
                $projectIds[(string) $dependency->getManifest()->getProject()->getId()] = true;
                $statuses[$this->technologySupportEvaluator->evaluate($technology, $dependency->getLockedVersion())->value] = true;
            }

            $statuses = array_keys($statuses);

            return [
                'technology' => $technology,
                'projectCount' => \count($projectIds),
                'statuses' => $statuses,
                'worstStatusRank' => array_reduce($statuses, static fn (int $worst, string $status): int => max($worst, $statusRank($status)), 0),
            ];
        }, $catalogTechnologies);

        // Technologies with the least healthy status (end of life first) surface first;
        // ties broken by project count, so widely-used technologies still stand out.
        usort($rows, static fn (array $a, array $b): int => ($b['worstStatusRank'] <=> $a['worstStatusRank']) ?: ($b['projectCount'] <=> $a['projectCount']));

        return $this->render('technology/index.html.twig', [
            'rows' => $rows,
            'technologyCount' => \count($rows),
        ]);
    }

    #[Route('/technologies/{technology}', name: 'app_technology_show', methods: ['GET'])]
    public function show(Technology $technology): Response
    {
        $dependencies = $this->findDependencies($technology);

        $rows = array_map(fn (Dependency $dependency): array => [
            'dependency' => $dependency,
            'status' => $this->technologySupportEvaluator->evaluate($technology, $dependency->getLockedVersion()),
        ], $dependencies);

        $cycles = $this->technologyReleaseCycleRepository->findByTechnology($technology);
        usort(
            $cycles,
            static fn (TechnologyReleaseCycle $a, TechnologyReleaseCycle $b): int => ($b->getReleaseDate() ?? new \DateTimeImmutable('@0')) <=> ($a->getReleaseDate() ?? new \DateTimeImmutable('@0')),
        );

        return $this->render('technology/show.html.twig', [
            'technology' => $technology,
            'rows' => $rows,
            'projectCount' => \count(array_unique(array_map(
                static fn (Dependency $dependency): string => (string) $dependency->getManifest()->getProject()->getId(),
                $dependencies,
            ))),
            'cycles' => $cycles,
        ]);
    }

    /**
     * @return Dependency[]
     */
    private function findDependencies(Technology $technology): array
    {
        $signalPackage = $technology->getSignalPackage();

        if (null === $signalPackage) {
            return [];
        }

        [$vendorName, $packageName] = $signalPackage;
        $package = $this->packageRepository->findOneByVendorAndName($vendorName, $packageName);

        return null !== $package ? $this->dependencyRepository->findByPackageWithProject($package) : [];
    }
}
