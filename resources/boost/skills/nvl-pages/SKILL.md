---
name: nvl-pages
description: Use when implementing or reviewing localized hierarchical Pages, native lifecycle and reads, public resolution, private previews, dynamic resources, or optional Content and SEO composition with nvl/pages.
---

# NVL Pages

Use the native PHP 8.4 / Laravel 13 services in `Nvl\Pages\Services`. Read the current package README and service signatures before integration. Consumers own application Actions, input/output data, editor assembly, routes, controllers and preview responses.

## Complete native operations

| Service | Operations | Results |
| --- | --- | --- |
| `PageLifecycle` | `create`, `update`, `move`, `delete`, `restore` | Refreshed `Page`; deletion returns `bool` |
| `PageReader` | `get`, `paginate`, `children`, `navigation`, `keyAvailable` | Canonical models, Laravel collections/paginators or documented arrays |
| `PageResolver` | `resolve`, `preview` | `array{page:Page,resource:array{type:string,id:string,payload:array<string,mixed>}|null}` |

- Create with a documented attribute array and explicit authenticatable or `SystemActor`. Update takes the Page, complete replacement array, separate expected revision and actor. Omitted defaulted replacement fields reset to defaults. Move takes parent ID, position, expected revision and actor; delete/restore take the expected revision separately.
- Keep key, site, parent and ownership identities outside editable replacement input. Use the complete lifecycle operations for writes; retain tree locks, cycle/depth checks, conflict handling, descendant path rebuilding and source-connection transactions.
- Put localized title, navigation label and summary values in Translatable rows. Lifecycle operations own their replacement transaction. Preserve locale fallback and choose the locale explicitly for projections.
- `get()` and `paginate()` authorize model reads. Pagination uses fixed native status, kind, parent, navigable and sort filters. Select documented safe fields and loaded translations for consumer responses.
- `children()` returns one bounded level of visible same-site children; use `PageKind` and `PublicChildPageOrder` for supported selection/order. `navigation()` returns the localized visible tree. `keyAvailable()` checks the actual unique key without revealing a foreign site's conflicting ID.

## Authorization and resolution

Bind `Nvl\Pages\Contracts\PageAuthorization` for host policy. It receives `PageAbility`, an explicit authenticatable, `SystemActor` or `PublicActor`, the canonical Page when applicable and native context facts. Trusted application code supplies background actors; request input never grants authority.

`PageResolver::resolve($path, $site, $locale, $context)` authorizes `PublicActor::Anonymous` and retains publication/expiration windows, exact site/resource identity, supported locale and the original not-found failures. Private `preview()` requires a persisted authenticatable and separate Preview authorization. The default policy does not grant private previews.

For isolated configured Page models, enter native Ownership through verified public owner/site admission before public resolution. `PublicOwnerSiteRunner::run($canonicalOrigin, $callback)` takes the canonical origin string. Preserve the admitted owner, site, origin and resource capabilities; caller selectors cannot replace them. Public admission grants no management authority. Keep configured model identities, stored morph values and reviewed adoption intact.

## Dynamic resources

Register stable aliases under `nvl-pages.resources` and implement `PageResourceHandler`. Retain exact route-parameter rules, constrained query/fetch conditions and sanitized presentation.

- Request: `array{pageId:string,site:string,locale:string,parameters:array<string,string>}`.
- Result: `array{type:string,id:string,payload:array<string,mixed>}`.
- Owned handlers implement `OwnershipSafePageResourceHandler` and declare the exact native model. Query and fetched model identities must retain the admitted ownership capability.
- Optional sitemap streams yield absolute `Nvl\Seo\ValueObjects\SitemapEntry` values in bounded chunks. Page visibility/site/sitemap inclusion and explicit active SEO exclusions remain authoritative.

## Optional composition

Plain localized Pages require Core and Translatable. Content, SEO, Metafields, Filterable and DTO libraries are not required runtime dependencies.

`nvl-pages.integrations.content`, `seo` and `metafields` accept `null` for loaded-provider detection, `false` to disable or `true` to require the provider. Explicitly enabled missing providers fail. The base Page does not require Content or SEO interfaces/traits.

When selected, adapters register the configured Page identity, Content group `content`, scoped SEO profiles/sitemaps and optional model relations. Content retains atomic owner cleanup on hard deletion. Assemble rendering, SEO output, custom fields and editor payloads in the consumer through those packages' native services. Do not restore package workflow interfaces, payload DTOs, HTTP routes or the retired Tenancy bridge.

## Configuration and checks

Use canonical `nvl-pages` configuration and publish tags. Select one migration owner, preserve existing IDs/history and run `nvl:pages:doctor --strict --format=json` after relevant configuration/schema changes. Test lifecycle/publication/resource/ownership guarantees through the native services; keep consumer mapping and HTTP assertions in the host. Report only checks actually run.
