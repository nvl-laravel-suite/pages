# Upgrading NVL Pages

## Consumer contracts, committed events and runtime policy (5.x)

Prefer focused public interfaces in constructor injection; native implementations remain container defaults and host prebindings win. Returned models are documented identity/data handles: use package contracts for reads/writes and capability-specific batch readers instead of direct package queries. Enable the shipped Core PHPStan include in your host; do not invoke the suite workbench static audit command in a consumer.

Events now carry immutable schemaVersion=1 and scalar/DTO snapshots. Replace model-bearing event fields with the IDs listed in [events](docs/events.md); load only through an authorized public reader when needed. Only six declared legacy `*Event` names are retained as PHP aliases for major 5, removal no earlier than major 6. Migrate exact imports/listeners/fakes to canonical names, replace suffix wildcard patterns explicitly, drain old queued payloads, rebuild event caches and restart workers. Framework Verified/PasswordReset remain native classes. Source-connection callbacks are process-local after-commit publication, not a durable outbox or exactly-once delivery.

Package failures have a marker and optional response metadata. Opt into Core's JSON renderer deliberately; preserve existing host handlers and request-locale selection. Missing required host adapters produce `binding_required`/500; genuine configured authorization denial retains native handling. See the README error table and required-bindings section where applicable.

Factories ship in runtime package mappings for host tests. Ordinary make may persist parents; withoutParents()->make creates detached fixtures. Supply persisted native owners/parents and active tenants explicitly, retain source revisions, and never treat a factory row as a real storage/provider/workflow effect. Core's optional installer publishes common config without enabling features; strict Doctor and explicit deployment cache/worker steps belong in the host release process. The PHP 8.4/Laravel 13 local Dagger release gate and fresh public Composer installation passed for 5.0.0. Additional compatibility legs need separate evidence; hosts must verify their own adoption.


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

## Tagged consumer PHP boundary

Use source `@api` workflows, extension contracts, and value types for application integration. Direct use of untagged implementations or `@internal` members is unsupported. This classification keeps existing concrete Action signatures and runtime behavior; it does not authorize package model persistence, ad hoc queries, relation traversal, or generic model serialization. Returned models are identity/result handles with only the explicitly declared in-memory read fields described in the README.

`PageData::fromModel`, `PageOptionData::fromModel`, and `PublicPageData::fromModel` are internal projectors. Use `GetPageAction` for authorized management, `ListPageOptionsAction` for selectors, and `GetPagePublicationProjectionAction` or `ResolvePageAction` for the appropriate public workflow with its trusted request context.

## Major 5 workflow injection

Replace host constructor dependencies on selected concrete Actions with their focused `Nvl\Pages\Contracts\*Contract` equivalents listed in the README. Existing equivalent workflow contracts are reused. Native concrete constructors, qualifiers, argument defaults, result types, and execution behavior remain compatible. Internal package Action/service chains retain their existing concrete dependencies.

Default workflow registrations use `bindIf`, retaining host interfaces/instances registered before discovery. Register substitutes at the interface key; newly resolved host services receive late replacements. Substituting a workflow does not exercise the native authorization, storage, or lifecycle invariants, which require the owning integration coverage.
