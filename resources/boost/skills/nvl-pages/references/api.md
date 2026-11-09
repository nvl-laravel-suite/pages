# Public API: nvl/pages

Generated from the package public contract. Use this reference to confirm fully qualified names, parameter order, defaults, native array shapes and return types. Read [usage](usage.md) for policy and operation semantics; declarations alone never grant authority.

Search for the relevant fully qualified service or contract and read only its matching symbol sections; this is a lookup catalog, not an instruction to load every declaration. `resolved` annotations expand imported aliases and domain type shapes. Framework inheritance and host-authored extensions remain outside this package contract.

## Symbols

- `Nvl\Pages\Contracts\AbstractPageResourceHandler`
- `Nvl\Pages\Contracts\OwnershipSafePageResourceHandler`
- `Nvl\Pages\Contracts\PageAuthorization`
- `Nvl\Pages\Contracts\PageResourceHandler`
- `Nvl\Pages\Contracts\PageUrlGenerator`
- `Nvl\Pages\Database\Factories\PageFactory`
- `Nvl\Pages\Database\Factories\PageTranslationFactory`
- `Nvl\Pages\Enums\PageAbility`
- `Nvl\Pages\Enums\PageChangeOperation`
- `Nvl\Pages\Enums\PageKind`
- `Nvl\Pages\Enums\PageStatus`
- `Nvl\Pages\Enums\PagesResponseCode`
- `Nvl\Pages\Enums\PublicChildPageOrder`
- `Nvl\Pages\Events\PageChanged`
- `Nvl\Pages\Exceptions\InvalidPageMutationException`
- `Nvl\Pages\Exceptions\PageConflictException`
- `Nvl\Pages\Exceptions\PageHierarchyException`
- `Nvl\Pages\Exceptions\PagesException`
- `Nvl\Pages\Exceptions\StalePageException`
- `Nvl\Pages\Models\Page`
- `Nvl\Pages\Models\PageTranslation`
- `Nvl\Pages\Services\ConfiguredPageAuthorization`
- `Nvl\Pages\Services\PageLifecycle`
- `Nvl\Pages\Services\PageModelRegistry`
- `Nvl\Pages\Services\PageNavigationBuilder`
- `Nvl\Pages\Services\PageReader`
- `Nvl\Pages\Services\PageResolver`
- `Nvl\Pages\Services\PageResourceRegistry`

## Nvl\Pages\Contracts\AbstractPageResourceHandler

```text
abstract class Nvl\Pages\Contracts\AbstractPageResourceHandler implements Nvl\Pages\Contracts\PageResourceHandler
@template TResource of Model
resolved @template TResource of Illuminate\Database\Eloquent\Model
@implements PageResourceHandler<TResource>
resolved @implements Nvl\Pages\Contracts\PageResourceHandler<TResource>
template Nvl\Pages\Contracts\AbstractPageResourceHandler::TResource of Illuminate\Database\Eloquent\Model
public function fetch(Illuminate\Database\Eloquent\Builder $query, array $request): ?Illuminate\Database\Eloquent\Model
@param ResourceRequest $request
resolved @param array{pageId: string, site: string, locale: string, parameters: array<string, string>} $request
@param Builder<TResource> $query
resolved @param Illuminate\Database\Eloquent\Builder<TResource> $query
@return (TResource | null)
template Nvl\Pages\Contracts\AbstractPageResourceHandler::TResource of Illuminate\Database\Eloquent\Model
public function rules(Nvl\Pages\Models\Page $page): array
@return array<string, mixed>
public function sitemapEntries(Nvl\Pages\Models\Page $page, string $scope): iterable
@return iterable<SitemapEntry>
resolved @return iterable<Nvl\Seo\ValueObjects\SitemapEntry>
```

## Nvl\Pages\Contracts\OwnershipSafePageResourceHandler

