<?php

declare(strict_types=1);

namespace Nvl\Pages\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * @api

 * Raised when a concurrent write violates a canonical page uniqueness constraint.
 */
final class PageConflictException extends PagesException implements ShouldntReport {}
