<?php

namespace Tests\Feature;

use Tests\TestCase;

class AudioAssistantConfigTest extends TestCase
{
    public function test_audio_settings_are_an_independent_admin_livewire_module(): void
    {
        $component = file_get_contents(app_path('Livewire/Admin/Config/Tools/AudioAssistantConfig.php'));
        $view = file_get_contents(resource_path('views/livewire/admin/config/tools/audio-assistant-config.blade.php'));
        $adminConfig = file_get_contents(resource_path('views/livewire/admin-config.blade.php'));

        $this->assertStringContainsString("'audio_input_model'", $component);
        $this->assertStringContainsString("'audio_output_model'", $component);
        $this->assertStringContainsString('isOpenRouterSpeechUrl', $component);
        $this->assertStringContainsString('/api/v1/audio/speech', $component);
        $this->assertStringContainsString('wire:submit.prevent="saveSettings"', $view);
        $this->assertStringContainsString('Output-Modalitaet', $view);
        $this->assertStringContainsString('x-ai/grok-voice-tts-1.0', $view);
        $this->assertStringContainsString('requiresPcmFormat', $component);
        $this->assertStringContainsString('browserkompatibles WAV', $view);
        $this->assertStringContainsString("@livewire('admin.config.tools.audio-assistant-config')", $adminConfig);
    }
}
