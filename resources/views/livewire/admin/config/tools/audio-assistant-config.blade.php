<x-settings-collapse>
    <x-slot name="trigger">
        AI-Audio
    </x-slot>

    <x-slot name="content">
        <div class="space-y-5">
            <div class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                Konfiguriert die Modell-IDs fuer Audio-Eingabe und Audio-Ausgabe des Assistenten. Die Werte werden in der gemeinsamen settings-Tabelle gespeichert und von der Base-Installation fuer den AI-TTS-Audiostream genutzt.
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
                            Speech-to-Text-Modell fuer die Umwandlung gesprochener Sprache in Text. Die aktuelle Chat-Eingabe kann weiterhin unveraendert bleiben.
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
                            placeholder="z. B. tts-1 oder gpt-4o-mini-tts"
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

                <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                    <div class="mb-4">
                        <h4 class="text-sm font-semibold text-gray-900">Separater Endpoint fuer Audio-Ausgabe</h4>
                        <p class="mt-1 text-xs leading-5 text-gray-600">
                            Hier kann ein eigener TTS-/Audio-Speech-Endpoint hinterlegt werden. Wenn das Feld leer bleibt, nutzt die Base-Installation automatisch einen Fallback: zuerst eine aus der Chat-URL abgeleitete <code class="rounded bg-white px-1 py-0.5">/audio/speech</code>-URL, sonst <code class="rounded bg-white px-1 py-0.5">https://api.openai.com/v1/audio/speech</code>.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label for="ai-audio-output-api-url" class="block text-sm font-medium text-gray-700">
                                Audio-Ausgabe Endpoint-URL
                            </label>
                            <input
                                id="ai-audio-output-api-url"
                                type="url"
                                wire:model.defer="audioOutputApiUrl"
                                placeholder="z. B. https://api.openai.com/v1/audio/speech"
                                class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                            <p class="mt-2 text-xs text-gray-500">
                                Muss ein OpenAI-kompatibler oder provider-kompatibler Text-to-Speech-Endpoint sein, der Audio als Response zurueckgibt. Fuer OpenRouter nur eintragen, wenn der verwendete Provider diesen Audio-Endpoint unterstuetzt.
                            </p>
                            @error('audioOutputApiUrl')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label for="ai-audio-output-voice" class="block text-sm font-medium text-gray-700">
                                    Stimme fuer Audio-Ausgabe
                                </label>
                                <input
                                    id="ai-audio-output-voice"
                                    type="text"
                                    wire:model.defer="audioOutputVoice"
                                    placeholder="z. B. alloy, nova, shimmer"
                                    class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                <p class="mt-2 text-xs text-gray-500">
                                    Voice-ID des TTS-Anbieters. Wenn leer, nutzt die Base-Ausgabe <code class="rounded bg-white px-1 py-0.5">alloy</code> als Standard.
                                </p>
                                @error('audioOutputVoice')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="ai-audio-output-format" class="block text-sm font-medium text-gray-700">
                                    Audio-Format
                                </label>
                                <select
                                    id="ai-audio-output-format"
                                    wire:model.defer="audioOutputFormat"
                                    class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option value="mp3">MP3 / audio/mpeg empfohlen</option>
                                    <option value="opus">OPUS / audio/opus</option>
                                    <option value="wav">WAV / audio/wav</option>
                                    <option value="pcm">PCM / audio/pcm</option>
                                </select>
                                <p class="mt-2 text-xs text-gray-500">
                                    Fuer den Browser-Audiostream ist <code class="rounded bg-white px-1 py-0.5">mp3</code> am kompatibelsten.
                                </p>
                                @error('audioOutputFormat')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
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
