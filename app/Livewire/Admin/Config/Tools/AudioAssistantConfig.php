<?php

namespace App\Livewire\Admin\Config\Tools;

use App\Models\Setting;
use Livewire\Component;

class AudioAssistantConfig extends Component
{
    public string $audioInputModel = '';

    public string $audioOutputModel = '';

    public function mount(): void
    {
        $this->audioInputModel = (string) (Setting::getValue('ai_assistant', 'audio_input_model') ?? '');
        $this->audioOutputModel = (string) (Setting::getValue('ai_assistant', 'audio_output_model') ?? '');
    }

    public function saveSettings(): void
    {
        $validated = $this->validate([
            'audioInputModel' => ['nullable', 'string', 'max:255'],
            'audioOutputModel' => ['nullable', 'string', 'max:255'],
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

        $this->dispatch('showAlert', 'AI-Audio-Einstellungen wurden gespeichert.', 'success');
    }

    public function render()
    {
        return view('livewire.admin.config.tools.audio-assistant-config');
    }
}
