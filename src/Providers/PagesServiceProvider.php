<?php

declare(strict_types=1);

namespace Nvl\Pages\Providers;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentOwnerRegistrar;
use Nvl\Content\Models\ContentPlacement;
use Nvl\Content\Providers\ContentServiceProvider;
use Nvl\Content\Relations\StringMorphMany as ContentMorphMany;
use Nvl\Metafields\Models\Metafield;
use Nvl\Metafields\Providers\MetafieldsServiceProvider;
use Nvl\Metafields\Relations\StringMorphMany;
use Nvl\Metafields\Support\MetafieldOwnerRegistry;
use Nvl\Ownership\Services\OwnershipAdoptionRegistry;
use Nvl\Pages\Console\Commands\PagesDoctorCommand;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Contracts\PageResourceHandler;
use Nvl\Pages\Contracts\PageUrlGenerator;
use Nvl\Pages\Events\PageChanged;
use Nvl\Pages\Listeners\InvalidatePageSitemap;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Ownership\PagesAdoptionAdapter;
use Nvl\Pages\Ownership\PagesResourceRegistrar;
use Nvl\Pages\Seo\PageSitemapSource;
use Nvl\Pages\Services\ConfiguredPageAuthorization;
use Nvl\Pages\Services\ConfiguredPageUrlGenerator;
use Nvl\Pages\Services\PageModelRegistry;
use Nvl\Pages\Services\PageResourceRegistry;
use Nvl\Pages\Services\PagesDoctor;
use Nvl\Pages\Support\PagesConfiguration;
use Nvl\Pages\Support\PagesMigrationRollbackGuard;
use Nvl\Seo\Providers\SeoServiceProvider;
use Nvl\Seo\Relations\StringMorphMany as SeoMorphMany;
use Nvl\Seo\Services\SeoModelRegistry;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Seo\Services\SitemapRegistry;
use Nvl\Support\Doctor\PackageDoctorContributor;
use Nvl\Support\Globals\GlobalNames;
use Nvl\Support\Integrations\OptionalIntegration;
use Nvl\Support\OwnerRegistry;
use Nvl\Support\Ownership\Contracts\OwnershipIntegratedProvider;
use Nvl\Support\Ownership\Services\OwnershipResourceRegistry;
use Nvl\Support\Providers\SupportServiceProvider;
use Nvl\Support\Traits\MergesPackageConfiguration;
use Nvl\Support\Traits\RegistersNamespacedResources;
use Nvl\Translatable\Services\TranslationResourceRegistry;

/** Registers plain Pages and lazily selected existing package adapters. */
final class PagesServiceProvider extends ServiceProvider implements OwnershipIntegratedProvider
{
    use MergesPackageConfiguration;
    use RegistersNamespacedResources;

    public function register(): void
    {
        $this->app->register(SupportServiceProvider::class);
        $this->mergePackageConfiguration(__DIR__.'/../../config/nvl-pages.php', 'pages');
        PackageDoctorContributor::register($this->app, 'nvl/pages', fn (): array => PackageDoctorContributor::reportChecks($this->app->make(PagesDoctor::class)->inspect(), 'nvl:pages:doctor'));
        $this->app->singleton(PageModelRegistry::class);
        $this->app->booting(fn () => $this->app->make(PageModelRegistry::class)->prepare());
        foreach ([PageAuthorization::class => config('nvl-pages.authorization.class', ConfiguredPageAuthorization::class),
            PageUrlGenerator::class => config('nvl-pages.urls.generator', ConfiguredPageUrlGenerator::class)] as $contract => $class) {
            if (! is_string($class) || ! is_a($class, $contract, true)) {
                throw new InvalidArgumentException('Configured Pages integrations must implement their declared contract.');
            }
            $this->app->bindIf($contract, $class);
        }
        $this->app->singleton(PageResourceRegistry::class);
        $this->app->singleton(PagesMigrationRollbackGuard::class);
    }

