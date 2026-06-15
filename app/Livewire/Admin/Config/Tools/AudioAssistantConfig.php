<?php

namespace App\Livewire\Admin\Config\Tools;

use App\Models\Setting;
use Illuminate\Support\Str;
use Livewire\Component;

class AudioAssistantConfig extends Component
{
    private const OPENROUTER_AUDIO_SPEECH_URL = 'https://openrouter.ai/api/v1/audio/speech';

    public string $audioInputModel = '';

    public string $audioOutputModel = '';

    public string $audioOutputApiUrl = '';

    public string $audioOutputVoice = '';

    public string $audioOutputFormat = 'mp3';

    public function mount(): void
    {
        $this->audioInputModel = (string) (Setting::getValue('ai_assistant', 'audio_input_model') ?? '');
        $this->audioOutputModel = (string) (Setting::getValue('ai_assistant', 'audio_output_model') ?? '');
        $this->audioOutputApiUrl = (string) (Setting::getValue('ai_assistant', 'audio_output_api_url') ?? '');
        $this->audioOutputVoice = (string) (Setting::getValue('ai_assistant', 'audio_output_voice') ?? '');
        $this->audioOutputFormat = (string) (Setting::getValue('ai_assistant', 'audio_output_format') ?? 'mp3');

        if (! in_array($this->audioOutputFormat, ['mp3', 'opus', 'wav', 'pcm'], true)) {
            $this->audioOutputFormat = 'mp3';
        }
    }

    public function saveSettings(): void
    {
        $validated = $this->validate([
            'audioInputModel' => ['nullable', 'string', 'max:255'],
            'audioOutputModel' => ['nullable', 'string', 'max:255'],
            'audioOutputApiUrl' => ['nullable', 'url', 'max:2048'],
            'audioOutputVoice' => ['nullable', 'string', 'max:80'],
            'audioOutputFormat' => ['required', 'in:mp3,opus,wav,pcm'],
        ]);

        $audioOutputApiUrl = trim((string) ($validated['audioOutputApiUrl'] ?? ''));

        if ($audioOutputApiUrl !== '' && ! $this->isOpenRouterUrl($audioOutputApiUrl)) {
            $this->addError('audioOutputApiUrl', 'Die Audio-Ausgabe soll ueber OpenRouter laufen. Bitte eine OpenRouter-URL verwenden oder das Feld leer lassen.');

            return;
        }

        Setting::setValue(
            'ai_assistant',
            'audio_input_model',
            trim((string) ($validated['audioInputModel'] ?? '')),
        );
        Setting::setValue(
            'ai_assistant',
            'audio_output_model',
            trim((string) ($validated['audioOutputModel'] ?? '')),
        );
        Setting::setValue(
            'ai_assistant',
            'audio_output_api_url',
            $audioOutputApiUrl,
        );
        Setting::setValue(
            'ai_assistant',
            'audio_output_voice',
            trim((string) ($validated['audioOutputVoice'] ?? '')),
        );
        Setting::setValue(
            'ai_assistant',
            'audio_output_format',
            (string) ($validated['audioOutputFormat'] ?? 'mp3'),
        );

        $this->dispatch('showAlert', 'OpenRouter-Audio-Einstellungen wurden gespeichert.', 'success');
    }

    public function openRouterAudioSpeechUrl(): string
    {
        return self::OPENROUTER_AUDIO_SPEECH_URL;
    }

    private function isOpenRouterUrl(string $url): bool
    {
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'openrouter.ai' || Str::endsWith($host, '.openrouter.ai');
    }

    public function render()
    {
        return view('livewire.admin.config.tools.audio-assistant-config');
    }
}
