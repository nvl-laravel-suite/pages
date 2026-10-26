# NVL Pages

Localized hierarchical Pages for PHP 8.4 and Laravel 13. Core and Translatable are required. Plain Pages load without Content, SEO, Filterable, Metafields or application DTO libraries.

Pages owns structural identities, translations, hierarchy, publication windows and native resource resolution. The consuming application owns Actions, input/output data, editor assembly, routes, controllers and preview HTTP responses.

## Native services

Inject services from `Nvl\Pages\Services`:

| Service | Operations | Results |
| --- | --- | --- |
| `PageLifecycle` | `create`, `update`, `move`, `delete`, `restore` | Refreshed `Page` models; deletion returns `bool` |
| `PageReader` | `get`, `paginate`, `children`, `navigation`, `keyAvailable` | Models, Laravel collections/paginators or documented arrays |
| `PageResolver` | `resolve`, `preview` | `['page' => Page, 'resource' => ?array]` |

```php
$page = $pages->create([
    'key' => 'about',
    'slug' => 'about',
    'site' => 'default',
    'status' => 'published',
    'translations' => ['en' => ['title' => 'About us']],
], $actor);

$page = $pages->update($page, [
    'slug' => 'about',
    'kind' => 'static',
    'status' => 'draft',
    'translations' => ['en' => ['title' => 'About us']],
], $page->revision, $actor);

$page = $pages->move($page, $parentId, $position, $page->revision, $actor);
$pages->delete($page, $page->revision, $actor);
$page = $pages->restore($page, $page->revision, $actor);

$result = $resolver->resolve($path, $site, $locale);
$preview = $resolver->preview($path, $site, $locale, $persistedActor);
```

Creation and replacement accept the documented native attribute arrays. Replacement retains the original complete-replacement semantics; omitted defaulted editorial fields reset to their defaults. Key, site, parent identity and ownership cannot be changed through replacement. Move owns parent changes. Expected revisions are separate arguments. Unknown/protected fields are rejected inside the service.

Mutations preserve serialized site-tree locks, cycle/depth checks, descendant path rebuilding, unique key/slug constraints, source-connection transactions and committed events. Locale replacement uses Translatable's supported related-row store inside the Page transaction. An enclosing host transaction remains authoritative.

## Authorization and public resolution

Bind `Nvl\Pages\Contracts\PageAuthorization` for application policy. It receives `PageAbility`, an explicit Laravel authenticatable, `SystemActor` or `PublicActor`, the canonical Page where applicable, and documented native context facts. Background identity is supplied only by trusted application code and requires explicit consumer authorization for management. The default adapter grants eligible public reads; it does not grant background management authority.

Public resolution always authorizes `PublicActor::Anonymous`; preview requires a persisted authenticatable and separate `Preview` authorization. The default policy does not grant private previews. Public matching preserves the original not-found failures, publication/expiration windows, exact site, locale and owner isolation. Caller context cannot replace canonical site/path/resource facts.

For isolated configured Page models, enter native Ownership using a verified public owner/site mapping before resolution. `PublicOwnerSiteRunner::run($canonicalOrigin, $callback)` accepts the canonical origin string. The application owns request parsing. A supplied site must match the verified site; a public admission grants no management rights.

`PageReader::children()` and `navigation()` apply public visibility. `get()` and `paginate()` authorize management reads and return canonical models with localized copy. Pagination accepts fixed native filters for status, kind, parent, navigable and sort; it does not require Filterable. Consumers select safe fields and loaded translations for their response projections.

## Dynamic resources

Register stable aliases under `nvl-pages.resources`. `PageResourceHandler` retains route patterns, exact route-parameter rules, constrained queries/fetching, sanitized presentation and optional sitemap entries. Request records are `array{pageId:string,site:string,locale:string,parameters:array<string,string>}`. Presentation records are `array{type:string,id:string,payload:array<string,mixed>}`.

Owned handlers implement `OwnershipSafePageResourceHandler` and declare the exact native model. Pages checks both query and returned model identity under the admitted resource. It does not infer ownership from route parameters or presentation IDs.

## Optional composition

`nvl-pages.integrations.content`, `seo` and `metafields` accept `null` for loaded-provider detection, `false` to disable, or `true` to require the provider. Explicitly enabled missing providers fail during boot. The base Page has no hard Content or SEO interface/trait dependency.

When selected, the existing adapters register the configured Page identity, Content group `content`, scoped SEO profiles/sitemaps and optional model relations. Hard deletion retains Content's atomic owner cleanup. Consumers compose Content rendering and SEO output through those packages' native services; Pages returns its own canonical model and resource record.

## Installation and verification

Configure `nvl-pages.models.page`, `connection`, table identities, authorization, URL generation and resource handlers. Select one owner for migrations: vendor loading or host-published copies. Preserve existing IDs, revision history and reviewed native Ownership adoption. The legacy Tenancy bridge and package HTTP routes are removed.

Publish canonical `nvl-pages-config`, `nvl-pages-migrations` and `nvl-pages-skills` resources as needed. Run `nvl:pages:doctor --strict --format=json` for schema, resource, optional-adapter and tree diagnostics. Host configuration/cache/worker deployment steps remain application-owned.
