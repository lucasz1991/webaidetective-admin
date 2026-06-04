<div wire:loading.class="cursor-wait" class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Profile</h1>
        <p class="mt-2 text-sm text-gray-500">
            Hier werden die getrackten Instagram-Profile aus dem Monitoring angezeigt, nicht die Scraper-Accounts aus den Einstellungen.
        </p>
    </div>

    @if (! $tablesAvailable)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Die Tracking-Tabellen `tracked_people` oder `instagram_profiles` sind in dieser Installation aktuell nicht verfuegbar.
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-4">
                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Name, Alias, @username oder Benutzer suchen..."
                        class="w-80 rounded-full border border-gray-300 px-4 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                </div>

                <select wire:model.live="filterByUser" class="rounded border border-gray-300 px-3 py-2 text-sm">
                    <option value="">Alle Benutzer</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-2 text-sm text-blue-900">
                {{ $profiles->total() }} getrackte {{ $profiles->total() === 1 ? 'Profil' : 'Profile' }}
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="hidden grid-cols-12 gap-4 border-b border-gray-200 bg-gray-50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 lg:grid">
                <div class="col-span-4">
                    <button wire:click="sortByField('tracked_people.instagram_username')" class="flex items-center gap-2 text-left">
                        Profil
                    </button>
                </div>
                <div class="col-span-2">
                    <button wire:click="sortByField('instagram_profiles.followers_count')" class="flex items-center gap-2 text-left">
                        Reichweite
                    </button>
                </div>
                <div class="col-span-2">
                    <button wire:click="sortByField('users.name')" class="flex items-center gap-2 text-left">
                        Benutzer
                    </button>
                </div>
                <div class="col-span-2">
                    <button wire:click="sortByField('tracked_people.last_instagram_analyzed_at')" class="flex items-center gap-2 text-left">
                        Letzte Analyse
                    </button>
                </div>
                <div class="col-span-2">
                    <button wire:click="sortByField('tracked_people.updated_at')" class="flex items-center gap-2 text-left">
                        Aktualisiert
                    </button>
                </div>
            </div>

            <div class="divide-y divide-gray-200">
                @forelse ($profiles as $profile)
                    @php
                        $displayName = trim(collect([$profile->first_name, $profile->last_name])->filter()->implode(' '));
                        $handle = $profile->profile_username ?: $profile->instagram_username;
                        $imageUrl = $profile->profile_image_path
                            ? \Illuminate\Support\Facades\Storage::disk('public')->url($profile->profile_image_path)
                            : $profile->profile_image_url;
                    @endphp

                    <div class="grid gap-4 px-5 py-4 text-sm lg:grid-cols-12">
                        <div class="flex min-w-0 items-start gap-3 lg:col-span-4">
                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-full bg-gray-100">
                                @if($imageUrl)
                                    <img src="{{ $imageUrl }}" alt="{{ $handle }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-xs font-semibold text-gray-500">
                                        {{ strtoupper(substr((string) ($handle ?: $displayName ?: '?'), 0, 1)) }}
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-semibold text-gray-900">
                                        {{ $profile->profile_display_name ?: $profile->profile_full_name ?: $displayName ?: 'Unbenanntes Profil' }}
                                    </p>

                                    @if($profile->is_primary)
                                        <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">Primaer</span>
                                    @endif

                                    @if($profile->monitoring_enabled)
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Monitoring aktiv</span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">Monitoring aus</span>
                                    @endif
                                </div>

                                <p class="mt-1 truncate text-xs text-gray-500">
                                    {{ $handle ? '@'.ltrim($handle, '@') : 'Kein Instagram-Handle' }}
                                    @if($profile->alias)
                                        · Alias: {{ $profile->alias }}
                                    @endif
                                </p>

                                @if($profile->last_instagram_status_message)
                                    <p class="mt-2 line-clamp-2 text-xs text-gray-500">
                                        {{ $profile->last_instagram_status_message }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 lg:col-span-2">
                            <div class="rounded-md bg-blue-50 px-3 py-2 text-center">
                                <p class="text-[11px] uppercase tracking-wide text-blue-600">Follower</p>
                                <p class="font-semibold text-blue-900">{{ number_format((int) ($profile->followers_count ?? 0), 0, ',', '.') }}</p>
                            </div>
                            <div class="rounded-md bg-emerald-50 px-3 py-2 text-center">
                                <p class="text-[11px] uppercase tracking-wide text-emerald-600">Folgt</p>
                                <p class="font-semibold text-emerald-900">{{ number_format((int) ($profile->following_count ?? 0), 0, ',', '.') }}</p>
                            </div>
                            <div class="rounded-md bg-slate-50 px-3 py-2 text-center">
                                <p class="text-[11px] uppercase tracking-wide text-slate-600">Posts</p>
                                <p class="font-semibold text-slate-900">{{ number_format((int) ($profile->posts_count ?? 0), 0, ',', '.') }}</p>
                            </div>
                        </div>

                        <div class="lg:col-span-2">
                            @if($profile->user_id)
                                <a href="{{ route('admin.user-profile', ['userId' => $profile->user_id]) }}" wire:navigate class="font-medium text-blue-600 hover:underline">
                                    {{ $profile->user_name ?: 'Benutzer #'.$profile->user_id }}
                                </a>
                            @else
                                <span class="text-gray-400">Kein Benutzer</span>
                            @endif
                        </div>

                        <div class="lg:col-span-2">
                            <p class="text-gray-900">
                                {{ $profile->last_instagram_analyzed_at ? \Carbon\Carbon::parse($profile->last_instagram_analyzed_at)->format('d.m.Y H:i') : 'Noch nie' }}
                            </p>
                            @if($profile->last_scanned_at)
                                <p class="mt-1 text-xs text-gray-500">
                                    Profilscan: {{ \Carbon\Carbon::parse($profile->last_scanned_at)->format('d.m.Y H:i') }}
                                </p>
                            @endif
                        </div>

                        <div class="lg:col-span-2">
                            <p class="text-gray-900">
                                {{ \Carbon\Carbon::parse($profile->updated_at)->format('d.m.Y H:i') }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                Angelegt: {{ \Carbon\Carbon::parse($profile->created_at)->format('d.m.Y') }}
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center text-sm text-gray-500">
                        Keine getrackten Instagram-Profile gefunden.
                    </div>
                @endforelse
            </div>
        </div>

        <div>
            {{ $profiles->links() }}
        </div>
    @endif
</div>
