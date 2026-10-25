<?php

declare(strict_types=1);

namespace Nvl\Pages\Enums;

/**
 * Allowlisted ordering modes for bounded public child-page reads.
 *
 * @api
 */
enum PublicChildPageOrder: string
{
    case Sibling = 'sibling';
    case Newest = 'newest';
}
