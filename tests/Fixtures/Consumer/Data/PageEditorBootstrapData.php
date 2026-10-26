<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Nvl\Content\Models\ContentPlacement;
use Nvl\Seo\Models\SeoProfile;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * Complete application-owned bootstrap for one authorized Page editor.
 *
 * @api
 */
final class PageEditorBootstrapData extends Data
{
    /**
     * @param  list<ContentPlacement>  $content
     * @param  list<PageMetafieldFieldData>  $metafields
     * @param  list<string>  $pageKinds
     * @param  list<string>  $pageStatuses
     * @param  list<string>  $resourceAliases
     */
    public function __construct(
        public readonly PageData $page,
        public readonly array $content,
        public readonly ?SeoProfile $seo,
        #[DataCollectionOf(PageMetafieldFieldData::class)]
        public readonly array $metafields,
        public readonly array $pageKinds,
        public readonly array $pageStatuses,
        public readonly array $resourceAliases,
        public readonly int $maximumDepth,
    ) {}
}
