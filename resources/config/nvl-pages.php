<?php

declare(strict_types=1);
use Nvl\Pages\Definitions\Tables\PagesTables;
use Nvl\Pages\Services\ConfiguredPageAuthorization;

return [
    'connection' => null,
    'tables' => ['pages' => PagesTables::Pages, 'pages_i18n' => PagesTables::I18n, 'page_tree_locks' => PagesTables::TreeLocks],
    'migrations' => ['enabled' => true],
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
    'authorization' => ['class' => ConfiguredPageAuthorization::class],
    'routes' => ['public' => ['enabled' => false, 'prefix' => 'nvl/api/v1/pages', 'name' => 'nvl.pages.public.', 'middleware' => ['api', 'throttle:120,1']], 'management' => ['enabled' => false, 'prefix' => 'nvl/api/v1/pages/_manage', 'name' => 'nvl.pages.management.', 'middleware' => ['api', 'auth', 'throttle:60,1']]],
];
