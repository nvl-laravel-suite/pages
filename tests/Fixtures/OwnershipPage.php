<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures;

use Nvl\Pages\Models\Page;
use Nvl\Support\Ownership\ValueObjects\OwnershipDefinition;

/** Native host Page extension keeps package storage/morph identity and adds one primary owner. */
class OwnershipPage extends Page
{
    public static function ownershipDefinition(): OwnershipDefinition
    {
        return OwnershipDefinition::column(OwnershipCompany::class, 'company_id');
    }
}
