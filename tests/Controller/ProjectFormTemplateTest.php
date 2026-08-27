<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Form\ProjectType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ProjectFormTemplateTest extends KernelTestCase
{
    public function testFormRendersSshLinkFieldInFrench(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/projects/new'));
        self::getContainer()->get('translator')->setLocale('fr');

        $form = self::getContainer()->get('form.factory')->create(ProjectType::class);

        $html = self::getContainer()->get('twig')->render('project/new.html.twig', [
            'form' => $form->createView(),
        ]);

        self::assertStringContainsString('Ajouter un projet', $html);
        self::assertStringContainsString('Lien SSH', $html);
        self::assertStringContainsString('name="project[sshLink]"', $html);
    }
}
