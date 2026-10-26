<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Integrations;

use Nvl\Pages\Models\Page;
use Nvl\Pages\Tests\Fixtures\Consumer\Contracts\PageMetafields;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageMetafieldFieldData;

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
