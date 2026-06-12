<section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm" wire:poll.5s>
    @php
        $runningCount = $scans->where('is_running', true)->count();
        $errorCount = $scans->where('status_level', 'error')->count();
    @endphp

    <header class="border-b border-slate-200 bg-slate-50/80 px-5 py-5 sm:px-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-950 text-white shadow-sm">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M7 12h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-black text-slate-950">Scan-Monitor</h2>
                        @if($runningCount > 0)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                                {{ $runningCount }} live
                            </span>
                        @endif
                        @if($errorCount > 0)
                            <span class="rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-bold text-rose-700">{{ $errorCount }} fehlerhaft</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-slate-500">Alle Analyse-, Listen-, Beitrags-, Vorschlags- und Verbindungsscans. Automatische Aktualisierung alle 5 Sekunden.</p>
                </div>
            </div>

            <div class="inline-flex w-fit rounded-xl border border-slate-200 bg-white p-1 text-xs font-bold shadow-sm">
                @foreach(['all' => 'Alle', 'running' => 'Laufend', 'completed' => 'Abgeschlossen'] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('filter', '{{ $value }}')"
                        class="rounded-lg px-3.5 py-2 transition {{ $filter === $value ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </header>

    @if(! $tablesAvailable)
        <div class="m-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
            Die Scan-Tabellen sind in dieser Installation noch nicht verfuegbar.
        </div>
    @else
        <div class="grid gap-5 p-5 sm:p-6 xl:grid-cols-2">
            @forelse($scans as $scan)
                <x-admin.scan-card :scan="$scan" wire:key="{{ $scan->scan_key }}" />
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center">
                    <svg class="mx-auto h-10 w-10 text-slate-300" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3M7 12h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <div class="mt-4 text-sm font-bold text-slate-700">Keine Scans fuer diesen Filter</div>
                    <div class="mt-1 text-xs text-slate-500">Neue und laufende Scans erscheinen hier automatisch.</div>
                </div>
            @endforelse
        </div>
    @endif
</section>
