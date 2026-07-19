<?php

namespace App\Contracts;

interface LauncherMetaInterface
{
    /** @return array{icon: string, tone: string} */
    public function forBuiltIn(string $slug): array;

    /** @return array{icon: string, tone: string} */
    public function forCustom(string $slug): array;
}
