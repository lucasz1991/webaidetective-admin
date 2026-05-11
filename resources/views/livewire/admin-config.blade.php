<div wire:loading.class="cursor-wait">
    <h1 class="mb-6 text-2xl font-semibold">Konfiguration</h1>

    <div x-data="{ activeTab: 'overview' }">
        <div class="mb-6 border-b">
            <nav class="-mb-px flex flex-wrap gap-6">
                <button
                    type="button"
                    class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium"
                    :class="{ 'border-blue-500 text-blue-600': activeTab === 'overview', 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700': activeTab !== 'overview' }"
                    @click="activeTab = 'overview'"
                >
                    Uebersicht
                </button>
                <button
                    type="button"
                    class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium"
                    :class="{ 'border-blue-500 text-blue-600': activeTab === 'basis', 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700': activeTab !== 'basis' }"
                    @click="activeTab = 'basis'"
                >
                    Basis
                </button>
                <button
                    type="button"
                    class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium"
                    :class="{ 'border-blue-500 text-blue-600': activeTab === 'mails', 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700': activeTab !== 'mails' }"
                    @click="activeTab = 'mails'"
                >
                    Mails
                </button>
                <button
                    type="button"
                    class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium"
                    :class="{ 'border-blue-500 text-blue-600': activeTab === 'scraper', 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700': activeTab !== 'scraper' }"
                    @click="activeTab = 'scraper'"
                >
                    Scraper
                </button>
            </nav>
        </div>

        <div x-show="activeTab === 'overview'" x-cloak class="space-y-8" x-collapse.duration.300ms>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Aenderungen in diesem Bereich wirken sofort auf die Anwendungen. Bitte pruefe Einstellungen fuer Mail und Scraper besonders sorgfaeltig.
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <h3 class="text-lg font-semibold text-gray-700">Basis</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Logo, Farben und allgemeine Stammdaten der Installation.
                    </p>
                    <button type="button" @click="activeTab = 'basis'" class="mt-3 text-sm font-medium text-blue-600">
                        Basis-Einstellungen oeffnen
                    </button>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <h3 class="text-lg font-semibold text-gray-700">Mails</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Admin-Adresse und automatische Mail-Benachrichtigungen verwalten.
                    </p>
                    <button type="button" @click="activeTab = 'mails'" class="mt-3 text-sm font-medium text-blue-600">
                        Mail-Einstellungen oeffnen
                    </button>
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <h3 class="text-lg font-semibold text-gray-700">Scraper-Profil</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Browser-Profil, Auto-Login und Session-Speicherung fuer den Instagram-Scraper.
                    </p>
                    <button type="button" @click="activeTab = 'scraper'" class="mt-3 text-sm font-medium text-blue-600">
                        Scraper-Einstellungen oeffnen
                    </button>
                </div>
            </div>
        </div>

        <div x-show="activeTab === 'basis'" x-cloak x-collapse.duration.300ms>
            @livewire('admin.config.basic-settings')
        </div>

        <div x-show="activeTab === 'mails'" x-cloak class="space-y-6" x-collapse.duration.300ms>
            <h2 class="text-2xl font-semibold">Mail-Einstellungen</h2>

            <x-settings-collapse>
                <x-slot name="trigger">
                    Admin E-Mail Adresse
                </x-slot>
                <x-slot name="content">
                    <div class="mb-4 rounded-md border border-blue-200 bg-blue-100 p-4 text-sm text-blue-700">
                        Diese Adresse wird fuer Systemmeldungen und Admin-Benachrichtigungen verwendet.
                    </div>
                    <div class="mb-4">
                        <label for="admin_email" class="block text-sm font-medium text-gray-700">Admin E-Mail Adresse</label>
                        <input
                            id="admin_email"
                            type="email"
                            wire:model="adminEmail"
                            class="mt-1 block w-full rounded-md border border-gray-300 p-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                        @error('adminEmail')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button wire:click="saveAdminEmail" class="rounded-md bg-blue-500 px-4 py-2 text-white hover:bg-blue-600">
                        E-Mail Adresse speichern
                    </button>
                </x-slot>
            </x-settings-collapse>

            <x-settings-collapse>
                <x-slot name="trigger">
                    Automatische Admin-Mails
                </x-slot>
                <x-slot name="content">
                    <div class="mb-4 rounded-md border border-blue-200 bg-blue-100 p-4 text-sm text-blue-700">
                        Lege fest, welche automatischen Meldungen an den Admin versendet werden.
                    </div>
                    <div class="space-y-3">
                        @foreach ($adminEmailNotifications as $key => $value)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="adminEmailNotifications.{{ $key }}" class="rounded border-gray-300">
                                <span class="text-sm font-medium text-gray-700">{{ __("mails.admin.$key") }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button wire:click="saveAdminMailSettings" class="mt-4 rounded-md bg-blue-500 px-4 py-2 text-white hover:bg-blue-600">
                        Aenderungen speichern
                    </button>
                </x-slot>
            </x-settings-collapse>

            <x-settings-collapse>
                <x-slot name="trigger">
                    Benutzer-Mails
                </x-slot>
                <x-slot name="content">
                    <div class="mb-4 rounded-md border border-blue-200 bg-blue-100 p-4 text-sm text-blue-700">
                        Lege fest, welche automatischen Mails an Benutzer versendet werden.
                    </div>
                    <div class="space-y-3">
                        @foreach ($userEmailNotifications as $key => $value)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model="userEmailNotifications.{{ $key }}" class="rounded border-gray-300">
                                <span class="text-sm font-medium text-gray-700">{{ __("mails.user.$key") }}</span>
                            </label>
                        @endforeach
                    </div>
                    <button wire:click="saveUserMailSettings" class="mt-4 rounded-md bg-blue-500 px-4 py-2 text-white hover:bg-blue-600">
                        Aenderungen speichern
                    </button>
                </x-slot>
            </x-settings-collapse>
        </div>

        <div x-show="activeTab === 'scraper'" x-cloak class="space-y-6" x-collapse.duration.300ms>
            <h2 class="text-2xl font-semibold">Scraper-Einstellungen</h2>
            @livewire('admin.config.scraper-settings')
        </div>
    </div>
</div>
