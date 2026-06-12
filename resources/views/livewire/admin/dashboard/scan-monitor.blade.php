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
                @php
                    $statusClasses = match (true) {
                        $scan->is_running => 'bg-amber-100 text-amber-800 ring-amber-200',
                        $scan->status_level === 'success' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                        $scan->status_level === 'cancelled' => 'bg-slate-200 text-slate-700 ring-slate-300',
                        $scan->status_level === 'error' => 'bg-rose-100 text-rose-800 ring-rose-200',
                        $scan->status_level === 'partial' => 'bg-blue-100 text-blue-800 ring-blue-200',
                        default => 'bg-slate-100 text-slate-600 ring-slate-200',
                    };
                    $statusLabel = match (true) {
                        $scan->is_running => 'Laeuft',
                        $scan->status_level === 'success' => 'Abgeschlossen',
                        $scan->status_level === 'cancelled' => 'Abgebrochen',
                        $scan->status_level === 'error' => 'Fehlerhaft',
                        $scan->status_level === 'partial' => 'Teilweise abgeschlossen',
                        default => ucfirst($scan->status_level),
                    };
                    $borderClass = match (true) {
                        $scan->is_running => 'border-amber-200',
                        $scan->status_level === 'error' => 'border-rose-200',
                        $scan->status_level === 'cancelled' => 'border-slate-300',
                        default => 'border-slate-200',
                    };
                    $initial = strtoupper(substr($scan->username ?: $scan->display_name, 0, 1));
                @endphp

                <article
                    wire:key="{{ $scan->scan_key }}"
                    class="group overflow-hidden rounded-3xl border bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-xl {{ $borderClass }}"
                >
                    <div class="grid min-h-[15rem] md:grid-cols-[minmax(0,1fr)_13rem]">
                        <div class="flex min-w-0 flex-col p-5 sm:p-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-wide ring-1 {{ $statusClasses }}">
                                    @if($scan->is_running)
                                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                                    @endif
                                    {{ $statusLabel }}
                                </span>
                                <span class="rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-wide ring-1 {{ $scan->scan_type_classes }}">
                                    {{ $scan->scan_type_label }}
                                </span>
                                @if($scan->context_label)
                                    <span class="truncate text-[11px] font-semibold text-slate-500">{{ $scan->context_label }}</span>
                                @endif
                            </div>

                            <div class="mt-5 flex items-start justify-between gap-4">
                                <div class="flex min-w-0 items-center gap-3">
                                    @if($scan->instagram_profile_id)
                                        <a
                                            href="{{ route('admin.profile-detail', $scan->instagram_profile_id) }}"
                                            wire:navigate
                                            class="h-14 w-14 shrink-0 overflow-hidden rounded-2xl bg-slate-100 shadow-sm ring-1 ring-slate-200 transition hover:ring-2 hover:ring-indigo-400"
                                            aria-label="Profildetails von {{ $scan->display_name }} oeffnen"
                                        >
                                            @if($scan->profile_image_url)
                                                <img src="{{ $scan->profile_image_url }}" alt="{{ $scan->display_name }}" class="h-full w-full object-cover">
                                            @else
                                                <div class="flex h-full w-full items-center justify-center text-base font-black text-slate-500">{{ $initial }}</div>
                                            @endif
                                        </a>
                                    @else
                                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded-2xl bg-slate-100 shadow-sm ring-1 ring-slate-200">
                                            @if($scan->profile_image_url)
                                                <img src="{{ $scan->profile_image_url }}" alt="{{ $scan->display_name }}" class="h-full w-full object-cover">
                                            @else
                                                <div class="flex h-full w-full items-center justify-center text-base font-black text-slate-500">{{ $initial }}</div>
                                            @endif
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        @if($scan->instagram_profile_id)
                                            <a
                                                href="{{ route('admin.profile-detail', $scan->instagram_profile_id) }}"
                                                wire:navigate
                                                class="block truncate text-lg font-black tracking-tight text-slate-950 transition hover:text-indigo-700"
                                            >
                                                {{ $scan->display_name }}
                                            </a>
                                        @else
                                            <div class="truncate text-lg font-black tracking-tight text-slate-950">{{ $scan->display_name }}</div>
                                        @endif
                                        <div class="mt-0.5 truncate text-xs font-bold text-indigo-600">{{ $scan->username ? '@'.$scan->username : 'Kein Instagram-Handle' }}</div>
                                        @if($scan->user_name)
                                            @if($scan->user_id)
                                                <a
                                                    href="{{ route('admin.user-profile', $scan->user_id) }}"
                                                    wire:navigate
                                                    class="mt-1 block truncate text-xs text-slate-500 transition hover:text-indigo-700"
                                                >
                                                    Besitzer: <span class="font-semibold">{{ $scan->user_name }}</span>
                                                </a>
                                            @else
                                                <div class="mt-1 truncate text-xs text-slate-500">Besitzer: {{ $scan->user_name }}</div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 text-right text-[11px] text-slate-500">
                                    <div class="font-bold text-slate-700">{{ $scan->scanned_at ? \Carbon\Carbon::parse($scan->scanned_at)->diffForHumans() : '-' }}</div>
                                    @if($scan->scanned_at)
                                        <div class="mt-1">{{ \Carbon\Carbon::parse($scan->scanned_at)->format('d.m.Y H:i') }}</div>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-5 flex gap-3 rounded-2xl bg-slate-50 p-4 text-xs leading-5 text-slate-600 ring-1 ring-slate-200">
                                <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white text-slate-400 shadow-sm ring-1 ring-slate-200">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M12 8v4l2.5 1.5M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <div class="line-clamp-3">{{ $scan->status_message ?: 'Keine Statusmeldung vorhanden.' }}</div>
                            </div>



                        </div>

                        <div class="relative min-h-80 overflow-hidden border-t border-slate-200 bg-slate-950 md:min-h-full md:border-l md:border-t-0">
                            @if($scan->screenshot_url)
                                <a href="{{ $scan->screenshot_url }}" target="_blank" rel="noopener" class="block h-full min-h-80 w-full md:min-h-full">
                                    <img
                                        src="{{ $scan->screenshot_url }}"
                                        alt="Scan-Screenshot von {{ $scan->username }}"
                                        class="h-full min-h-80 w-full object-cover object-top transition duration-500 group-hover:scale-[1.025] md:min-h-full"
                                    >
                                    <span class="absolute inset-x-3 bottom-3 rounded-xl bg-slate-950/85 px-3 py-2 text-center text-[10px] font-black uppercase tracking-wide text-white shadow-lg backdrop-blur">
                                        Screenshot oeffnen
                                    </span>
                                </a>
                            @else
                                <div class="flex h-full min-h-80 items-center justify-center bg-gradient-to-br from-slate-950 via-slate-900 to-indigo-950 px-5 text-center md:min-h-full">
                                    <div>
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-white/5 text-slate-500 ring-1 ring-white/10">
                                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"/>
                                                <path d="m7 15 3-3 2 2 2-2 3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                        <div class="mt-4 text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">
                                            {{ $scan->is_running ? 'Live-Screenshot wird vorbereitet' : 'Kein Screenshot gespeichert' }}
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center">
                    <svg class="mx-auto h-10 w-10 text-slate-300" viewBox="0 0 24 24" fill="none"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3M7 12h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <div class="mt-4 text-sm font-bold text-slate-700">Keine Scans fuer diesen Filter</div>
                    <div class="mt-1 text-xs text-slate-500">Neue und laufende Scans erscheinen hier automatisch.</div>
                </div>
            @endforelse
        </div>
    @endif
</section>
