<?php

declare(strict_types=1);

namespace Nvl\Pages\Events;

use Illuminate\Container\Container;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Enums\PageChangeOperation;
use Nvl\Seo\Data\SitemapCacheIdentity;
use Nvl\Seo\Services\SitemapCache;
use Nvl\Support\Contracts\DomainEvent;

/**
 * Signals a committed page structure or lifecycle mutation.
 *
 * @api
 */
final readonly class PageChanged implements DomainEvent
{
    public SitemapCacheIdentity $sitemapIdentity;

    /**
     * Create a committed page-change event.
     *
     * @param  list<string>  $affectedPageIds
     */
    public function __construct(
        public string $pageId,
        public string $site,
        public PageChangeOperation $operation,
        public int $revision,
        public PageActorData $actor,
        public array $affectedPageIds = [],
        ?SitemapCacheIdentity $sitemapIdentity = null,
        public int $schemaVersion = 1,
    ) {
        $this->sitemapIdentity = $sitemapIdentity
            ?? Container::getInstance()->make(SitemapCache::class)->capture($site);
    }

    /** Return the immutable event payload schema version. */
    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }
}
