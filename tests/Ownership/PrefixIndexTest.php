<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Ownership;

use Nvl\Pages\Definitions\Tables\PagesTables;
use Nvl\Pages\Services\PageLifecycle;
use Nvl\Pages\Services\PageModelRegistry;
use Nvl\Pages\Tests\Fixtures\AddsHostPagePrefixIndex;
use Nvl\Support\Enums\SystemActor;

uses(AddsHostPagePrefixIndex::class);

it('keeps complete foreign key support when a host prefix lookup also exists', function (): void {
    $connection = PageModelRegistry::newInstance()->getConnection();
    if ($connection->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL CHAR UUID prefix fixture; native MariaDB UUID columns do not support prefix lengths.');
    }
    $this->owned(7, function (): void {
        $page = app(PageLifecycle::class)->create(['key' => 'prefix-safe', 'slug' => 'prefix-safe', 'translations' => ['en' => ['title' => 'Prefix safe']]], SystemActor::Background);
        expect($page->translations->first()?->page_id)->toBe($page->id);
    });
    $table = $connection->getTablePrefix().PagesTables::get(PagesTables::I18n);
    $prefix = $connection->selectOne('SELECT SUB_PART AS prefix_length FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?', [$table, 'pages_host_prefix_lookup'], false);
    $complete = $connection->selectOne('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND SEQ_IN_INDEX = 1 AND SUB_PART IS NULL AND NON_UNIQUE = 1 AND INDEX_TYPE = ? LIMIT 1', [$table, 'page_id', 'BTREE'], false);

    expect((int) $prefix?->prefix_length)->toBe(8)->and($complete)->not->toBeNull();
});
