<div wire:loading.class="cursor-wait" class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-900">Profile</h1>
        <p class="mt-2 text-sm text-gray-500">
            Hier werden alle gespeicherten Instagram-Profile aus dem System angezeigt, inklusive ihrer Verknuepfungen zu Personen und Benutzern.
        </p>
    </div>

    @if (! $tablesAvailable)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Die Tabelle `instagram_profiles` ist in dieser Installation aktuell nicht verfuegbar.
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-4">
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Name, @username oder Biografie suchen..."
                    class="w-80 rounded-full border border-gray-300 px-4 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                >

                @if($hasUserRelations)
                    <select wire:model.live="filterByUser" class="rounded border border-gray-300 px-3 py-2 text-sm">
                        <option value="">Alle Benutzer</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-2 text-sm text-blue-900">
                {{ $profiles->total() }} gespeicherte {{ $profiles->total() === 1 ? 'Profil' : 'Profile' }}
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="hidden grid-cols-12 gap-4 border-b border-gray-200 bg-gray-50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 lg:grid">
                <div class="col-span-4">
                    <button wire:click="sortByField('instagram_profiles.username')" class="flex items-center gap-2 text-left">
                        Profil
                    </button>
                </div>
                <div class="col-span-2">
                    <button wire:click="sortByField('instagram_profiles.followers_count')" class="flex items-center gap-2 text-left">
                        Reichweite
                    </button>
                </div>
                <div class="col-span-3">
                    Verknuepfungen
                </div>
                <div class="col-span-1">
                    <button wire:click="sortByField('instagram_profiles.last_scanned_at')" class="flex items-center gap-2 text-left">
                        Scan
                    </button>
                </div>
                <div class="col-span-2">
                    <button wire:click="sortByField('instagram_profiles.updated_at')" class="flex items-center gap-2 text-left">
                        Aktualisiert
                    </button>
                </div>
            </div>

            <div class="divide-y divide-gray-200">
                @forelse ($profiles as $profile)
                    @php
                        $handle = $profile->username ? '@'.ltrim($profile->username, '@') : 'Kein Instagram-Handle';
                        $displayName = $profile->display_name ?: $profile->full_name ?: $handle;
                        $imageUrl = \App\Support\PublicAssetUrl::fromStorageOrRemote($profile->profile_image_path, $profile->profile_image_url);
                        $visibility = $profile->profile_visibility ?: ($profile->is_private ? 'private' : 'unknown');
                    @endphp

                    <div class="grid gap-4 px-5 py-4 text-sm lg:grid-cols-12">
                        <div class="flex min-w-0 items-start gap-3 lg:col-span-4">
                            <div class="h-12 w-12 shrink-0 overflow-hidden rounded-full bg-gray-100">
                                @if($imageUrl)
                                    <img src="{{ $imageUrl }}" alt="{{ $handle }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center text-xs font-semibold text-gray-500">
                                        {{ strtoupper(substr(ltrim((string) ($profile->username ?: $displayName ?: '?'), '@'), 0, 1)) }}
                                    </div>
                                @endif
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a
                                        href="{{ route('admin.profile-detail', ['profileId' => $profile->id]) }}"
                                        wire:navigate
                                        class="truncate font-semibold text-gray-900 hover:text-blue-600 hover:underline"
                                    >
                                        {{ $displayName }}
                                    </a>

                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                        @if($visibility === 'public') bg-emerald-100 text-emerald-700
                                        @elseif($visibility === 'private') bg-amber-100 text-amber-800
                                        @else bg-gray-100 text-gray-600 @endif">
                                        {{ $visibility }}
                                    </span>
                                </div>

                                <p class="mt-1 truncate text-xs text-gray-500">{{ $handle }}</p>
                                <p class="mt-1">
                                    <a href="{{ route('admin.profile-detail', ['profileId' => $profile->id]) }}" wire:navigate class="text-xs font-medium text-blue-600 hover:underline">
                                        Profil-Details ansehen
                                    </a>
                                </p>

                                @if($profile->last_status_message)
                                    <p class="mt-2 line-clamp-2 text-xs text-gray-500">
                                        {{ $profile->last_status_message }}
                                    </p>
                                @elseif($profile->biography)
                                    <p class="mt-2 line-clamp-2 text-xs text-gray-500">
                                        {{ $profile->biography }}
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

                        <div class="lg:col-span-3">
                            @if(($profile->linked_people_count ?? 0) > 0)
                                <p class="font-medium text-gray-900">
                                    {{ $profile->linked_people_count }} {{ $profile->linked_people_count === 1 ? 'Person verknuepft' : 'Personen verknuepft' }}
                                </p>

                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($profile->linked_people as $linkedPerson)
                                        @if($linkedPerson->user_id)
                                            <a
                                                href="{{ route('admin.user-profile', ['userId' => $linkedPerson->user_id]) }}"
                                                wire:navigate
                                                class="rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-800 hover:bg-blue-100"
                                            >
                                                {{ $linkedPerson->display_name }}
                                                @if($linkedPerson->relation_label)
                                                    · {{ $linkedPerson->relation_label }}
                                                @endif
                                            </a>
                                        @else
                                            <span class="rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-700">
                                                {{ $linkedPerson->display_name }}
                                                @if($linkedPerson->relation_label)
                                                    · {{ $linkedPerson->relation_label }}
                                                @endif
                                            </span>
                                        @endif
                                    @endforeach
                                </div>

                                @if($profile->linked_users->isNotEmpty())
                                    <p class="mt-2 text-xs text-gray-500">
                                        Benutzer: {{ $profile->linked_users->implode(', ') }}
                                    </p>
                                @endif
                            @else
                                <span class="text-gray-400">Noch keiner Person zugeordnet</span>
                            @endif
                        </div>

                        <div class="lg:col-span-1">
                            <p class="text-gray-900">
                                {{ $profile->last_scanned_at ? \Carbon\Carbon::parse($profile->last_scanned_at)->format('d.m.Y') : 'Nie' }}
                            </p>
                            @if($profile->last_scanned_at)
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ \Carbon\Carbon::parse($profile->last_scanned_at)->format('H:i') }}
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
                        Keine gespeicherten Instagram-Profile gefunden.
                    </div>
                @endforelse
            </div>
        </div>

        <div>
            {{ $profiles->links() }}
        </div>

        <livewire:admin.network-map />
    @endif
</div>
