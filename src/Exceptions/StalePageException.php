<?php

declare(strict_types=1);

namespace Nvl\Pages\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * @api

 * Raised when a mutation targets an obsolete page revision.
 */
final class StalePageException extends PagesException implements ShouldntReport
{
    /**
     * Create a stale-write exception for one page.
     */
    public static function forPage(string $pageId): self
    {
        return new self("Page [{$pageId}] changed after it was read.");
    }
}