```text
interface Nvl\Pages\Contracts\OwnershipSafePageResourceHandler extends Nvl\Pages\Contracts\PageResourceHandler
@template TResource of Model
resolved @template TResource of Illuminate\Database\Eloquent\Model
@extends PageResourceHandler<TResource>
resolved @extends Nvl\Pages\Contracts\PageResourceHandler<TResource>
template Nvl\Pages\Contracts\OwnershipSafePageResourceHandler::TResource of Illuminate\Database\Eloquent\Model
public abstract function alias(): string
public abstract function fetch(Illuminate\Database\Eloquent\Builder $query, array $request): ?Illuminate\Database\Eloquent\Model
@param ResourceRequest $request
resolved @param array{pageId: string, site: string, locale: string, parameters: array<string, string>} $request
@param Builder<TResource> $query
resolved @param Illuminate\Database\Eloquent\Builder<TResource> $query
@return (TResource | null)
template Nvl\Pages\Contracts\PageResourceHandler::TResource of Illuminate\Database\Eloquent\Model
public abstract function ownershipResourceModel(): string
@return class-string<TResource>
template Nvl\Pages\Contracts\OwnershipSafePageResourceHandler::TResource of Illuminate\Database\Eloquent\Model
public abstract function present(Illuminate\Database\Eloquent\Model $resource, array $request): array
@param ResourceRequest $request
resolved @param array{pageId: string, site: string, locale: string, parameters: array<string, string>} $request
@param TResource $resource
@return ResourceResult
resolved @return array{type: string, id: string, payload: array<string, mixed>}
template Nvl\Pages\Contracts\PageResourceHandler::TResource of Illuminate\Database\Eloquent\Model
public abstract function query(array $request): Illuminate\Database\Eloquent\Builder
@param ResourceRequest $request
resolved @param array{pageId: string, site: string, locale: string, parameters: array<string, string>} $request
@return Builder<TResource>
resolved @return Illuminate\Database\Eloquent\Builder<TResource>
template Nvl\Pages\Contracts\PageResourceHandler::TResource of Illuminate\Database\Eloquent\Model
public abstract function routePattern(): string
public abstract function rules(Nvl\Pages\Models\Page $page): array
@return array<string, mixed>
public abstract function sitemapEntries(Nvl\Pages\Models\Page $page, string $scope): iterable
@return iterable<SitemapEntry>
resolved @return iterable<Nvl\Seo\ValueObjects\SitemapEntry>
```

## Nvl\Pages\Contracts\PageAuthorization

```text
interface Nvl\Pages\Contracts\PageAuthorization
public abstract function authorize(Nvl\Pages\Enums\PageAbility $ability, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor|Nvl\Support\Enums\PublicActor $actor, ?Nvl\Pages\Models\Page $page=null, array $context=[]): void
@param array{site?: string, parentId?: ?string, path?: string, locale?: string, resourceId?: ?string, ...} $context
```

## Nvl\Pages\Contracts\PageResourceHandler

```text
interface Nvl\Pages\Contracts\PageResourceHandler
@template TResource of Model
resolved @template TResource of Illuminate\Database\Eloquent\Model
public abstract function alias(): string
public abstract function fetch(Illuminate\Database\Eloquent\Builder $query, array $request): ?Illuminate\Database\Eloquent\Model
@param ResourceRequest $request
resolved @param array{pageId: string, site: string, locale: string, parameters: array<string, string>} $request
@param Builder<TResource> $query
resolved @param Illuminate\Database\Eloquent\Builder<TResource> $query
@return (TResource | null)
template Nvl\Pages\Contracts\PageResourceHandler::TResource of Illuminate\Database\Eloquent\Model
public abstract function present(Illuminate\Database\Eloquent\Model $resource, array $request): array
@param ResourceRequest $request
resolved @param array{pageId: string, site: string, locale: string, parameters: array<string, string>} $request
@param TResource $resource
@return ResourceResult
resolved @return array{type: string, id: string, payload: array<string, mixed>}
template Nvl\Pages\Contracts\PageResourceHandler::TResource of Illuminate\Database\Eloquent\Model
public abstract function query(array $request): Illuminate\Database\Eloquent\Builder
@param ResourceRequest $request
resolved @param array{pageId: string, site: string, locale: string, parameters: array<string, string>} $request
@return Builder<TResource>
resolved @return Illuminate\Database\Eloquent\Builder<TResource>
template Nvl\Pages\Contracts\PageResourceHandler::TResource of Illuminate\Database\Eloquent\Model
public abstract function routePattern(): string
public abstract function rules(Nvl\Pages\Models\Page $page): array
@return array<string, mixed>
public abstract function sitemapEntries(Nvl\Pages\Models\Page $page, string $scope): iterable
@return iterable<SitemapEntry>
resolved @return iterable<Nvl\Seo\ValueObjects\SitemapEntry>
```

