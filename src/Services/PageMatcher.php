<?php

declare(strict_types=1);

namespace Nvl\Pages\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Nvl\Pages\Contracts\OwnershipSafePageResourceHandler;
use Nvl\Pages\Enums\PageKind;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Ownership\PagesOwnership;
use Nvl\Pages\Support\PagePath;
use Nvl\Pages\Support\PagesConfiguration;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary as TenantBoundary;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves static paths first, then registered resource patterns by specificity.
 */
final readonly class PageMatcher
{
    /**
     * Create the static and dynamic page matcher.
     */
    public function __construct(
        private PageResourceRegistry $resources,
        private Factory $validation,
        private TenantBoundary $tenancy,
        private OwnershipResourceRegistry $ownershipResources,
    ) {}

    /**
     * Resolve one canonical request path to its page and optional resource.
     *
     * @return array{page:Page,resource:array{type:string,id:string,payload:array<string,mixed>}|null}
     */
    public function resolve(
        string $path,
        string $site,
        string $locale,
        bool $publicOnly = true,
    ): array {
        try {
            $path = PagePath::request($path);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException('The requested page was not found.');
        }

        $staticQuery = PageModelRegistry::query()
            ->where('site', $site)
            ->where('kind', PageKind::Static)
            ->where('path_hash', PagePath::hash($site, strtolower($path)));

        if ($publicOnly) {
            $staticQuery->publiclyVisible();
        }

        $static = $staticQuery->with('translations')->first();

        if ($static instanceof Page && hash_equals($static->path, $path)) {
            return ['page' => $static, 'resource' => null];
        }

        $resourceQuery = PageModelRegistry::query()
            ->where('site', $site)
            ->where('kind', PageKind::Resource)
            ->whereIn('path_hash', $this->candidateHashes($site, $path))
            ->with('translations');

        if ($publicOnly) {
            $resourceQuery->publiclyVisible();
        }

        $pages = $resourceQuery
            ->get()
            ->sortByDesc(static fn (Page $page): int => strlen($page->path));

        foreach ($pages as $page) {
            if ($page->resource === null || ! $this->resources->has($page->resource)) {
                continue;
            }

            $handler = $this->resources->get($page->resource);
            $parameters = $this->match(
                $page->path.'/'.trim($handler->routePattern(), '/'),
                $path,
            );

            if ($parameters === null) {
                continue;
            }

            $rules = $handler->rules($page);
            $this->assertRuleParity($handler->routePattern(), $rules, $page->resource);

            try {
                $this->validation
                    ->make($parameters, $rules)
                    ->validate();
            } catch (ValidationException) {
                continue;
            }
            $request = ['pageId' => $page->id, 'site' => $site, 'locale' => $locale, 'parameters' => $parameters];
            $handlerQuery = $handler->query($request);
            $this->resources->assertOwner($page->resource, $handlerQuery->getModel());
            $tenantResource = null;
            if (PagesOwnership::declared()) {
                if (! $handler instanceof OwnershipSafePageResourceHandler
                    || $handlerQuery->getModel()::class !== $handler->ownershipResourceModel()) {
                    throw new InvalidArgumentException('The Page resource query differs from its declared ownership capability.');
                }
                $tenantResource = $this->ownershipResources->forModel($handlerQuery->getModel());
                $this->tenancy->query($handlerQuery, $tenantResource->key);
            }
            $resource = $handler->fetch($handlerQuery, $request);

            if (! $resource instanceof Model) {
                throw new NotFoundHttpException('The dynamic page resource was not found.');
            }
            $this->resources->assertOwner($page->resource, $resource);

            if ($tenantResource !== null) {
                $this->tenancy->assertRecord($resource, $tenantResource->key);
            }

            $presented = $handler->present($resource, $request);
            if (! is_string($presented['type'] ?? null) || ! is_string($presented['id'] ?? null)
                || ! is_array($presented['payload'] ?? null) || array_diff(array_keys($presented), ['type', 'id', 'payload']) !== []) {
                throw new InvalidArgumentException('Page resource handlers must return the declared type, id and payload record.');
            }

            return ['page' => $page, 'resource' => $presented];
        }

        throw new NotFoundHttpException('The requested page was not found.');
    }

    /**
     * Return hashes for every possible structural page prefix in the request path.
     *
     * @return list<string>
     */
    private function candidateHashes(string $site, string $path): array
    {
        $segments = explode('/', strtolower($path));
        $maximum = min(count($segments), PagesConfiguration::maximumDepth());
        $hashes = [];

        for ($depth = 1; $depth <= $maximum; $depth++) {
            $hashes[] = PagePath::hash(
                $site,
                implode('/', array_slice($segments, 0, $depth)),
            );
        }

        return $hashes;
    }

    /**
     * Ensure every route parameter has one exact handler validation rule.
     *
     * @param  array<string, mixed>  $rules
     */
    private function assertRuleParity(string $pattern, array $rules, string $alias): void
    {
        preg_match_all('/\\{([a-z][a-zA-Z0-9_]*)\\}/', $pattern, $matches);
        $parameters = array_values(array_filter($matches[1], 'is_string'));
        $ruleKeys = array_keys($rules);
        sort($parameters);
        sort($ruleKeys);

        if ($parameters !== $ruleKeys) {
            throw new InvalidArgumentException(
                "Page resource [{$alias}] rules must exactly match its route parameters.",
            );
        }
    }

    /**
     * @return array<string, string>|null
     */
    private function match(string $pattern, string $path): ?array
    {
        $names = [];
        $segments = [];

        foreach (explode('/', $pattern) as $segment) {
            if (preg_match('/^\\{([a-z][a-zA-Z0-9_]*)\\}$/D', $segment, $matches) === 1) {
                $names[] = $matches[1];
                $segments[] = '([^/]+)';
            } else {
                $segments[] = preg_quote($segment, '#');
            }
        }

        if (preg_match('#^'.implode('/', $segments).'$#D', $path, $matches) !== 1) {
            return null;
        }

        array_shift($matches);
        $parameters = [];

        foreach ($names as $index => $name) {
            $parameters[$name] = rawurldecode((string) ($matches[$index] ?? ''));
        }

        return $parameters;
    }
}
