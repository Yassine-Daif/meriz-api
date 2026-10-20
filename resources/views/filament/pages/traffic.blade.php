<x-filament-panels::page>
    @php($report = $this->report())
    <div wire:poll.60s style="display:grid;gap:24px">
        <x-filament::section heading="Les visites, au plus près du serveur" description="Rapports GoAccess actualisés toutes les cinq minutes, sans cookie de mesure d’audience ni service de suivi externe.">
            <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:end">
                <label style="display:grid;gap:8px;min-width:260px">Espace
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="source">
                            @foreach(\App\Services\TrafficReport::SOURCES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label style="display:grid;gap:8px;min-width:200px">Période demandée
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="days">
                            <option value="1">Aujourd’hui</option><option value="7">7 jours</option><option value="30">30 jours</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <x-filament::button wire:click="$refresh" icon="heroicon-o-arrow-path">Actualiser</x-filament::button>
            </div>
        </x-filament::section>
        @if(!$report)
            <x-filament::section heading="Collecte en préparation">
                Le premier rapport apparaîtra après la prochaine analyse automatique des journaux.
            </x-filament::section>
        @else
            @if(\Illuminate\Support\Carbon::parse($report['generated_at'])->lt(now()->subMinutes(15)))
                <x-filament::section heading="Actualisation en retard" icon="heroicon-o-exclamation-triangle">
                    Ces données restent consultables, mais le dernier calcul date de plus de quinze minutes.
                </x-filament::section>
            @endif
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Généré le {{ \Illuminate\Support\Carbon::parse($report['generated_at'])->timezone('Europe/Paris')->format('d/m/Y à H:i') }}.
                Données disponibles : {{ $report['start_date'] ?: 'aucune visite' }} → {{ $report['end_date'] ?: '—' }}.
                La période est limitée aux journaux conservés ; les journées absentes ne signifient pas zéro visite.
            </p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px">
                @foreach(['visitors' => 'Visiteurs / jours estimés', 'requests' => 'Requêtes analysées', 'pages' => 'Requêtes hors fichiers statiques', 'errors' => 'Erreurs HTTP (4xx / 5xx)'] as $key => $label)
                    <x-filament::section>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
                        <p style="font-size:2rem;font-weight:700;margin-top:8px">{{ number_format($report['summary'][$key], 0, ',', ' ') }}</p>
                    </x-filament::section>
                @endforeach
            </div>
            <x-filament::section heading="Évolution quotidienne" description="Un visiteur estimé correspond à une adresse IP et un navigateur sur une journée, pas à une personne identifiée.">
                <div style="overflow-x:auto">
                    <table style="width:100%;text-align:left;border-collapse:collapse">
                        <thead><tr><th style="padding:10px">Jour</th><th style="padding:10px">Visiteurs estimés</th><th style="padding:10px">Requêtes</th><th style="padding:10px;width:40%">Activité</th></tr></thead>
                        <tbody>
                        @foreach($report['daily'] as $row)
                            <tr style="border-top:1px solid #8883">
                                <td style="padding:10px">{{ $row['label'] }}</td><td style="padding:10px">{{ $row['visitors'] }}</td><td style="padding:10px">{{ $row['hits'] }}</td>
                                <td style="padding:10px"><meter style="width:100%;accent-color:#d97706" min="0" max="{{ max(1, max(array_column($report['daily'], 'hits'))) }}" value="{{ $row['hits'] }}" aria-label="Requêtes du {{ $row['label'] }}">{{ $row['hits'] }}</meter></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));gap:24px">
                @foreach(['pages' => 'Pages et routes les plus consultées', 'browsers' => 'Navigateurs', 'os' => 'Systèmes d’exploitation', 'countries' => 'Pays estimés', 'referrers' => 'Sites référents', 'statuses' => 'Codes HTTP'] as $key => $heading)
                    <x-filament::section :heading="$heading" description="Nombre de requêtes, hors robots reconnus.">
                        @forelse($report[$key] as $row)
                            <div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;padding:10px 0;border-bottom:1px solid #8882">
                                <span style="overflow-wrap:anywhere">{{ $row['label'] }}</span>
                                <span style="font-variant-numeric:tabular-nums;font-weight:600">{{ number_format($row['hits'], 0, ',', ' ') }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">Aucune donnée sur cette période.</p>
                        @endforelse
                    </x-filament::section>
                @endforeach
            </div>
            <x-filament::section heading="Comment lire ces chiffres" collapsible>
                <p>Les robots reconnus sont exclus. Les bloqueurs, VPN, caches et navigateurs peuvent fausser les estimations. Les pays sont déduits de l’adresse IP et ne représentent pas une localisation certaine. L’application étant une page unique, les changements de vue internes ne sont pas comptés comme des pages web.</p>
                <p style="margin-top:12px">Les anciens journaux ne distinguent pas les domaines : consultez « Historique commun » pour les voir. Les nouveaux rapports sont séparés par domaine. Les adresses IP, jetons et paramètres d’URL ne figurent pas dans ces rapports.</p>
                <p style="margin-top:12px">Géolocalisation : <a href="https://db-ip.com" target="_blank" rel="noopener noreferrer" class="text-primary-600">DB-IP Lite</a> (CC BY 4.0), base {{ $report['geo_updated_at'] ?? 'indisponible' }}.</p>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
