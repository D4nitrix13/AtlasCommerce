<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\ScrambleConfigServiceProvider;
use App\Providers\ViewNamespaceServiceProvider;

return [
    AppServiceProvider::class,
    ScrambleConfigServiceProvider::class,
    ViewNamespaceServiceProvider::class,
];
