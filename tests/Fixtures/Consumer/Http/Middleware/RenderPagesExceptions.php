<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as LaravelResponse;
use Nvl\Support\Contracts\RespondableException;
use Symfony\Component\HttpFoundation\Response;

/** Preserves the native Pages envelope on already enabled package routes. */
final readonly class RenderPagesExceptions
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $response = $next($request);
        } catch (RespondableException $exception) {
            if ($exception->package() !== 'pages') {
                throw $exception;
            }

            return $this->response($exception);
        }

        if (($response instanceof JsonResponse || $response instanceof LaravelResponse)
            && $response->exception instanceof RespondableException
            && $response->exception->package() === 'pages') {
            return $this->response($response->exception);
        }

        return $response;
    }

    /** Reconstruct the established route envelope from safe metadata. */
    private function response(RespondableException $exception): JsonResponse
    {
        $key = $exception->translationKey();
        $message = $key === null ? null : trans($key, $exception->translationParameters());

        return new JsonResponse(['message' => is_string($message) ? $message : 'The operation could not be completed.'], $exception->suggestedStatus(), $exception->responseHeaders());
    }
}
