<?php

declare(strict_types=1);

namespace Nvl\Pages\Exceptions;

use Nvl\Pages\Enums\PagesResponseCode;
use Nvl\Support\Contracts\RespondableException;
use Nvl\Support\Exceptions\ExceptionResponse;
use Nvl\Support\Traits\InteractsWithPackageFailure;
use RuntimeException;

/**
 * @api
 * Base package exception for invalid page structures or resolution.
 */
class PagesException extends RuntimeException implements RespondableException
{
    use InteractsWithPackageFailure;

    /** Resolve the declared safe failure for this native hierarchy. */
    protected function exceptionResponse(): ExceptionResponse
    {
        return match (static::class) {
            InvalidPageMutationException::class => new ExceptionResponse('pages', PagesResponseCode::InvalidPageMutation, 422),
            PageConflictException::class => new ExceptionResponse('pages', PagesResponseCode::PageConflict, 409),
            PageHierarchyException::class => new ExceptionResponse('pages', PagesResponseCode::PageHierarchyConflict, 409),
            StalePageException::class => new ExceptionResponse('pages', PagesResponseCode::StalePage, 409),
            default => new ExceptionResponse('pages', PagesResponseCode::OperationFailed),
        };
    }
}
