<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            <div class="h-20 w-20 overflow-hidden rounded-full bg-gray-100 ring-2 ring-gray-200">
                @if($profile->image_url)
                    <img src="{{ $profile->image_url }}" alt="{{ '@'.ltrim((string) $profile->username, '@') }}" class="h-full w-full object-cover">
                @else
                    <div class="flex h-full w-full items-center justify-center text-xl font-bold text-gray-500">
                        {{ strtoupper(substr(ltrim((string) $profile->username, '@'), 0, 1)) }}
                    </div>
                @endif
            </div>

            <div class="min-w-0">
                <a href="{{ route('admin.profiles') }}" wire:navigate class="text-sm font-medium text-blue-600 hover:underline">
                    Zurueck zur Profil-Liste
                </a>
                <h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ $profile->display_label }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ '@'.ltrim((string) $profile->username, '@') }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold
                        @if($profile->visibility === 'public') bg-emerald-100 text-emerald-700
                        @elseif($profile->visibility === 'private') bg-amber-100 text-amber-800
                        @else bg-gray-100 text-gray-700 @endif">
                        {{ $profile->visibility }}
                    </span>

                    @if($profile->last_status_level)
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                            Status: {{ $profile->last_status_level }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        @if($profile->profile_url)
            <a href="{{ $profile->profile_url }}" target="_blank" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Instagram oeffnen
            </a>
        @endif
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-blue-600">Follower</div>
            <div class="mt-1 text-2xl font-bold text-blue-900">{{ number_format((int) ($profile->followers_count ?? 0), 0, ',', '.') }}</div>
        </div>
        <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Folgt</div>
            <div class="mt-1 text-2xl font-bold text-emerald-900">{{ number_format((int) ($profile->following_count ?? 0), 0, ',', '.') }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-600">Posts</div>
            <div class="mt-1 text-2xl font-bold text-slate-900">{{ number_format((int) ($profile->posts_count ?? 0), 0, ',', '.') }}</div>
        </div>
        <div class="rounded-lg border border-violet-100 bg-violet-50 px-4 py-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-violet-600">Verknuepfte Personen</div>
            <div class="mt-1 text-2xl font-bold text-violet-900">{{ number_format($linkedPeople->count(), 0, ',', '.') }}</div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-6">
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Profilinformationen</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Username</div>
                        <div class="mt-1 text-sm text-gray-900">{{ '@'.ltrim((string) $profile->username, '@') }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Zuletzt gescannt</div>
                        <div class="mt-1 text-sm text-gray-900">
                            {{ $profile->last_scanned_at ? \Carbon\Carbon::parse($profile->last_scanned_at)->format('d.m.Y H:i') : 'Nie' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Angelegt</div>
                        <div class="mt-1 text-sm text-gray-900">{{ \Carbon\Carbon::parse($profile->created_at)->format('d.m.Y H:i') }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Aktualisiert</div>
                        <div class="mt-1 text-sm text-gray-900">{{ \Carbon\Carbon::parse($profile->updated_at)->format('d.m.Y H:i') }}</div>
                    </div>
                </div>

                @if($profile->last_status_message)
                    <div class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Letzte Statusmeldung</div>
                        <p class="mt-2 text-sm text-gray-700">{{ $profile->last_status_message }}</p>
                    </div>
                @endif

                @if($profile->biography)
                    <div class="mt-5">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Biografie</div>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700">{{ $profile->biography }}</p>
                    </div>
                @endif
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Letzte Listen-Scans</h2>
                <div class="mt-4 space-y-3">
                    @forelse($recentScans as $scan)
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-900">{{ $scan->list_type }}</span>
                                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $scan->status_level }}</span>
                                <span class="text-xs text-gray-500">
                                    {{ $scan->scanned_at ? \Carbon\Carbon::parse($scan->scanned_at)->format('d.m.Y H:i') : 'ohne Datum' }}
                                </span>
                            </div>
                            <div class="mt-2 grid gap-2 md:grid-cols-5">
                                <div>Aktiv: {{ number_format((int) ($scan->active_count ?? 0), 0, ',', '.') }}</div>
                                <div>Beobachtet: {{ number_format((int) ($scan->observed_count ?? 0), 0, ',', '.') }}</div>
                                <div>Bekannt: {{ number_format((int) ($scan->known_count ?? 0), 0, ',', '.') }}</div>
                                <div>Neu: {{ number_format((int) ($scan->added_count ?? 0), 0, ',', '.') }}</div>
                                <div>Entfernt: {{ number_format((int) ($scan->removed_count ?? 0), 0, ',', '.') }}</div>
                            </div>
                            @if($scan->status_message)
                                <p class="mt-2 text-xs text-gray-600">{{ $scan->status_message }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Noch keine Listen-Scans gespeichert.</p>
                    @endforelse
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Bekannte Beziehungen</h2>
                <div class="mt-4 space-y-3">
                    @forelse($relationships as $relationship)
                        <a
                            href="{{ route('admin.profile-detail', ['profileId' => $relationship->related_profile_id]) }}"
                            wire:navigate
                            class="block rounded-lg border border-gray-200 bg-gray-50 p-4 hover:bg-white"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-900">
                                    {{ $relationship->related_display_name ?: $relationship->related_full_name ?: '@'.ltrim((string) $relationship->related_username, '@') }}
                                </span>
                                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $relationship->list_type }}</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $relationship->status }}</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">{{ '@'.ltrim((string) $relationship->related_username, '@') }}</p>
                            <p class="mt-2 text-xs text-gray-500">
                                Erstmalig: {{ $relationship->first_seen_at ? \Carbon\Carbon::parse($relationship->first_seen_at)->format('d.m.Y H:i') : 'unbekannt' }}
                                · Zuletzt: {{ $relationship->last_seen_at ? \Carbon\Carbon::parse($relationship->last_seen_at)->format('d.m.Y H:i') : 'unbekannt' }}
                            </p>
                        </a>
                    @empty
                        <p class="text-sm text-gray-500">Keine gespeicherten Beziehungen gefunden.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">Verknuepfte Personen</h2>
                <div class="mt-4 space-y-3">
                    @forelse($linkedPeople as $linkedPerson)
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-900">{{ $linkedPerson->display_name }}</span>
                                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-gray-700">{{ $linkedPerson->relation_label }}</span>
                                @if($linkedPerson->monitoring_enabled)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Monitoring aktiv</span>
                                @endif
                            </div>
                            @if($linkedPerson->user_id)
                                <a href="{{ route('admin.user-profile', ['userId' => $linkedPerson->user_id]) }}" wire:navigate class="mt-2 inline-block text-sm text-blue-600 hover:underline">
                                    {{ $linkedPerson->user_name ?: 'Benutzer #'.$linkedPerson->user_id }}
                                </a>
                            @elseif($linkedPerson->user_name)
                                <p class="mt-2 text-sm text-gray-600">{{ $linkedPerson->user_name }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Keine Person ist mit diesem Profil verknuepft.</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
</div>
