# Pages usage and composition

Read [installation](installation.md) for setup and [API](api.md) for exact declarations.
Example `App\...` classes are owned by the host application.

## Host policy and storage

Bind `Nvl\Pages\Contracts\PageAuthorization` to the host adapter or set
`nvl-pages.authorization.class` before package registration. Its method is
`authorize(PageAbility $ability, Authenticatable|SystemActor|PublicActor $actor,
?Page $page = null, array $context = []): void`. Throw on denied capability.
Context includes site/parent/path/locale/resource facts where relevant. Do not
read an ambient guard to replace the supplied actor.

The default `ConfiguredPageAuthorization` supports eligible public View and
ViewNavigation only. Compose it deliberately with management/preview permission;
an unconditional allow would expose drafts. Native mutation services reload
Eloquent actors and current Page site. External identity providers retain their
own freshness contract; preview specifically requires persisted Eloquent identity.

Select `nvl-pages.models.page` with a concrete Page subclass before boot. Keep the
native UUID, casts, relationships, tree and ownership hooks, effective table and
connection (`nvl-pages.connection` / `tables`). Selection is immutable. Added columns
need host migrations. Fixed translation/tree-lock records have no model selector.

Host URL generation can bind `PageUrlGenerator` or configure `nvl-pages.urls.generator`.
The native generator uses `urls.base_url`, `urls.locale_prefix` and
`urls.default_locale`; these shape URLs without registering routes.

## Create, replace, move and publication

```php
use Nvl\Pages\Services\PageLifecycle;

$pages = app(PageLifecycle::class);
$page = $pages->create([
    'key' => 'about', 'slug' => 'about', 'site' => 'main',
    'translations' => ['en' => ['title' => 'About us']],
], $editor);
$page = $pages->update($page, [
    'slug' => 'about', 'kind' => 'static', 'status' => 'published',
    'translations' => ['en' => ['title' => 'About our company']],
], $page->revision, $editor);
$page = $pages->move($page, null, 0, $page->revision, $editor);
```

Create requires `key` and `slug`; it accepts `parentId`, `site`, `kind`, `resource`,
`status`, `position`, `isNavigable`, `sitemapIncluded`, `sitemapPriority`,
`sitemapChangeFrequency`, `publishedAt`, `expiresAt`, `translations`.
Draft is the initial default. Kind values are `static` and `resource`; status
values are `draft`, `scheduled`, `published`, `archived`.

Update requires `slug`, `kind`, `status`. It replaces editable state with defaults
for omitted fields; it does not preserve omitted navigation, sitemap, dates or
translations as a generic patch would. Do not send `key`, `site`, `parentId`, owner
fields or `expected_revision` inside this array. Supply the separate revision argument.
Use `move` to reparent/reorder, `delete` to soft-delete, `restore` to restore.
Each takes its documented expected revision. Stale state is a conflict, not a
reason to reload and silently overwrite a user's concurrent edit.

Translation rows are locale keyed with required `title`, optional nullable
`navigationLabel` and `summary`. An explicit replacement retains exactly the
validated locale map. Site and parent tree rules remain canonical. Publication
requires separate host ability and valid scheduling/expiration dates. Services
own tree locking, depth/cycle validation, unique key/path handling, descendant path
changes and transactional domain events on the actual Page connection.

## Read, resolve and preview

```php
use Nvl\Pages\Services\PageReader;
use Nvl\Pages\Services\PageResolver;

$catalog = app(PageReader::class)->paginate('main', $editor, [
    'status' => 'published', 'navigable' => true,
], 25);
$resolved = app(PageResolver::class)->resolve('/about', 'main', 'en');
$navigation = app(PageReader::class)->navigation('main', 'en');
```

`resolve` returns `['page' => Page, 'resource' => null|['type'=>string,
'id'=>string,'payload'=>array]]`. It authorizes the explicit anonymous public
identity and enforces publication/expiration, site and locale. No match retains
the service's not-found failure; the consumer maps that to its own response.
Private `preview` accepts a persisted human actor and separate Preview authority;
do not use public resolution with draft flags as a preview endpoint.

`PageReader::get` accepts the explicit viewer. Pagination filters are `status`,
`kind`, `parent`, `navigable`, `sort`, with supported enum/list selections.
`children` is one bounded level, with optional `PageKind` and
`PublicChildPageOrder`; navigation produces a localized tree with safe URLs.
`keyAvailable` returns site/key/available/conflictingPageId while hiding a foreign
site's conflicting record. This check is advisory; the write still enforces uniqueness.

In owned hosts, establish `PublicOwnerSiteRunner::run($canonicalOrigin, $callback)`
before resolution/navigation. The origin is verified host configuration, not a
submitted owner/site parameter. Keep caller site selectors consistent with the
admitted site, and retain original Ownership resource/morph identity and adoption.

## Dynamic resource pages

Register `nvl-pages.resources` as alias-to-`PageResourceHandler` class entries.
A shared-identity entry may use `['handler'=>ArticlePageHandler::class,
'owner'=>'article']` when that owner alias is registered.
For example, an application `ArticlePageHandler` can own the alias `article` and
route pattern `{slug}`. Configure a resource Page with `kind='resource'` and
`resource='article'`. The handler must implement all methods from [API](api.md):
`alias`, `routePattern`, `rules`, `query`, `fetch`, `present`, `sitemapEntries`.

The request is `['pageId'=>string,'site'=>string,'locale'=>string,
'parameters'=>array<string,string>]`. Rules must correspond exactly to route
parameters. Query must retain publication, ownership and host predicates before
fetch. Present returns `['type'=>string,'id'=>string,'payload'=>array]` with only
safe consumer data. Do not query the model again without the admitted constraints.

An owned handler implements `OwnershipSafePageResourceHandler` and declares its
exact native resource model capability. Native checks protect both query and fetched
identity. Resource sitemap streams yield absolute SEO `SitemapEntry` values and
must preserve the same visibility/site constraints and bounded iteration.

## Optional Content, SEO and Metafields

```php
config([
    'nvl-pages.integrations.content' => true,
    'nvl-pages.integrations.seo' => true,
    'nvl-pages.integrations.metafields' => false,
]);
```

Only set true when the provider is installed and loaded. Null detects a loaded
provider, false disables, and true requires it. Base Pages works with all three
absent. Integrations register the selected Page identity with Content group
`content`, SEO alias `nvl-page`, and Metafields alias `nvl-page` with configured
sections (`general`, `navigation` by default). The alias/section config keys are
`seo_owner_alias`, `metafield_owner_alias`, `metafield_sections` under integrations.

The host composes `PageResolver` output with authorized `ContentRenderer`,
`SeoReader`/`SeoHeadRenderer`, and `MetafieldReader` operations. Keep their own actor,
owner, locale, revision and privacy contracts; the Page adapter is not a blanket
authorization grant. Do not add package DTOs or cross-package workflow interfaces.
Content integration retains atomic owner cleanup on hard deletion. SEO sitemaps
retain Page visibility/site/inclusion and explicit active-profile exclusions.

## Delivery and verification

The host owns routes, preview authentication, cache policy, HTTP status and HTML.
Do not expose private resource projections or previews through public caches.
Translate hierarchy, stale-version, input and not-found failures without leaking
foreign owner IDs or raw database errors. Test direct services for replacement
semantics, concurrent tree changes, publication windows, canonical policy facts,
foreign resources and rollback; add focused transport tests for preview and routing.
