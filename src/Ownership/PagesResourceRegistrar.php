<?php

declare(strict_types=1);

namespace Nvl\Pages\Ownership;

use Nvl\Pages\Models\PageTranslation;
use Nvl\Pages\Services\PageModelRegistry;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Ownership\ValueObjects\OwnershipDefinition;
use Nvl\Support\Ownership\ValueObjects\OwnershipResourceDefinition;

/** Compiles the one configured page tree and its inherited localized copy. @internal */
final readonly class PagesResourceRegistrar
{
    public function __construct(private PageModelRegistry $models) {}

    public function register(OwnershipResourceRegistry $resources): void
    {
        $class = $this->models->pageClass();
        $owned = $class::ownershipDefinition()->strategy !== 'unscoped';
        $resources->register(new OwnershipResourceDefinition('pages.pages', 'pages', $class, $class::ownershipDefinition(), immutableFields: $owned ? ['site'] : []));
        $resources->register(new OwnershipResourceDefinition('pages.translations', 'pages', PageTranslation::class, $owned ? OwnershipDefinition::inherited('page') : OwnershipDefinition::unscoped(), parentResource: $owned ? 'pages.pages' : null));
    }
}
