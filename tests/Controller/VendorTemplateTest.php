<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\DependencyManager;
use App\Entity\Vendor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class VendorTemplateTest extends KernelTestCase
{
    public function testEmptyState(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/vendors'));
        $twig = self::getContainer()->get('twig');

        $html = $twig->render('vendor/index.html.twig', [
            'rows' => [],
            'vendorCount' => 0,
        ]);

        self::assertStringContainsString('Vendors', $html);
        self::assertStringContainsString('No vendors discovered yet.', $html);
    }

    public function testPopulatedStateInFrench(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/vendors'));
        self::getContainer()->get('translator')->setLocale('fr');
        $twig = self::getContainer()->get('twig');

        $composer = new DependencyManager('Composer');
        $vendor = new Vendor('symfony', $composer);

        $html = $twig->render('vendor/index.html.twig', [
            'rows' => [
                ['vendor' => $vendor, 'packageCount' => 12, 'projectCount' => 5],
            ],
            'vendorCount' => 1,
        ]);

        self::assertStringContainsString('symfony', $html);
        self::assertStringContainsString('Composer', $html);
        self::assertStringContainsString('Un vendor découvert', $html);
    }
}
