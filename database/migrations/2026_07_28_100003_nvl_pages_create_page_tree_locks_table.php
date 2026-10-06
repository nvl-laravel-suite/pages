<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nvl\Pages\Definitions\Tables\PagesTables;
use Nvl\Support\Config\PackageStorage;

return new class extends Migration
{
    /** Use the effective package connection for Laravel's migration transaction. */
    public function getConnection(): ?string
    {
        return PackageStorage::connection('pages');
    }

    /**
     * Create the stable per-site rows used to serialize page-tree mutations.
     */
    public function up(): void
    {
        $connection = config('nvl-pages.connection');
        $schema = Schema::connection(is_string($connection) ? $connection : null);
        $tableName = (string) config('nvl-pages.tables.page_tree_locks', PagesTables::get(PagesTables::TreeLocks));

        if ($schema->hasTable($tableName)) {
            throw new LogicException('Existing package table is not owned by this migration. Run nvl:doctor --strict and use nvl:schema:upgrade for a verified legacy installation.');
        }

        $schema->create($tableName, function (Blueprint $table): void {
            $table->string('site', 64)->primary();
        });
    }

    /**
     * Drop the page-tree serialization table.
     */
    public function down(): void
    {
        $connection = config('nvl-pages.connection');

        Schema::connection(is_string($connection) ? $connection : null)
            ->dropIfExists((string) config('nvl-pages.tables.page_tree_locks', PagesTables::get(PagesTables::TreeLocks)));
    }
};
