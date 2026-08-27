<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ScanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ScanController extends AbstractController
{
    public function __construct(
        private readonly ScanRepository $scanRepository,
    ) {
    }

    #[Route('/scans', name: 'app_scan_index', methods: ['GET'])]
    public function __invoke(): Response
    {
        $scans = $this->scanRepository->findAllWithRelationsOrderedByMostRecent();

        return $this->render('scan/index.html.twig', [
            'scans' => $scans,
            'scanCount' => \count($scans),
        ]);
    }
}
