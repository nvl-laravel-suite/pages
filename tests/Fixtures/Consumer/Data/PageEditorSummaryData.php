<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Models\ContentPlacement;
use Nvl\Seo\Models\SeoProfile;
use Spatie\LaravelData\Data;

/**
 * Bounded Page, Content, and SEO projection for management indexes.
 *
 * @api
 */
final class PageEditorSummaryData extends Data
{
    /**
     * @param  list<ContentPlacement>  $placements
     */
    public function __construct(
        public readonly PageData $page,
        public readonly string $label,
        public readonly array $placements,
        public readonly ?SeoProfile $seo,
    ) {}
}
