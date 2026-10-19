<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests;

use Illuminate\Database\Schema\Blueprint;
use Nvl\Ownership\Services\OwnerIdentityResolver;
use Nvl\Ownership\ValueObjects\OwnershipMapping;
use Nvl\Pages\Definitions\Tables\PagesTables;
use Nvl\Pages\Services\PageModelRegistry;
use Nvl\Pages\Support\PagePath;
use Nvl\Pages\Tests\Fixtures\OwnershipCompany;

/** Rehearses reviewed nonempty tenant-native tree and locale adoption. */
abstract class LegacyOwnershipTestCase extends OwnershipTestCase
{
    public const string ROOT = '20000000-0000-4000-8000-000000000001';

    public const string CHILD = '20000000-0000-4000-8000-000000000002';

    public const string COPY = '20000000-0000-4000-8000-000000000003';

    public const string TENANT = '20000000-0000-4000-8000-000000000007';

    protected function reviewedMappings(): array
    {
        $connection = PageModelRegistry::newInstance()->getConnection();
        $schema = $connection->getSchemaBuilder();
        $pages = PageModelRegistry::newInstance()->getTable();
        $copy = PagesTables::get(PagesTables::I18n);
        foreach ([$pages, $copy] as $table) {
            $schema->table($table, static fn (Blueprint $blueprint) => $blueprint->uuid('tenant_id'));
        }
        $schema->table($pages, static fn (Blueprint $table) => $table->unique(['tenant_id', 'id']));
        foreach ([[$pages, 'parent_id'], [$copy, 'page_id']] as [$table, $column]) {
            foreach ($schema->getForeignKeys($table) as $foreign) {
                if ($foreign['columns'] === [$column]) {
                    $schema->table($table, static fn (Blueprint $blueprint) => $blueprint->dropForeign($foreign['name'] ?? [$column]));
                }
            }
            $schema->table($table, static fn (Blueprint $blueprint) => $blueprint->foreign(['tenant_id', $column])->references(['tenant_id', 'id'])->on($pages));
        }
        $connection->table($pages)->insert(['id' => self::ROOT, 'tenant_id' => self::TENANT, 'key' => 'legacy-root', 'slug' => 'legacy', 'path' => 'legacy', 'path_hash' => PagePath::hash('default', 'legacy')]);
        $connection->table($pages)->insert(['id' => self::CHILD, 'tenant_id' => self::TENANT, 'parent_id' => self::ROOT, 'parent_key' => self::ROOT, 'key' => 'legacy-child', 'slug' => 'child', 'path' => 'legacy/child', 'path_hash' => PagePath::hash('default', 'legacy/child')]);
        $connection->table($copy)->insert(['id' => self::COPY, 'tenant_id' => self::TENANT, 'page_id' => self::CHILD, 'locale' => 'en', 'title' => 'Legacy child']);
        $owner = app(OwnerIdentityResolver::class)->reference(OwnershipCompany::findOrFail(7));

        return [new OwnershipMapping('pages.pages', self::ROOT, $owner, legacyTenantId: self::TENANT), new OwnershipMapping('pages.pages', self::CHILD, $owner, legacyTenantId: self::TENANT), new OwnershipMapping('pages.translations', self::COPY, $owner, legacyTenantId: self::TENANT)];
    }
}
