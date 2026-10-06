---
name: nvl-pages
description: Implement and review hierarchical pages, Content composition, dynamic resource handlers, SEO, Metafields, translations, and sitemap integration with nvl/pages.
---

# NVL Pages

## Tenant sites

- Resolve public hosts with `ResolvePublicTenant` before bindings, cache work,
  and Page/SEO errors. Use only the resulting `TenantSiteContext` for tenant,
  site, and canonical origin.
- Outside HTTP, enter `TenantRunner` and install the same host-verified site
  context for the operation. Never mutate Config or retain site state in a
  singleton.
- In adopted mode, implement `TenantSafePageResourceHandler`, declare the exact
  registered model, and let Pages tenant-scope the query before count,
  pagination, or fetch. Return only `PageResourceData`.
- Adopt Media, Content, Metafields, and SEO before Pages; verify every parent,
  translation, site, and path edge before final constraints.

Use this skill when application work creates, resolves, translates, composes, or extends Pages.

## Required approach

1. Use `CreatePageAction`, `UpdatePageAction`, `MovePageAction`, `DeletePageAction`, and `RestorePageAction`; do not write package tables directly.
2. Keep the page tree at or below the configured four-level maximum.
3. Put localized title, navigation label, and summary values in Translatable locale rows.
4. Put page sections in the Page model’s `content` Content group through
   `HasContent`; consume the injected `Nvl\Content\Content` application
   surface and adapt the actor with `PageActorData::contentActor()`. Keep
   discoverability data in SEO and custom fields in Metafields.
5. Implement `PageResourceHandler` for dynamic routes. Its query must include every publication, tenancy, policy, and eager-load condition.
6. Return only a sanitized `PageResourceData`; never serialize the resolved Eloquent model.
7. Stream absolute `SitemapEntry` values from a handler in bounded chunks when dynamic resources belong in a sitemap.
8. Supply exact revisions through the update, move, delete, and restore DTOs; handle stale and uniqueness conflicts.
9. Use `PublicPageData` for public output and `PageData` only for authorized management or preview output.
10. Resolve public site and locale through `PageRequestContextResolver`; never trust a caller-supplied site query directly.
11. Use `FindPageByKeyAction` for exact site-scoped management lookup and
    `CheckPageKeyAvailabilityAction` for validation against the actual globally
    unique Page key. A foreign-site conflict is unavailable but does not expose
    its ID; `exceptId` only excludes the same-site row.
12. Use `ListPageOptionsAction` for localized management selectors. It searches
    key, path, title, and navigation label, returns no results for a one-character
    search, resolves Translatable fallback, orders by path/ID, and never returns
    more than the configured or absolute 100-row limit.
13. Use `ListPublicChildPagesAction` with trusted `PageRequestContextData` for a
    one-level public listing. The parent and children must be public in the same
    site; results are `PublicPageData` and capped at 100. The default uses
    sibling order. Pass an allowlisted `PageKind` and
    `PublicChildPageOrder::Newest` when a feed must filter and order by effective
    publication time before applying its limit.
    Package-built projections populate the optional `publishedAt` field from the
    publication timestamp or persisted creation fallback for public cards.
14. Use `GetNavigationAction` for localized navigation and `PreviewPageAction` for authorized non-public rendering.
15. Use `ListPageEditorSummariesAction` for bounded management indexes. It
    authorizes the site-level list before SQL and batches Page, Content
    placement, and SEO projections without repeating catalogs per row. SEO
    authorizes every owner before its batched query, and configured/requested
    page sizes cannot exceed the absolute 100-owner ceiling.
16. Use `GetPageEditorBootstrapAction` for one complete editor payload. It
    composes authorized Page, Content, SEO, and Metafields reads plus Page
    kinds, statuses, resource aliases, and maximum depth; do not rebuild that
    graph in a controller.
17. Use `GetPagePublicationProjectionAction` only for a currently public static
    Page known by ID. Use `ResolvePageAction` for paths and resource Pages, and
    `PreviewPageAction` for management preview.
18. Keep public and management routes disabled unless the application explicitly secures and enables them.
19. Run `nvl:pages:doctor --strict` after configuration or schema changes.
20. Let Pages own sitemap eligibility for Page SEO profiles. Page visibility,
    site and sitemap inclusion apply before SEO metadata; explicit active SEO
    `noindex` or sitemap exclusion suppresses static and dynamic entries.
    Moves provide the requested `parentId` to authorization, and restoration
    derives the path from the current parent.

