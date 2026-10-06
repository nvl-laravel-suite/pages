<?php

declare(strict_types=1);

namespace Nvl\Pages\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * @api

 * Raised for invalid parent, cycle, cross-site, or depth operations.
 */
final class PageHierarchyException extends PagesException implements ShouldntReport {}
