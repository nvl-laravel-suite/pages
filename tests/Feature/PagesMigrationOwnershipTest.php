<?php

declare(strict_types=1);

use Illuminate\Database\Events\MigrationStarted;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Nvl\Pages\Support\PagesMigrationRollbackGuard;

it('does not probe Pages storage for an unclaimed identical host migration', function (): void {
    $path = sys_get_temp_dir().'/nvl-pages-unclaimed-'.bin2hex(random_bytes(8)).'.php';
    copy(__DIR__.'/../../database/migrations/2026_07_28_100001_nvl_pages_create_pages_table.php', $path);
    try {
        $migration = require $path;
        $queries = [];
        DB::listen(function (QueryExecuted $event) use (&$queries): void {
            $queries[] = $event->sql;
        });
        app(PagesMigrationRollbackGuard::class)->before(new MigrationStarted($migration, 'down'));
        expect($queries)->toBe([]);
    } finally {
        unlink($path);
    }
});
