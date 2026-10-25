<?php

declare(strict_types=1);

namespace Nvl\Pages\Enums;

/**
 * Editorial lifecycle for a page.
 *
 * @api
 */
enum PageStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}
