<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Manifest;
use App\Repository\DependencyRepository;
use App\Repository\ManifestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ManifestController extends AbstractController
{
    public function __construct(
        private readonly ManifestRepository $manifestRepository,
        private readonly DependencyRepository $dependencyRepository,
    ) {
    }

    #[Route('/manifests', name: 'app_manifest_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        $manifests = $this->manifestRepository->findAllWithProjectAndDependencyManager();
        $dependencyCounts = $this->dependencyRepository->countByManifest();

        $rows = array_map(static fn (Manifest $manifest): array => [
            'manifest' => $manifest,
            'dependencyCount' => $dependencyCounts[$manifest->getId()] ?? 0,
        ], $manifests);

        return $this->render('manifest/index.html.twig', [
            'rows' => $rows,
            'manifestCount' => \count($manifests),
        ]);
    }
}
