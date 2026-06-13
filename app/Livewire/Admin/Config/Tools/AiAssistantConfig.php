<?php

namespace App\Livewire\Admin\Config\Tools;

use App\Models\Setting;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AiAssistantConfig extends Component
{
    public bool $status = false;
    public string $assistantName = '';
    public string $apiUrl = '';
    public string $apiKeyInput = '';
    public bool $apiKeyConfigured = false;
    public ?string $apiKeyError = null;
    public string $aiModel = '';
    public string $modelTitle = '';
    public string $refererUrl = '';
    public string $trainContent = '';

    public function mount(): void
    {
        $this->status = (bool) Setting::getValue('ai_assistant', 'status');
        $this->assistantName = (string) (Setting::getValue('ai_assistant', 'assistant_name') ?? '');
        $this->apiUrl = (string) (Setting::getValue('ai_assistant', 'api_url') ?? '');
        $this->apiKeyConfigured = $this->ensureEncryptedApiKey();
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

        DB::transaction(function () use ($validated): void {
            Setting::setValue('ai_assistant', 'status', $validated['status']);
            Setting::setValue('ai_assistant', 'assistant_name', $validated['assistantName'] ?? '');
            Setting::setValue('ai_assistant', 'api_url', $validated['apiUrl'] ?? '');
            Setting::setValue('ai_assistant', 'ai_model', $validated['aiModel'] ?? '');
            Setting::setValue('ai_assistant', 'model_title', $validated['modelTitle'] ?? '');
            Setting::setValue('ai_assistant', 'referer_url', $validated['refererUrl'] ?? '');
            Setting::setValue('ai_assistant', 'train_content', $validated['trainContent'] ?? '');

            if (filled($validated['apiKeyInput'] ?? null)) {
                $plainText = trim($validated['apiKeyInput']);
                $encrypted = $this->baseProjectEncrypter()->encryptString($plainText);
                Setting::setValue('ai_assistant', 'api_key', $encrypted);

                $stored = Setting::getValue('ai_assistant', 'api_key');
                $roundTrip = is_string($stored)
                    ? trim($this->baseProjectEncrypter()->decryptString($stored))
                    : '';

                if (! hash_equals($plainText, $roundTrip)) {
                    throw new \RuntimeException('Der AI API-Key konnte nach dem Speichern nicht verifiziert werden.');
                }
            }
        });

        if (filled($validated['apiKeyInput'] ?? null)) {
            $this->apiKeyInput = '';
            $this->apiKeyConfigured = true;
            $this->apiKeyError = null;
        }

        session()->flash('success', 'AI-Assistent-Einstellungen wurden gespeichert.');
    }

    public function clearApiKey(): void
    {
        Setting::setValue('ai_assistant', 'api_key', null);
        $this->apiKeyInput = '';
        $this->apiKeyConfigured = false;
        $this->apiKeyError = null;

        session()->flash('success', 'AI API-Key wurde entfernt.');
    }

    private function ensureEncryptedApiKey(): bool
    {
        $value = Setting::getValue('ai_assistant', 'api_key');

        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        try {
            $this->baseProjectEncrypter()->decryptString($value);

            return true;
        } catch (\Throwable) {
            try {
                $plainText = Crypt::decryptString($value);
            } catch (\Throwable) {
                $this->apiKeyError = 'Der vorhandene API-Key wurde mit einem unbekannten Schluessel gespeichert. Bitte einen neuen Key eingeben.';

                return false;
            }

            $encrypted = $this->baseProjectEncrypter()->encryptString($plainText);
            Setting::setValue('ai_assistant', 'api_key', $encrypted);

            return true;
        }
    }

    private function baseProjectEncrypter(): Encrypter
    {
        $baseProjectPath = trim((string) (
            env('SCRAPER_BASE_PROJECT_PATH')
            ?? getenv('SCRAPER_BASE_PROJECT_PATH')
            ?: base_path('../webaidetective-base')
        ));
        $environmentPath = rtrim($baseProjectPath, '\\/').DIRECTORY_SEPARATOR.'.env';

        if (! File::exists($environmentPath)) {
            throw new \RuntimeException('Die .env der Base-Installation wurde nicht gefunden.');
        }

        $appKey = null;

        foreach (preg_split('/\r\n|\n|\r/', File::get($environmentPath)) as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'APP_KEY=')) {
                $appKey = trim(substr($line, 8), " \t\n\r\0\x0B\"'");
                break;
            }
        }

        $key = is_string($appKey) && str_starts_with($appKey, 'base64:')
            ? base64_decode(substr($appKey, 7), true)
            : $appKey;

        if (! is_string($key) || ! Encrypter::supported($key, 'AES-256-CBC')) {
            throw new \RuntimeException('Der APP_KEY der Base-Installation ist ungueltig.');
        }

        return new Encrypter($key, 'AES-256-CBC');
    }

    public function render()
    {
        return view('livewire.admin.config.tools.ai-assistant-config');
    }
}
