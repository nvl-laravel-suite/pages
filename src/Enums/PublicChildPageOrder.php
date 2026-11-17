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
    /** Publication timestamp first, newest first; undated pages follow, newest created first. */
    case Newest = 'newest';
}
