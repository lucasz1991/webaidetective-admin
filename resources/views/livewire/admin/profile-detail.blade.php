<div class="space-y-6 py-6">
    @php
        $visibilityClasses = match ($profile->visibility) {
            'public' => 'bg-emerald-400/15 text-emerald-200 ring-emerald-400/25',
            'private' => 'bg-amber-400/15 text-amber-200 ring-amber-400/25',
            default => 'bg-white/10 text-slate-200 ring-white/15',
        };
        $statusClasses = match ($profile->last_status_level) {
            'success' => 'bg-emerald-100 text-emerald-800',
            'error' => 'bg-rose-100 text-rose-800',
            'cancelled' => 'bg-slate-200 text-slate-700',
            'partial' => 'bg-blue-100 text-blue-800',
            default => 'bg-slate-100 text-slate-700',
        };
        $filterTabs = [
            'all' => 'Alle',
            'running' => 'Laufend',
            'analysis' => 'Analysen',
            'lists' => 'Listen',
            'posts' => 'Beitraege',
            'suggestions' => 'Vorschlaege',
            'connections' => 'Verbindungen',
            'errors' => 'Fehler',
        ];
    @endphp

    <section class="relative overflow-hidden rounded-3xl bg-slate-950 text-white shadow-xl">
        <div class="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-indigo-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 left-1/3 h-52 w-52 rounded-full bg-cyan-400/10 blur-3xl"></div>

        <div class="relative p-6 sm:p-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-start gap-5">
                    <div class="h-24 w-24 shrink-0 overflow-hidden rounded-3xl bg-white/10 shadow-xl ring-1 ring-white/15">
                        @if($profile->image_url)
                            <img src="{{ $profile->image_url }}" alt="{{ $profile->display_label }}" class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full items-center justify-center text-3xl font-black text-slate-300">
                                {{ strtoupper(substr(ltrim((string) $profile->username, '@'), 0, 1)) }}
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <a href="{{ route('admin.profiles') }}" wire:navigate class="inline-flex items-center gap-2 text-xs font-bold text-indigo-200 transition hover:text-white">
                            <span aria-hidden="true">&larr;</span>
                            Profil-Liste
                        </a>
                        <h1 class="mt-3 truncate text-3xl font-black tracking-tight sm:text-4xl">{{ $profile->display_label }}</h1>
                        <p class="mt-1 truncate text-sm font-semibold text-indigo-200">{{ '@'.ltrim((string) $profile->username, '@') }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <span class="rounded-full px-3 py-1 text-[11px] font-black uppercase tracking-wide ring-1 {{ $visibilityClasses }}">
                                {{ $profile->visibility === 'public' ? 'Oeffentlich' : ($profile->visibility === 'private' ? 'Privat' : 'Unbekannt') }}
                            </span>
                            @if($profile->last_status_level)
                                <span class="rounded-full bg-white/10 px-3 py-1 text-[11px] font-bold text-slate-200 ring-1 ring-white/15">
                                    Status: {{ ucfirst($profile->last_status_level) }}
                                </span>
                            @endif
                            @if($scanCounts['running'] > 0)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-400/15 px-3 py-1 text-[11px] font-bold text-amber-200 ring-1 ring-amber-400/25">
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-400"></span>
                                    {{ $scanCounts['running'] }} Scan live
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if($profile->profile_url)
                        <a href="{{ $profile->profile_url }}" target="_blank" rel="noopener" class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-950 shadow-sm transition hover:bg-indigo-50">
                            Instagram oeffnen
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach([
            ['label' => 'Follower', 'value' => $profile->followers_count, 'tone' => 'blue'],
            ['label' => 'Folgt', 'value' => $profile->following_count, 'tone' => 'emerald'],
            ['label' => 'Beitraege', 'value' => $profile->posts_count, 'tone' => 'violet'],
            ['label' => 'Alle Scans', 'value' => $scanCounts['all'], 'tone' => 'slate'],
            ['label' => 'Verknuepfte Personen', 'value' => $linkedPeople->count(), 'tone' => 'amber'],
        ] as $stat)
            @php
                $statClasses = match ($stat['tone']) {
                    'blue' => 'border-blue-100 bg-blue-50 text-blue-900',
                    'emerald' => 'border-emerald-100 bg-emerald-50 text-emerald-900',
                    'violet' => 'border-violet-100 bg-violet-50 text-violet-900',
                    'amber' => 'border-amber-100 bg-amber-50 text-amber-900',
                    default => 'border-slate-200 bg-slate-50 text-slate-900',
                };
            @endphp
            <article class="rounded-2xl border p-5 shadow-sm {{ $statClasses }}">
                <div class="text-[11px] font-black uppercase tracking-[0.14em] opacity-60">{{ $stat['label'] }}</div>
                <div class="mt-3 text-3xl font-black tracking-tight">{{ number_format((int) ($stat['value'] ?? 0), 0, ',', '.') }}</div>
            </article>
        @endforeach
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">Profil</div>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Profilinformationen</h2>
                </div>
                @if($profile->last_status_level)
                    <span class="rounded-full px-3 py-1 text-[11px] font-bold {{ $statusClasses }}">{{ ucfirst($profile->last_status_level) }}</span>
                @endif
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Username</dt>
                    <dd class="mt-1 truncate text-sm font-bold text-slate-900">{{ '@'.ltrim((string) $profile->username, '@') }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Zuletzt gescannt</dt>
                    <dd class="mt-1 text-sm font-bold text-slate-900">{{ $profile->last_scanned_at ? \Carbon\Carbon::parse($profile->last_scanned_at)->format('d.m.Y H:i') : 'Nie' }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Angelegt</dt>
                    <dd class="mt-1 text-sm font-bold text-slate-900">{{ \Carbon\Carbon::parse($profile->created_at)->format('d.m.Y H:i') }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <dt class="text-[10px] font-black uppercase tracking-wide text-slate-400">Aktualisiert</dt>
                    <dd class="mt-1 text-sm font-bold text-slate-900">{{ \Carbon\Carbon::parse($profile->updated_at)->format('d.m.Y H:i') }}</dd>
                </div>
            </dl>

            @if($profile->last_status_message)
                <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-[10px] font-black uppercase tracking-wide text-slate-400">Letzte Statusmeldung</div>
                    <p class="mt-2 text-sm leading-6 text-slate-700">{{ $profile->last_status_message }}</p>
                </div>
            @endif

            @if($profile->biography)
                <div class="mt-5">
                    <div class="text-[10px] font-black uppercase tracking-wide text-slate-400">Biografie</div>
                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $profile->biography }}</p>
                </div>
            @endif
        </section>

        <aside class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-black text-slate-950">Verknuepfte Personen</h2>
                <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-black text-indigo-700">{{ $linkedPeople->count() }}</span>
            </div>
            <div class="mt-4 max-h-80 space-y-3 overflow-y-auto pr-2">
                @forelse($linkedPeople as $linkedPerson)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-slate-900">{{ $linkedPerson->display_name }}</span>
                            @if($linkedPerson->monitoring_enabled)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Monitoring</span>
                            @endif
                        </div>
                        <div class="mt-1 text-xs text-slate-500">{{ $linkedPerson->relation_label }}</div>
                        @if($linkedPerson->user_id)
                            <a href="{{ route('admin.user-profile', $linkedPerson->user_id) }}" wire:navigate class="mt-2 inline-flex text-xs font-bold text-indigo-600 hover:text-indigo-800">
                                {{ $linkedPerson->user_name ?: 'Benutzer #'.$linkedPerson->user_id }}
                            </a>
                        @endif
                    </div>
                @empty
                    <p class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-500">Keine Person ist mit diesem Profil verknuepft.</p>
                @endforelse
            </div>
        </aside>
    </div>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-200 bg-slate-50/80 px-5 py-5 sm:px-6">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <div class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">Scan-Historie</div>
                    <h2 class="mt-1 text-xl font-black text-slate-950">Alle Scans dieses Profils</h2>
                    <p class="mt-1 text-sm text-slate-500">Analysen, Listen, Beitraege, Vorschlaege und Verbindungsscans in einer gemeinsamen Zeitleiste.</p>
                </div>

                <div class="flex max-w-full gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1 shadow-sm">
                    @foreach($filterTabs as $value => $label)
                        <button
                            type="button"
                            wire:click="$set('scanFilter', '{{ $value }}')"
                            class="inline-flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold transition {{ $scanFilter === $value ? 'bg-slate-950 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-900' }}"
                        >
                            {{ $label }}
                            <span class="rounded-full px-1.5 py-0.5 text-[9px] {{ $scanFilter === $value ? 'bg-white/15 text-white' : 'bg-slate-100 text-slate-500' }}">
                                {{ $scanCounts[$value] }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        </header>

        <div class="grid gap-5 p-5 sm:p-6 xl:grid-cols-2">
            @forelse($scans as $scan)
                @php
                    $scanStatusClasses = match (true) {
                        $scan->is_running => 'bg-amber-100 text-amber-800 ring-amber-200',
                        $scan->status_level === 'success' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                        $scan->status_level === 'cancelled' => 'bg-slate-200 text-slate-700 ring-slate-300',
                        $scan->status_level === 'error' => 'bg-rose-100 text-rose-800 ring-rose-200',
                        $scan->status_level === 'partial' => 'bg-blue-100 text-blue-800 ring-blue-200',
                        default => 'bg-slate-100 text-slate-600 ring-slate-200',
                    };
                    $scanStatusLabel = match (true) {
                        $scan->is_running => 'Laeuft',
                        $scan->status_level === 'success' => 'Abgeschlossen',
                        $scan->status_level === 'cancelled' => 'Abgebrochen',
                        $scan->status_level === 'error' => 'Fehlerhaft',
                        $scan->status_level === 'partial' => 'Teilweise abgeschlossen',
                        default => ucfirst($scan->status_level),
                    };
                    $scanBorderClass = match (true) {
                        $scan->is_running => 'border-amber-200',
                        $scan->status_level === 'error' => 'border-rose-200',
                        default => 'border-slate-200',
                    };
                @endphp

                <article wire:key="{{ $scan->scan_key }}" class="group overflow-hidden rounded-3xl border bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg {{ $scanBorderClass }}">
                    <div class="grid min-h-[18rem] md:grid-cols-[minmax(0,1fr)_12rem]">
                        <div class="flex min-w-0 flex-col p-5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-wide ring-1 {{ $scanStatusClasses }}">
                                    @if($scan->is_running)
                                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                                    @endif
                                    {{ $scanStatusLabel }}
                                </span>
                                <span class="rounded-full px-3 py-1.5 text-[10px] font-black uppercase tracking-wide ring-1 {{ $scan->scan_type_classes }}">
                                    {{ $scan->scan_type_label }}
                                </span>
                            </div>

                            <div class="mt-4 flex items-start gap-3">
                                <div class="h-12 w-12 shrink-0 overflow-hidden rounded-2xl bg-slate-100 ring-1 ring-slate-200">
                                    @if($scan->profile_image_url)
                                        <img src="{{ $scan->profile_image_url }}" alt="{{ $scan->display_name }}" class="h-full w-full object-cover">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center font-black text-slate-500">{{ strtoupper(substr($scan->username, 0, 1)) }}</div>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate text-base font-black text-slate-950">{{ $scan->display_name }}</div>
                                    <div class="truncate text-xs font-bold text-indigo-600">{{ '@'.$scan->username }}</div>
                                    @if($scan->user_id && $scan->user_name)
                                        <a href="{{ route('admin.user-profile', $scan->user_id) }}" wire:navigate class="mt-1 block truncate text-xs text-slate-500 hover:text-indigo-700">
                                            Besitzer: <span class="font-semibold">{{ $scan->user_name }}</span>
                                        </a>
                                    @endif
                                </div>
                            </div>

                            @if($scan->context_label)
                                <div class="mt-3 text-[11px] font-semibold text-slate-500">{{ $scan->context_label }}</div>
                            @endif

                            <div class="mt-4 rounded-2xl bg-slate-50 p-3 text-xs leading-5 text-slate-600 ring-1 ring-slate-200">
                                {{ $scan->status_message ?: 'Keine Statusmeldung vorhanden.' }}
                            </div>

                            <div class="mt-4 grid grid-cols-3 gap-2">
                                @foreach($scan->metrics as $metric)
                                    <div class="min-w-0 rounded-xl border border-slate-200 px-2 py-2.5 text-center">
                                        <div class="truncate text-base font-black text-slate-950">{{ is_numeric($metric->value) ? number_format((int) $metric->value, 0, ',', '.') : ($metric->value ?: '-') }}</div>
                                        <div class="truncate text-[9px] font-black uppercase tracking-wide text-slate-400" title="{{ $metric->label }}">{{ $metric->label }}</div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-auto pt-4 text-[11px] font-semibold text-slate-500">
                                {{ $scan->scanned_at ? \Carbon\Carbon::parse($scan->scanned_at)->format('d.m.Y H:i') : 'Ohne Datum' }}
                            </div>
                        </div>

                        <div class="relative min-h-72 overflow-hidden border-t border-slate-200 bg-slate-950 md:min-h-full md:border-l md:border-t-0">
                            @if($scan->screenshot_url)
                                <a href="{{ $scan->screenshot_url }}" target="_blank" rel="noopener" class="block h-full min-h-72">
                                    <img src="{{ $scan->screenshot_url }}" alt="Screenshot {{ $scan->scan_type_label }}" class="h-full min-h-72 w-full object-cover object-top transition duration-500 group-hover:scale-[1.025]">
                                    <span class="absolute inset-x-3 bottom-3 rounded-xl bg-slate-950/85 px-3 py-2 text-center text-[9px] font-black uppercase tracking-wide text-white backdrop-blur">Screenshot oeffnen</span>
                                </a>
                            @else
                                <div class="flex h-full min-h-72 items-center justify-center bg-gradient-to-br from-slate-950 to-indigo-950 p-5 text-center">
                                    <div class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">
                                        {{ $scan->is_running ? 'Live-Screenshot wird vorbereitet' : 'Kein Screenshot gespeichert' }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-16 text-center">
                    <div class="text-sm font-bold text-slate-700">Keine Scans fuer diesen Filter</div>
                    <div class="mt-1 text-xs text-slate-500">Sobald passende Scans vorhanden sind, erscheinen sie hier.</div>
                </div>
            @endforelse
        </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="text-xs font-black uppercase tracking-[0.14em] text-indigo-600">Netzwerk</div>
                <h2 class="mt-1 text-xl font-black text-slate-950">Bekannte Beziehungen</h2>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-700">{{ $relationships->count() }}</span>
        </div>

        <div class="mt-5 max-h-[32rem] overflow-y-auto pr-2">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @forelse($relationships as $relationship)
                    <a href="{{ route('admin.profile-detail', $relationship->related_profile_id) }}" wire:navigate class="rounded-2xl border border-slate-200 bg-slate-50 p-4 transition hover:border-indigo-300 hover:bg-indigo-50">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate font-bold text-slate-900">{{ $relationship->related_display_name ?: $relationship->related_full_name ?: '@'.ltrim((string) $relationship->related_username, '@') }}</span>
                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-slate-600 ring-1 ring-slate-200">{{ $relationship->list_type }}</span>
                        </div>
                        <p class="mt-1 truncate text-xs font-semibold text-indigo-600">{{ '@'.ltrim((string) $relationship->related_username, '@') }}</p>
                        <p class="mt-3 text-[11px] text-slate-500">
                            Zuletzt {{ $relationship->last_seen_at ? \Carbon\Carbon::parse($relationship->last_seen_at)->diffForHumans() : 'unbekannt' }}
                        </p>
                    </a>
                @empty
                    <p class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">Keine gespeicherten Beziehungen gefunden.</p>
                @endforelse
            </div>
        </div>
    </section>
</div>