## Nvl\Pages\Contracts\PageUrlGenerator

```text
interface Nvl\Pages\Contracts\PageUrlGenerator
public abstract function url(Nvl\Pages\Models\Page $page, ?string $locale=null): string
```

## Nvl\Pages\Database\Factories\PageFactory

```text
final class Nvl\Pages\Database\Factories\PageFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<Page>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Pages\Models\Page>
public function definition(): array
@return array<model-property<Page>, mixed>
resolved @return array<model-property<Nvl\Pages\Models\Page>, mixed>
public function modelName(): string
```

## Nvl\Pages\Database\Factories\PageTranslationFactory

```text
final class Nvl\Pages\Database\Factories\PageTranslationFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<PageTranslation>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Pages\Models\PageTranslation>
public function definition(): array
@return array<model-property<PageTranslation>, mixed>
resolved @return array<model-property<Nvl\Pages\Models\PageTranslation>, mixed>
public function forPage(Nvl\Pages\Models\Page $parent): static
```

## Nvl\Pages\Enums\PageAbility

```text
enum Nvl\Pages\Enums\PageAbility implements BackedEnum, UnitEnum: string
public const Nvl\Pages\Enums\PageAbility Archive=Nvl\Pages\Enums\PageAbility::Archive="archive"
public const Nvl\Pages\Enums\PageAbility Create=Nvl\Pages\Enums\PageAbility::Create="create"
public const Nvl\Pages\Enums\PageAbility Delete=Nvl\Pages\Enums\PageAbility::Delete="delete"
public const Nvl\Pages\Enums\PageAbility List=Nvl\Pages\Enums\PageAbility::List="list"
public const Nvl\Pages\Enums\PageAbility Move=Nvl\Pages\Enums\PageAbility::Move="move"
public const Nvl\Pages\Enums\PageAbility Preview=Nvl\Pages\Enums\PageAbility::Preview="preview"
public const Nvl\Pages\Enums\PageAbility Publish=Nvl\Pages\Enums\PageAbility::Publish="publish"
public const Nvl\Pages\Enums\PageAbility Restore=Nvl\Pages\Enums\PageAbility::Restore="restore"
public const Nvl\Pages\Enums\PageAbility Update=Nvl\Pages\Enums\PageAbility::Update="update"
public const Nvl\Pages\Enums\PageAbility View=Nvl\Pages\Enums\PageAbility::View="view"
public const Nvl\Pages\Enums\PageAbility ViewNavigation=Nvl\Pages\Enums\PageAbility::ViewNavigation="view_navigation"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Pages\Enums\PageChangeOperation

```text
enum Nvl\Pages\Enums\PageChangeOperation implements BackedEnum, UnitEnum: string
public const Nvl\Pages\Enums\PageChangeOperation Created=Nvl\Pages\Enums\PageChangeOperation::Created="created"
public const Nvl\Pages\Enums\PageChangeOperation Deleted=Nvl\Pages\Enums\PageChangeOperation::Deleted="deleted"
public const Nvl\Pages\Enums\PageChangeOperation Moved=Nvl\Pages\Enums\PageChangeOperation::Moved="moved"
public const Nvl\Pages\Enums\PageChangeOperation Restored=Nvl\Pages\Enums\PageChangeOperation::Restored="restored"
public const Nvl\Pages\Enums\PageChangeOperation Updated=Nvl\Pages\Enums\PageChangeOperation::Updated="updated"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Pages\Enums\PageKind

