<?php

declare(strict_types=1);

namespace Nvl\Pages\Contracts;

use Illuminate\Database\Eloquent\Model;

/** Declares the exact native model scoped by Pages before a dynamic query executes. @api
 * @template TResource of Model
 *
 * @extends PageResourceHandler<TResource>
 */
interface OwnershipSafePageResourceHandler extends PageResourceHandler
{
    /** @return class-string<TResource> */
    public function ownershipResourceModel(): string;
}
