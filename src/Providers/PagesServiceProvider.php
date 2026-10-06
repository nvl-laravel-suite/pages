<?php

declare(strict_types=1);

namespace Nvl\Pages\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Nvl\Content\Contracts\ContentOwnerRegistrar;
use Nvl\Data\Services\TypeScriptSourceRegistry;
use Nvl\Metafields\Providers\MetafieldsServiceProvider;
use Nvl\Metafields\Support\MetafieldOwnerRegistry;
use Nvl\Pages\Actions\CheckPageKeyAvailabilityAction;
use Nvl\Pages\Actions\CreatePageAction;
use Nvl\Pages\Actions\DeletePageAction;
use Nvl\Pages\Actions\FindPageByKeyAction;
use Nvl\Pages\Actions\GetNavigationAction;
use Nvl\Pages\Actions\GetPageAction;
use Nvl\Pages\Actions\GetPageEditorBootstrapAction;
use Nvl\Pages\Actions\GetPagePublicationProjectionAction;
use Nvl\Pages\Actions\ListPageEditorSummariesAction;
use Nvl\Pages\Actions\ListPageOptionsAction;
use Nvl\Pages\Actions\ListPagesAction;
use Nvl\Pages\Actions\ListPublicChildPagesAction;
use Nvl\Pages\Actions\MovePageAction;
use Nvl\Pages\Actions\PreviewPageAction;
use Nvl\Pages\Actions\ResolvePageAction;
use Nvl\Pages\Actions\RestorePageAction;
use Nvl\Pages\Actions\UpdatePageAction;
use Nvl\Pages\Console\PagesDoctorCommand;
use Nvl\Pages\Contracts\CheckPageKeyAvailabilityContract;
use Nvl\Pages\Contracts\CreatePageContract;
use Nvl\Pages\Contracts\DeletePageContract;
use Nvl\Pages\Contracts\FindPageByKeyContract;
use Nvl\Pages\Contracts\GetNavigationContract;
use Nvl\Pages\Contracts\GetPageContract;
use Nvl\Pages\Contracts\GetPageEditorBootstrapContract;
use Nvl\Pages\Contracts\GetPagePublicationProjectionContract;
use Nvl\Pages\Contracts\ListPageEditorSummariesContract;
use Nvl\Pages\Contracts\ListPageOptionsContract;
use Nvl\Pages\Contracts\ListPagesContract;
use Nvl\Pages\Contracts\ListPublicChildPagesContract;
use Nvl\Pages\Contracts\MovePageContract;
use Nvl\Pages\Contracts\PageAuthorization;
use Nvl\Pages\Contracts\PageMetafields;
use Nvl\Pages\Contracts\PageRequestContextResolver;
use Nvl\Pages\Contracts\PageResourceHandler;
use Nvl\Pages\Contracts\PageUrlGenerator;
use Nvl\Pages\Contracts\PreviewPageContract;
use Nvl\Pages\Contracts\ResolvePageContract;
use Nvl\Pages\Contracts\RestorePageContract;
use Nvl\Pages\Contracts\UpdatePageContract;
use Nvl\Pages\Events\PageChanged;
use Nvl\Pages\Integrations\EmptyPageMetafields;
use Nvl\Pages\Integrations\MetafieldsPageAdapter;
use Nvl\Pages\Listeners\InvalidatePageSitemap;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Seo\PageSitemapSource;
use Nvl\Pages\Services\ConfiguredPageAuthorization;
use Nvl\Pages\Services\ConfiguredPageRequestContextResolver;
use Nvl\Pages\Services\ConfiguredPageUrlGenerator;
use Nvl\Pages\Services\PageResourceRegistry;
use Nvl\Pages\Services\PagesDoctor;
use Nvl\Pages\Support\PagesConfiguration;
use Nvl\Pages\Support\PagesMigrationRollbackGuard;
use Nvl\Pages\Tenancy\PagesResourceRegistrar;
use Nvl\Seo\Services\SeoOwnerRegistry;
use Nvl\Seo\Services\SitemapRegistry;
use Nvl\Support\Doctor\PackageDoctorContributor;
use Nvl\Support\Globals\GlobalNames;
use Nvl\Support\Integrations\OptionalIntegration;
use Nvl\Support\OwnerRegistry;
use Nvl\Support\Providers\SupportServiceProvider;
use Nvl\Support\Providers\TenantServiceProvider;
use Nvl\Support\Tenancy\Services\TenantResourceRegistry;
use Nvl\Support\Traits\MergesPackageConfiguration;
use Nvl\Support\Traits\RegistersNamespacedResources;
use Nvl\Tenancy\Services\TenantAdoptionRegistry;
use Nvl\Translatable\Services\TranslationResourceRegistry;

