@props([
    'scan',
    'profileId' => null,
])

@php
    $resolvedProfileId = $profileId ?: ($scan->instagram_profile_id ?? null);
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
    $scanProcesses = collect($scan->processes ?? []);
    $scanEvents = collect($scan->events ?? []);
    $activeScanState = $scan->active_scan_state ?? null;
    $hasRuntimeDetails = $activeScanState || $scanProcesses->isNotEmpty() || $scanEvents->isNotEmpty();
@endphp

<article {{ $attributes->class([
    'group overflow-hidden rounded-3xl border bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-xl',
    $borderClass,
]) }}>
    <div class="grid min-h-[18rem] md:grid-cols-[minmax(0,1fr)_13rem]">
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
                    @if($resolvedProfileId)
                        <a
                            href="{{ route('admin.profile-detail', $resolvedProfileId) }}"
                            wire:navigate
                            class="h-14 w-14 shrink-0 overflow-hidden rounded-2xl bg-slate-100 shadow-sm ring-1 ring-slate-200 transition hover:ring-2 hover:ring-indigo-400"
                            aria-label="Profildetails von {{ $scan->display_name }} oeffnen"
                        >
                            @if($scan->profile_image_url)
                                <img src="{{ $scan->profile_image_url }}" alt="{{ $scan->display_name }}" class="h-full w-full object-cover object-top">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-base font-black text-slate-500">{{ $initial }}</div>
                            @endif
                        </a>
                    @else
                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded-2xl bg-slate-100 shadow-sm ring-1 ring-slate-200">
                            @if($scan->profile_image_url)
                                <img src="{{ $scan->profile_image_url }}" alt="{{ $scan->display_name }}" class="h-full w-full object-cover object-top">
                            @else
                                <div class="flex h-full w-full items-center justify-center text-base font-black text-slate-500">{{ $initial }}</div>
                            @endif
                        </div>
                    @endif

                    <div class="min-w-0">
                        @if($resolvedProfileId)
                            <a href="{{ route('admin.profile-detail', $resolvedProfileId) }}" wire:navigate class="block truncate text-lg font-black tracking-tight text-slate-950 transition hover:text-indigo-700">
                                {{ $scan->display_name }}
                            </a>
                        @else
                            <div class="truncate text-lg font-black tracking-tight text-slate-950">{{ $scan->display_name }}</div>
                        @endif
                        <div class="mt-0.5 truncate text-xs font-bold text-indigo-600">{{ $scan->username ? '@'.$scan->username : 'Kein Instagram-Handle' }}</div>
                        @if($scan->user_name)
                            @if($scan->user_id)
                                <a href="{{ route('admin.user-profile', $scan->user_id) }}" wire:navigate class="mt-1 block truncate text-xs text-slate-500 transition hover:text-indigo-700">
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

            @if($scan->metrics->isNotEmpty())
                <div class="mt-5 grid grid-cols-3 gap-2">
                    @foreach($scan->metrics as $metric)
                        <div class="min-w-0 rounded-2xl border border-slate-200 bg-white px-2 py-3 text-center shadow-sm">
                            <div class="truncate text-lg font-black tracking-tight text-slate-950">
                                {{ is_numeric($metric->value) ? number_format((int) $metric->value, 0, ',', '.') : ($metric->value ?: '-') }}
                            </div>
                            <div class="mt-0.5 truncate text-[9px] font-black uppercase tracking-wide text-slate-400" title="{{ $metric->label }}">
                                {{ $metric->label }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($hasRuntimeDetails)
                <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">Laufzeit & Verlauf</div>
                        <div class="flex flex-wrap gap-1.5">
                            @if($activeScanState)
                                <span class="rounded-full {{ $activeScanState->is_responsive ? 'bg-emerald-50 text-emerald-700 ring-emerald-100' : 'bg-amber-50 text-amber-700 ring-amber-100' }} px-2 py-1 text-[9px] font-black uppercase tracking-wide ring-1">
                                    {{ $activeScanState->is_responsive ? 'Heartbeat aktiv' : 'Heartbeat pruefen' }}
                                </span>
                            @endif
                            @if($scanProcesses->isNotEmpty())
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-slate-600 ring-1 ring-slate-200">
                                    {{ $scanProcesses->count() }} Prozesse
                                </span>
                            @endif
                            @if($scanEvents->isNotEmpty())
                                <span class="rounded-full bg-indigo-50 px-2 py-1 text-[9px] font-black uppercase tracking-wide text-indigo-700 ring-1 ring-indigo-100">
                                    {{ $scanEvents->count() }} Events
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($activeScanState)
                        @php
                            $heartbeatAt = $activeScanState->last_output_at ?: $activeScanState->updated_at;
                        @endphp
                        <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-black text-slate-800">{{ $activeScanState->label }}</span>
                                <span class="rounded-md bg-white px-2 py-0.5 text-[10px] font-bold text-slate-500 ring-1 ring-slate-200">Gen. {{ $activeScanState->generation }}</span>
                                @if($activeScanState->graceful_stop_requested)
                                    <span class="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-800">Stopp angefragt</span>
                                @endif
                            </div>
                            @if($heartbeatAt)
                                <div class="mt-1 text-[11px] text-slate-500">
                                    Letzter Output {{ \Carbon\Carbon::parse($heartbeatAt)->diffForHumans() }}
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($scanProcesses->isNotEmpty())
                        <div class="mt-4">
                            <div class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Prozessfamilie</div>
                            <div class="mt-2 space-y-2">
                                @foreach($scanProcesses as $process)
                                    @php
                                        $depth = min(5, max(0, (int) ($process->tree_depth ?? 0)));
                                        $isScraperCommand = (bool) ($process->is_scraper_command ?? false);
                                        $relatedUsernames = collect($process->effective_related_usernames ?? $process->related_usernames ?? [])
                                            ->filter()
                                            ->take(2);
                                    @endphp
                                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2" style="margin-left: {{ $depth * 0.85 }}rem">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="rounded-md {{ $isScraperCommand ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }} px-2 py-0.5 text-[10px] font-black">PID {{ $process->pid }}</span>
                                            <span class="rounded-md bg-white px-2 py-0.5 text-[10px] font-bold text-slate-500 ring-1 ring-slate-200">PPID {{ $process->parent_pid }}</span>
                                            <span class="rounded-md bg-white px-2 py-0.5 text-[10px] font-bold text-slate-500 ring-1 ring-slate-200">{{ $isScraperCommand ? 'Scraper' : 'Kind' }}</span>
                                            @if($process->script_name || $process->family_script_name)
                                                <span class="max-w-full truncate rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700">
                                                    {{ $process->script_name ?: $process->family_script_name }}
                                                </span>
                                            @endif
                                            @foreach($relatedUsernames as $username)
                                                <span class="rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700">@{{ $username }}</span>
                                            @endforeach
                                            <span class="rounded-md bg-white px-2 py-0.5 text-[10px] font-bold text-slate-500 ring-1 ring-slate-200">CPU {{ number_format($process->cpu, 1, ',', '.') }}%</span>
                                        </div>
                                        <div class="mt-1 truncate font-mono text-[10px] text-slate-400" title="{{ $process->command }}">
                                            {{ $process->short_command }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($scanEvents->isNotEmpty())
                        <div class="mt-4">
                            <div class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">Letzte Scan-Events</div>
                            <div class="mt-2 space-y-2">
                                @foreach($scanEvents as $event)
                                    @php
                                        $eventDotClass = match ($event->status_level) {
                                            'success' => 'bg-emerald-500',
                                            'error' => 'bg-rose-500',
                                            'cancelled' => 'bg-slate-400',
                                            'partial' => 'bg-blue-500',
                                            default => 'bg-slate-300',
                                        };
                                    @endphp
                                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="flex min-w-0 items-center gap-2">
                                                <span class="h-2 w-2 shrink-0 rounded-full {{ $eventDotClass }}"></span>
                                                <span class="truncate font-black text-slate-800">{{ $event->stage ?: $event->phase ?: 'scan-event' }}</span>
                                                @if($event->percent !== null)
                                                    <span class="rounded-md bg-white px-2 py-0.5 text-[10px] font-bold text-slate-500 ring-1 ring-slate-200">{{ $event->percent }}%</span>
                                                @endif
                                            </div>
                                            @if($event->occurred_at)
                                                <span class="shrink-0 text-[10px] font-semibold text-slate-400">{{ \Carbon\Carbon::parse($event->occurred_at)->diffForHumans() }}</span>
                                            @endif
                                        </div>
                                        <div class="mt-1 line-clamp-2 text-[11px] leading-5 text-slate-500">
                                            {{ $event->message ?: 'Kein Event-Text gespeichert.' }}
                                        </div>
                                        @if($event->payload_summary)
                                            <div class="mt-1 truncate text-[10px] font-semibold text-slate-400">{{ $event->payload_summary }}</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endif
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
