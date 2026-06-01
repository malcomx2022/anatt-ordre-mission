<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    // TelescopeServiceProvider est enregistré conditionnellement (local + paquet présent)
    // dans AppServiceProvider::register() — cf. composer require-dev.
];
