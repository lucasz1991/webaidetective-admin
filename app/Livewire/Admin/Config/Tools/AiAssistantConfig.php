<?php

namespace App\Livewire\Admin\Config\Tools;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Livewire\Component;

class AiAssistantConfig extends Component
{
    public bool $status = false;
    public string $assistantName = '';
    public string $apiUrl = '';
    public string $apiKeyInput = '';
    public bool $apiKeyConfigured = false;
    public string $aiModel = '';
    public string $modelTitle = '';
    public string $refererUrl = '';
    public string $trainContent = '';

    public function mount(): void
    {
        $this->status = (bool) Setting::getValue('ai_assistant', 'status');
        $this->assistantName = (string) (Setting::getValue('ai_assistant', 'assistant_name') ?? '');
        $this->apiUrl = (string) (Setting::getValue('ai_assistant', 'api_url') ?? '');
        $this->apiKeyConfigured = filled($this->ensureEncryptedApiKey());
        $this->aiModel = (string) (Setting::getValue('ai_assistant', 'ai_model') ?? '');
        $this->modelTitle = (string) (Setting::getValue('ai_assistant', 'model_title') ?? '');
        $this->refererUrl = (string) (Setting::getValue('ai_assistant', 'referer_url') ?? '');
        $this->trainContent = (string) (Setting::getValue('ai_assistant', 'train_content') ?? '');
    }

    public function saveSettings(): void
    {
        $validated = $this->validate([
            'status' => ['boolean'],
            'assistantName' => ['nullable', 'string', 'max:255'],
            'apiUrl' => ['nullable', 'url'],
            'apiKeyInput' => ['nullable', 'string', 'max:2000'],
            'aiModel' => ['nullable', 'string', 'max:255'],
            'modelTitle' => ['nullable', 'string', 'max:255'],
            'refererUrl' => ['nullable', 'url'],
            'trainContent' => ['nullable', 'string'],
        ]);

        Setting::setValue('ai_assistant', 'status', $validated['status']);
        Setting::setValue('ai_assistant', 'assistant_name', $validated['assistantName'] ?? '');
        Setting::setValue('ai_assistant', 'api_url', $validated['apiUrl'] ?? '');
        Setting::setValue('ai_assistant', 'ai_model', $validated['aiModel'] ?? '');
        Setting::setValue('ai_assistant', 'model_title', $validated['modelTitle'] ?? '');
        Setting::setValue('ai_assistant', 'referer_url', $validated['refererUrl'] ?? '');
        Setting::setValue('ai_assistant', 'train_content', $validated['trainContent'] ?? '');

        if (filled($validated['apiKeyInput'] ?? null)) {
            Setting::setValue('ai_assistant', 'api_key', Crypt::encryptString(trim($validated['apiKeyInput'])));
            $this->apiKeyInput = '';
            $this->apiKeyConfigured = true;
        }

        session()->flash('success', 'AI-Assistent-Einstellungen wurden gespeichert.');
    }

    public function clearApiKey(): void
    {
        Setting::setValue('ai_assistant', 'api_key', null);
        $this->apiKeyInput = '';
        $this->apiKeyConfigured = false;

        session()->flash('success', 'AI API-Key wurde entfernt.');
    }

    private function ensureEncryptedApiKey(): ?string
    {
        $value = Setting::getValue('ai_assistant', 'api_key');

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            Crypt::decryptString($value);

            return $value;
        } catch (\Throwable) {
            $encrypted = Crypt::encryptString(trim($value));
            Setting::setValue('ai_assistant', 'api_key', $encrypted);

            return $encrypted;
        }
    }

    public function render()
    {
        return view('livewire.admin.config.tools.ai-assistant-config');
    }
}
