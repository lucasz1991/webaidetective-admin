<div class="space-y-6" wire:loading.class="opacity-50 pointer-events-none">
    @unless($billingTablesReady)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">Billing-Tabellen fehlen noch.</p>
            <p class="mt-1">Fuehre die Billing-Migration in der Base-Installation aus, damit Pakete, Subscriptions, Wallets und Credit-Transaktionen verfuegbar sind.</p>
        </div>
    @endunless

    <form wire:submit.prevent="savePlans" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Pakete</h2>
                    <p class="mt-1 text-sm text-gray-500">Limits, Credit-Kontingente und Leistungen der SaaS-Tarife.</p>
                </div>
                <button type="submit" @disabled(! $billingTablesReady) class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50">
                    Pakete speichern
                </button>
            </div>
        </div>

        <div class="divide-y divide-gray-200">
            @forelse($plans as $index => $plan)
                <div wire:key="plan-{{ $plan['id'] }}" class="p-5">
                    <div class="grid gap-4 lg:grid-cols-6">
                        <div class="lg:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" wire:model.defer="plans.{{ $index }}.name" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Max Profile</label>
                            <input type="number" min="1" wire:model.defer="plans.{{ $index }}.max_profiles" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Benutzer</label>
                            <input type="number" min="1" wire:model.defer="plans.{{ $index }}.max_users" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Credits/Monat</label>
                            <input type="number" min="0" wire:model.defer="plans.{{ $index }}.monthly_credits" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Prioritaet</label>
                            <input type="number" min="0" wire:model.defer="plans.{{ $index }}.priority_level" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Historie in Tagen</label>
                            <input type="number" min="1" wire:model.defer="plans.{{ $index }}.max_history_days" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Scanfrequenz in Minuten</label>
                            <input type="number" min="1" wire:model.defer="plans.{{ $index }}.scan_frequency_minutes" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Leistungen</label>
                            <textarea rows="4" wire:model.defer="plans.{{ $index }}.features_text" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                            <p class="mt-1 text-xs text-gray-500">Eine Leistung pro Zeile.</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-5 text-sm text-gray-500">Keine Pakete gefunden.</div>
            @endforelse
        </div>
    </form>

    <form wire:submit.prevent="saveCreditSettings" class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Credit-Verbrauch</h2>
                    <p class="mt-1 text-sm text-gray-500">Variable Kosten pro Funktion.</p>
                </div>
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                    Credits speichern
                </button>
            </div>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Basis-Profilscan</label>
                    <input type="number" min="0" wire:model.defer="creditCosts.profile_scan" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Profilbild pruefen</label>
                    <input type="number" min="0" wire:model.defer="creditCosts.profile_image_scan" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Beitragspruefung</label>
                    <input type="number" min="0" wire:model.defer="creditCosts.post_scan" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Neue Beitraege archivieren</label>
                    <input type="number" min="0" wire:model.defer="creditCosts.new_posts_archive" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Medien pro Datei</label>
                    <input type="number" min="0" wire:model.defer="creditCosts.media_download_per_file" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">KI-Multiplikator</label>
                    <input type="number" min="1" wire:model.defer="creditCosts.ai_analysis_multiplier" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">Zusatz-Creditpakete</h2>
            <p class="mt-1 text-sm text-gray-500">Pakete fuer spaetere Credit-Kaeufe.</p>

            <div class="mt-5 space-y-4">
                @foreach($creditPackages as $index => $package)
                    <div wire:key="credit-package-{{ $index }}" class="grid gap-3 rounded-md border border-gray-200 bg-gray-50 p-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" wire:model.defer="creditPackages.{{ $index }}.name" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Credits</label>
                            <input type="number" min="1" wire:model.defer="creditPackages.{{ $index }}.credits" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </form>
</div>
