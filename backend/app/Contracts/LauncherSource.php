<?php

namespace App\Contracts;

/**
 * Shared contract for any launcher that can be used to execute a run.
 *
 * Both built-in App\Models\Launcher and user-created App\Models\UserLauncher
 * satisfy this interface, so RunExecutor and friends can consume either
 * through a single type.
 */
interface LauncherSource
{
    public function getSlug(): string;

    public function getName(): string;

    public function getDescription(): string;

    public function getPromptTemplate(): string;

    public function getInputType(): string;

    /** @return array<string, mixed> */
    public function getOutputSchema(): array;

    public function isBuiltIn(): bool;
}
