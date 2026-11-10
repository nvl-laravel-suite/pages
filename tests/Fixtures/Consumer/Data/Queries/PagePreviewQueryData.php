<?php

declare(strict_types=1);

namespace Nvl\Pages\Tests\Fixtures\Consumer\Data\Queries;

use Illuminate\Support\Facades\Validator;

/**
 * Validated site and locale boundary for page previews.
 */
final class PagePreviewQueryData
{
    /**
     * Create a page preview query.
     */
    public function __construct(
        public readonly string $site,
        public readonly string $locale,
    ) {}

    /**
     * Return page preview query validation rules.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'site' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
            'locale' => ['required', 'string', 'max:35'],
        ];
    }

    /**
     * Validate native HTTP input and map it to this consumer fixture.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        $validated = Validator::make($input, self::rules())->safe();

        return new self(
            site: $validated->string('site')->toString(),
            locale: $validated->string('locale')->toString(),
        );
    }
}
