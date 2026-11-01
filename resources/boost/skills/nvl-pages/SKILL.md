---
name: nvl-pages
description: Use when integrating nvl/pages localized hierarchical pages, publication lifecycle, public path resolution, private previews, dynamic resource pages, navigation or optional Content/SEO/Metafields composition.
---

# NVL Pages

Use the native PHP 8.4 / Laravel 13 services. Consumers own application Actions,
DTOs, editor assembly, routes/controllers, preview responses and transport errors.

## Reference routing

- [Installation](references/installation.md): runtime requirements, configuration, migrations and publication.
- [Native API](references/api.md): full current signatures and domain values.
- [Usage](references/usage.md): host authorization, lifecycle replacement, resources and composition.

## Complete service choice

| Need | Service and result |
| --- | --- |
| Create/update/move/delete/restore | `PageLifecycle`; fresh `Page` or deletion `bool` |
| Get, paginate, children, navigation, key availability | `PageReader`; models, collections or documented arrays |
| Public path and private preview | `PageResolver`; page plus optional resource projection |
| Resource alias integration | `PageResourceRegistry` and `PageResourceHandler` |
| Host URL presentation | `PageUrlGenerator` |

## Lifecycle input semantics

Create takes native attributes and an explicit actor. Update is a complete edit:
`slug`, `kind` and `status` are required and omitted defaulted fields reset.
It is not a presence-based patch. The expected revision is a separate argument.
Use dedicated move for parent/position changes and its own expected revision.
Key, site, parent and owner identity are outside editable replacement input.

Keep localized title, navigation label and summary in Translatable rows. Lifecycle
replaces the supplied locale set transactionally. Choose locale deliberately in
projections, preserving fallback. Publication and expiration windows remain enforced.

## Authority and public resolution

Bind `PageAuthorization` for host policy. The default admits eligible public
reads/navigation and denies management/private previews. Explicit background actors
still need host permission. Eloquent actors and page/site facts reload canonically;
provider-owned external principals retain their own freshness contract.

`PageResolver::resolve` uses `PublicActor::Anonymous`; preview requires a persisted
Eloquent authenticatable and separate Preview authorization. Preserve original
not-found failures, exact site/resource identity and supported locale boundaries.
Public admission does not grant management rights.

With owned configured Pages, verify the canonical origin through native
`PublicOwnerSiteRunner` before public resolution. Keep admitted owner/site and
resource capabilities; request selectors cannot replace them.

## Dynamic resources and optional composition

Register stable resource aliases in `nvl-pages.resources`. Handlers supply route
pattern/rules, a constrained query, fetch and sanitized presentation; request class
names are never resolver registrations. Owned handlers implement
`OwnershipSafePageResourceHandler` and retain the declared native model capability.
Sitemap streams yield absolute `SitemapEntry` values when SEO is enabled.

Base Pages need Core and Translatable. `nvl-pages.integrations.content`, `.seo`
and `.metafields` accept null for loaded-provider detection, false to disable or true
to require the provider. Explicitly enabled absence fails. The native Page requires
no Content/SEO workflow trait. Assemble those native results in the consumer.

## Models, atomicity and checks

Select `nvl-pages.models.page` before provider boot. Retain UUIDs, native casts,
relations, tree hooks, ownership and package-configured storage; added columns need
host migrations. The class selection is immutable and translations/locks stay fixed.

Lifecycle owns tree locks, cycle/depth checks, descendant path rebuilding,
revision checks and the actual source-connection transaction. Events follow outer
commit. Preserve atomic Content owner cleanup on hard deletion when integrated.
Test public/private policy, replacement defaults, windows, tree conflicts, dynamic
resources and owner isolation. Consult [usage](references/usage.md) before inventing
transport, integration flags or a generic page mutation wrapper.
