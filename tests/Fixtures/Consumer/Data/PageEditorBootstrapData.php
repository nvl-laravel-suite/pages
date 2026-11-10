<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Pages\Tests\Fixtures\Consumer\Support\SerializesPageValues;
use Nvl\Seo\Models\SeoProfile;

/**
 * Complete application-owned bootstrap for one authorized Page editor.
 *
 * @implements Arrayable<string, mixed>
 *
 * @api
 */
final class PageEditorBootstrapData implements Arrayable, JsonSerializable
{
    use SerializesPageValues;

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
        public readonly array $metafields,
        public readonly array $pageKinds,
        public readonly array $pageStatuses,
        public readonly array $resourceAliases,
        public readonly int $maximumDepth,
    ) {}
}
