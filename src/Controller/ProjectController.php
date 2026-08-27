<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Command\Project\ImportProjectCommand;
use App\Form\ProjectType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
        private readonly TranslatorInterface $translator,
    ) {
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

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('project/new.html.twig', [
            'form' => $form,
        ]);
    }
}
