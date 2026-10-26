<?php

declare(strict_types=1);
use Nvl\Pages\Definitions\Tables\PagesTables;
use Nvl\Pages\Models\Page;
use Nvl\Pages\Services\ConfiguredPageAuthorization;

/** Common published defaults; advanced defaults remain in the package configuration. */
return [
    'models' => ['page' => Page::class],
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
];
