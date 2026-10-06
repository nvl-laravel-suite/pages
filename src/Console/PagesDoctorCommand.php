<?php

declare(strict_types=1);

namespace Nvl\Pages\Console;

use Illuminate\Console\Command;
use Nvl\Pages\Services\PagesDoctor;

/**
 * Renders the package-owned read-only installation diagnostics.
 */
final class PagesDoctorCommand extends Command
{
    protected $signature = 'nvl:pages:doctor {--strict} {--format=text}';

    /** @var string */
    protected $description = 'Inspect the NVL Pages installation without changing state';

    /**
     * Run all non-mutating Pages installation and data-integrity diagnostics.
     */
    public function handle(PagesDoctor $doctor): int
    {

        $checks = $doctor->inspect();
        $healthy = $checks['healthy'];

        if ($this->option('format') === 'json') {
            $this->line((string) json_encode($checks, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $check => $value) {
                $this->line(sprintf(
                    '%-40s %s',
                    $check,
                    json_encode($value, JSON_THROW_ON_ERROR),
                ));
            }
        }

        return $healthy || ! $this->option('strict') ? self::SUCCESS : self::FAILURE;
    }
}
