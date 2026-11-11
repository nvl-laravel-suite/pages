<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures;

use Nvl\Pages\Models\Page;

/** Unsupported consumer root that replaces a required package cast. */
final class RecastPage extends Page
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...parent::casts(), 'position' => 'string'];
    }
}
