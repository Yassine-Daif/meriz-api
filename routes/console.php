<?php

use Illuminate\Support\Facades\Schedule;

// Supprime les jetons d'accès expirés depuis plus de 24 heures.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
