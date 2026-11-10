<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations;

use Illuminate\Support\Facades\Validator;

/**
 * Reparents and reorders one page with optimistic concurrency.
 *
 * @api
 */
final class MovePageData
{
    /**
     * Create one validated page move payload.
     */
    public function __construct(
        public readonly ?string $parentId,
        public readonly int $position,
        public readonly int $expectedRevision,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'parentId' => ['nullable', 'uuid'],
            'position' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Validate native HTTP input and map it to this consumer fixture.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        if (! array_key_exists('expected_revision', $input) && array_key_exists('expectedRevision', $input)) {
            $input['expected_revision'] = $input['expectedRevision'];
        }

        $validated = Validator::make($input, self::rules())->safe();

        return new self(
            parentId: $validated->input('parentId') === null ? null : $validated->string('parentId')->toString(),
            position: $validated->integer('position'),
            expectedRevision: $validated->integer('expected_revision'),
        );
    }
}
