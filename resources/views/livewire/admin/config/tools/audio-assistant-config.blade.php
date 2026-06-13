<x-settings-collapse>
    <x-slot name="trigger">
        AI-Audio
    </x-slot>

    <x-slot name="content">
        <div class="space-y-5">
            <div class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                Konfiguriert die Modell-IDs fuer Audio-Eingabe und Audio-Ausgabe des Assistenten. Die Werte werden in der gemeinsamen settings-Tabelle gespeichert.
            </div>

            <form wire:submit.prevent="saveSettings" class="space-y-5">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="ai-audio-input-model" class="block text-sm font-medium text-gray-700">
                            Modell fuer Audio-Eingabe
                        </label>
                        <input
                            id="ai-audio-input-model"
                            type="text"
                            wire:model.defer="audioInputModel"
                            placeholder="z. B. whisper-1"
                            class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                        <p class="mt-2 text-xs text-gray-500">
                            Speech-to-Text-Modell fuer die Umwandlung gesprochener Sprache in Text.
                        </p>
                        @error('audioInputModel')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="ai-audio-output-model" class="block text-sm font-medium text-gray-700">
                            Modell fuer Audio-Ausgabe
                        </label>
                        <input
                            id="ai-audio-output-model"
                            type="text"
                            wire:model.defer="audioOutputModel"
                            placeholder="z. B. tts-1"
                            class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                        <p class="mt-2 text-xs text-gray-500">
                            Text-to-Speech-Modell fuer die Sprachausgabe der Assistentenantwort.
                        </p>
                        @error('audioOutputModel')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-button type="submit" class="btn-sm text-sm">
                        AI-Audio speichern
                    </x-button>
                </div>
            </form>
        </div>
    </x-slot>
</x-settings-collapse>
