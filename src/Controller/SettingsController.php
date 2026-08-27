<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\AppSettingsType;
use App\Repository\AppSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly AppSettingsRepository $appSettingsRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/settings', name: 'app_settings', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $settings = $this->appSettingsRepository->get();

        $form = $this->createForm(AppSettingsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newToken = $form->get('githubToken')->getData();

            if ($form->get('removeGithubToken')->getData()) {
                $settings->setGithubToken(null);
            } elseif (null !== $newToken && '' !== $newToken) {
                $settings->setGithubToken($newToken);
            }

            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('settings.saved'));

            return $this->redirectToRoute('app_settings');
        }

        return $this->render('settings/index.html.twig', [
            'form' => $form,
            'hasGithubToken' => null !== $settings->getGithubToken() && '' !== $settings->getGithubToken(),
        ]);
    }
}
