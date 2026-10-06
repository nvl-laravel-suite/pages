<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Nvl\Pages\Definitions\Tables\PagesTables;
use Nvl\Pages\Support\PagesConfiguration;
use Nvl\Support\Config\PackageStorage;

return new class extends Migration
{
    /** Use the effective package connection for Laravel's migration transaction. */
    public function getConnection(): ?string
    {
        return PackageStorage::connection('pages');
    }

    public function up(): void
    {
        $schema = Schema::connection(PagesConfiguration::connection());
        foreach ([PagesTables::get(PagesTables::Pages), PagesTables::get(PagesTables::I18n), PagesTables::get(PagesTables::TreeLocks)] as $name) {
            $table = PagesConfiguration::table($name, $name);
            $schema->table($table, static fn (Blueprint $blueprint) => $blueprint->uuid('tenant_id')->nullable(false)->change());
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(PagesConfiguration::connection());
        foreach ([PagesTables::get(PagesTables::Pages), PagesTables::get(PagesTables::I18n), PagesTables::get(PagesTables::TreeLocks)] as $name) {
            $table = PagesConfiguration::table($name, $name);
            $schema->table($table, static fn (Blueprint $blueprint) => $blueprint->uuid('tenant_id')->nullable()->change());
        }
    }
};
