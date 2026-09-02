<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    // TelescopeServiceProvider est enregistré conditionnellement (local + paquet présent)
    // dans AppServiceProvider::register() — cf. composer require-dev.
];
