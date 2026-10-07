# NVL pages events

This document describes the implemented source behavior. Acceptance is exercised by the owning package suites and Core committed-event regression tests; current release execution evidence is tracked in consumer-readiness.md. The authoritative machine-readable schema is [event-catalog.json](../resources/event-catalog.json), catalog version `1`. Event `schemaVersion` is independent of catalog version.

## Publication and listener timing

The native host dispatcher receives the captured event after the supplied source connection outer commit, or immediately when that connection has no active transaction.

Callbacks attach to the matching native connection and current nesting record; native outer/savepoint rollback discards the corresponding callbacks.

Missing source transaction records fail before commit; Mail Notifications reports and drops unusable observations.

Host after-commit listeners and queue after_commit policies can add their own deferral after publication. Host transaction infrastructure and dispatcher bindings are preserved.

Local callbacks are not an outbox. Process exit between commit and callback can lose delivery; no crash durability or exactly-once delivery is promised.

One canonical object is dispatched per qualifying producer call. This is local publication, not cross-process deduplication or a guarantee that repeated observations are unique.

Use Nvl\Support\Events\DomainEventDispatcher::dispatch($event, $writerConnection). Native Event::dispatch() is immediate and has no package interception.

## Payload security and no-op behavior

Scalar actor/page/site identities and affected page IDs, plus captured SitemapCacheIdentity (connection, tenantId, site, origin, scope, version, key, namespace). Cache identity is internal infrastructure metadata; do not expose it as a public API.

Existing change/revision guards govern publication, including affected-page snapshots. No generic request deduplication is added.

The sitemapIdentity constructor parameter is nullable with default null; the public property is a non-null SitemapCacheIdentity captured during construction. Its fallback resolves SitemapCache from the current container, so manual producers should pass a captured identity.

Actor/owner identifiers do not grant access. Listeners must preserve the captured ownership and apply their own authorization when reading storage. Readonly payload fields and native value objects are schema facts; public constructors with mixed arrays do not create a new recursive sanitization boundary. Package producer shapes are documented below; hosts must not attach models, mutable service objects or private arbitrary data.

## Canonical events

| Event | Schema version | Trigger |
| --- | --- | --- |
| [PageChanged](#pagechanged) | 1 | Page structure/lifecycle revision changed. |

### PageChanged

`Nvl\Pages\Events\PageChanged` · [source](../src/Events/PageChanged.php) · event schema `1`.

Page structure/lifecycle revision changed.

Constructor parameters, in native order:

| Parameter | Native PHP type | Visibility | Default | Collection shape |
| --- | --- | --- | --- | --- |
| `$pageId` | `string` | public | `required` | — |
| `$site` | `string` | public | `required` | — |
| `$operation` | `Nvl\Pages\Enums\PageChangeOperation` | public | `required` | — |
| `$revision` | `int` | public | `required` | — |
| `$actor` | `Nvl\Pages\Data\PageActorData` | public | `required` | — |
| `$affectedPageIds` | `array` | public | `[]` | `list<string>` |
| `$sitemapIdentity` | `?Nvl\Seo\Data\SitemapCacheIdentity` | parameter | `null` | — |
| `$schemaVersion` | `int` | public | `1` | — |

Public payload fields:

| Field | Native PHP type | Collection shape |
| --- | --- | --- |
| `$pageId` | `string` | — |
| `$site` | `string` | — |
| `$operation` | `Nvl\Pages\Enums\PageChangeOperation` | — |
| `$revision` | `int` | — |
| `$actor` | `Nvl\Pages\Data\PageActorData` | — |
| `$affectedPageIds` | `array` | `list<string>` |
| `$sitemapIdentity` | `Nvl\Seo\Data\SitemapCacheIdentity` | — |
| `$schemaVersion` | `int` | — |

Producers and exact scheduling connection expressions (variables are resolved in the linked source):

| Producer | Source connection |
| --- | --- |
| [Actions/CreatePageAction.php](../src/Actions/CreatePageAction.php) | `$page->getConnection()` |
| [Actions/DeletePageAction.php](../src/Actions/DeletePageAction.php) | `$page->getConnection()` |
| [Actions/MovePageAction.php](../src/Actions/MovePageAction.php) | `$page->getConnection()` |
| [Actions/RestorePageAction.php](../src/Actions/RestorePageAction.php) | `$page->getConnection()` |
| [Actions/UpdatePageAction.php](../src/Actions/UpdatePageAction.php) | `$page->getConnection()` |

## Referenced payload types

Native event field types are listed above; nested declared fields and backed enum values follow. Private captured envelopes are included because serialized/queued objects retain them. Dates use `Carbon\CarbonImmutable`. Spatie Data serialization can also carry its protected transformation metadata; immutable serialized payload graphs are checked by the C4 contract suite.

### PageActorData

`Nvl\Pages\Data\PageActorData` · [source](../src/Data/PageActorData.php).

| Declared public field | Native PHP type | Collection shape |
| --- | --- | --- |
| `$type` | `?string` | — |
| `$id` | `int\|string\|null` | — |
| `$system` | `bool` | — |

### PageChangeOperation

`Nvl\Pages\Enums\PageChangeOperation` · [source](../src/Enums/PageChangeOperation.php).

Backed string values: `Created = created`, `Updated = updated`, `Moved = moved`, `Deleted = deleted`, `Restored = restored`.

### SitemapCacheIdentity

`Nvl\Seo\Data\SitemapCacheIdentity` · [source](../../seo/src/Data/SitemapCacheIdentity.php).

| Declared public field | Native PHP type | Collection shape |
| --- | --- | --- |
| `$connection` | `string` | — |
| `$tenantId` | `string` | — |
| `$site` | `string` | — |
| `$origin` | `string` | — |
| `$scope` | `string` | — |
| `$version` | `int` | — |
| `$key` | `string` | — |
| `$namespace` | `string` | — |

## Deferred acceptance checks

Final testing must compare catalog types/defaults/aliases with actual classes, recursively inspect producer payloads, and prove source outer commit, nested rollback, unrelated connection independence and retry behavior without an uncommitted test-harness transaction. Where applicable it must cover legacy exact/cached/queued listeners, canonical fakes and wildcard delivery, tenant capture, package no-op guards and observational failure containment. This document does not report those checks as passing.

## Consumer event assertions

Use the canonical event class listed in the catalog for `Event::fake([...])` and `Event::assertDispatched(...)`. Laravel fake filters compare the emitted class name; an old alias import does not rename that canonical object. Legacy exact listeners are bridged at delivery time through Laravel’s native dispatcher. Keep compatibility listener tests on their exact legacy name, and migrate suffix-specific wildcards to canonical names.

