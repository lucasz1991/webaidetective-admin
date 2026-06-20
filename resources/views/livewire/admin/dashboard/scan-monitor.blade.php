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

    @if($loadAllSources || $scraperProcesses->isNotEmpty() || $processNotice)
        @php
            $scraperCommandCount = $scraperProcesses->where('is_scraper_command', true)->count();
            $scraperFamilyCount = $scraperProcesses->count();
            $idleScraperCount = $scraperProcesses
                ->where('is_scraper_command', true)
                ->where('is_idle_suspect', true)
                ->count();
        @endphp
        <div class="border-b border-slate-200 bg-slate-50/70 px-5 py-5 sm:px-6">
            <details class="group" @if($processAccordionOpen) open @endif>
                <summary wire:click="toggleProcessAccordion" class="flex cursor-pointer list-none flex-col gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm transition hover:bg-slate-50 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-black uppercase tracking-[0.16em] text-slate-700">Node-Scraper-Prozesse</h3>
                        @if($scraperProcesses->isNotEmpty())
                            <span class="rounded-full bg-slate-900 px-2.5 py-1 text-[10px] font-black text-white">{{ $scraperCommandCount }} Scraper</span>
                            <span class="rounded-full bg-white px-2.5 py-1 text-[10px] font-black text-slate-600 ring-1 ring-slate-200">{{ $scraperFamilyCount }} Prozesse inkl. Kinder</span>
                        @endif
                        @if($idleScraperCount > 0)
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black text-amber-800">
                                {{ $idleScraperCount }} Leerlauf-Verdacht
                            </span>
                        @endif
                    </div>
                    <p class="mt-1 text-xs leading-5 text-slate-500">
                        Prozessfamilien werden ueber PID/PPID rekonstruiert. Unter einem Scraper erscheinen auch Kindprozesse wie Browser-Worker.
                    </p>
                    </div>
                    <span class="inline-flex w-fit items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs font-black uppercase tracking-wide text-slate-600">
                        {{ $processAccordionOpen ? 'Zuklappen' : 'Aufklappen' }}
                    </span>
                </summary>

            @if($processNotice)
                <div class="mt-4 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-900">
                    {{ $processNotice }}
                </div>
            @endif

            @if($scraperProcesses->isEmpty())
                <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    Keine laufenden Instagram-Node-Scraper-Prozesse erkannt.
                </div>
            @else
                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div class="divide-y divide-slate-200">
                        @foreach($scraperProcesses as $process)
                            @php
                                $depth = min(6, max(0, (int) ($process->tree_depth ?? 0)));
                                $isScraperCommand = (bool) ($process->is_scraper_command ?? false);
                                $relatedUsernames = collect($process->effective_related_usernames ?? $process->related_usernames ?? [])
                                    ->filter()
                                    ->take(3);
                            @endphp
                            <div class="grid gap-3 px-4 py-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                                <div class="min-w-0">
                                    <div class="flex min-w-0 gap-3" style="padding-left: {{ $depth * 1.15 }}rem">
                                        @if($depth > 0)
                                            <div class="mt-2 h-10 w-4 shrink-0 border-l border-b border-slate-200"></div>
                                        @endif
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-lg {{ $isScraperCommand ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700' }} px-2.5 py-1 text-xs font-black">
                                                    PID {{ $process->pid }}
                                                </span>
                                                <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">PPID {{ $process->parent_pid }}</span>
                                                <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">
                                                    {{ $isScraperCommand ? 'Scraper' : 'Kindprozess' }}
                                                </span>
                                                @if($process->script_name)
                                                    <span class="rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">{{ $process->script_name }}</span>
                                                @elseif($process->family_script_name)
                                                    <span class="rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">unter {{ $process->family_script_name }}</span>
                                                @endif
                                                @if($process->scan_type_label || $process->family_scan_type_label)
                                                    <span class="rounded-lg bg-white px-2.5 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">
                                                        {{ $process->scan_type_label ?: $process->family_scan_type_label }}
                                                    </span>
                                                @endif
                                                @foreach($relatedUsernames as $username)
                                                    <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">@{{ $username }}</span>
                                                @endforeach
                                                <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ $process->elapsed }}</span>
                                                <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">CPU {{ number_format($process->cpu, 1, ',', '.') }}%</span>
                                                <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">RAM {{ number_format($process->memory, 1, ',', '.') }}%</span>
                                                @if($process->children_count > 0)
                                                    <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ $process->children_count }} Kinder</span>
                                                @endif
                                                @if($isScraperCommand && $process->is_idle_suspect)
                                                    <span class="rounded-lg bg-amber-100 px-2.5 py-1 text-xs font-black text-amber-800">Leerlauf-Verdacht</span>
                                                @endif
                                            </div>
                                            <div class="mt-2 truncate font-mono text-xs text-slate-500" title="{{ $process->command }}">
                                                {{ $process->short_command }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    @if($isScraperCommand)
                                        <button
                                            type="button"
                                            wire:click="terminateScraperProcess({{ $process->pid }})"
                                            wire:confirm="Diesen Instagram-Scraper-Prozess mit SIGTERM beenden?"
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2 text-xs font-black text-amber-800 transition hover:bg-amber-100 disabled:cursor-wait disabled:opacity-60"
                                        >
                                            Beenden
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="terminateScraperProcess({{ $process->pid }}, true)"
                                            wire:confirm="Diesen Instagram-Scraper-Prozess sofort mit SIGKILL beenden? Das sollte nur im Notfall passieren."
                                            wire:loading.attr="disabled"
                                            class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2 text-xs font-black text-rose-700 transition hover:bg-rose-100 disabled:cursor-wait disabled:opacity-60"
                                        >
                                            Erzwingen
                                        </button>
                                    @else
                                        <span class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs font-bold text-slate-500">
                                            Root PID {{ $process->family_root_pid }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            </details>
        </div>
    @endif

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

        @if($hasMore)
            <div class="border-t border-slate-200 bg-slate-50/80 px-6 py-5 text-center">
                <button
                    type="button"
                    wire:click="loadMore"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-wait disabled:opacity-60"
                >
                    Weitere Scans laden
                </button>
            </div>
        @endif
    @endif
</section>
