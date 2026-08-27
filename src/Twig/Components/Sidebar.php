<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Sidebar
{
    private const array LOCALES = ['en', 'fr'];

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<array{route: string, label: string, icon: string, activeRoutes?: string[]}>
     */
    public function getNavItems(): array
    {
        return [
            ['route' => 'app_dashboard', 'label' => 'nav.dashboard', 'icon' => 'tabler:layout-dashboard'],
            ['route' => 'app_project_index', 'label' => 'nav.projects', 'icon' => 'tabler:folder', 'activeRoutes' => ['app_project_index', 'app_project_new']],
            ['route' => 'app_manifest_index', 'label' => 'nav.manifests', 'icon' => 'tabler:file-text'],
            ['route' => 'app_package_index', 'label' => 'nav.packages', 'icon' => 'tabler:package'],
            ['route' => 'app_vendor_index', 'label' => 'nav.vendors', 'icon' => 'tabler:building'],
            ['route' => 'app_dependency_index', 'label' => 'nav.dependencies', 'icon' => 'tabler:git-branch'],
            ['route' => 'app_scan_index', 'label' => 'nav.scans', 'icon' => 'tabler:history'],
        ];
    }

    /**
     * @param array{route: string, activeRoutes?: string[]} $item
     */
    public function isActive(array $item): bool
    {
        $current = $this->requestStack->getCurrentRequest()?->attributes->get('_route');
        $activeRoutes = $item['activeRoutes'] ?? [$item['route']];

        return \in_array($current, $activeRoutes, true);
    }

    /**
     * @return string[]
     */
    public function getLocales(): array
    {
        return self::LOCALES;
    }

    public function getCurrentLocale(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale();
    }
}
