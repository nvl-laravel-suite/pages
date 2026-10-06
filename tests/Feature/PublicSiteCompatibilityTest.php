<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Nvl\Pages\Services\ConfiguredPageRequestContextResolver;
use Nvl\Support\Tenancy\Services\TenantSiteAttributes;
use Nvl\Support\Tenancy\ValueObjects\TenantId;
use Nvl\Support\Tenancy\ValueObjects\TenantSiteContext;

it('retains legacy verified Page sites while canonical presence wins conflicts', function (): void {
    config()->set('tenancy.enabled', true);
    $request = Request::create('https://legacy.pages.test');
    $legacy = new TenantSiteContext(new TenantId('10000000-0000-4000-8000-000000000001'), 'legacy', 'https://legacy.pages.test');
    $canonical = new TenantSiteContext($legacy->tenantId, 'canonical', 'https://canonical.pages.test');
    $request->attributes->set(TenantSiteAttributes::LegacyKey, $legacy);
    $resolver = app(ConfiguredPageRequestContextResolver::class);

    expect($resolver->resolve($request)->tenantSite)->toBe($legacy)
        ->and($resolver->resolve($request)->site)->toBe('legacy');
    $request->attributes->set(TenantSiteContext::class, $canonical);
    expect($resolver->resolve($request)->tenantSite)->toBe($canonical)
        ->and($resolver->resolve($request)->site)->toBe('canonical');
    $request->attributes->set(TenantSiteContext::class, null);
    expect(fn () => $resolver->resolve($request))->toThrow(InvalidArgumentException::class, 'A verified public tenant site is required');
});
