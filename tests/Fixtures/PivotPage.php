<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures;

use Nvl\Support\Ownership\ValueObjects\OwnershipDefinition;

/** A consumer's exclusive native primary-pivot declaration. */
final class PivotPage extends OwnershipPage
{
    public static function ownershipDefinition(): OwnershipDefinition
    {
        return OwnershipDefinition::pivot(OwnershipCompany::class, 'pages_owned_primary', 'page_id', 'company_id');
    }
}
