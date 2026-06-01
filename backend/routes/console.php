<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Backup automatique quotidien a 02:00
Schedule::command('db:backup --compress')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/backup.log'));

// Purge des tokens Sanctum expires (hygiene : ils sont deja refuses passe
// SANCTUM_EXPIRATION ; ceci nettoie la table personal_access_tokens).
Schedule::command('sanctum:prune-expired --hours=24')
    ->daily()
    ->onOneServer();