    public function boot(TranslationResourceRegistry $translations, PageResourceRegistry $resources): void
    {
        $this->app->make(PagesResourceRegistrar::class)->register($this->app->make(OwnershipResourceRegistry::class));
        if ($this->app->bound(OwnershipAdoptionRegistry::class)) {
            $this->app->make(OwnershipAdoptionRegistry::class)->register('pages', PagesAdoptionAdapter::class);
        }
        $this->app->make(GlobalNames::class)->translations('pages', __DIR__.'/../../lang', $this->app->make('translation.loader'));
        $this->app->make(OwnerRegistry::class)->registerPackage(Page::CONTENT_OWNER_TYPE, Page::class, package: 'pages');
        $this->registerResources($resources);
        $model = $this->app->make(PageModelRegistry::class)->prepare();
        $integrations = $this->app->make(OptionalIntegration::class);
        if ($integrations->enabled('pages.integrations.content', ContentServiceProvider::class)) {
            $this->app->make(ContentOwnerRegistrar::class)->register(Page::CONTENT_OWNER_TYPE, $model, [Page::CONTENT_GROUP]);
            $model::resolveRelationUsing('contentPlacements', static function (Page $page): MorphMany {
                $related = new ContentPlacement;

                return new ContentMorphMany($related->newQuery(), $page, $related->qualifyColumn('owner_type'), $related->qualifyColumn('owner_id'), $page->getKeyName());
            });
        }
        if ($integrations->enabled('pages.integrations.seo', SeoServiceProvider::class)) {
            $this->app->make(SeoOwnerRegistry::class)->register(PagesConfiguration::alias('seo_owner_alias', 'nvl-page'), $model);
            $model::resolveRelationUsing('seoProfiles', static function (Page $page): MorphMany {
                $related = SeoModelRegistry::newProfile();

                return new SeoMorphMany($related->newQuery(), $page, $related->qualifyColumn('seoable_type'), $related->qualifyColumn('seoable_id'), $page->getKeyName());
            });
            $this->app->make(SitemapRegistry::class)->registerType(PageSitemapSource::class, 'nvl/pages', [$model]);
            Event::listen(PageChanged::class, InvalidatePageSitemap::class);
        }
        $model::resolveRelationUsing('metafields', function (Page $page): MorphMany {
            $this->app->make(OptionalIntegration::class)->enabled('pages.integrations.metafields', MetafieldsServiceProvider::class, requested: true);
            $related = new Metafield;

            return new StringMorphMany($related->newQuery(), $page, $related->qualifyColumn('metafieldable_type'), $related->qualifyColumn('metafieldable_id'), $page->getKeyName());
        });
        if ($integrations->enabled('pages.integrations.metafields', MetafieldsServiceProvider::class)) {
            $sections = config('nvl-pages.integrations.metafield_sections', ['general']);
            if (! is_array($sections) || ! array_is_list($sections) || array_filter($sections, is_string(...)) !== $sections) {
                throw new InvalidArgumentException('Page metafield sections must be a list of strings.');
            }
            $this->app->make(MetafieldOwnerRegistry::class)->register(PagesConfiguration::alias('metafield_owner_alias', 'nvl-page'), $model, 'Pages', $sections);
        }
        $translations->register(key: 'pages.pages', modelClass: $model, label: 'Pages',
            searchableColumns: ['key', 'site', 'slug', 'path', 'status', 'resource'],
            displayColumns: ['key', 'site', 'path', 'kind', 'status', 'revision'], orderColumn: 'path');
        Event::listen(MigrationStarted::class, $this->app->make(PagesMigrationRollbackGuard::class)->before(...));
        if (config('nvl-pages.migrations.enabled', true) === true) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }
        $this->commands([PagesDoctorCommand::class]);
        $this->publishes([__DIR__.'/../../lang' => lang_path('vendor/nvl-pages')], 'nvl-pages-translations');
        $this->publishes([__DIR__.'/../../config/nvl-pages.php' => config_path('nvl-pages.php')], 'nvl-pages-config');
        $this->publishesMigrations([__DIR__.'/../../database/migrations' => database_path('migrations')], 'nvl-pages-migrations');
        $this->publishes([__DIR__.'/../../resources/boost/skills' => base_path('.agents/skills')], 'nvl-pages-skills');
    }

    private function registerResources(PageResourceRegistry $registry): void
    {
        $configured = config('nvl-pages.resources', []);

        if (! is_array($configured)) {
            throw new InvalidArgumentException('pages.resources must be an alias-to-handler map.');
        }

        foreach ($configured as $alias => $handler) {
            $owner = null;

            if (is_array($handler)) {
                $owner = $handler['owner'] ?? null;
                $handler = $handler['handler'] ?? null;

                if (! is_string($owner)) {
                    throw new InvalidArgumentException('Every pages identity reference must declare an owner alias.');
                }
            }

            if (! is_string($alias)
                || ! is_string($handler)
                || ! is_a($handler, PageResourceHandler::class, true)) {
                throw new InvalidArgumentException(
                    'Every configured page resource handler is invalid.',
                );
            }

            $registry->register($alias, $handler, $owner);
        }
    }
}
