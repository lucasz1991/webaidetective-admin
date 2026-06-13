<x-settings-collapse>
    <x-slot name="trigger">
        AI-Assistent
    </x-slot>
    <x-slot name="content">
        <div class="space-y-5">
            @if (session()->has('success'))
                <div class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                Konfiguriert den AI-Assistenten im Frontend. Der Anbieter-API-Key wird verschluesselt in der gemeinsamen settings-Tabelle gespeichert und nicht wieder im Formular angezeigt.
            </div>

            <form wire:submit.prevent="saveSettings" class="space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label for="ai-assistant-status" class="flex items-center cursor-pointer">
                        <input
                            id="ai-assistant-status"
                            name="status"
                            type="checkbox"
                            wire:model.live="status"
                            class="sr-only peer"
                        >
                        <div class="relative h-5 w-9 rounded-full bg-gray-200 peer-checked:bg-blue-600 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 after:absolute after:start-[2px] after:top-[2px] after:h-4 after:w-4 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white"></div>
                        <span class="ms-3 text-sm font-medium text-gray-900">AI-Assistent aktiv</span>
                    </label>

                    <x-button type="submit" class="btn-xs text-xs">Speichern</x-button>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="ai-assistant-name" class="block text-sm font-medium text-gray-700">Assistant Name</label>
                        <input id="ai-assistant-name" type="text" wire:model.defer="assistantName" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('assistantName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ai-assistant-model" class="block text-sm font-medium text-gray-700">AI Model</label>
                        <input id="ai-assistant-model" type="text" wire:model.defer="aiModel" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('aiModel') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ai-assistant-api-url" class="block text-sm font-medium text-gray-700">API URL</label>
                        <input id="ai-assistant-api-url" type="url" wire:model.defer="apiUrl" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('apiUrl') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ai-assistant-referer" class="block text-sm font-medium text-gray-700">Referer URL</label>
                        <input id="ai-assistant-referer" type="url" wire:model.defer="refererUrl" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('refererUrl') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ai-assistant-model-title" class="block text-sm font-medium text-gray-700">Modell Titel</label>
                        <input id="ai-assistant-model-title" type="text" wire:model.defer="modelTitle" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error('modelTitle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="ai-assistant-api-key" class="block text-sm font-medium text-gray-700">API Key</label>
                        <input id="ai-assistant-api-key" type="password" wire:model.defer="apiKeyInput" autocomplete="new-password" placeholder="{{ $apiKeyConfigured ? 'Neuen Key eingeben, um bestehenden zu ersetzen' : 'API-Key hinterlegen' }}" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                            <span>{{ $apiKeyConfigured ? 'API-Key ist verschluesselt gespeichert.' : 'Noch kein API-Key gespeichert.' }}</span>
                            @if($apiKeyConfigured)
                                <button type="button" wire:click="clearApiKey" class="font-semibold text-red-600 hover:text-red-700">
                                    Key entfernen
                                </button>
                            @endif
                        </div>
                        @if($apiKeyError)
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $apiKeyError }}</p>
                        @endif
                        @error('apiKeyInput') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="ai-assistant-training" class="block text-sm font-medium text-gray-700">Trainingsinhalt / System-Ergaenzung</label>
                    <textarea id="ai-assistant-training" wire:model.defer="trainContent" rows="8" class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    @error('trainContent') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex justify-end">
                    <x-button type="submit" class="btn-sm text-sm">AI-Assistent speichern</x-button>
                </div>
            </form>
        </div>
    </x-slot>
</x-settings-collapse>
