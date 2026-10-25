<?php

declare(strict_types=1);

namespace Nvl\Pages\Enums;

/**
 * Distinguishes persisted pages from resource-backed route definitions.
 *
 * @api
 */
enum PageKind: string
{
    case Static = 'static';
    case Resource = 'resource';
}
