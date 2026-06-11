<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" wire:poll.5s>
    <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-5 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-bold text-slate-950">Scan-Monitor</h2>
                <span wire:loading class="h-2 w-2 animate-pulse rounded-full bg-indigo-500"></span>
            </div>
            <p class="mt-1 text-sm text-slate-500">Laufende und zuletzt abgeschlossene Instagram-Scans, Aktualisierung alle 5 Sekunden.</p>
        </div>

        <div class="inline-flex w-fit rounded-lg bg-slate-100 p-1 text-xs font-semibold">
            @foreach(['all' => 'Alle', 'running' => 'Laufend', 'completed' => 'Abgeschlossen'] as $value => $label)
                <button
                    type="button"
                    wire:click="$set('filter', '{{ $value }}')"
                    class="rounded-md px-3 py-1.5 transition {{ $filter === $value ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    @if(! $tablesAvailable)
        <div class="m-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Die Scan-Tabellen sind in dieser Installation noch nicht verfuegbar.
        </div>
    @else
        <div class="grid gap-4 p-5 xl:grid-cols-2">
            @forelse($scans as $scan)
                @php
                    $statusClasses = match ($scan->status_level) {
                        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                        'partial' => 'bg-amber-50 text-amber-700 ring-amber-200',
                        'error' => 'bg-rose-50 text-rose-700 ring-rose-200',
                        default => 'bg-slate-100 text-slate-600 ring-slate-200',
                    };
                    $statusLabel = match ($scan->status_level) {
                        'success' => 'Erfolgreich',
                        'partial' => 'Laeuft',
                        'error' => 'Fehler',
                        default => ucfirst($scan->status_level),
                    };
                @endphp

                <article wire:key="{{ $scan->is_running ? 'running' : 'snapshot' }}-{{ $scan->is_running ? $scan->tracked_person_id : $scan->snapshot_id }}" class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                    <div class="grid sm:grid-cols-[180px_minmax(0,1fr)]">
                        <div class="relative min-h-40 overflow-hidden bg-slate-200">
                            @if($scan->screenshot_url)
                                <a href="{{ $scan->screenshot_url }}" target="_blank" rel="noopener" class="block h-full">
                                    <img src="{{ $scan->screenshot_url }}" alt="Scan-Screenshot von {{ $scan->username }}" class="h-full min-h-40 w-full object-cover transition hover:scale-[1.02]">
                                </a>
                            @else
                                <div class="flex h-full min-h-40 items-center justify-center px-4 text-center text-xs font-semibold text-slate-500">
                                    Noch kein Screenshot verfuegbar
                                </div>
                            @endif
                            @if($scan->is_running)
                                <span class="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-slate-950/85 px-2.5 py-1 text-[11px] font-bold text-white">
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-400"></span>
                                    LIVE
                                </span>
                            @endif
                        </div>

                        <div class="min-w-0 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-white ring-1 ring-slate-200">
                                        @if($scan->profile_image_url)
                                            <img src="{{ $scan->profile_image_url }}" alt="{{ $scan->display_name }}" class="h-full w-full object-cover">
                                        @else
                                            <div class="flex h-full w-full items-center justify-center text-xs font-bold text-slate-500">
                                                {{ strtoupper(substr($scan->username ?: $scan->display_name, 0, 1)) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="truncate font-bold text-slate-950">{{ $scan->display_name }}</div>
                                        <div class="truncate text-xs font-semibold text-slate-500">{{ $scan->username ? '@'.$scan->username : 'Kein Instagram-Handle' }}</div>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 {{ $statusClasses }}">{{ $statusLabel }}</span>
                            </div>

                            <div class="mt-3 line-clamp-2 min-h-10 text-xs leading-5 text-slate-600">
                                {{ $scan->status_message ?: 'Keine Statusmeldung vorhanden.' }}
                            </div>

                            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-lg bg-white px-2 py-2 ring-1 ring-slate-200">
                                    <div class="text-sm font-black text-slate-900">{{ $scan->posts_count !== null ? number_format($scan->posts_count, 0, ',', '.') : '-' }}</div>
                                    <div class="text-[10px] text-slate-500">Posts</div>
                                </div>
                                <div class="rounded-lg bg-white px-2 py-2 ring-1 ring-slate-200">
                                    <div class="text-sm font-black text-slate-900">{{ $scan->followers_count !== null ? number_format($scan->followers_count, 0, ',', '.') : '-' }}</div>
                                    <div class="text-[10px] text-slate-500">Follower</div>
                                </div>
                                <div class="rounded-lg bg-white px-2 py-2 ring-1 ring-slate-200">
                                    <div class="text-sm font-black text-slate-900">{{ $scan->following_count !== null ? number_format($scan->following_count, 0, ',', '.') : '-' }}</div>
                                    <div class="text-[10px] text-slate-500">Folgt</div>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                                <div class="text-slate-500">
                                    {{ $scan->scanned_at ? \Carbon\Carbon::parse($scan->scanned_at)->diffForHumans() : 'Zeitpunkt unbekannt' }}
                                    @if($scan->user_name)
                                        | {{ $scan->user_name }}
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    @if($scan->user_id)
                                        <a href="{{ route('admin.user-profile', $scan->user_id) }}" wire:navigate class="font-semibold text-slate-600 hover:text-slate-950">Benutzer</a>
                                    @endif
                                    @if($scan->instagram_profile_id)
                                        <a href="{{ route('admin.profile-detail', $scan->instagram_profile_id) }}" wire:navigate class="font-semibold text-indigo-600 hover:text-indigo-800">Profil</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-slate-300 px-5 py-12 text-center text-sm text-slate-500">
                    Fuer diesen Filter sind keine Scans vorhanden.
                </div>
            @endforelse
        </div>
    @endif
</section>
