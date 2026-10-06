<?php

declare(strict_types=1);

namespace Nvl\Pages\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * @api

 * Raised when a validated transport payload violates a page domain invariant.
 */
final class InvalidPageMutationException extends PagesException implements ShouldntReport {}
