<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data\Mutations;

use Illuminate\Support\Facades\Validator;

/**
 * Soft-delete contract protected by an exact page revision.
 *
 * @api
 */
final class DeletePageData
{
    /**
     * Create one validated page deletion payload.
     */
    public function __construct(
        public readonly int $expectedRevision,
    ) {}

    /**
     * Return the validation rules for a page deletion.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
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
            expectedRevision: $validated->integer('expected_revision'),
        );
    }
}
