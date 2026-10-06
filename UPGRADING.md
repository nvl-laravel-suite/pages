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
- The default navigation endpoint is `/api/v1/pages/_navigation` and the default management prefix is `/api/v1/pages/_manage`; the leading underscore keeps both outside the valid page-slug grammar.

Run `php artisan nvl:pages:doctor --strict --format=json` before and after an application adoption.

## Shared owner registry compatibility

Move model identity declarations to `nvl-core.owners` and reference the alias from `pages` capability configuration as described in the [README](README.md#shared-owner-identity). Preserve package contracts, resolvers/handlers, visibility scopes, and mutation authorization. Core does not grant package capabilities. Conflicting aliases or multiple canonical aliases for one model fail before use.

Legacy class inputs remain accepted for one major cycle and are reported through Core diagnostics. Existing Content, Taxonomy, and Metafields mappings keep their established aliases. Legacy SEO, Media, Comments, Templates, Pages, and Translatable class-backed behavior does not automatically create a new morph alias. Existing host morph mappings are respected.

Adding a canonical Core alias changes Laravel's write-time morph type for that model. Before adding it to an existing class-backed deployment, explicitly convert the known package-owned morph columns and reconcile every other affected host relationship. Keep unrelated rows and host-owned morph tables unchanged. This release performs no automatic owner-data conversion and does not ship `nvl:owners:upgrade`. Preserve existing aliases when no conversion is required, rebuild configuration caches, and restart workers after the cutover.

## Shared Doctor integration

The loaded package provider now contributes its existing inspection checks to Core's `nvl:doctor --strict --format=json`. The package command remains available. The shared gate fails errors and, in strict mode, warnings; no data upgrade is required for diagnostics.

## Optional Metafields dependency

Metafields moves from a runtime requirement to a suggestion. Nullable `pages.integrations.metafields` activates only when its provider is loaded; `false` disables and `true` requires it. Page editor bootstraps return an empty Metafields section when inactive. `PageEditorBootstrapData.metafields` now contains Pages-owned `PageMetafieldFieldData`, preserving its serialized field shape.

Existing `$page->metafields()` relation calls remain supported while the integration is active. Pages registers the relation lazily instead of composing `HasMetafields` directly, preserving the Page's registered morph identity. An absent provider or explicitly disabled integration makes a relation call fail with a configuration error. Use package Actions for authorized field reads and mutations, or the `PageMetafields` read contract for editor integration.

## Next major: isolated schema identities

This is a breaking schema identity change. Back up storage and migration history, pause writes/workers, install this code with automatic package migrations disabled, and select one owner for migrations (vendor or published).

```sh
php artisan nvl:doctor --strict --format=json
php artisan nvl:schema:upgrade --package=pages --claim-legacy --dry-run --format=json
php artisan nvl:schema:upgrade --package=pages --claim-legacy --format=json
```

The command validates released columns and relational keys plus creating migration history, renames owned legacy tables to the effective `tables.*` targets and rewrites exact package migration identities while retaining batches and unrelated host records. It refuses foreign/incomplete shapes and conflicting targets. Explicit old table mappings retain those names; remove them when choosing new defaults. A second run is empty.

Unmodified published files, including changed timestamps, map by verified checksum to the exact vendor migration identity and current package migration implementation. Modified host copies remain host-owned. Disable vendor loading when retaining a published owner; duplicate ownership fails before migration. No migration files or stored morph types are rewritten.

DDL transactions are driver dependent and per connection. Inspect dry-run warnings for MySQL/MariaDB or split storage; after a failure, inspect completed steps before resuming. Schema-qualified rename targets require an explicit host schema move first. Re-enable your selected migration owner, migrate remaining package changes and rerun Doctor before resuming writes. See the suite upgrade guide for shared owner/locale inputs, Core option defaults and one-major deprecation rules.
