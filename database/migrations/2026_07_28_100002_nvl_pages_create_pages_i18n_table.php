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
     * Create the related page translation table.
     */
    public function up(): void
    {
        $connection = config('nvl-pages.connection');
        $schema = Schema::connection(is_string($connection) ? $connection : null);
        $tableName = (string) config('nvl-pages.tables.pages_i18n', PagesTables::get(PagesTables::I18n));

        if ($schema->hasTable($tableName)) {
            throw new LogicException('Existing package table is not owned by this migration. Run nvl:doctor --strict and use nvl:schema:upgrade for a verified legacy installation.');
        }

        $schema->create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('page_id');
            $table->string('locale', 35);
            $table->string('title');
            $table->string('navigation_label')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'locale'], 'pages_i18n_owner_locale_unique');
            $table->index(['locale', 'title'], 'pages_i18n_locale_title_index');
            $table->foreign('page_id')
                ->references('id')
                ->on((string) config('nvl-pages.tables.pages', PagesTables::get(PagesTables::Pages)))
                ->cascadeOnDelete();
        });
    }

    /**
     * Drop the page translation table.
     */
    public function down(): void
    {
        $connection = config('nvl-pages.connection');
        Schema::connection(is_string($connection) ? $connection : null)
            ->dropIfExists((string) config('nvl-pages.tables.pages_i18n', PagesTables::get(PagesTables::I18n)));
    }
};
