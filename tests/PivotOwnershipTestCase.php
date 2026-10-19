<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests;

use Illuminate\Database\Schema\Blueprint;
use Nvl\Pages\Tests\Fixtures\PivotPage;

/** Uses the same Actions and inherited graph with an exclusive native owner pivot. */
abstract class PivotOwnershipTestCase extends OwnershipTestCase
{
    protected function pageClass(): string
    {
        return PivotPage::class;
    }

    protected function prepareOwnershipSchema(): void
    {
        $root = new PivotPage;
        $root->getConnection()->getSchemaBuilder()->create('pages_owned_primary', static function (Blueprint $table) use ($root): void {
            $table->uuid('page_id')->primary();
            $table->unsignedBigInteger('company_id');
            $table->foreign('page_id')->references('id')->on($root->getTable())->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('content_owned_companies')->restrictOnDelete();
        });
    }
}