/**
 * Registers the standalone Pages runtime and its package integrations.
 */
final class PagesServiceProvider extends ServiceProvider
{
    use MergesPackageConfiguration;
    use RegistersNamespacedResources;

    /**
     * Register validated Pages contracts and singleton registries.
     */
    public function register(): void
    {
        $this->app->bindIf(CheckPageKeyAvailabilityContract::class, CheckPageKeyAvailabilityAction::class);
        $this->app->bindIf(CreatePageContract::class, CreatePageAction::class);
        $this->app->bindIf(DeletePageContract::class, DeletePageAction::class);
        $this->app->bindIf(FindPageByKeyContract::class, FindPageByKeyAction::class);
        $this->app->bindIf(GetNavigationContract::class, GetNavigationAction::class);
        $this->app->bindIf(GetPageContract::class, GetPageAction::class);
        $this->app->bindIf(GetPageEditorBootstrapContract::class, GetPageEditorBootstrapAction::class);
        $this->app->bindIf(GetPagePublicationProjectionContract::class, GetPagePublicationProjectionAction::class);
        $this->app->bindIf(ListPageEditorSummariesContract::class, ListPageEditorSummariesAction::class);
        $this->app->bindIf(ListPageOptionsContract::class, ListPageOptionsAction::class);
        $this->app->bindIf(ListPagesContract::class, ListPagesAction::class);
        $this->app->bindIf(ListPublicChildPagesContract::class, ListPublicChildPagesAction::class);
        $this->app->bindIf(MovePageContract::class, MovePageAction::class);
        $this->app->bindIf(PreviewPageContract::class, PreviewPageAction::class);
        $this->app->bindIf(ResolvePageContract::class, ResolvePageAction::class);
        $this->app->bindIf(RestorePageContract::class, RestorePageAction::class);
        $this->app->bindIf(UpdatePageContract::class, UpdatePageAction::class);

        $this->app->register(SupportServiceProvider::class);
        PackageDoctorContributor::register($this->app, 'nvl/pages', fn (): array => PackageDoctorContributor::reportChecks($this->app->make(PagesDoctor::class)->inspect(), 'nvl:pages:doctor'));

        $this->mergePackageConfiguration(__DIR__.'/../../config/nvl-pages.php', 'pages');
        $this->app->register(TenantServiceProvider::class);
        (new PagesResourceRegistrar)->register($this->app->make(TenantResourceRegistry::class));
        $this->app->booted(function (): void {
            if ($this->app->bound(TenantAdoptionRegistry::class)) {
                (new PagesResourceRegistrar)->register($this->app->make(TenantResourceRegistry::class), $this->app->make(TenantAdoptionRegistry::class));
            }
        });
        $authorization = config(
            'nvl-pages.authorization.class',
            ConfiguredPageAuthorization::class,
        );
        $urlGenerator = config(
            'nvl-pages.urls.generator',
            ConfiguredPageUrlGenerator::class,
        );
        $contextResolver = config(
            'nvl-pages.public.context_resolver',
            ConfiguredPageRequestContextResolver::class,
        );

        if (! is_string($authorization)
            || ! is_a($authorization, PageAuthorization::class, true)) {
            throw new InvalidArgumentException(
                'pages.authorization.class must implement PageAuthorization.',
            );
        }

        if (! is_string($urlGenerator)
            || ! is_a($urlGenerator, PageUrlGenerator::class, true)) {
            throw new InvalidArgumentException(
                'pages.urls.generator must implement PageUrlGenerator.',
            );
        }

        if (! is_string($contextResolver)
            || ! is_a($contextResolver, PageRequestContextResolver::class, true)) {
            throw new InvalidArgumentException(
                'pages.public.context_resolver must implement PageRequestContextResolver.',
            );
        }

        $this->app->bindIf(PageMetafields::class, static function (Application $app): PageMetafields {
            $enabled = $app->make(OptionalIntegration::class)->enabled('pages.integrations.metafields', MetafieldsServiceProvider::class);

            return $app->make($enabled ? MetafieldsPageAdapter::class : EmptyPageMetafields::class);
        });
        $this->app->booting(function (): void {
            if ($this->app->make(OptionalIntegration::class)->enabled('pages.integrations.metafields', MetafieldsServiceProvider::class)) {
                $this->app->make(TenantResourceRegistry::class)->requireCompatible('pages', 'metafields');
            }
        });
        $this->app->bindIf(PageAuthorization::class, $authorization);
        $this->app->bindIf(PageRequestContextResolver::class, $contextResolver);
        $this->app->bindIf(PageUrlGenerator::class, $urlGenerator);
        $this->app->singleton(PageResourceRegistry::class);
        $this->app->singleton(PagesMigrationRollbackGuard::class);
    }

