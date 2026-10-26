# NVL Pages events

`Nvl\Pages\Events\PageChanged` records a committed Page structure or lifecycle revision. The event schema version is `1`; the machine-readable catalog has its own version in [event-catalog.json](../resources/event-catalog.json).

## Publication

`PageLifecycle` schedules events through `Nvl\Support\Events\DomainEventDispatcher` on the Page's actual database connection. Delivery follows the outer source transaction's commit, or happens immediately when that connection has no transaction. Outer/savepoint rollback discards the corresponding callbacks. Host listener and queue commit policies remain authoritative.

Callbacks are local publication, without an outbox or cross-process deduplication. Existing revision/change guards determine whether an operation emits an event. Direct Laravel event dispatch is immediate.

## PageChanged payload

[Source](../src/Events/PageChanged.php). Constructor fields remain in this order:

| Field | Native PHP type | Default |
| --- | --- | --- |
| `pageId` | `string` | Required |
| `site` | `string` | Required |
| `operation` | `PageChangeOperation` | Required |
| `revision` | `int` | Required |
| `actor` | `array` | Required |
| `affectedPageIds` | `array` | `[]` |
| `sitemapIdentity` | `?array` | `null` |
| `schemaVersion` | `int` | `1` |

All are public payload fields except the `sitemapIdentity` constructor parameter, which initializes the public readonly property of the same name.

`actor` is a native identity record:

```php
array{type:?string,id:int|string|null,system:bool}
```

Producers retain only scalar actor facts. `affectedPageIds` is `list<string>` and includes affected descendants when a path change rebuilds the subtree. Actor identifiers and the system flag do not grant listener authority.

`sitemapIdentity` is a nullable native capture:

```php
array{
    connection:string,
    tenantId:string,
    site:string,
    origin:string,
    scope:string,
    version:int,
    key:string,
    namespace:string
}|null
```

A supplied capture is retained. Otherwise construction captures the current sitemap namespace only when the SEO integration is selected and loaded; the public property remains `null` without that integration. The `tenantId` key is part of the existing cache namespace shape. This record is internal infrastructure metadata and should not appear in public responses. Sitemap invalidation uses the captured record after commit rather than borrowing a later owner's context.

## Producers

All producers are methods of [PageLifecycle](../src/Services/PageLifecycle.php) and dispatch on `$page->getConnection()`:

| Method | `PageChangeOperation` |
| --- | --- |
| `create` | `Created` (`created`) |
| `update` | `Updated` (`updated`) |
| `move` | `Moved` (`moved`) |
| `delete` | `Deleted` (`deleted`) |
| `restore` | `Restored` (`restored`) |

The backed enum is declared in [PageChangeOperation](../src/Enums/PageChangeOperation.php). Event fields contain native records and enum/scalar values, without Page/actor models or passive payload DTOs.

## Consumer assertions

Use the canonical `PageChanged` class with Laravel event fakes and assertions. Retain the appropriate ownership admission and apply listener authorization before reading storage. Verify outer commit, nested rollback, source-connection independence and affected-page facts where the consumer relies on them; this guide does not claim execution evidence for those checks.
