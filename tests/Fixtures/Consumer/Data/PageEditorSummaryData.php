<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Data\ContentPlacementData;
use Nvl\Seo\Data\SeoProfileData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * Bounded Page, Content, and SEO projection for management indexes.
 *
 * @api
 */
final class PageEditorSummaryData extends Data
{
    /**
     * @param  list<ContentPlacementData>  $placements
     */
    public function __construct(
        public readonly PageData $page,
        public readonly string $label,
        #[DataCollectionOf(ContentPlacementData::class)]
        public readonly array $placements,
        public readonly ?SeoProfileData $seo,
    ) {}
}
