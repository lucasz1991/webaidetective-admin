<?php

namespace App\Livewire\Admin\Config\Tools;

use App\Models\Setting;
use Livewire\Component;

class AudioAssistantConfig extends Component
{
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
            trim((string) ($validated['audioOutputApiUrl'] ?? '')),
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

        $this->dispatch('showAlert', 'AI-Audio-Einstellungen wurden gespeichert.', 'success');
    }

    public function render()
    {
        return view('livewire.admin.config.tools.audio-assistant-config');
    }
}
