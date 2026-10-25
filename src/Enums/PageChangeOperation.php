<?php

declare(strict_types=1);

namespace Nvl\Pages\Enums;

/**
 * Canonical operation names emitted by committed page-change events.
 *
 * @api
 */
enum PageChangeOperation: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Moved = 'moved';
    case Deleted = 'deleted';
    case Restored = 'restored';
}
