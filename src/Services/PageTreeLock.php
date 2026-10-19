<?php

declare(strict_types=1);

namespace Nvl\Pages\Services;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\DB;
use Nvl\Pages\Definitions\Tables\PagesTables;
use Nvl\Pages\Ownership\PagesOwnership;
use Nvl\Pages\Support\PagesConfiguration;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Tenancy\Contracts\TenantContext;

/**
 * Serializes every structural mutation for one site through a stable database row.
 */
final readonly class PageTreeLock
{
    /** Create the tenant-aware page-tree lock boundary. */
    public function __construct(private Repository $configuration, private TenantContext $tenancy, private OwnershipBoundary $ownership) {}

    /**
     * Acquire the site tree lock for the surrounding database transaction.
     */
    public function acquire(string $site): void
    {
        $connection = DB::connection(PagesConfiguration::connection());
        $table = PagesConfiguration::table(PagesTables::get(PagesTables::TreeLocks), PagesTables::get(PagesTables::TreeLocks));

        $identity = ['site' => $site];
        if (PagesOwnership::declared()) {
            $table = PagesTables::get(PagesTables::OwnerTreeLocks);
            $identity = ['site' => hash('sha256', $this->ownership->key('pages.pages', $site))];
        }
        if ($this->configuration->get('nvl-tenancy.enabled') === true) {
            $identity = ['tenant_id' => $this->tenancy->requireTenant()->value, ...$identity];
        }
        $connection->table($table)->insertOrIgnore($identity);
        if ($connection->table($table)->where($identity)->lockForUpdate()->first() === null) {
            throw new OwnershipViolation('The canonical Page tree lock could not be acquired.');
        }
    }
}
