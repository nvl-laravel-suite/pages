# NVL Pages — API and usage

## Quickstart

```sh
composer require nvl/pages:^5.0
php artisan nvl:install pages --dry-run
php artisan nvl:install pages
```

Required NVL dependencies: `nvl/content` (`^5.0`), `nvl/core` (`^5.0`), `nvl/filterable` (`^5.0`), `nvl/seo` (`^5.0`), `nvl/translatable` (`^5.0`). Configure sites, locale/content/media integrations and PageActorData authorization. Supply validated FilterSet and an allowed site from host configuration.
Review the published common config, select one migration owner, and run schema preflight before existing-table upgrades. The installer does not enable features or run migrations. Follow the detailed installation and capability sections below before invoking a storage/provider operation.

Inject `Nvl\Pages\Contracts\ListPagesContract` in a host service. After supplying the trusted inputs described above, the first public call is:

```php
use Nvl\Pages\Contracts\ListPagesContract;

/** @var ListPagesContract $capability */
$result = $capability->execute($filters, $site, $actor);
```

Use the [event catalog](docs/events.md) and [Testing your app](#testing-your-app) below. The suite [getting-started guide](https://github.com/nvl-laravel-suite/laravel-suite/blob/main/docs/getting-started.md) provides a complete Comments host fixture; package archives retain their own local references.


[← NVL Laravel Suite](https://github.com/nvl-laravel-suite)

For support, [open an issue](https://github.com/nvl-laravel-suite/pages/issues). For vulnerabilities, use
[private reporting](https://github.com/nvl-laravel-suite/pages/security/advisories/new). See [Contributing](CONTRIBUTING.md).

See the [installation and publishing guide](https://github.com/nvl-laravel-suite/laravel-suite/blob/main/docs/installation.md) for Composer setup, configuration, migration ownership, and agent skills.

## Quick reference

| Item | Value |
|---|---|
| Installed through | `composer require nvl/pages:^5.0` |
| Module identifier | `nvl/pages` |
| PHP namespace | `Nvl\Pages` |
| Service provider | `Nvl\Pages\Providers\PagesServiceProvider` |
| Configuration | `config/nvl-pages.php` |

`nvl/pages` is a headless Laravel package for structural pages, four-level navigation trees, localized editorial copy, dynamic resource-backed routes, composed Content blocks, SEO, Metafields, and sitemap discovery.

## Purpose and boundaries

Pages owns URL structure, hierarchy, lifecycle, navigation state, resource-handler registration, resolution, and sitemap participation. It deliberately does not duplicate:

- localized content fields, which belong to `nvl/translatable`;
- page sections and blocks, which belong to `nvl/content`;
- metadata and structured data, which belong to `nvl/seo`;
- application-defined custom fields, which belong to `nvl/metafields`;
- binary assets, which are referenced by Content through `nvl/media`.

The package is intended for Laravel applications that need a stable front-end content entry point without adopting an admin UI or a monolithic CMS. It supports PHP 8.4+ and Laravel 13.

## Optional tenant sites

When tenancy is enabled, adopt the `media`, `content`, `metafields`, `seo`, and
`pages` families as one compatible graph. Pages become tenant roots; locale
rows and parent paths inherit the root tenant. Key, site/path, tree locks, and
parent constraints are tenant-leading. The normal DTOs remain tenant-agnostic.

Public routes prepend `ResolvePublicTenant`. The host `TenantSiteResolver` must
return one verified `TenantSiteContext` containing tenant, site, and canonical
origin before model binding, cache lookup, or error-producing Page work. The
default request-context and URL services use only that context when adopted.
Outside HTTP, enter `TenantRunner` and install a host-verified site context for
the duration of the operation; caller-selected site/origin strings are not
authority.

Dynamic handlers in an adopted deployment implement
`TenantSafePageResourceHandler`, declare their exact registered model, and
return a query that Pages scopes before count, pagination, or fetch. Returned
models are canonically checked again before projection.

## Requirements and installation

Install the package in a clean Laravel application:

```bash
composer require nvl/pages:^5.0
php artisan vendor:publish --tag=nvl-pages-translations
php artisan vendor:publish --tag=nvl-pages-config
php artisan vendor:publish --tag=nvl-pages-skills
php artisan migrate
php artisan nvl:pages:doctor --strict
```

Laravel package discovery registers the provider. Composer installs the required Content, Core, Filterable, SEO, and Translatable packages and their dependencies automatically. Metafields and Tenancy are optional integrations. The default tables are `nvl_pages_pages`, `nvl_pages_i18n`, and `nvl_pages_tree_locks`; their names and the database connection are configurable.

Routes are disabled by default. Publishing migrations is optional because package migrations load automatically while `nvl-pages.migrations.enabled` is true.

Choose exactly one migration owner. For automatic vendor loading, leave
`nvl-pages.migrations.enabled=true` and do not publish `nvl-pages-migrations`. For
host-owned migrations, run
`php artisan vendor:publish --tag=nvl-pages-migrations`, set
`nvl-pages.migrations.enabled=false` before the first migration, and maintain the
copied files as application migrations. Never run both sources; Laravel
retimestamps published migrations.

## First working page

Use the system actor only from trusted application code such as a deployment seeder or an authorized application Action:

```php
use Nvl\Pages\Actions\CreatePageAction;
use Nvl\Pages\Data\Mutations\CreatePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Enums\PageStatus;
use Nvl\Pages\Models\Page;

final readonly class CreateAboutPage
{
    public function __construct(private CreatePageAction $createPage) {}

    public function execute(): Page
    {
        return $this->createPage->execute(
            new CreatePageData(
                key: 'company.about',
                slug: 'about',
                status: PageStatus::Published,
                translations: [
                    'en' => [
                        'title' => 'About',
                        'navigationLabel' => 'About',
                        'summary' => 'How the organization works.',
                    ],
                ],
            ),
            PageActorData::system(),
        );
    }
}
```

The provider automatically registers the Page model as:

- a Content placement owner using the `page` alias;
- an SEO owner using the `page` alias;
- a Metafields owner using the `page` alias;
- a Translatable resource under `pages.pages`;
- an SEO sitemap source.

The SEO and Metafields aliases are configurable and checked for collisions. The canonical Content owner alias is intentionally fixed as `page`.

## Hierarchy and paths

Pages use stable UUIDs, globally unique keys, site-scoped sibling slugs, a canonical materialized path, and a SHA-256 path identity. The hierarchy limit defaults to four and cannot be configured above four. Every structural mutation acquires one stable per-site database lock before validating the tree. Moves authorize the requested destination through `PageAuthorizationContextData::parentId`, reject cycles and cross-site parents, update only paths that changed, increment affected revisions, and invalidate the sitemap scope once after commit. Restoration recomputes a deleted page's path from its current parent, including parent moves and renames performed during deletion.

Slugs are structural and locale-independent. Titles, navigation labels, and summaries live only in `pages_i18n` and use Translatable’s deterministic locale fallback.

`CreatePageAction`, `UpdatePageAction`, `MovePageAction`, `DeletePageAction`, and `RestorePageAction` own their transactions. Updates, moves, deletion, and restoration require an exact revision DTO. A parent cannot be deleted while it has children. Lifecycle abilities are checked only when the status actually changes; creating or entering a published/scheduled state requires `publish`, while entering archived requires `archive`.

## Dynamic resource pages

A resource page stores a stable handler alias, never an arbitrary class name from a request. Register handlers in `nvl-pages.resources`:

```php
use Domain\Site\CatalogEntryPageHandler;

'resources' => [
    'catalog.entry' => CatalogEntryPageHandler::class,
],
```

The handler implements `PageResourceHandler` or extends `AbstractPageResourceHandler`. It defines:

- a stable alias;
- a relative route pattern such as `{id}`;
- Laravel validation rules for every route parameter;
- the fully constrained Eloquent query, including tenancy, publication, policy, and eager-load conditions;
- fetching from that constrained query;
- a sanitized `PageResourceData` projection;
- optional absolute `SitemapEntry` objects streamed in bounded application-defined chunks.

If a resource page has path `pages/catalog` and its handler pattern is `{id}`, resolution accepts `pages/catalog/42`. Static paths are tested first. Dynamic candidates are prefiltered by structural path-prefix hashes, then evaluated by longest base path. Handler rule keys must exactly match route placeholders. Invalid parameters and missing resources return not-found responses rather than exposing validation or query details.

The package never serializes the resolved Eloquent model. The handler must explicitly construct its public DTO payload.

## Bounded page reads

Consumers should use the focused DTO-first reads for page selectors, key
validation, and one-level public listings instead of querying `Page` or its
translations directly:

```php
use Nvl\Pages\Actions\CheckPageKeyAvailabilityAction;
use Nvl\Pages\Actions\FindPageByKeyAction;
use Nvl\Pages\Actions\ListPageOptionsAction;
use Nvl\Pages\Actions\ListPublicChildPagesAction;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Data\PageRequestContextData;
use Nvl\Pages\Enums\PageKind;
use Nvl\Pages\Enums\PublicChildPageOrder;

$actor = PageActorData::fromAuthenticatable($user);
$page = app(FindPageByKeyAction::class)->execute('main', 'about', $actor);
$availability = app(CheckPageKeyAvailabilityAction::class)->execute(
    'main',
    'about',
    $actor,
    exceptId: $page->id,
);
$options = app(ListPageOptionsAction::class)->execute(
    'main',
    'bg',
    $actor,
    search: 'about',
);
$children = app(ListPublicChildPagesAction::class)->execute(
    $page->id,
    new PageRequestContextData('main', 'bg'),
    limit: 24,
    kind: PageKind::Static,
    order: PublicChildPageOrder::Newest,
);
```

`FindPageByKeyAction` trims and validates the site and globally unique key,
keeps the lookup site-scoped, authorizes `View`, and returns `PageData` with its
translation map. `CheckPageKeyAvailabilityAction` authorizes `List` before SQL
and mirrors the actual global unique index, including soft-deleted rows. A
same-site conflict exposes its ID so an update can use `exceptId`; a conflict in
another authorized site reports unavailable without disclosing that page's ID.
An `exceptId` only excludes the same-site row, so a foreign UUID cannot bypass
the write constraint.

`ListPageOptionsAction` returns `PageOptionData(id, key, label, path, kind,
status, revision)` ordered by path and ID. Labels resolve the requested locale
through Translatable fallback, then fall back to the stable key. Empty search
returns the default bounded list, one-character typeahead input returns an
empty collection without storage queries, and longer input searches key, path,
title, and navigation label case-insensitively. The requested limit is clamped
to `nvl-pages.limits.maximum_page_options` and an absolute 100-row ceiling. Search
input must be valid UTF-8 without NUL bytes so behavior remains portable across
supported databases.

`ListPublicChildPagesAction` validates the trusted site/locale context, requires
the parent itself to be publicly visible in that site, authorizes
`ViewNavigation` before child SQL, and returns only currently public children as
localized `PublicPageData`. Package-built public projections include the
optional additive `publishedAt` field, using the explicit publication time or
the persisted creation time when publication is immediate. The PHP constructor
and generated TypeScript property remain optional for 1.x source compatibility.
The default uses canonical sibling order. Consumers can allowlist one
`PageKind` and select `PublicChildPageOrder::Newest` to filter and order by the
effective publication timestamp before the requested limit—for example, a
static news-card feed. Results are clamped to
`nvl-pages.limits.maximum_public_children` plus the same absolute 100-row ceiling.
Option reads use two fixed queries and populated public-child reads use three,
whether one or 25 records are returned. These projections are uncached because
authorization, locale fallback, publication windows, and hierarchy are
request-sensitive.

## Editor and publication projections

Pages composes its neighboring package reads so applications do not have to
assemble Page, Content, SEO, and Metafields state in controllers. Inject the
smallest projection for the workflow:

```php
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Nvl\Pages\Actions\GetPageEditorBootstrapAction;
use Nvl\Pages\Actions\GetPagePublicationProjectionAction;
use Nvl\Pages\Actions\ListPageEditorSummariesAction;
use Nvl\Pages\Data\PageActorData;

final readonly class PageWorkspace
{
    public function __construct(
        private ListPageEditorSummariesAction $summaries,
        private GetPageEditorBootstrapAction $editor,
        private GetPagePublicationProjectionAction $publication,
    ) {}

    public function index(string $site, string $locale, PageActorData $actor): LengthAwarePaginator
    {
        return $this->summaries->execute($site, $locale, $actor, perPage: 25);
    }

    public function edit(string $pageId, string $locale, PageActorData $actor): array
    {
        return $this->editor->execute($pageId, $locale, $actor)->toArray();
    }

    public function show(string $pageId, string $locale, PageActorData $actor): array
    {
        return $this->publication->execute($pageId, $locale, $actor)->toArray();
    }
}
```

`ListPageEditorSummariesAction` authorizes the site-level `List` ability before
SQL, clamps the page size to 100, and returns a paginator of
`PageEditorSummaryData`. Each item contains the management `PageData`, a
localized label with stable key fallback, Content placement summaries, and the
site-scoped SEO profile. SEO authorizes every owner before its batched profile
query; a denial returns no summaries and performs no SEO profile query. A
populated one- or 25-page result uses the same fixed query count and at most ten
queries. Definition and preset catalogs intentionally do not repeat on every
row, and both configured and requested page sizes remain under the absolute
100-owner ceiling.

`GetPageEditorBootstrapAction` authorizes and resolves one Page, then returns
`PageEditorBootstrapData`: Page state, the complete Content editor projection,
the site-scoped SEO profile, authorized Metafields, Page kinds and statuses,
registered resource aliases, and the configured maximum depth. Content, SEO,
and Metafields retain their own authorization boundaries; a denial propagates
and no partial bootstrap is returned. Empty optional state is represented by
empty collections or `null`, not by consumer-side fallback queries.

`GetPagePublicationProjectionAction` is the ID-based public seam for a static
Page already known to the application. It requires current public visibility,
authorizes `View`, renders public-only Content, resolves SEO, and returns the
same redacted `ResolvedPageData` shape as path delivery. Use
`ResolvePageAction` when resolving a public path or dynamic resource Page, and
`PreviewPageAction` for authorized management preview; the ID-based publication
Action rejects resource Pages because their handler parameters are path-owned.

These projections are uncached. Authorization, locale fallback, Page lifecycle,
publication windows, Content visibility, SEO, and custom values can all change
within a request-sensitive workflow.

## Content blocks

`Page` implements `ContentOwner` with `HasContent`. Its sections use the
canonical `page` morph alias and `content` composition group. Use Content
through the injected `Nvl\Content\Content` application surface, or its facade,
with the Page model itself; do not pass owner aliases or IDs through
application code. `PageActorData::contentActor()` preserves the same actor
identity at the Content authorization boundary. Public resolution calls
`Content::render()` with `publicOnly: true`, so hidden, private, unpublished,
invalid, or unauthorized references do not enter the page response.

The public `ResolvedPageData` contains:

- locale-resolved, management-redacted `PublicPageData`;
- `RenderedContentCompositionData` with ordered block trees and regions;
- `ResolvedSeoData`;
- optional sanitized `PageResourceData`.

`PageData` is reserved for authorized management reads and previews. It contains lifecycle, revision, translation-map, and sitemap state that public delivery deliberately omits.

There is no page-block pivot in this package because Content already provides placement identity, scope, region, ordering, nesting, revisions, visibility, and Media validation.

## SEO, structured data, and sitemaps

Attach SEO profiles through SEO Actions. SEO profiles own localized canonical paths, social metadata, robots rules, images, and JSON-LD providers. Pages owns all Page sitemap entries and registers that ownership with SEO so the generic profile source does not emit them independently. Every entry requires a currently public, sitemap-included Page in the requested site. An active scoped profile with `isIndexable=false` or `sitemapIncluded=false` suppresses the Page entry, including dynamic handler entries. A qualifying routed profile supplies canonical URLs and language alternates through SEO's shared projection; absent profiles or profiles without routes use the Page path. External canonical overrides do not trigger a local fallback URL.

Dynamic handlers stream their own canonical `SitemapEntry` objects because only the application knows how to chunk and constrain its resource query. Every page mutation invalidates only the changed site’s sitemap cache after commit.

Configure absolute page URLs:

```php
'urls' => [
    'base_url' => 'https://example.test',
    'locale_prefix' => true,
    'default_locale' => 'en',
],
```

Bind `PageUrlGenerator` for tenant domains, locale domains, signed previews, or another URL policy.

## Navigation, preview, and APIs

Mutation Actions fail closed unless called by a system actor or allowed by a consumer `PageAuthorization` binding. Anonymous reads are allowed only for publicly eligible pages and navigation. Scheduled pages resolve after `published_at`; expired and archived pages do not.

Public HTTP requests use `PageRequestContextResolver`. The default implementation takes the site from `nvl-pages.public.default_site` and validates the requested locale against Translatable. It never trusts a caller-supplied site. Bind the contract to a host-, domain-, or tenant-aware implementation for multi-site applications.

The public and management route groups are independent:

```php
'routes' => [
    'public' => [
        'enabled' => true,
        'prefix' => 'nvl/api/v1/pages',
        'name' => 'nvl.pages.public.',
        'middleware' => ['api', 'throttle:120,1'],
    ],
        'management' => [
            'enabled' => false,
            'prefix' => 'nvl/api/v1/pages/_manage',
        'name' => 'nvl.pages.management.',
        'middleware' => ['api', 'auth', 'throttle:60,1'],
    ],
],
```

The public endpoints resolve `GET /nvl/api/v1/pages/{path}` and `GET /nvl/api/v1/pages/_navigation`. Management endpoints default to `/nvl/api/v1/pages/_manage`, where they list one explicit site, create, inspect, replace, move, preview, soft-delete, and restore pages. The leading-underscore transport segments cannot collide with valid page slugs. Route names, prefixes, and non-empty middleware lists are validated before registration and work with route caching.

## Commands and operations

```bash
php artisan nvl:pages:doctor
php artisan nvl:pages:doctor --strict --format=json
```

The doctor is read-only. It checks all three tables, required columns, configured resource handlers, route and middleware parity, management authorization, path/hash/parent drift, cycles, orphans, depth, lifecycle dates, statuses, and resource aliases. Strict mode exits non-zero when the installation is unhealthy.

Run sitemap generation and Content/SEO/Metafields operations through those packages’ documented commands. Pages does not hide their operational boundaries behind duplicate commands.

## Extension points

- Bind `PageAuthorization` to a policy adapter.
- Bind `PageRequestContextResolver` to trusted tenant/site resolution.
- Bind `PageUrlGenerator` to a site-aware URL implementation.
- Register `PageResourceHandler` implementations in configuration.
- Use Content definitions, field adapters, renderers, and owner placements.
- Use SEO structured-data providers for resource-specific JSON-LD.
- Use Metafields definitions for page-specific custom fields.
- Listen to the after-commit `PageChanged` event.

## Failure and concurrency behavior

Database mutations are atomic on the configured Pages connection and tree mutations are serialized per site. Resource handlers do not receive unvalidated parameters. Duplicate keys, sibling slugs, and paths raise `PageConflictException`; revision mismatches raise `StalePageException`; invalid parents, cycles, excessive depth, and unsafe deletion raise `PageHierarchyException`. Package HTTP adapters render these as stable 409 responses, while invalid mutation invariants render as 422 and invalid public paths as 404.

Content, SEO, and Metafields retain their own authorization and mutation contracts. Applications coordinating writes across packages should configure the same database connection and establish one application-level transaction.

## Verification and development

From a standalone checkout of the public Pages repository:

```bash
composer install
composer quality
```

Maintainer CI additionally checks the package family and generated types from the private source workbench. The test suite boots Pages with only declared dependencies and covers clean migration, redacted static resolution, dynamic handler conditions, localized navigation, hierarchy limits, selective path rebuilding, site locks, lifecycle abilities, stale and duplicate mutations, site-scoped lists, preview, restoration, sitemap delegation, route defaults, and doctor output.

## Injectable workflow contracts

Constructor-inject focused interfaces from `Nvl\Pages\Contracts` when composing host workflows. Each interface retains the native Action’s complete `execute` parameters, defaults, return type, and documented generic/shape result. Concrete Actions remain directly usable in major 5.

```php
use Nvl\Pages\Contracts\CreatePageContract;
use Nvl\Pages\Data\Mutations\CreatePageData;
use Nvl\Pages\Data\PageActorData;
use Nvl\Pages\Models\Page;

final readonly class CreatePageWorkflow
{
    public function __construct(private CreatePageContract $workflow) {}

    public function execute(CreatePageData $data, PageActorData $actor): Page
    {
        return $this->workflow->execute($data, $actor);
    }
}
```

The provider installs conditional transient defaults (`bindIf`) for the following selected workflows. A host interface binding registered before package discovery is retained; a later binding/instance replacement is used by newly resolved host services. Keep authorization, validation, query ownership, and mutation behavior inside the owning package workflow.

| Contract | Native implementation |
| --- | --- |
| `CheckPageKeyAvailabilityContract` | `CheckPageKeyAvailabilityAction` |
| `CreatePageContract` | `CreatePageAction` |
| `DeletePageContract` | `DeletePageAction` |
| `FindPageByKeyContract` | `FindPageByKeyAction` |
| `GetNavigationContract` | `GetNavigationAction` |
| `GetPageContract` | `GetPageAction` |
| `GetPageEditorBootstrapContract` | `GetPageEditorBootstrapAction` |
| `GetPagePublicationProjectionContract` | `GetPagePublicationProjectionAction` |
| `ListPageEditorSummariesContract` | `ListPageEditorSummariesAction` |
| `ListPageOptionsContract` | `ListPageOptionsAction` |
| `ListPagesContract` | `ListPagesAction` |
| `ListPublicChildPagesContract` | `ListPublicChildPagesAction` |
| `MovePageContract` | `MovePageAction` |
| `PreviewPageContract` | `PreviewPageAction` |
| `ResolvePageContract` | `ResolvePageAction` |
| `RestorePageContract` | `RestorePageAction` |
| `UpdatePageContract` | `UpdatePageAction` |

## Supported PHP usage

The source `@api` declarations identify supported workflows, extension contracts, and value types. Public members marked `@internal` and untagged implementation types remain package-owned. Concrete Actions retain their existing constructors, qualifiers, and `execute()` signatures.

A package model returned or accepted by a public workflow is an identity/result handle. Use its declared type and `getKey()`, `getKeyName()`, `getMorphClass()`, `getRouteKey()`, `getRouteKeyName()`, `is()`, `isNot()`, and `relationLoaded()`. Read only explicitly declared in-memory `@nvl-consumer-read` fields; ordinary model PHPDocs and fillable attributes do not grant consumer reads. Obtain display projections through public reads. Persistence, additional model queries, relation access/loading, and generic model serialization are outside this contract. Host-model queries remain available, while traversal or aggregates of package capability relations require the package public reader or authorized adapter.

## Shared owner identity

Declare a model once in `config/nvl-core.php`:

```php
'owners' => [Article::class],
```

Enable this package capability separately in `config/nvl-pages.php`:

```php
'resources' => [
    'articles.detail' => ['owner' => Article::class, 'handler' => ArticlePageHandler::class],
],
```

Keep the resource handler, route pattern, query visibility, and presentation behavior. Resource keys may differ from owner aliases; query and fetched models must match the declared owner. Core registration does not add the model to this package's allowlist.

Laravel's `getMorphClass()` determines stored identity. These class declarations do not install host morph maps. Keep resolvers, handlers and authorization independent; use `nvl:doctor --strict --format=json` to review legacy alias mismatches or stored identity drift. See [UPGRADING.md](UPGRADING.md) before changing the host's morph map.

## Shared consumer diagnostics

Run `php artisan nvl:doctor --strict --format=json` to combine the read-only checks from loaded NVL package providers. Errors fail the gate, and strict mode also fails warnings. This package's existing Doctor command remains available and uses the same package-owned inspection service.

## Optional Metafields editor adapter

Pages installs without `nvl/metafields`. `nvl-pages.integrations.metafields` accepts `null` (automatic activation from a loaded Metafields provider), `false` (disabled), or `true` (required). Explicitly requiring an unavailable adapter produces a configuration error. Core Doctor reports automatic inactivity as information.

`GetPageEditorBootstrapAction` returns an empty `metafields` array while this adapter is inactive. Content and SEO remain required editor integrations. When Metafields is loaded, its own authorized read Action supplies fields through `Nvl\Pages\Contracts\PageMetafields`. Hosts can bind that contract before the package default.

Editor fields use Pages-owned `PageMetafieldFieldData`; the serialized field shape is preserved without importing a foreign DTO. DTO discovery and generated TypeScript work when Metafields is absent.

Pages retains an internal lazy Metafields relation resolver when the provider is loaded and the integration is active; Page no longer composes `HasMetafields` directly. Consumers read editor fields through `GetPageEditorBootstrapAction` and `PageMetafields`, and use the authorized Metafields Actions for reads and mutations. Migrate direct `$page->metafields()` traversal to these entry points or an explicitly authorized host adapter. An empty editor section while the integration is inactive does not grant relation access.

## Next major: isolated schema identities

Use `nvl-pages.tables.<logical-key>` for every table and `nvl-pages.connection` for its database connection. Null connection inherits `nvl-core.connection`, then Laravel's default. Tables are resolved at runtime by the package table definition helper.

| Logical key | New default | Previous name |
| --- | --- | --- |
| `pages` | `nvl_pages_pages` | `pages` |
| `i18n` | `nvl_pages_i18n` | `pages_i18n` |
| `tree_locks` | `nvl_pages_tree_locks` | `page_tree_locks` |

Migration filenames contain `nvl_pages_`. Existing installations must complete the upgrade in `UPGRADING.md` before running new migrations. A pending creator rejects an existing target before that owned migration runs; use `nvl:schema:preflight` for an explicit whole-batch check; legacy storage with old history needs an ownership decision.

## Canonical configuration ownership

Use `nvl-pages` settings in `config/nvl-pages.php` and canonical package environment names. Old generic roots are foreign unless an upgrading NVL host explicitly selects them in Core's default-off compatibility. Canonical false/null/empty values win; no old roots are populated or written back. Keep logical package/resource IDs unchanged. Review [Core's rename inventory and cache/worker cutover](https://github.com/nvl-laravel-suite/core/blob/main/UPGRADING.md#major-5-canonical-configuration-and-environment).

## Testing your app

Inject the supported contract rather than constructing its concrete Action or querying package tables. Replace `Nvl\Pages\Contracts\ListPagesContract` in Laravel's native container for a host-workflow test:

```php
use Nvl\Pages\Contracts\ListPagesContract;

$double = Mockery::mock(ListPagesContract::class);
$this->app->instance(ListPagesContract::class, $double);
// Configure the exact execute arguments and documented return value for your host case.
```

The package's conditional native binding preserves host substitutions. Production uses the real contract; test doubles do not prove its storage/authorization behavior.

A detached fixture for a returned identity/data handle is:

```php
use Nvl\Pages\Models\Page;
$fixture = Page::factory()->withoutParents()->make();
```

Ordinary `make()` may persist declared package parents. `withoutParents()->make()` disables parent expansion/admission for detached fixtures; use explicit persisted parents/owners and matching effective connections for a real `create()`. Factories do not authorize workflows, call Stripe, create backing Media objects or publish Template artifacts. Enabled tenancy requires explicit admitted persisted tenants/parents. Your host test installation supplies Faker; no test runner is a runtime package dependency.

Use Laravel `Event::fake()`, `Queue::fake()`, `Mail::fake()` or `Storage::fake()` only for the effects the host test intends to isolate. Use real commits/listeners for timing proof. Add the optional Core consumer boundary rules to host PHPStan:

```neon
includes:
    - vendor/nvl/core/support/consumer-audit.neon
parameters:
    nvlConsumer:
        testPaths: [tests]
        tableNames: []
        exceptions: []
```

Rules read installed public metadata without suite boot. They flag internal symbols, package model queries/writes, capability relations and owned tables; they cannot prove dynamic code or runtime authorization. Exact exceptions require `file`, `identifier`, `symbol`, and a documented `reason`. New C3/C4/E tests, archives and guide execution remain pending until the integration phase records results.

### Shipped factory states

These runtime builders keep Laravel's native Factory API. The listed methods name explicit supported parent/owner/lifecycle states; follow each factory's native admission requirements. Detached examples above do not assert persistence validity.

| Factory | Explicit states |
| --- | --- |
| [`PageFactory`](database/factories/PageFactory.php) | Native Factory states only |
| [`PageTranslationFactory`](database/factories/PageTranslationFactory.php) | `forPage(Page $parent)` |

## Error codes and events

All recognized package failures implement `Nvl\Support\Contracts\PackageException`; only `RespondableException` opts into safe response metadata. Keep native PHP programmer errors and Laravel/SDK exceptions distinct. The optional `PackageExceptionRenderer` is registered by the host in `withExceptions`; it leaves unrelated, marker-only and non-JSON handling to the host. Its JSON envelope is `{message:string, code:string, context:object}`. Request locale is host-owned; diagnostics/previous exceptions are not public copy. Event schemas and source connections are documented in [events](docs/events.md).

The table lists enum discriminators, including any successful codes retained for compatibility. A code is not itself an HTTP status; the throwing exception's `suggestedStatus()` is authoritative, especially legacy/custom constructors. Empty context renders as `{}`; only documented JSON-safe context is presented.

| Code | Suggested status | Public context | Translation key |
| --- | --- | --- | --- |
| `operation_failed` | Exception-defined; see `suggestedStatus()` | Declared safe scalar/array map; otherwise `{}` | `nvl-pages::responsecode.operation_failed` |
| `invalid_page_mutation` | 422 | Declared safe scalar/array map; otherwise `{}` | `nvl-pages::responsecode.invalid_page_mutation` |
| `page_conflict` | 409 | Declared safe scalar/array map; otherwise `{}` | `nvl-pages::responsecode.page_conflict` |
| `page_hierarchy_conflict` | 409 | Declared safe scalar/array map; otherwise `{}` | `nvl-pages::responsecode.page_hierarchy_conflict` |
| `stale_page` | 409 | Declared safe scalar/array map; otherwise `{}` | `nvl-pages::responsecode.stale_page` |


## License

NVL Pages is open-sourced software licensed under the MIT license.
