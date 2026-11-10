<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\SerializesPageValues;
use Nvl\Seo\Models\SeoProfile;

/**
 * Bounded Page, Content, and SEO projection for management indexes.
 *
 * @implements Arrayable<string, mixed>
 *
 * @api
 */
final class PageEditorSummaryData implements Arrayable, JsonSerializable
{
    use SerializesPageValues;

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
