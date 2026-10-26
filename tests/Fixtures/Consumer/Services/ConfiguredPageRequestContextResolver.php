<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Nvl\Pages\Ownership\PagesOwnership;
use Nvl\Pages\Tests\Fixtures\Consumer\Contracts\PageRequestContextResolver;
use Nvl\Pages\Tests\Fixtures\Consumer\Data\PageRequestContextData;
use Nvl\Support\Contracts\LocaleCatalog;
use Nvl\Support\Ownership\Services\OwnerSiteContext;
use Nvl\Translatable\Exceptions\InvalidLocaleException;

/**
 * Resolves one configured site and a validated supported content locale.
 */
final readonly class ConfiguredPageRequestContextResolver implements PageRequestContextResolver
{
    /**
     * Create the configured public request context resolver.
     */
    public function __construct(
        private LocaleCatalog $locales,
        private Repository $configuration,
    ) {}

    /**
     * Resolve the configured public site and a supported request locale.
     */
    public function resolve(Request $request): PageRequestContextData
    {
        $ownerSite = PagesOwnership::declared() ? OwnerSiteContext::require() : null;
        $site = $ownerSite !== null ? $ownerSite->site : $this->configuration->get('nvl-pages.public.default_site', 'default');

        if (! is_string($site)
            || preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/D', $site) !== 1) {
            throw new InvalidArgumentException(
                'pages.public.default_site must be a valid page site.',
            );
        }

        $requestedLocale = $request->query('locale', $this->locales->default());
        try {
            $locale = $this->locales->assertSupported(
                is_string($requestedLocale) ? $requestedLocale : '',
            );
        } catch (InvalidLocaleException) {
            throw ValidationException::withMessages([
                'locale' => ['The selected locale is not supported.'],
            ]);
        }

        return new PageRequestContextData($site, $locale, $ownerSite);
    }
}
