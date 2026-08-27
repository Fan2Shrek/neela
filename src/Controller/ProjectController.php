<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Command\Project\ImportProjectCommand;
use App\Domain\Command\Project\RescanProjectCommand;
use App\Entity\Dependency;
use App\Entity\Manifest;
use App\Entity\ManifestTechnology;
use App\Entity\Project;
use App\Form\ProjectType;
use App\Repository\DependencyRepository;
use App\Repository\ManifestRepository;
use App\Repository\ManifestTechnologyRepository;
use App\Repository\ProjectRepository;
use App\Repository\ScanRepository;
use App\Repository\VersionRepository;
use App\Repository\VulnerabilityRepository;
use App\Service\Package\PackageUpdateChecker;
use App\Service\Technology\DetectedTechnology;
use App\Service\Technology\TechnologyDetector;
use App\Service\Technology\TechnologySupportEvaluator;
use App\Service\VCS\Client\Exception\VCSException;
use App\Service\VCS\RepositoryDiscoveryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ManifestRepository $manifestRepository,
        private readonly ManifestTechnologyRepository $manifestTechnologyRepository,
        private readonly ScanRepository $scanRepository,
        private readonly DependencyRepository $dependencyRepository,
        private readonly VersionRepository $versionRepository,
        private readonly VulnerabilityRepository $vulnerabilityRepository,
        private readonly PackageUpdateChecker $packageUpdateChecker,
        private readonly TechnologyDetector $technologyDetector,
        private readonly TechnologySupportEvaluator $technologySupportEvaluator,
        private readonly MessageBusInterface $bus,
        private readonly TranslatorInterface $translator,
        private readonly RepositoryDiscoveryInterface $repositoryDiscovery,
    ) {
    }

    #[Route('/projects', name: 'app_project_index', methods: ['GET'])]
    public function index(): Response
    {
        $projects = $this->projectRepository->findAll();

        $rows = array_map(function (Project $project): array {
            $manifests = $this->manifestRepository->findBy(['project' => $project]);

            $dependencyManagers = array_unique(array_map(
                static fn (Manifest $manifest): string => $manifest->getDependencyManager()->getName(),
                $manifests,
            ));

            return [
                'project' => $project,
                'manifestCount' => \count($manifests),
                'dependencyManagers' => $dependencyManagers,
                'lastScan' => $this->scanRepository->findLatestForProject($project),
            ];
        }, $projects);

        return $this->render('project/index.html.twig', [
            'rows' => $rows,
            'projectCount' => \count($projects),
        ]);
    }

    #[Route('/projects/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(ProjectType::class, ['scanNow' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sshLink = $form->get('sshLink')->getData();
            $scanNow = $form->get('scanNow')->getData();
            $this->bus->dispatch(new ImportProjectCommand($sshLink, $scanNow));

            $this->addFlash('success', $this->translator->trans('project.import.queued'));

            return $this->redirectToRoute('app_project_index');
        }

        return $this->render('project/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/projects/discover', name: 'app_project_discover', methods: ['GET'])]
    public function discover(Request $request): Response
    {
        $account = trim((string) $request->query->get('account', ''));
        $repositories = [];
        $alreadyImportedSshLinks = [];
        $error = null;

        if ('' !== $account) {
            try {
                $repositories = $this->repositoryDiscovery->discoverRepositories($account);

                $alreadyImportedSshLinks = array_map(
                    static fn (Project $project): string => $project->getSshLink(),
                    $this->projectRepository->findAll(),
                );
            } catch (VCSException $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('project/discover.html.twig', [
            'account' => $account,
            'repositories' => $repositories,
            'alreadyImportedSshLinks' => $alreadyImportedSshLinks,
            'error' => $error,
        ]);
    }

    #[Route('/projects/discover', name: 'app_project_discover_import', methods: ['POST'])]
    public function discoverImport(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('discover_import', $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $sshLinks = $request->request->all('ssh_links');

        foreach ($sshLinks as $sshLink) {
            $this->bus->dispatch(new ImportProjectCommand($sshLink, scanNow: true));
        }

        $this->addFlash('success', $this->translator->trans('project.discover.import.queued', ['%count%' => \count($sshLinks)]));

        return $this->redirectToRoute('app_project_index');
    }

    #[Route('/projects/{id}', name: 'app_project_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(Project $project, Request $request): Response
    {
        $dependencyType = $request->query->get('dependency_type', '');
        $manifests = $this->manifestRepository->findByProjectWithDependencyManager($project);
        $dependencyCounts = $this->dependencyRepository->countByManifest();
        $dependencies = $this->dependencyRepository->findByProjectWithPackage($project);

        $dependenciesByManifestId = [];
        foreach ($dependencies as $dependency) {
            $dependenciesByManifestId[$dependency->getManifest()->getId()][] = $dependency;
        }

        $manifestTechnologiesByManifestId = $this->manifestTechnologyRepository->findAllIndexedByManifestId();

        $manifestRows = array_map(function (Manifest $manifest) use ($dependencyCounts, $dependenciesByManifestId, $manifestTechnologiesByManifestId): array {
            $detected = $this->technologyDetector->detect($dependenciesByManifestId[$manifest->getId()] ?? []);

            $runtimes = array_map(fn (ManifestTechnology $manifestTechnology): array => [
                'technology' => $manifestTechnology->getTechnology(),
                'version' => $manifestTechnology->getVersion(),
                'supportStatus' => $this->technologySupportEvaluator->evaluate($manifestTechnology->getTechnology(), $manifestTechnology->getVersion()),
            ], $manifestTechnologiesByManifestId[$manifest->getId()] ?? []);

            return [
                'manifest' => $manifest,
                'dependencyCount' => $dependencyCounts[$manifest->getId()] ?? 0,
                'technology' => $detected?->technology,
                'technologyVersion' => $detected?->dependency->getLockedVersion(),
                'technologySupportStatus' => $detected instanceof DetectedTechnology
                    ? $this->technologySupportEvaluator->evaluate($detected->technology, $detected->dependency->getLockedVersion())
                    : null,
                'runtimes' => $runtimes,
            ];
        }, $manifests);

        $packageIds = array_values(array_unique(array_map(
            static fn (Dependency $dependency): int => $dependency->getPackage()->getId(),
            $dependencies,
        )));
        $stableVersionsByPackageId = $this->versionRepository->findStableVersionsIndexedByPackageId($packageIds);

        $outdatedDependencyRows = [];
        foreach ($dependencies as $dependency) {
            $availableVersions = $stableVersionsByPackageId[$dependency->getPackage()->getId()] ?? [];
            $latestVersion = $this->packageUpdateChecker->findLatestSatisfying($availableVersions, $dependency->getConstraint());

            if (null !== $latestVersion && $dependency->getLockedVersion() !== $latestVersion) {
                if ('' !== $dependencyType && $dependency->getDependencyType() !== $dependencyType) {
                    continue;
                }

                $outdatedDependencyRows[] = [
                    'dependency' => $dependency,
                    'latestVersion' => $latestVersion,
                ];
            }
        }

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'manifestRows' => $manifestRows,
            'manifestCount' => \count($manifests),
            'dependencyCount' => array_sum(array_column($manifestRows, 'dependencyCount')),
            'outdatedDependencyRows' => $outdatedDependencyRows,
            'dependencyType' => $dependencyType,
            'vulnerabilityRows' => $this->vulnerabilityRepository->findAffectingProject($project),
            'scans' => $this->scanRepository->findByProjectOrderedByMostRecent($project),
            'lastScan' => $this->scanRepository->findLatestForProject($project),
        ]);
    }

    #[Route('/projects/{id}/rescan', name: 'app_project_rescan', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function rescan(Project $project, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('rescan_project', $request->request->get('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->bus->dispatch(new RescanProjectCommand((string) $project->getId()));

        $this->addFlash('success', $this->translator->trans('project.show.rescan.queued'));

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }
}
