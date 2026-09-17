<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Supprime les jetons d'accès expirés depuis plus de 24 heures.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
