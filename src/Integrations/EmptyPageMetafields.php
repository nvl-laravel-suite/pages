<?php

declare(strict_types=1);

namespace Nvl\Pages\Integrations;

use Nvl\Pages\Contracts\PageMetafields;
use Nvl\Pages\Data\PageMetafieldFieldData;
use Nvl\Pages\Models\Page;

/** Supplies the editor's empty metafields section when its optional adapter is inactive. */
final class EmptyPageMetafields implements PageMetafields
{
    /**
     * Return an empty section without resolving optional package classes.
     *
     * @return list<PageMetafieldFieldData>
     */
    public function fields(Page $page, string $locale): array
    {
        return [];
    }
}
