<?php

declare(strict_types=1);

use Nvl\Pages\Definitions\Tables\PagesTables;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\ConfiguredPageAuthorization;
use Nvl\Pages\Services\ConfiguredPageUrlGenerator;
use Nvl\Support\Config\PackageEnvironment;

/** Complete runtime defaults; publication sections are declared in ../resources/config/sections.json. */
return [
    'models' => ['page' => Page::class],
    'connection' => null,

    'tables' => [
        'owner_tree_locks' => PagesTables::OwnerTreeLocks,
        'pages' => PagesTables::Pages,
        'pages_i18n' => PagesTables::I18n,
        'page_tree_locks' => PagesTables::TreeLocks,
    ],

    'migrations' => [
        'enabled' => true,
    ],

    'hierarchy' => [
        'maximum_depth' => 4,
    ],

    'transactions' => [
        'attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic page resources
    |--------------------------------------------------------------------------
    |
    | Register stable aliases rather than accepting handler class names from
    | requests. Each handler owns its query, conditions, route parameters,
    | transport-safe presentation, and optional sitemap stream.
    |
    */
    'resources' => [],

    'authorization' => [
        'class' => ConfiguredPageAuthorization::class,
    ],

    'urls' => [
        'generator' => ConfiguredPageUrlGenerator::class,
        'base_url' => PackageEnvironment::get('NVL_PAGES_BASE_URL', env('APP_URL', 'http://localhost')),
        'locale_prefix' => false,
        'default_locale' => env('APP_LOCALE', 'en'),
    ],

    'integrations' => [
        'content' => null,
        'seo' => null,
        'metafields' => null,
        'seo_owner_alias' => 'nvl-page',
        'metafield_owner_alias' => 'nvl-page',
        'metafield_sections' => ['general', 'navigation'],
    ],

];
