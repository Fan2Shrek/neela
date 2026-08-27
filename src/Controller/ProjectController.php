<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Command\Project\ImportProjectCommand;
use App\Entity\Manifest;
use App\Entity\Project;
use App\Form\ProjectType;
use App\Repository\ManifestRepository;
use App\Repository\ProjectRepository;
use App\Repository\ScanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ManifestRepository $manifestRepository,
        private readonly ScanRepository $scanRepository,
        private readonly MessageBusInterface $bus,
        private readonly TranslatorInterface $translator,
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
}
