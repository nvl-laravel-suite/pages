<?php

declare(strict_types=1);

namespace Nvl\Pages\Events;

use Illuminate\Container\Container;
use Nvl\Pages\Enums\PageChangeOperation;
use Nvl\Seo\Providers\SeoServiceProvider;
use Nvl\Seo\Services\SitemapCache;
use Nvl\Support\Contracts\DomainEvent;
use Nvl\Support\Integrations\OptionalIntegration;

/**
 * Signals a committed page structure or lifecycle mutation.
 *
 * @api
 */
final readonly class PageChanged implements DomainEvent
{
    /** @var array{connection:string,tenantId:string,site:string,origin:string,scope:string,version:int,key:string,namespace:string}|null */
    public ?array $sitemapIdentity;

    /**
     * Create a committed page-change event.
     *
     * @param  array{type:?string,id:int|string|null,system:bool}  $actor
     * @param  array{connection:string,tenantId:string,site:string,origin:string,scope:string,version:int,key:string,namespace:string}|null  $sitemapIdentity
     * @param  list<string>  $affectedPageIds
     */
    public function __construct(
        public string $pageId,
        public string $site,
        public PageChangeOperation $operation,
        public int $revision,
        public array $actor,
        public array $affectedPageIds = [],
        ?array $sitemapIdentity = null,
        public int $schemaVersion = 1,
    ) {
        $this->sitemapIdentity = $sitemapIdentity
            ?? (app(OptionalIntegration::class)->enabled('pages.integrations.seo', SeoServiceProvider::class) ? Container::getInstance()->make(SitemapCache::class)->capture($site) : null);
    }

    /** Return the immutable event payload schema version. */
    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }
}
