<?php

declare(strict_types=1);

namespace Nvl\Pages\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Nvl\Content\Providers\ContentServiceProvider;
use Nvl\Metafields\Providers\MetafieldsServiceProvider;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Definitions\Tables\PagesTables;
use Nvl\Pages\Enums\PageKind;
use Nvl\Pages\Enums\PageStatus;
use Nvl\Pages\Ownership\PagesOwnership;
use Nvl\Pages\Support\PagePath;
use Nvl\Pages\Support\PagesConfiguration;
use Nvl\Seo\Providers\SeoServiceProvider;
use Nvl\Support\Integrations\OptionalIntegration;
use stdClass;

/**
 * Performs non-mutating installation, registry, route, and hierarchy diagnostics.
 *
 * @internal
 */
final class PagesDoctor
{
    /** Initialize the package-owned inspection dependencies. */
    public function __construct(
        private PageResourceRegistry $resources,
        private PageAuthorization $authorization,
        private OptionalIntegration $integrations,
    ) {}

    /**
     * Inspect package readiness without rendering a command or changing state.
     *
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $resources = $this->resources;
        $authorization = $this->authorization;

        $connection = PageModelRegistry::newInstance()->getConnection();
        $schema = $connection->getSchemaBuilder();
        $pages = PagesConfiguration::table(PagesTables::get(PagesTables::Pages), PagesTables::get(PagesTables::Pages));
        $i18n = PagesConfiguration::table(PagesTables::get(PagesTables::I18n), PagesTables::get(PagesTables::I18n));
        $treeLocks = PagesConfiguration::table(PagesTables::get(PagesTables::TreeLocks), PagesTables::get(PagesTables::TreeLocks));
        $pagesTableExists = $this->probe($connection, static fn (): bool => $schema->hasTable($pages));
        $i18nTableExists = $this->probe($connection, static fn (): bool => $schema->hasTable($i18n));
        $treeLocksTableExists = $this->probe($connection, static fn (): bool => $schema->hasTable($treeLocks));
        $checks = [
            'integrations.content' => $this->integrations->check('pages.integrations.content', ContentServiceProvider::class),
            'integrations.seo' => $this->integrations->check('pages.integrations.seo', SeoServiceProvider::class),
            'integrations.metafields' => $this->integrations->check('pages.integrations.metafields', MetafieldsServiceProvider::class),
            'table.pages' => $pagesTableExists,
            'table.pages_i18n' => $i18nTableExists,
            'table.page_tree_locks' => $treeLocksTableExists,
            'hierarchy.maximum_depth' => PagesConfiguration::maximumDepth(),
            'resources' => $resources->aliases(),
        ];

        if (PagesOwnership::declared()) {
            $ownerTreeLocks = PagesTables::get(PagesTables::OwnerTreeLocks);
            $checks['table.owner_tree_locks'] = $this->probe($connection, static fn (): bool => $schema->hasTable($ownerTreeLocks));
        }

        if ($pagesTableExists) {
            $existingPageColumns = $this->probe($connection, static fn (): array => $schema->getColumnListing($pages));
            $required = [
                'id',
                'parent_id',
                'key',
                'site',
                'slug',
                'path',
                'path_hash',
                'kind',
                'resource',
                'status',
                'revision',
            ];

            foreach ($required as $column) {
                $checks["column.pages.{$column}"] = in_array($column, $existingPageColumns, true);
            }

            $hierarchyColumns = ['id', 'parent_id', 'parent_key', 'site', 'slug', 'path', 'path_hash'];
            if (array_diff($hierarchyColumns, $existingPageColumns) === []) {
                $pageRows = $this->probe(
                    $connection,
                    static fn (): Collection => $connection->table($pages)->get($hierarchyColumns),
                );
                $checks['data.path_hash_drift'] = $pageRows->contains(
                    static fn (stdClass $page): bool => ! is_string($page->path_hash ?? null)
                        || ! is_string($page->site ?? null)
                        || ! is_string($page->path ?? null)
                        || ! hash_equals(
                            $page->path_hash,
                            PagePath::hash($page->site, $page->path),
                        ),
                );
                $hierarchy = $this->hierarchyIssues($pageRows);
                $checks['data.excessive_depth'] = $hierarchy['excessive_depth'];
                $checks['data.cycle'] = $hierarchy['cycle'];
                $checks['data.orphaned_parent'] = $hierarchy['orphaned_parent'];
                $checks['data.parent_site_drift'] = $hierarchy['parent_site_drift'];
                $checks['data.parent_key_drift'] = $hierarchy['parent_key_drift'];
                $checks['data.parent_path_drift'] = $hierarchy['parent_path_drift'];
            } else {
                $checks['data.path_hash_drift'] = true;
                $checks['data.excessive_depth'] = false;
                $checks['data.cycle'] = false;
                $checks['data.orphaned_parent'] = false;
                $checks['data.parent_site_drift'] = false;
                $checks['data.parent_key_drift'] = true;
                $checks['data.parent_path_drift'] = false;
            }

            if (in_array('kind', $existingPageColumns, true) && in_array('resource', $existingPageColumns, true)) {
                $checks['data.unknown_resource'] = $this->probe(
                    $connection,
                    static fn (): bool => $connection->table($pages)
                        ->where('kind', PageKind::Resource->value)
                        ->whereNotNull('resource')
                        ->pluck('resource')
                        ->filter(
                            static fn (mixed $alias): bool => is_string($alias)
                                && ! $resources->has($alias),
                        )
                        ->isNotEmpty(),
                );
                $checks['data.invalid_kind_resource'] = $this->probe(
                    $connection,
                    static fn (): bool => $connection->table($pages)
                        ->where(static function (Builder $query): void {
                            $query
                                ->where(static function (Builder $query): void {
                                    $query
                                        ->where('kind', PageKind::Static->value)
                                        ->whereNotNull('resource');
                                })
                                ->orWhere(static function (Builder $query): void {
                                    $query
                                        ->where('kind', PageKind::Resource->value)
                                        ->whereNull('resource');
                                });
                        })
                        ->exists(),
                );
            } else {
                $checks['data.unknown_resource'] = true;
                $checks['data.invalid_kind_resource'] = true;
            }

            $checks['data.invalid_status'] = in_array('status', $existingPageColumns, true)
                ? $this->probe(
                    $connection,
                    static fn (): bool => $connection->table($pages)
                        ->whereNotIn('status', array_column(PageStatus::cases(), 'value'))
                        ->exists(),
                )
                : true;

            $checks['data.invalid_lifecycle_dates'] = array_diff(['status', 'published_at', 'expires_at'], $existingPageColumns) === []
                ? $this->probe(
                    $connection,
                    static fn (): bool => $connection->table($pages)
                        ->where(static function (Builder $query): void {
                            $query
                                ->where(static function (Builder $query): void {
                                    $query
                                        ->where('status', PageStatus::Scheduled->value)
                                        ->whereNull('published_at');
                                })
                                ->orWhere(static function (Builder $query): void {
                                    $query
                                        ->whereNotNull('published_at')
                                        ->whereNotNull('expires_at')
                                        ->whereColumn('expires_at', '<=', 'published_at');
                                });
                        })
                        ->exists(),
                )
                : true;
        }

        if ($i18nTableExists) {
            $existingI18nColumns = $this->probe($connection, static fn (): array => $schema->getColumnListing($i18n));
            foreach (['id', 'page_id', 'locale', 'title'] as $column) {
                $checks["column.pages_i18n.{$column}"] = in_array($column, $existingI18nColumns, true);
            }
        }

        if ($treeLocksTableExists) {
            $checks['column.page_tree_locks.site'] = $this->probe(
                $connection,
                static fn (): bool => $schema->hasColumn($treeLocks, 'site'),
            );
        }

        $healthy = collect($checks)->every(
            static fn (mixed $value, string $key): bool => match (true) {
                $key === 'integrations.metafields' => is_array($value) && ($value['passed'] ?? false) === true,
                str_starts_with($key, 'table.') => $value === true,
                str_starts_with($key, 'column.') => $value === true,
                str_starts_with($key, 'data.') => $value === false,
                str_ends_with($key, '.middleware') => $value === true,
                $key === 'routes.management.authorization' => $value === true,
                default => true,
            },
        );
        $checks['healthy'] = $healthy;

        return $checks;
    }

    /**
     * Return all hierarchy integrity flags in one bounded in-memory tree pass.
     *
     * @param  Collection<int, stdClass>  $pages
     * @return array{
     * excessive_depth: bool,
     * cycle: bool,
     * orphaned_parent: bool,
     * parent_site_drift: bool,
     * parent_key_drift: bool,
     * parent_path_drift: bool
     * }
     */
    private function hierarchyIssues(Collection $pages): array
    {
        $byId = $pages->keyBy('id');
        $issues = [
            'excessive_depth' => false,
            'cycle' => false,
            'orphaned_parent' => false,
            'parent_site_drift' => false,
            'parent_key_drift' => false,
            'parent_path_drift' => false,
        ];

        foreach ($pages as $page) {
            $expectedParentKey = $page->parent_id ?? '__root__';

            if ($page->parent_key !== $expectedParentKey) {
                $issues['parent_key_drift'] = true;
            }

            if ($page->parent_id === null && $page->path !== $page->slug) {
                $issues['parent_path_drift'] = true;
            }

            $depth = 1;
            $visited = [];
            $cursor = $page;

            while ($cursor->parent_id !== null) {
                $cursorId = is_string($cursor->id) || is_int($cursor->id) ? (string) $cursor->id : '';
                if (isset($visited[$cursorId])) {
                    $issues['cycle'] = true;

                    break;
                }

                $visited[$cursorId] = true;
                $parentId = is_string($cursor->parent_id) || is_int($cursor->parent_id) ? $cursor->parent_id : null;
                $parent = $parentId !== null ? $byId->get($parentId) : null;

                if (! $parent instanceof stdClass) {
                    $issues['orphaned_parent'] = true;

                    break;
                }

                if ($cursor->site !== $parent->site) {
                    $issues['parent_site_drift'] = true;
                }

                $parentPath = is_string($parent->path) ? $parent->path : '';
                $cursorSlug = is_string($cursor->slug) ? $cursor->slug : '';
                if ($cursor->path !== $parentPath.'/'.$cursorSlug) {
                    $issues['parent_path_drift'] = true;
                }

                $depth++;
                $cursor = $parent;
            }

            if ($depth > PagesConfiguration::maximumDepth()) {
                $issues['excessive_depth'] = true;
            }
        }

        return $issues;
    }

    /**
     * Execute a read-only database probe inside a savepoint when an outer transaction is active.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $operation
     * @return TResult
     */
    private function probe(ConnectionInterface $connection, callable $operation): mixed
    {
        return $connection->transactionLevel() > 0
            ? $connection->transaction(static fn () => $operation())
            : $operation();
    }
}
