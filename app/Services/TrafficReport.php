<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class TrafficReport
{
    public const SOURCES = [
        'site' => 'Vitrine · meriz.fr',
        'app' => 'Application · app.meriz.fr',
        'api' => 'API · api.meriz.fr',
        'history' => 'Historique commun · domaines indissociables',
    ];

    public function read(string $source, int $days): ?array
    {
        abort_unless(isset(self::SOURCES[$source]) && in_array($days, [1, 7, 30], true), 422);
        $path = config('traffic.directory').'/'.$source.'-'.$days.'.json';

        if (! File::exists($path)) {
            return null;
        }

        try {
            return json_decode(File::get($path), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
