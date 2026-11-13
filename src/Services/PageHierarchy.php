<?php

declare(strict_types=1);

namespace Nvl\Pages\Services;

use Nvl\Pages\Exceptions\PageHierarchyException;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Support\PagesConfiguration;

/**
 * Validates page parents, cycles, subtree height, and the family-wide four-level cap.
 *
 * @internal
 */
final class PageHierarchy
{
    /**
     * Validate a parent assignment while the caller holds the matching site tree lock.
     */
    public function assertValid(string $site, ?string $parentId, ?string $pageId = null): void
    {
        $depth = 1;
        $visited = [];
        $cursorId = $parentId;

        while ($cursorId !== null) {
            if ($cursorId === $pageId || isset($visited[$cursorId])) {
                throw new PageHierarchyException('A page hierarchy cannot contain a cycle.');
            }

            $cursor = PageModelRegistry::query()->where('site', $site)->find($cursorId);
            if (! $cursor instanceof Page) {
                throw new PageHierarchyException(
                    "Parent [{$cursorId}] does not belong to site [{$site}].",
                );
            }

            if ($cursor->id === $pageId || isset($visited[$cursor->id])) {
                throw new PageHierarchyException('A page hierarchy cannot contain a cycle.');
            }

            $visited[$cursor->id] = true;
            $depth++;
            $cursorId = $cursor->parent_id;
        }

        $subtreeHeight = $pageId !== null
            ? $this->subtreeHeight($site, $pageId)
            : 1;

        if ($depth + $subtreeHeight - 1 > PagesConfiguration::maximumDepth()) {
            throw new PageHierarchyException(
                'The page operation would exceed the configured hierarchy depth.',
            );
        }
    }

    /**
     * Build a canonical path while the caller holds the matching site tree lock.
     */
    public function path(string $site, ?string $parentId, string $slug): string
    {
        if ($parentId === null) {
            return $slug;
        }

        $parent = PageModelRegistry::query()
            ->where('site', $site)
            ->find($parentId);

        if (! $parent instanceof Page) {
            throw new PageHierarchyException(
                "Parent [{$parentId}] does not belong to site [{$site}].",
            );
        }

        return $parent->path.'/'.$slug;
    }

    /**
     * Recompute all descendant paths after a slug or parent change.
     *
     * Deleted descendants are rewritten too, so they stop reserving the former paths; only live ones are returned.
     *
     * @return list<Page>
     */
    public function rebuildDescendantPaths(Page $page): array
    {
        $changed = [];
        $parents = [$page->id => $page->path];
        $visited = [$page->id => true];

        while ($parents !== []) {
            $next = [];
            foreach (array_chunk(array_keys($parents), 500) as $parentIds) {
                $children = PageModelRegistry::query()
                    ->withTrashed()
                    ->where('site', $page->site)
                    ->whereIn('parent_id', $parentIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($children as $child) {
                    if (isset($visited[$child->id])) {
                        throw new PageHierarchyException('A page hierarchy cannot contain a cycle.');
                    }
                    $visited[$child->id] = true;
                    $path = $parents[$child->parent_id ?? throw new PageHierarchyException('A descendant must have a parent.')].'/'.$child->slug;
                    if ($child->path !== $path) {
                        $child->path = $path;
                        $child->save();
                        if (! $child->trashed()) {
                            $changed[] = $child;
                        }
                    }
                    $next[$child->id] = $path;
                }
            }
            $parents = $next;
        }

        return $changed;
    }

    /** Measure only this subtree, with one bounded parent batch per level. */
    private function subtreeHeight(string $site, string $pageId): int
    {
        $height = 1;
        $parents = [$pageId];
        $visited = [$pageId => true];
        while ($parents !== []) {
            $next = [];
            foreach (array_chunk($parents, 500) as $parentIds) {
                $children = PageModelRegistry::query()
                    ->withTrashed()
                    ->where('site', $site)
                    ->whereIn('parent_id', $parentIds)
                    ->pluck('id');
                foreach ($children as $childId) {
                    if (! is_string($childId)) {
                        throw new PageHierarchyException('A descendant must have a canonical page identifier.');
                    }
                    if (isset($visited[$childId])) {
                        throw new PageHierarchyException('A page hierarchy cannot contain a cycle.');
                    }
                    $visited[$childId] = true;
                    $next[] = $childId;
                }
            }
            if ($next !== []) {
                $height++;
                if ($height > PagesConfiguration::maximumDepth()) {
                    throw new PageHierarchyException('The page operation would exceed the configured hierarchy depth.');
                }
            }
            $parents = $next;
        }

        return $height;
    }
}
