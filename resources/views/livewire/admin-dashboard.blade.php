<div class="space-y-6 py-6" wire:poll.30s>
    <div class="rounded-2xl bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950 px-6 py-7 text-white shadow-lg">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-indigo-200">Systemuebersicht</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">Admin-Dashboard</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-300">
                    Benutzeraktivitaet, Profilbestand und der aktuelle Zustand der Instagram-Scans auf einen Blick.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.profiles') }}" wire:navigate class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-slate-100">
                    Profile anzeigen
                </a>
                <a href="{{ route('admin.users') }}" wire:navigate class="rounded-lg border border-white/20 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10">
                    Benutzer verwalten
                </a>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $cards = [
                ['label' => 'Benutzer gesamt', 'value' => $statistics['users'], 'detail' => $statistics['active_users'].' in den letzten 15 Min. aktiv', 'color' => 'blue'],
                ['label' => 'Beobachtete Personen', 'value' => $statistics['tracked_people'], 'detail' => $statistics['monitored_people'].' mit Dauerbeobachtung', 'color' => 'indigo'],
                ['label' => 'Instagram-Profile', 'value' => $statistics['profiles'], 'detail' => 'Zentral gespeicherte Profile', 'color' => 'violet'],
                ['label' => 'Scans heute', 'value' => $statistics['scans_today'], 'detail' => $statistics['running_scans'].' laufen gerade', 'color' => 'emerald'],
                ['label' => 'Fehler letzte 24 Std.', 'value' => $statistics['failed_scans'], 'detail' => 'Fehlgeschlagene Profilscans', 'color' => 'rose'],
            ];
            $colorClasses = [
                'blue' => 'bg-blue-50 text-blue-700 ring-blue-100',
                'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-100',
                'violet' => 'bg-violet-50 text-violet-700 ring-violet-100',
                'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
                'rose' => 'bg-rose-50 text-rose-700 ring-rose-100',
            ];
        @endphp

        @foreach($cards as $card)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="inline-flex rounded-lg px-2.5 py-1 text-xs font-bold uppercase tracking-wide ring-1 {{ $colorClasses[$card['color']] }}">
                    {{ $card['label'] }}
                </div>
                <div class="mt-4 text-3xl font-black text-slate-950">{{ number_format($card['value'], 0, ',', '.') }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ $card['detail'] }}</div>
            </div>
        @endforeach
    </div>

    <livewire:admin.dashboard.scan-monitor />
</div>
