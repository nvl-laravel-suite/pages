<?php

declare(strict_types=1);

namespace Nvl\Pages\Enums;

/**
 * Fine-grained capabilities enforced by every public Page action.
 *
 * @api
 */
enum PageAbility: string
{
    case List = 'list';
    case View = 'view';
    case ViewNavigation = 'view_navigation';
    case Preview = 'preview';
    case Create = 'create';
    case Update = 'update';
    case Move = 'move';
    case Publish = 'publish';
    case Archive = 'archive';
    case Delete = 'delete';
    case Restore = 'restore';
}
