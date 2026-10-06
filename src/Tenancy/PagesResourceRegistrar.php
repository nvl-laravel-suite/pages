<?php

declare(strict_types=1);

namespace Nvl\Pages\Tenancy;

use Nvl\Pages\Models\Page;
use Nvl\Pages\Models\PageTranslation;
use Nvl\Support\Tenancy\Enums\TenantResourceKind;
use Nvl\Support\Tenancy\Services\TenantResourceRegistry;
use Nvl\Support\Tenancy\ValueObjects\TenantResourceDefinition;
use Nvl\Tenancy\Services\TenantAdoptionRegistry;

/** Registers the complete Page tree ownership graph and adopter. */
final class PagesResourceRegistrar
{
    /** Register Pages roots, inherited copy, and compatible integrations. */
    public function register(TenantResourceRegistry $resources, ?TenantAdoptionRegistry $adoption = null): void
    {
        foreach (['content', 'seo'] as $dependency) {
            $resources->requireCompatible('pages', $dependency);
        }
        $resources->register(new TenantResourceDefinition('pages.pages', 'pages', Page::class));
        $resources->register(new TenantResourceDefinition('pages.translations', 'pages', PageTranslation::class, TenantResourceKind::Inherited, 'pages.pages', 'page'));
        $adoption?->register('pages', PagesAdoptionAdapter::class);
    }
}