```text
enum Nvl\Pages\Enums\PageKind implements BackedEnum, UnitEnum: string
public const Nvl\Pages\Enums\PageKind Resource=Nvl\Pages\Enums\PageKind::Resource="resource"
public const Nvl\Pages\Enums\PageKind Static=Nvl\Pages\Enums\PageKind::Static="static"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Pages\Enums\PageStatus

```text
enum Nvl\Pages\Enums\PageStatus implements BackedEnum, UnitEnum: string
public const Nvl\Pages\Enums\PageStatus Archived=Nvl\Pages\Enums\PageStatus::Archived="archived"
public const Nvl\Pages\Enums\PageStatus Draft=Nvl\Pages\Enums\PageStatus::Draft="draft"
public const Nvl\Pages\Enums\PageStatus Published=Nvl\Pages\Enums\PageStatus::Published="published"
public const Nvl\Pages\Enums\PageStatus Scheduled=Nvl\Pages\Enums\PageStatus::Scheduled="scheduled"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Pages\Enums\PagesResponseCode

```text
enum Nvl\Pages\Enums\PagesResponseCode implements BackedEnum, Nvl\Support\Contracts\ResponseCode, UnitEnum: string
public const Nvl\Pages\Enums\PagesResponseCode InvalidPageMutation=Nvl\Pages\Enums\PagesResponseCode::InvalidPageMutation="invalid_page_mutation"
public const Nvl\Pages\Enums\PagesResponseCode OperationFailed=Nvl\Pages\Enums\PagesResponseCode::OperationFailed="operation_failed"
public const Nvl\Pages\Enums\PagesResponseCode PageConflict=Nvl\Pages\Enums\PagesResponseCode::PageConflict="page_conflict"
public const Nvl\Pages\Enums\PagesResponseCode PageHierarchyConflict=Nvl\Pages\Enums\PagesResponseCode::PageHierarchyConflict="page_hierarchy_conflict"
public const Nvl\Pages\Enums\PagesResponseCode StalePage=Nvl\Pages\Enums\PagesResponseCode::StalePage="stale_page"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Pages\Enums\PublicChildPageOrder

```text
enum Nvl\Pages\Enums\PublicChildPageOrder implements BackedEnum, UnitEnum: string
public const Nvl\Pages\Enums\PublicChildPageOrder Newest=Nvl\Pages\Enums\PublicChildPageOrder::Newest="newest"
public const Nvl\Pages\Enums\PublicChildPageOrder Sibling=Nvl\Pages\Enums\PublicChildPageOrder::Sibling="sibling"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Pages\Events\PageChanged

```text
final readonly class Nvl\Pages\Events\PageChanged implements Nvl\Support\Contracts\DomainEvent
public function __construct(string $pageId, string $site, Nvl\Pages\Enums\PageChangeOperation $operation, int $revision, array $actor, array $affectedPageIds=[], ?array $sitemapIdentity=null, int $schemaVersion=1): mixed
@param array{type: ?string, id: (int | string | null), system: bool} $actor
@param (array{connection: string, tenantId: string, site: string, origin: string, scope: string, version: int, key: string, namespace: string} | null) $sitemapIdentity
@param list<string> $affectedPageIds
public function schemaVersion(): int
public readonly array $actor
@param array{type: ?string, id: (int | string | null), system: bool} $actor
public readonly array $affectedPageIds
@param list<string> $affectedPageIds
public readonly Nvl\Pages\Enums\PageChangeOperation $operation
public readonly string $pageId
public readonly int $revision
public readonly int $schemaVersion
public readonly string $site
public readonly ?array $sitemapIdentity
@var (array{connection: string, tenantId: string, site: string, origin: string, scope: string, version: int, key: string, namespace: string} | null)
```

## Nvl\Pages\Exceptions\InvalidPageMutationException

```text
final class Nvl\Pages\Exceptions\InvalidPageMutationException extends Nvl\Pages\Exceptions\PagesException implements Illuminate\Contracts\Debug\ShouldntReport
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Pages\Exceptions\PageConflictException

```text
final class Nvl\Pages\Exceptions\PageConflictException extends Nvl\Pages\Exceptions\PagesException implements Illuminate\Contracts\Debug\ShouldntReport
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Pages\Exceptions\PageHierarchyException

