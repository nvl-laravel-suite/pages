<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Nvl\Pages\Providers\PagesServiceProvider;
use Nvl\Support\Providers\LocaleServiceProvider;
use Nvl\Support\Providers\SupportServiceProvider;
use Nvl\Translatable\Providers\TranslatableServiceProvider;
use Orchestra\Testbench\TestCase;

/** Boots the actual standalone Pages dependency graph. */
abstract class StandalonePagesTestCase extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [LocaleServiceProvider::class, SupportServiceProvider::class, TranslatableServiceProvider::class, PagesServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'nvl-translatable.locales' => ['en', 'bg'], 'nvl-translatable.fallback_locales' => ['en'],
            'nvl-pages.urls.base_url' => 'https://pages.test']);
    }

    protected function defineDatabaseMigrationsAfterDatabaseRefreshed(): void
    {
        Schema::create('page_test_actors', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }
}
