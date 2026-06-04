<div x-data="{ selectedTab: 'userDetails' }">
    <div class="relative h-32 overflow-hidden rounded-t-lg bg-gray-200">
        <div class="absolute left-2 top-2 rounded-full px-3 py-1 text-xs font-semibold text-white"
             :class="{ 'bg-green-500': {{ $user->isActive() ? 'true' : 'false' }}, 'bg-red-500': {{ $user->isActive() ? 'false' : 'true' }} }">
            {{ $user->isActive() ? 'Aktiv' : 'Inaktiv' }}
        </div>

        <div class="absolute right-2 top-2 rounded-full bg-gray-700 px-3 py-1 text-xs font-semibold text-white">
            Registriert: {{ $user->created_at->format('d.m.Y') }}
        </div>
    </div>

    <div class="-mt-16 mx-auto h-32 w-32 overflow-hidden rounded-full border-4 border-white">
        <img
            class="h-32 aspect-square object-cover object-center"
            src="{{ $user->profile_photo_url ?? 'https://via.placeholder.com/150' }}"
            alt="{{ $user->name }}"
        >
    </div>

    <div class="mt-2 text-center">
        <h2 class="text-lg font-semibold">{{ $user->name }}</h2>
    </div>

    <div class="mt-2 flex flex-wrap justify-end gap-4 border-t p-4">
        <div x-data="{ open: false }" class="relative">
            <x-button @click="open = !open">Optionen</x-button>

            <div
                x-show="open"
                @click.away="open = false"
                x-cloak
                class="absolute right-0 mt-2 w-40 rounded border border-gray-200 bg-white shadow-lg"
            >
                <ul>
                    <li>
                        @if ($user->status)
                            <button wire:click="deactivateUser()" class="block w-full px-4 py-2 text-left hover:bg-yellow-100">
                                Deaktivieren
                            </button>
                        @else
                            <button wire:click="activateUser()" class="block w-full px-4 py-2 text-left hover:bg-green-100">
                                Aktivieren
                            </button>
                        @endif
                    </li>
                    <li>
                        <button wire:click="openMailModal" class="block w-full px-4 py-2 text-left text-gray-700 hover:bg-blue-100">
                            Mail senden
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <ul class="flex w-full divide-gray-200 rounded-lg bg-gray-100 text-center text-sm font-medium text-gray-500 shadow">
        <li class="w-full">
            <button
                @click="selectedTab = 'userDetails'"
                :class="{ 'border-b-2 border-blue-600 bg-gray-100 text-blue-600': selectedTab === 'userDetails' }"
                class="w-full bg-gray-100 p-4 transition-all duration-200 hover:bg-blue-100 hover:text-blue-600 focus:outline-none"
            >
                Details
            </button>
        </li>
        <li class="w-full">
            <button
                @click="selectedTab = 'trackedProfiles'"
                :class="{ 'border-b-2 border-blue-600 bg-gray-100 text-blue-600': selectedTab === 'trackedProfiles' }"
                class="w-full bg-gray-100 p-4 transition-all duration-200 hover:bg-blue-100 hover:text-blue-600 focus:outline-none"
            >
                Getrackte Profile
            </button>
        </li>
        <li class="w-full">
            <button
                @click="selectedTab = 'usageCosts'"
                :class="{ 'border-b-2 border-blue-600 bg-gray-100 text-blue-600': selectedTab === 'usageCosts' }"
                class="w-full bg-gray-100 p-4 transition-all duration-200 hover:bg-blue-100 hover:text-blue-600 focus:outline-none"
            >
                Kosten / Verbrauch
            </button>
        </li>
    </ul>

    <div>
        <div x-show="selectedTab === 'userDetails'" x-collapse x-cloak>
            <div class="mt-4 w-full rounded-lg bg-gray-100 p-6 shadow">
                <h2 class="mb-4 text-2xl font-bold text-gray-800">Benutzerprofil</h2>

                <div class="mb-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-700">Benutzerdetails</h3>
                        <p><strong>Benutzername:</strong> {{ $user->name }}</p>
                        <div class="col-span-4 flex items-center space-x-2">
                            <span><strong>E-Mail:</strong> {{ $user->email }}</span>
                            @if($user->email_verified_at)
                                <span class="h-2 w-2 rounded-full bg-green-300" title="Verifiziert am: {{ $user->email_verified_at->format('d.m.Y H:i') }}"></span>
                            @else
                                <span class="h-2 w-2 rounded-full bg-red-300" title="E-Mail nicht verifiziert"></span>
                            @endif
                        </div>
                        <p><strong>Registriert am:</strong> {{ $user->created_at->format('d.m.Y') }}</p>
                    </div>

                    <div></div>
                </div>

                @if($user->customer)
                    <div>
                        <h2 class="mb-4 text-2xl font-bold text-gray-800">Kundendetails</h2>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Persoenliche Informationen</h3>
                                <p><strong>Vorname:</strong> {{ $user->customer->first_name }}</p>
                                <p><strong>Nachname:</strong> {{ $user->customer->last_name }}</p>
                                <p><strong>Telefonnummer:</strong> {{ $user->customer->phone_number }}</p>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold text-gray-700">Adresse</h3>
                                <p><strong>Strasse:</strong> {{ $user->customer->street }}</p>
                                <p><strong>Stadt:</strong> {{ $user->customer->city }}</p>
                                <p><strong>Bundesland:</strong> {{ $user->customer->state }}</p>
                                <p><strong>Postleitzahl:</strong> {{ $user->customer->postal_code }}</p>
                                <p><strong>Land:</strong> {{ $user->customer->country }}</p>
                            </div>
                        </div>
                    </div>
                @else
                    <p class="text-gray-500">Keine Kundendetails verfuegbar.</p>
                @endif
            </div>
        </div>

        <div x-show="selectedTab === 'trackedProfiles'" x-collapse x-cloak>
            <div class="mt-4 w-full rounded-lg bg-gray-100 p-6 shadow">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-2xl font-bold text-gray-800">Getrackte Profile</h2>
                    <div class="rounded-full bg-white px-3 py-1 text-sm font-semibold text-gray-700">
                        {{ $trackedProfiles->count() }} {{ $trackedProfiles->count() === 1 ? 'Profil' : 'Profile' }}
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    @forelse($trackedProfiles as $trackedProfile)
                        <div class="rounded-lg border border-gray-200 bg-white p-4">
                            <div class="flex flex-wrap items-start gap-4">
                                <div class="h-14 w-14 shrink-0 overflow-hidden rounded-full bg-gray-100">
                                    @if($trackedProfile->image_url)
                                        <img src="{{ $trackedProfile->image_url }}" alt="{{ $trackedProfile->handle }}" class="h-full w-full object-cover">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center text-sm font-semibold text-gray-500">
                                            {{ strtoupper(substr(ltrim((string) $trackedProfile->handle, '@'), 0, 1)) }}
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('admin.profile-detail', ['profileId' => $trackedProfile->instagram_profile_id]) }}" wire:navigate class="font-semibold text-gray-900 hover:text-blue-600 hover:underline">
                                            {{ $trackedProfile->display_name }}
                                        </a>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                            {{ $trackedProfile->relation_label }}
                                        </span>
                                        @if($trackedProfile->monitoring_enabled)
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Monitoring aktiv</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">{{ $trackedProfile->handle }}</p>
                                    <p class="mt-2 text-xs text-gray-500">
                                        Person: {{ $trackedProfile->tracked_person_name }}
                                        @if($trackedProfile->last_instagram_analyzed_at)
                                            · Letzte Analyse: {{ \Carbon\Carbon::parse($trackedProfile->last_instagram_analyzed_at)->format('d.m.Y H:i') }}
                                        @endif
                                    </p>
                                </div>

                                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                                    <div class="rounded-md bg-blue-50 px-3 py-2">
                                        <div class="uppercase tracking-wide text-blue-600">Follower</div>
                                        <div class="mt-1 font-semibold text-blue-900">{{ number_format($trackedProfile->followers_count, 0, ',', '.') }}</div>
                                    </div>
                                    <div class="rounded-md bg-emerald-50 px-3 py-2">
                                        <div class="uppercase tracking-wide text-emerald-600">Folgt</div>
                                        <div class="mt-1 font-semibold text-emerald-900">{{ number_format($trackedProfile->following_count, 0, ',', '.') }}</div>
                                    </div>
                                    <div class="rounded-md bg-slate-50 px-3 py-2">
                                        <div class="uppercase tracking-wide text-slate-600">Posts</div>
                                        <div class="mt-1 font-semibold text-slate-900">{{ number_format($trackedProfile->posts_count, 0, ',', '.') }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500">
                            Fuer diesen Benutzer wurden noch keine getrackten Instagram-Profile gefunden.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div x-show="selectedTab === 'usageCosts'" x-collapse x-cloak>
            <div class="mt-4 w-full rounded-lg bg-gray-100 p-6 shadow">
                <h2 class="text-2xl font-bold text-gray-800">Kosten / Verbrauch</h2>
                <p class="mt-4 text-sm text-gray-500">Dieser Bereich ist vorbereitet und bleibt vorerst leer.</p>
            </div>
        </div>
    </div>

    <div x-data="{ showModal: @entangle('showMailModal') }" x-init="$watch('showModal', value => { if (!value) $wire.set('mailUserId', {{ $user->id }}); })">
        <x-dialog-modal wire:model="showMailModal">
            <x-slot name="title">
                Mail verfassen
                <span class="mt-1 block text-sm text-gray-500">An: {{ $user->email }}</span>
            </x-slot>
            <x-slot name="content">
                <div class="mb-4 border-l-4 border-yellow-500 bg-yellow-100 p-4 text-yellow-700" role="alert">
                    <p class="font-bold">Wichtiger Hinweis</p>
                    <p>Bitte stelle sicher, dass die E-Mail sorgfaeltig und ueberlegt verfasst ist. Ueberpruefe insbesondere Betreff, Ueberschrift und Nachricht.</p>
                </div>
                <div>
                    <label for="mailSubject" class="block text-sm font-medium text-gray-700">Betreff</label>
                    <input type="text" id="mailSubject" wire:model="mailSubject" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    <x-input-error for="mailSubject" class="mt-2" />
                </div>
                <div class="mt-4">
                    <label for="mailHeader" class="block text-sm font-medium text-gray-700">Ueberschrift</label>
                    <input type="text" id="mailHeader" wire:model="mailHeader" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    <x-input-error for="mailHeader" class="mt-2" />
                </div>
                <div class="mt-4">
                    <label for="mailBody" class="block text-sm font-medium text-gray-700">Nachricht</label>
                    <textarea id="mailBody" rows="6" wire:model="mailBody" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"></textarea>
                    <x-input-error for="mailBody" class="mt-2" />
                </div>
                <div class="mt-4">
                    <label for="mailLink" class="block text-sm font-medium text-gray-700">Link (optional)</label>
                    <input type="url" id="mailLink" wire:model="mailLink" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                    <x-input-error for="mailLink" class="mt-2" />
                </div>
            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="resetMailModal" wire:loading.attr="disabled">
                    Abbrechen
                </x-secondary-button>
                <x-button wire:click="sendMail" wire:loading.attr="disabled" class="ml-2">
                    Senden
                </x-button>
            </x-slot>
        </x-dialog-modal>
    </div>
</div>
