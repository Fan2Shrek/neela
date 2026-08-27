<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\DependencyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DependencyController extends AbstractController
{
    public function __construct(
        private readonly DependencyRepository $dependencyRepository,
    ) {
    }

    #[Route('/dependencies', name: 'app_dependency_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        $dependencies = $this->dependencyRepository->findAllWithRelations();

        return $this->render('dependency/index.html.twig', [
            'dependencies' => $dependencies,
            'dependencyCount' => \count($dependencies),
        ]);
    }
}
