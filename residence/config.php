<?php

declare(strict_types=1);

define('RESIDENCE_ROOT', __DIR__);

function residence_asset(string $path): string
{
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/') . '/' . ltrim($path, '/');
}