```text
final class Nvl\Pages\Exceptions\PageHierarchyException extends Nvl\Pages\Exceptions\PagesException implements Illuminate\Contracts\Debug\ShouldntReport
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Pages\Exceptions\PagesException

```text
class Nvl\Pages\Exceptions\PagesException extends RuntimeException implements Nvl\Support\Contracts\RespondableException
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Pages\Exceptions\StalePageException

```text
final class Nvl\Pages\Exceptions\StalePageException extends Nvl\Pages\Exceptions\PagesException implements Illuminate\Contracts\Debug\ShouldntReport
public static function forPage(string $pageId): self
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Pages\Models\Page

```text
class Nvl\Pages\Models\Page extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable, Nvl\Translatable\Contracts\TranslatableModel
public const string CONTENT_GROUP="content"
public const string CONTENT_OWNER_TYPE="nvl-page"
public const string TENANT_RESOURCE="pages.pages"
public function children(): Illuminate\Database\Eloquent\Relations\HasMany
@return HasMany<Page, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasMany<Nvl\Pages\Models\Page, $this>
public function delete(): ?bool
public function displayTitle(?string $locale=null): string
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getAllTranslations(): Illuminate\Database\Eloquent\Collection
@return Collection<int, Model>
resolved @return Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Model>
public function getAvailableLocales(): array
@return list<string>
public function getConnectionName(): ?string
public function getCurrentLocale(): string
public function getRelationValue(mixed $key): mixed
@param string $key
public function getTable(): string
public function getTranslatedAttributes(?string $locale=null): array
@return array<string, mixed>
public function getTranslation(?string $locale=null, bool $withFallback=true): ?Illuminate\Database\Eloquent\Model
public function hasTranslation(?string $locale=null): bool
public function isTranslatableAttribute(string $key): bool
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function parent(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<Page, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Pages\Models\Page, $this>
public function refresh(): static
public function refreshForUpdate(): static
public function resolveTranslation(string $field, ?string $locale=null): Nvl\Translatable\TranslationResolution
public function save(array $options=[]): bool
@param array<string, mixed> $options
public function scopeOrderByTranslated(Illuminate\Database\Eloquent\Builder $query, string $field, string $direction="asc", ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page>
public function scopeOrdered(Illuminate\Database\Eloquent\Builder $query): void
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> $query
public function scopePubliclyVisible(Illuminate\Database\Eloquent\Builder $query, ?Carbon\Carbon $at=null): void
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> $query
public function scopeWhereTranslated(Illuminate\Database\Eloquent\Builder $query, string $field, mixed $operator, mixed $value=\Nvl\Translatable\Enums\TranslationComparisonValue::Omitted, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page>
public function scopeWhereTranslationNotNull(Illuminate\Database\Eloquent\Builder $query, string $field, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page>
public function scopeWhereTranslationNull(Illuminate\Database\Eloquent\Builder $query, string $field, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page>
public function scopeWithAllTranslations(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page>
public function scopeWithResolvedTranslations(Illuminate\Database\Eloquent\Builder $query, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page>
public function setLocale(string $locale): self
public function translated(string $field, ?string $locale=null): mixed
public function translation(?string $locale=null): Illuminate\Database\Eloquent\Relations\HasOne
@return HasOne<Model, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasOne<Illuminate\Database\Eloquent\Model, $this>
public function translationDefinition(): Nvl\Translatable\RelatedTranslationDefinition
public function translationResourceKey(): int|string
public function translations(): Illuminate\Database\Eloquent\Relations\HasMany
@return HasMany<Model, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasMany<Illuminate\Database\Eloquent\Model, $this>
@method static Builder<static> orderByTranslated(string $field, string $direction = 'asc', ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> orderByTranslated(string $field, string $direction = 'asc', ?string $locale = null)
@method static Builder<static> ordered()
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> ordered()
@method static Builder<static> publiclyVisible(?Carbon $at = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> publiclyVisible(?Carbon\Carbon $at = null)
@method \Illuminate\Database\Eloquent\Relations\MorphMany<\Nvl\Seo\Models\SeoProfile, $this> seoProfiles()
resolved @method Illuminate\Database\Eloquent\Relations\MorphMany<Nvl\Seo\Models\SeoProfile, $this> seoProfiles()
@method static Builder<static> whereTranslated(string $field, mixed $operator, mixed $value = TranslationComparisonValue::Omitted, ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> whereTranslated(string $field, mixed $operator, mixed $value = Nvl\Translatable\Enums\TranslationComparisonValue::Omitted, ?string $locale = null)
@method static Builder<static> whereTranslationNotNull(string $field, ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> whereTranslationNotNull(string $field, ?string $locale = null)
@method static Builder<static> whereTranslationNull(string $field, ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> whereTranslationNull(string $field, ?string $locale = null)
@method static Builder<static> withAllTranslations()
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> withAllTranslations()
@method static Builder<static> withResolvedTranslations(?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\Page> withResolvedTranslations(?string $locale = null)
@property-read Collection<int, Page> $children
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Pages\Models\Page> $children
@property Carbon $created_at
resolved @property Carbon\Carbon $created_at
@property (Carbon | null) $deleted_at
resolved @property (Carbon\Carbon | null) $deleted_at
@property (Carbon | null) $expires_at
resolved @property (Carbon\Carbon | null) $expires_at
@property string $id
@property bool $is_navigable
@property string $key
@property PageKind $kind
resolved @property Nvl\Pages\Enums\PageKind $kind
@property-read (Page | null) $parent
resolved @property-read (Nvl\Pages\Models\Page | null) $parent
@property (string | null) $parent_id
@property string $parent_key
@property string $path
@property string $path_hash
@property int $position
@property (Carbon | null) $published_at
resolved @property (Carbon\Carbon | null) $published_at
@property (string | null) $resource
@property int $revision
@property-read Collection<int, SeoProfile> $seoProfiles
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Seo\Models\SeoProfile> $seoProfiles
@property string $site
@property (SitemapChangeFrequency | null) $sitemap_change_frequency
resolved @property (Nvl\Seo\Enums\SitemapChangeFrequency | null) $sitemap_change_frequency
@property bool $sitemap_included
@property (string | null) $sitemap_priority
@property string $slug
@property PageStatus $status
resolved @property Nvl\Pages\Enums\PageStatus $status
@property (string | null) $tenant_id
@property-read Collection<int, PageTranslation> $translations
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Pages\Models\PageTranslation> $translations
@property Carbon $updated_at
resolved @property Carbon\Carbon $updated_at
```

## Nvl\Pages\Models\PageTranslation

```text
final class Nvl\Pages\Models\PageTranslation extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable
public const string TENANT_RESOURCE="pages.translations"
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getConnectionName(): ?string
public function getTable(): string
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Pages\Models\PageTranslation>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function page(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<Page, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Pages\Models\Page, $this>
public function refresh(): static
public function refreshForUpdate(): static
public function save(array $options=[]): bool
@param array<string, mixed> $options
@property Carbon $created_at
resolved @property Carbon\Carbon $created_at
@property string $id
@property string $locale
@property (string | null) $navigation_label
@property-read Page $page
resolved @property-read Nvl\Pages\Models\Page $page
@property string $page_id
@property (string | null) $summary
@property (string | null) $tenant_id
@property string $title
@property Carbon $updated_at
resolved @property Carbon\Carbon $updated_at
```

## Nvl\Pages\Services\ConfiguredPageAuthorization

```text
final class Nvl\Pages\Services\ConfiguredPageAuthorization implements Nvl\Pages\Contracts\PageAuthorization
public function authorize(Nvl\Pages\Enums\PageAbility $ability, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor|Nvl\Support\Enums\PublicActor $actor, ?Nvl\Pages\Models\Page $page=null, array $context=[]): void
```

## Nvl\Pages\Services\PageLifecycle

```text
final readonly class Nvl\Pages\Services\PageLifecycle
public function __construct(Nvl\Support\Auth\CanonicalActorResolver $actors, Nvl\Pages\Contracts\PageAuthorization $authorization, Nvl\Pages\Services\PageDatabaseConflict $conflicts, Nvl\Pages\Services\PageHierarchy $hierarchy, Nvl\Pages\Services\PageMutationValues $values, Nvl\Pages\Services\PageTreeLock $treeLock, Nvl\Translatable\Services\RelatedTranslationStore $translations, Nvl\Support\Events\DomainEventDispatcher $domainEvents): mixed
public function create(array $attributes, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Pages\Models\Page
@param array{key: string, slug: string, parentId?: ?string, site?: string, kind?: (PageKind | string), resource?: ?string, status?: (PageStatus | string), position?: int, isNavigable?: bool, sitemapIncluded?: bool, sitemapPriority?: ?string, sitemapChangeFrequency?: ?string, publishedAt?: ?string, expiresAt?: ?string, translations?: array<string, array{title: string, navigationLabel?: ?string, summary?: ?string}>} $attributes
resolved @param array{key: string, slug: string, parentId?: ?string, site?: string, kind?: (Nvl\Pages\Enums\PageKind | string), resource?: ?string, status?: (Nvl\Pages\Enums\PageStatus | string), position?: int, isNavigable?: bool, sitemapIncluded?: bool, sitemapPriority?: ?string, sitemapChangeFrequency?: ?string, publishedAt?: ?string, expiresAt?: ?string, translations?: array<string, array{title: string, navigationLabel?: ?string, summary?: ?string}>} $attributes
@throws InvalidPageMutationException
resolved @throws Nvl\Pages\Exceptions\InvalidPageMutationException
@throws PageHierarchyException
resolved @throws Nvl\Pages\Exceptions\PageHierarchyException
public function delete(Nvl\Pages\Models\Page|string $page, int $expectedRevision, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): bool
public function move(Nvl\Pages\Models\Page|string $page, ?string $parentId, int $position, int $expectedRevision, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Pages\Models\Page
public function restore(Nvl\Pages\Models\Page|string $page, int $expectedRevision, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Pages\Models\Page
public function update(Nvl\Pages\Models\Page|string $page, array $attributes, int $expectedRevision, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor): Nvl\Pages\Models\Page
@param array{slug: string, kind: (PageKind | string), resource?: ?string, status: (PageStatus | string), position?: int, isNavigable?: bool, sitemapIncluded?: bool, sitemapPriority?: ?string, sitemapChangeFrequency?: ?string, publishedAt?: ?string, expiresAt?: ?string, translations?: array<string, array{title: string, navigationLabel?: ?string, summary?: ?string}>} $attributes
resolved @param array{slug: string, kind: (Nvl\Pages\Enums\PageKind | string), resource?: ?string, status: (Nvl\Pages\Enums\PageStatus | string), position?: int, isNavigable?: bool, sitemapIncluded?: bool, sitemapPriority?: ?string, sitemapChangeFrequency?: ?string, publishedAt?: ?string, expiresAt?: ?string, translations?: array<string, array{title: string, navigationLabel?: ?string, summary?: ?string}>} $attributes
@throws StalePageException
resolved @throws Nvl\Pages\Exceptions\StalePageException
@throws InvalidPageMutationException
resolved @throws Nvl\Pages\Exceptions\InvalidPageMutationException
@throws PageHierarchyException
resolved @throws Nvl\Pages\Exceptions\PageHierarchyException
```

## Nvl\Pages\Services\PageModelRegistry

```text
final class Nvl\Pages\Services\PageModelRegistry
public function __construct(Illuminate\Contracts\Config\Repository $configuration, Nvl\Support\OwnerRegistry $identities): mixed
public function pageClass(): string
@return class-string<Page>
resolved @return class-string<Nvl\Pages\Models\Page>
public static function projectionColumns(array $columns): array
@param list<string> $columns
@return list<string>
```

## Nvl\Pages\Services\PageNavigationBuilder

```text
final readonly class Nvl\Pages\Services\PageNavigationBuilder
public function __construct(Nvl\Pages\Contracts\PageUrlGenerator $urls): mixed
public function build(Illuminate\Support\Collection $pages, string $locale): array
@param Collection<int, Page> $pages
resolved @param Illuminate\Support\Collection<int, Nvl\Pages\Models\Page> $pages
@return list<array{id: string, key: string, path: string, url: string, label: string, children: list<array<string, mixed>>}>
```

## Nvl\Pages\Services\PageReader

```text
final readonly class Nvl\Pages\Services\PageReader
public function __construct(Nvl\Support\Auth\CanonicalActorResolver $actors, Nvl\Pages\Contracts\PageAuthorization $authorization, Nvl\Pages\Services\PageIdentityGuard $identities, Nvl\Support\Contracts\LocaleCatalog $locales, Nvl\Pages\Services\PageNavigationBuilder $builder): mixed
public function children(string $parentId, string $site, string $locale, int $limit=50, ?Nvl\Pages\Enums\PageKind $kind=null, Nvl\Pages\Enums\PublicChildPageOrder $order=\Nvl\Pages\Enums\PublicChildPageOrder::Sibling): Illuminate\Support\Collection
@return Collection<int, Page>
resolved @return Illuminate\Support\Collection<int, Nvl\Pages\Models\Page>
public function get(Nvl\Pages\Models\Page|string $page, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor|Nvl\Support\Enums\PublicActor $actor): Nvl\Pages\Models\Page
public function keyAvailable(string $site, string $key, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, ?string $exceptId=null): array
@return array{site: string, key: string, available: bool, conflictingPageId: ?string}
public function navigation(string $site, string $locale): array
@return array{site: string, locale: string, items: list<array{id: string, key: string, path: string, url: string, label: string, children: list<array<string, mixed>>}>}
public function paginate(string $site, Illuminate\Contracts\Auth\Authenticatable|Nvl\Support\Enums\SystemActor $actor, array $filters=[], int $perPage=25): Illuminate\Pagination\LengthAwarePaginator
@param array{status?: (PageStatus | string | list<string>), kind?: (PageKind | string | list<string>), parent?: ?string, navigable?: bool, sort?: string} $filters
resolved @param array{status?: (Nvl\Pages\Enums\PageStatus | string | list<string>), kind?: (Nvl\Pages\Enums\PageKind | string | list<string>), parent?: ?string, navigable?: bool, sort?: string} $filters
@return LengthAwarePaginator<int, Page>
resolved @return Illuminate\Pagination\LengthAwarePaginator<int, Nvl\Pages\Models\Page>
```

## Nvl\Pages\Services\PageResolver

```text
final readonly class Nvl\Pages\Services\PageResolver
public function __construct(Nvl\Pages\Services\PageMatcher $matcher, Nvl\Pages\Contracts\PageAuthorization $authorization, Nvl\Pages\Services\PageIdentityGuard $identities, Nvl\Support\Contracts\LocaleCatalog $locales, Nvl\Support\Auth\CanonicalActorResolver $actors): mixed
public function preview(string $path, string $site, string $locale, Illuminate\Contracts\Auth\Authenticatable $actor, array $context=[]): array
@param array<string, mixed> $context
@return array{page: Page, resource: (array{type: string, id: string, payload: array<string, mixed>} | null)}
resolved @return array{page: Nvl\Pages\Models\Page, resource: (array{type: string, id: string, payload: array<string, mixed>} | null)}
public function resolve(string $path, string $site, string $locale, array $context=[]): array
@param array<string, mixed> $context
@return array{page: Page, resource: (array{type: string, id: string, payload: array<string, mixed>} | null)}
resolved @return array{page: Nvl\Pages\Models\Page, resource: (array{type: string, id: string, payload: array<string, mixed>} | null)}
```

## Nvl\Pages\Services\PageResourceRegistry

```text
final class Nvl\Pages\Services\PageResourceRegistry
public function __construct(Illuminate\Contracts\Container\Container $container, Nvl\Support\OwnerRegistry $identities): mixed
public function aliases(): array
@return list<string>
public function assertOwner(string $alias, Illuminate\Database\Eloquent\Model $owner): void
public function get(string $alias): Nvl\Pages\Contracts\PageResourceHandler
@return PageResourceHandler<Model>
resolved @return Nvl\Pages\Contracts\PageResourceHandler<Illuminate\Database\Eloquent\Model>
public function has(string $alias): bool
public function register(string $alias, string $handler, ?string $owner=null): void
```
