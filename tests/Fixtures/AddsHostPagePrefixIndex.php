<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Nvl\Pages\Definitions\Tables\PagesTables;
use Nvl\Pages\Services\PageModelRegistry;

/** Add a legitimate host lookup index before the real adoption coordinator runs. */
trait AddsHostPagePrefixIndex // @phpstan-ignore trait.unused (Pest composes this trait with uses() in Ownership/PrefixIndexTest.php.)
{
    protected function prepareOwnershipSchema(): void
    {
        parent::prepareOwnershipSchema();
        $connection = PageModelRegistry::newInstance()->getConnection();
        if ($connection->getDriverName() === 'mysql') {
            $connection->getSchemaBuilder()->table(PagesTables::get(PagesTables::I18n), static fn (Blueprint $table) => $table->rawIndex('page_id(8)', 'pages_host_prefix_lookup'));
        }
    }
}