    /**
     * Boot package integrations, resources, migrations, routes, and commands.
     */
    public function boot(
        TypeScriptSourceRegistry $typeScriptSources,
        TranslationResourceRegistry $translationResources,
        ContentOwnerRegistrar $contentOwners,
        SitemapRegistry $sitemaps,
        PageResourceRegistry $resources,
        SeoOwnerRegistry $seoOwners,
    ): void {
        $this->app->make(GlobalNames::class)->translations('pages', __DIR__.'/../../lang', $this->app->make('translation.loader'));
        $this->publishes([
            __DIR__.'/../../lang' => lang_path('vendor/nvl-pages'),
        ], 'nvl-pages-translations');
        $this->app->make(OwnerRegistry::class)->registerPackage(Page::CONTENT_OWNER_TYPE, Page::class, ['page']);
        $migrationRollbackGuard = $this->app->make(PagesMigrationRollbackGuard::class);
        $typeScriptSources->register(__DIR__.'/..', 'nvl/pages');
        $this->registerResources($resources);
        Page::resolveRelationUsing('metafields', function (Page $page): MorphMany {
            $this->app->make(OptionalIntegration::class)->enabled('pages.integrations.metafields', MetafieldsServiceProvider::class, requested: true);

            return MetafieldsPageAdapter::relation($page);
        });
        $seoOwners->register(PagesConfiguration::alias('seo_owner_alias', 'nvl-page'), Page::class);
        if ($this->app->make(OptionalIntegration::class)->enabled('pages.integrations.metafields', MetafieldsServiceProvider::class)) {
            $sections = config('nvl-pages.integrations.metafield_sections', ['general']);
            $this->app->make(MetafieldOwnerRegistry::class)->register(
                PagesConfiguration::alias('metafield_owner_alias', 'nvl-page'), Page::class, 'Pages',
                is_array($sections) ? array_values(array_filter($sections, 'is_string')) : ['general'],
            );
        }
        $contentAlias = Page::CONTENT_OWNER_TYPE;

        $registeredContentOwner = $contentOwners->registered($contentAlias);

        if ($registeredContentOwner === null) {
            $contentOwners->register($contentAlias, Page::class);
        } elseif ($registeredContentOwner !== Page::class) {
            throw new InvalidArgumentException(
                "Content owner alias [{$contentAlias}] must resolve to Page.",
            );
        }

        $translationResources->register(
            key: 'pages.pages',
            modelClass: Page::class,
            label: 'Pages',
            searchableColumns: ['key', 'site', 'slug', 'path', 'status', 'resource'],
            displayColumns: ['key', 'site', 'path', 'kind', 'status', 'revision'],
            orderColumn: 'path',
        );
        $sitemaps->registerType(PageSitemapSource::class, 'nvl/pages', [Page::class]);
        Event::listen(PageChanged::class, InvalidatePageSitemap::class);
        Event::listen(MigrationStarted::class, $migrationRollbackGuard->before(...));

        if ((bool) config('nvl-pages.migrations.enabled', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([PagesDoctorCommand::class]);
        }

        $this->publishes([
            __DIR__.'/../../config/nvl-pages.php' => config_path('nvl-pages.php'),
        ], 'pages-config');
        $this->publishesMigrations([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'pages-migrations');
        $this->publishes([
            __DIR__.'/../../resources/boost/skills' => base_path('.agents/skills'),
        ], 'pages-skills');
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
