<?php

declare(strict_types=1);

namespace Nvl\Pages\Definitions\Tables;

use Nvl\Support\Config\PackageStorage;

/**
 * Defines the canonical table names owned by the Pages package.
 */
final class PagesTables
{
    public const string Pages = 'nvl_pages_pages';

    public const string I18n = 'nvl_pages_i18n';

    public const string TreeLocks = 'nvl_pages_tree_locks';

    /** Return one configured logical or historical package table. */
    public static function get(string $key): string
    {
        return PackageStorage::resolveTable('pages', $key);
    }

    private function __construct() {}
}
