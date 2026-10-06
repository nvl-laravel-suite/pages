# Upgrading NVL Pages

## Optional tenancy adoption

Install the nullable Page expansion and adopt dependencies before Pages. Map
existing Page roots to reviewed tenants in bounded batches; translations derive
from Page identity. Verify every parent/child and site/path invariant before
activating non-null ownership and composite constraints. Hold maintenance,
drain old publication jobs, invalidate old global sitemap artifacts, restart
workers, and only then admit tenant traffic. Never re-disable tenancy after the
final constraints have activated.

Replace public-site configuration fallbacks with a real `TenantSiteResolver`.
Route middleware must resolve it before bindings. Update dynamic resource
handlers to `TenantSafePageResourceHandler` and register their model with the
tenancy resource registry.

## To 1.0

This is the first stable contract.

- Persist stable resource aliases rather than handler class names.
- Store structural slugs on Pages and localized copy in `pages_i18n`.
- Move page sections into the Page model’s canonical `content` group through
  `HasContent` and the model-first Content Actions or facade.
- Attach discoverability data through SEO profiles and custom fields through Metafields.
- Supply the current `revision` through the typed update, move, delete, and restore DTOs.
- Use `PublicPageData` for public delivery and reserve `PageData` for authorized management state.
- Store arbitrary custom values through Metafields and structured discoverability values through SEO; Pages has no generic metadata column.
- Bind `PageRequestContextResolver` for multi-site public HTTP delivery; the default resolver uses one configured trusted site.
- Keep application-specific route models, queries, tenant rules, DTO projection, and dynamic sitemap chunking inside registered resource handlers.
- The default navigation endpoint is `/nvl/api/v1/pages/_navigation` and the default management prefix is `/nvl/api/v1/pages/_manage`; the leading underscore keeps both outside the valid page-slug grammar.

Run `php artisan nvl:pages:doctor --strict --format=json` before and after an application adoption.

## Shared owner registry compatibility

Declare owner classes in `nvl-core.owners`, for example `'owners' => [Article::class]`, and reference the same model class from each package capability. Laravel's `getMorphClass()` is the stored owner identity: it returns the host-authored morph alias or the FQCN when no map exists. Core declarations and package allowlists do not add or enforce a host morph map and do not grant authorization.

Legacy alias references remain read compatibility during major 5 and are removed in major 6. A legacy configured alias must agree with the model's current `getMorphClass()`; mismatches are diagnostics and require a host decision. Doctor can inspect declared package owner columns for stored-versus-current identities without rewriting them. If the host introduces or changes its morph map, review and convert only the affected stored columns and reconcile host relationships before cutover. No automatic owner-data conversion or `nvl:owners:upgrade` is provided. Rebuild configuration caches and restart workers after the coordinated change.

## Shared Doctor integration

The loaded package provider now contributes its existing inspection checks to Core's `nvl:doctor --strict --format=json`. The package command remains available. The shared gate fails errors and, in strict mode, warnings; no data upgrade is required for diagnostics.

## Optional Metafields dependency

Metafields moves from a runtime requirement to a suggestion. Nullable `nvl-pages.integrations.metafields` activates only when its provider is loaded; `false` disables and `true` requires it. Page editor bootstraps return an empty Metafields section when inactive. `PageEditorBootstrapData.metafields` now contains Pages-owned `PageMetafieldFieldData`, preserving its serialized field shape.

Existing `$page->metafields()` relation calls remain supported while the integration is active. Pages registers the relation lazily instead of composing `HasMetafields` directly, preserving the Page's registered morph identity. An absent provider or explicitly disabled integration makes a relation call fail with a configuration error. Use package Actions for authorized field reads and mutations, or the `PageMetafields` read contract for editor integration.

## Next major: isolated schema identities

This is a breaking schema identity change. Back up storage and migration history, pause writes/workers, install this code with automatic package migrations disabled, and select one owner for migrations (vendor or published).

```sh
php artisan nvl:doctor --strict --format=json
php artisan nvl:schema:upgrade --package=pages --claim-legacy --migration-owner=vendor --dry-run --format=json
php artisan nvl:schema:upgrade --package=pages --claim-legacy --migration-owner=vendor --format=json
```

The command validates released columns and relational keys plus creating migration history, renames owned legacy tables to the effective `tables.*` targets and rewrites exact package migration identities while retaining batches and unrelated host records. It refuses foreign/incomplete shapes and conflicting targets. Explicit old table mappings retain those names; remove them when choosing new defaults. A second run is empty.

Declare each published path and canonical identity explicitly in `nvl-core.migrations.published`; retimestamped history also needs an exact `legacy` mapping. Use `--migration-owner=vendor` after manually archiving declared copies outside loaded paths, or `--migration-owner=published` after manually replacing executable copies with current migration code and disabling vendor loading. The plan verifies ownership and preserves batches; checksums do not automatically claim files. Modified host copies remain host-owned. No migration files or stored morph types are rewritten.

DDL transactions are driver dependent and per connection. Inspect dry-run warnings for MySQL/MariaDB or split storage; after a failure, inspect completed steps before resuming. Schema-qualified rename targets require an explicit host schema move first. Re-enable your selected migration owner, run `nvl:schema:preflight` with the same selected paths and connection, then migrate remaining package changes and rerun Doctor before resuming writes. See the suite upgrade guide for shared owner/locale inputs, Core option defaults and one-major deprecation rules.
