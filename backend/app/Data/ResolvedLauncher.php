<?php

namespace App\Data;

use App\Contracts\LauncherSource;

/**
 * Result of launcher resolution — the effective launcher model,
 * its prompt snapshot, and the FK values to store on the Run.
 */
readonly class ResolvedLauncher
{
    public function __construct(
        public LauncherSource $launcher,
        public string $promptSnapshot,
        /** @var int|null placeholder built-in ID for custom-launcher runs */
        public ?int $launcherId,
        /** @var string|null custom launcher UUID */
        public ?string $userLauncherId,
    ) {}
}
