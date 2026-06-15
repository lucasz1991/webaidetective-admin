<x-settings-collapse>
    <x-slot name="trigger">
        AI-Audio
    </x-slot>

    <x-slot name="content">
        <div class="space-y-5">
            <div class="rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                Konfiguriert die Audio-Einstellungen des Assistenten. Die Audio-Ausgabe ist auf OpenRouter ausgelegt und nutzt den allgemeinen OpenRouter API-Key, Referer und Modell-Titel aus der AI-Assistent-Konfiguration.
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
                            placeholder="optional, Eingabe bleibt aktuell unveraendert"
                            class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                        <p class="mt-2 text-xs text-gray-500">
                            Optionales Speech-to-Text-Modell. Die aktuelle Chat-Eingabe kann weiterhin unveraendert bleiben.
                        </p>
                        @error('audioInputModel')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="ai-audio-output-model" class="block text-sm font-medium text-gray-700">
                            OpenRouter-Modell fuer Audio-Ausgabe
                        </label>
                        <input
                            id="ai-audio-output-model"
                            type="text"
                            wire:model.defer="audioOutputModel"
                            placeholder="z. B. x-ai/grok-voice-tts-1.0"
                            class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                        <p class="mt-2 text-xs text-gray-500">
                            Modell-ID, die OpenRouter fuer Text-to-Speech akzeptiert. Fuer deutsche MP3-Ausgabe kann beispielsweise <code class="rounded bg-white px-1 py-0.5">x-ai/grok-voice-tts-1.0</code> verwendet werden.
                        </p>
                        @error('audioOutputModel')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                    <div class="mb-4">
                        <h4 class="text-sm font-semibold text-gray-900">OpenRouter Endpoint fuer Audio-Ausgabe</h4>
                        <p class="mt-1 text-xs leading-5 text-gray-600">
                            Die Base-Installation sendet die TTS-Anfragen fuer die Audio-Ausgabe ueber OpenRouter. Wenn das Feld leer bleibt, wird automatisch aus der AI-Assistent Chat-URL <code class="rounded bg-white px-1 py-0.5">/chat/completions</code> die URL <code class="rounded bg-white px-1 py-0.5">/audio/speech</code> abgeleitet. Falls das nicht moeglich ist, wird <code class="rounded bg-white px-1 py-0.5">{{ $this->openRouterAudioSpeechUrl() }}</code> genutzt.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label for="ai-audio-output-api-url" class="block text-sm font-medium text-gray-700">
                                OpenRouter Audio-Ausgabe Endpoint-URL
                            </label>
                            <input
                                id="ai-audio-output-api-url"
                                type="url"
                                wire:model.defer="audioOutputApiUrl"
                                placeholder="{{ $this->openRouterAudioSpeechUrl() }}"
                                class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                            <p class="mt-2 text-xs text-gray-500">
                                Erlaubt ist nur <code class="rounded bg-white px-1 py-0.5">{{ $this->openRouterAudioSpeechUrl() }}</code>. Leer lassen, wenn die Base automatisch den OpenRouter-Standard verwenden soll.
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
                                    placeholder="z. B. Eve, Ara, Rex, Sal oder Leo"
                                    class="mt-1 w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                <p class="mt-2 text-xs text-gray-500">
                                    Voice-ID des TTS-Anbieters. Fuer <code class="rounded bg-white px-1 py-0.5">x-ai/grok-voice-tts-1.0</code> sind beispielsweise Eve, Ara, Rex, Sal und Leo verfuegbar.
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
                                    Gemini-TTS wird beim Speichern automatisch auf PCM gesetzt und von der Base als browserkompatibles WAV ausgeliefert. Fuer xAI ist MP3 geeignet.
                                </p>
                                @error('audioOutputFormat')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-900">
                    Wichtig: Das Chat-Modell ist normalerweise kein TTS-Modell. Trage hier ein Modell ein, das bei OpenRouter die Output-Modalitaet <code class="rounded bg-white px-1 py-0.5">speech</code> unterstuetzt.
                </div>

                <div class="flex justify-end">
                    <x-button type="submit" class="btn-sm text-sm">
                        OpenRouter-Audio speichern
                    </x-button>
                </div>
            </form>
        </div>
    </x-slot>
</x-settings-collapse>
