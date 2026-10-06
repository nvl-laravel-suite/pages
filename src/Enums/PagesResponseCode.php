<?php

declare(strict_types=1);

namespace Nvl\Pages\Enums;

use Nvl\Support\Contracts\ResponseCode;

/** Stable public response discriminators for Pages.
 * @api
 */
enum PagesResponseCode: string implements ResponseCode
{
    case OperationFailed = 'operation_failed';
    case InvalidPageMutation = 'invalid_page_mutation';
    case PageConflict = 'page_conflict';
    case PageHierarchyConflict = 'page_hierarchy_conflict';
    case StalePage = 'stale_page';
}