Use `ResolvePageAction` for headless delivery. Its `ResolvedPageData` combines a localized redacted Page projection, Content, SEO, and the optional dynamic resource projection.

## Configurable-tenancy release discipline

- Preserve disabled compatibility and package independence; tenant support never creates an undeclared Auth or Suite dependency.
- Use registered package-owned resources, adoption adapters, Actions, and lifecycle APIs. Never add a generic tenant delete-all path or raw cross-package cleanup.
- Treat mapping/configuration hashes, interruption checkpoints, conservation evidence, worker context, tenant-leading queries, and standalone consumption as release contracts.

## Shared owner identities

- Declare owner class lists in `nvl-core.owners` and reference model classes in `pages` capability configuration. Laravel `getMorphClass()` supplies the host-authored stored identity; declarations do not add global host morph mappings.
- Keep the resource handler, route pattern, query visibility, and presentation behavior. Resource keys may differ from owner aliases; query and fetched models must match the declared owner.
- Keep the package allowlist and authorization independent of Core registration. Never authorize a model merely because Core knows it.
- Preserve resolvers, handlers and authorization. Legacy aliases require agreement with native `getMorphClass()` and are removed in major 6; Doctor reports mismatches and stored identity drift without conversion.
- If the host changes its morph map, explicitly reconcile reviewed package-owned columns and affected host relations before cutover. Core and package capability registration never mutate the host morph map or rewrite stored values.

## Shared consumer diagnostics

Run `php artisan nvl:doctor --strict --format=json` to combine checks from loaded NVL providers. Retain the package Doctor command for its detailed report; both paths reuse the package-owned inspection service.

## Optional Metafields editor adapter

Pages installs without `nvl/metafields`. `nvl-pages.integrations.metafields` accepts `null` (automatic activation from a loaded Metafields provider), `false` (disabled), or `true` (required). Explicitly requiring an unavailable adapter produces a configuration error. Core Doctor reports automatic inactivity as information.

`GetPageEditorBootstrapAction` returns an empty `metafields` array while this adapter is inactive. Content and SEO remain required editor integrations. When Metafields is loaded, its own authorized read Action supplies fields through `Nvl\Pages\Contracts\PageMetafields`. Hosts can bind that contract before the package default.

Editor fields use Pages-owned `PageMetafieldFieldData`; the serialized field shape is preserved without importing a foreign DTO. DTO discovery and generated TypeScript work when Metafields is absent.

Existing `$page->metafields()` calls remain supported through a lazy relation resolver when the Metafields provider is loaded and the integration is active. The relation preserves the Page's registered morph identity. Page no longer composes `HasMetafields` directly. Calling the relation while the provider is absent or the integration is disabled raises a configuration error; the empty editor section does not imply an available relation. Use package Actions for authorized field reads and mutations.

### Brownfield storage identities

Resolve all package tables through the table helper and canonical `nvl-pages.tables.*`, connections through `nvl-pages.connection` with Core/Laravel inheritance. Defaults use `nvl_pages_*`; migration filenames include that package slug. Never silently adopt a matching table or generic migration filename. Run shared `nvl:doctor --strict --format=json` and the explicit `nvl:schema:upgrade --package=pages --claim-legacy --dry-run --format=json` before upgrading owned legacy storage. Validate the complete plan and choose one migration owner. Preserve host records, constraint names and stored morph values. Deprecated config inputs last one major; canonical options take precedence.

## Canonical configuration ownership

- Read/write `nvl-pages` configuration and publish only canonical `nvl-<package>-<resource>` tags. Keep logical package/tenant resource identifiers unchanged.
- Generic config roots and unprefixed package environment names are foreign by default. For an upgrading NVL host only, select `nvl-core.compatibility.legacy_config` package IDs and `legacy_env` explicitly; both default off. Canonical presence wins, including false/null/empty values. Legacy inputs are read without writing back and are removed in major 6.
- Use canonical `NVL_<PACKAGE>_*` variables only in config evaluation, then rebuild configuration caches and restart workers after cutover. Shared Laravel environment variables retain their names. Consult Core's versioned `support/resources/global-names.json` for all renames.
- Old global aliases and legacy route families require separate explicit `global_aliases`/`legacy_routes` package selections. Preserve collisions and use Doctor diagnostics; never grant generic permissions automatically or claim signed-link compatibility without the same authorization/signature checks.
