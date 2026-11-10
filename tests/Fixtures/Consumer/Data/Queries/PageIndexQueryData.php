<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data\Queries;

use Illuminate\Support\Facades\Validator;

/**
 * Validated site and pagination boundary for page management listings.
 */
final class PageIndexQueryData
{
    /**
     * Create a bounded page management query.
     */
    public function __construct(
        public readonly string $site,
        public readonly int $perPage = 25,
    ) {}

    /**
     * Return page management query validation rules.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'site' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    /**
     * Validate native HTTP input and map it to this consumer fixture.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        if (! array_key_exists('per_page', $input) && array_key_exists('perPage', $input)) {
            $input['per_page'] = $input['perPage'];
        }

        $validated = Validator::make($input, self::rules())->safe();

        return new self(
            site: $validated->string('site')->toString(),
            perPage: $validated->integer('per_page', 25),
        );
    }
}
