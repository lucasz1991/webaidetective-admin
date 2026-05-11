<?php

namespace App\Livewire\Admin\Config;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Livewire\Component;

class ScraperSettings extends Component
{
    public string $profileLabel = 'instagram-default';

    public bool $persistentProfileEnabled = true;

    public string $browserProfilePath = 'browser-profiles/instagram/default';

    public string $cookieFilePath = 'cookies/instagram-cookies.json';

    public bool $headlessEnabled = true;

    public bool $autoLoginEnabled = false;

    public string $loginUsername = '';

    public string $loginPassword = '';

    public bool $hasStoredPassword = false;

    public int $navigationTimeoutSeconds = 120;

    public int $postLoginWaitMs = 2500;

    public int $typingDelayMs = 35;

    public ?array $sessionBuildResult = null;

    public function mount(): void
    {
        $settings = Setting::getValue('scraper', 'instagram_profile');

        if (! is_array($settings)) {
            return;
        }

        $this->profileLabel = (string) ($settings['profile_label'] ?? $this->profileLabel);
        $this->persistentProfileEnabled = (bool) ($settings['persistent_profile_enabled'] ?? $this->persistentProfileEnabled);
        $this->browserProfilePath = (string) ($settings['browser_profile_path'] ?? $this->browserProfilePath);
        $this->cookieFilePath = (string) ($settings['cookie_file_path'] ?? $this->cookieFilePath);
        $this->headlessEnabled = true;
        $this->autoLoginEnabled = (bool) ($settings['auto_login_enabled'] ?? $this->autoLoginEnabled);
        $this->loginUsername = (string) ($settings['login_username'] ?? $this->loginUsername);
        $this->navigationTimeoutSeconds = max(30, (int) ($settings['navigation_timeout_seconds'] ?? $this->navigationTimeoutSeconds));
        $this->postLoginWaitMs = max(500, (int) ($settings['post_login_wait_ms'] ?? $this->postLoginWaitMs));
        $this->typingDelayMs = max(0, (int) ($settings['typing_delay_ms'] ?? $this->typingDelayMs));
        $this->hasStoredPassword = filled($settings['login_password_encrypted'] ?? null);
    }

    public function saveSettings(): void
    {
        $this->persistSettings();

        session()->flash('success', 'Scraper-Profil wurde gespeichert.');
        $this->dispatch('showAlert', 'Scraper-Profil wurde gespeichert.', 'success');
    }

    public function buildInstagramSession(): void
    {
        try {
            $storedSettings = $this->persistSettings();
        } catch (\RuntimeException) {
            return;
        }

        try {
            $runtimeConfig = $this->buildRuntimeConfig($storedSettings);
            $baseProjectPath = $this->resolveBaseProjectPath();
            $runtimeConfigPath = $this->writeRuntimeConfigFile($baseProjectPath, $runtimeConfig);
            $nodeScript = $baseProjectPath.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'node'.DIRECTORY_SEPARATOR.'scraper'.DIRECTORY_SEPARATOR.'scrape-instagram.cjs';

            $result = Process::path($baseProjectPath)
                ->timeout(max(240, $this->navigationTimeoutSeconds + 180))
                ->run([
                    $this->resolveNodeBinary(),
                    $nodeScript,
                    '',
                    $runtimeConfigPath,
                    'login-session',
                ]);
        } catch (\Throwable $exception) {
            $this->sessionBuildResult = [
                'ok' => false,
                'statusMessage' => 'Der Session-Aufbau konnte nicht gestartet werden.',
                'warnings' => [$exception->getMessage()],
                'notes' => [],
            ];

            $this->dispatch('showAlert', 'Der Session-Aufbau konnte nicht gestartet werden.', 'error');

            return;
        } finally {
            if (isset($runtimeConfigPath) && File::exists($runtimeConfigPath)) {
                File::delete($runtimeConfigPath);
            }
        }

        $payload = json_decode(trim($result->output()), true);

        if (! is_array($payload)) {
            $this->sessionBuildResult = [
                'ok' => false,
                'statusMessage' => 'Der Session-Aufbau hat kein gueltiges JSON-Ergebnis geliefert.',
                'warnings' => [trim($result->errorOutput())],
                'notes' => [],
            ];

            $this->dispatch('showAlert', 'Der Session-Aufbau ist fehlgeschlagen.', 'error');

            return;
        }

        $this->sessionBuildResult = $payload;
        $this->hasStoredPassword = true;
        $this->loginPassword = '';

        $this->dispatch(
            'showAlert',
            $payload['ok'] ? 'Instagram-Session wurde aufgebaut.' : 'Instagram-Session konnte nicht aufgebaut werden.',
            $payload['ok'] ? 'success' : 'warning'
        );
    }

    public function clearStoredPassword(): void
    {
        $settings = Setting::getValue('scraper', 'instagram_profile');
        $settings = is_array($settings) ? $settings : [];
        $settings['login_password_encrypted'] = null;
        $settings['updated_at'] = now()->toIso8601String();

        Setting::setValue('scraper', 'instagram_profile', $settings);

        $this->loginPassword = '';
        $this->hasStoredPassword = false;

        session()->flash('success', 'Das gespeicherte Instagram-Passwort wurde entfernt.');
        $this->dispatch('showAlert', 'Das gespeicherte Instagram-Passwort wurde entfernt.', 'success');
    }

    public function render()
    {
        return view('livewire.admin.config.scraper-settings');
    }

    private function persistSettings(): array
    {
        $validated = $this->validate([
            'profileLabel' => ['required', 'string', 'max:120'],
            'persistentProfileEnabled' => ['boolean'],
            'browserProfilePath' => ['required', 'string', 'max:255'],
            'cookieFilePath' => ['required', 'string', 'max:255'],
            'autoLoginEnabled' => ['boolean'],
            'loginUsername' => ['nullable', 'string', 'max:255'],
            'loginPassword' => ['nullable', 'string', 'max:255'],
            'navigationTimeoutSeconds' => ['required', 'integer', 'min:30', 'max:300'],
            'postLoginWaitMs' => ['required', 'integer', 'min:500', 'max:15000'],
            'typingDelayMs' => ['required', 'integer', 'min:0', 'max:500'],
        ]);

        $existingSettings = Setting::getValue('scraper', 'instagram_profile');
        $existingPassword = is_array($existingSettings)
            ? ($existingSettings['login_password_encrypted'] ?? null)
            : null;

        $encryptedPassword = $existingPassword;

        if (filled($validated['loginPassword'] ?? null)) {
            $encryptedPassword = Crypt::encryptString($validated['loginPassword']);
        }

        if ($validated['autoLoginEnabled'] && (blank($validated['loginUsername']) || blank($encryptedPassword))) {
            $this->addError('loginUsername', 'Bitte hinterlege fuer den Auto-Login einen Instagram-Benutzernamen und ein Passwort.');

            throw new \RuntimeException('Auto-Login-Konfiguration unvollstaendig.');
        }

        $settings = [
            'profile_label' => trim($validated['profileLabel']),
            'persistent_profile_enabled' => (bool) $validated['persistentProfileEnabled'],
            'browser_profile_path' => trim($validated['browserProfilePath']),
            'cookie_file_path' => trim($validated['cookieFilePath']),
            'headless_enabled' => true,
            'auto_login_enabled' => (bool) $validated['autoLoginEnabled'],
            'login_username' => trim((string) ($validated['loginUsername'] ?? '')),
            'login_password_encrypted' => $encryptedPassword,
            'navigation_timeout_seconds' => (int) $validated['navigationTimeoutSeconds'],
            'post_login_wait_ms' => (int) $validated['postLoginWaitMs'],
            'typing_delay_ms' => (int) $validated['typingDelayMs'],
            'updated_at' => now()->toIso8601String(),
        ];

        Setting::setValue('scraper', 'instagram_profile', $settings);

        $this->loginPassword = '';
        $this->hasStoredPassword = filled($encryptedPassword);
        $this->headlessEnabled = true;

        return $settings;
    }

    private function buildRuntimeConfig(array $storedSettings): array
    {
        return [
            'profileLabel' => (string) ($storedSettings['profile_label'] ?? 'instagram-default'),
            'persistentProfileEnabled' => (bool) ($storedSettings['persistent_profile_enabled'] ?? true),
            'browserProfilePath' => $this->resolveStorageAwarePath($storedSettings['browser_profile_path'] ?? 'browser-profiles/instagram/default'),
            'cookieFilePath' => $this->resolveStorageAwarePath($storedSettings['cookie_file_path'] ?? 'cookies/instagram-cookies.json'),
            'headlessEnabled' => false,
            'autoLoginEnabled' => (bool) ($storedSettings['auto_login_enabled'] ?? false),
            'loginUsername' => trim((string) ($storedSettings['login_username'] ?? '')),
            'loginPassword' => $this->decryptRuntimePassword($storedSettings['login_password_encrypted'] ?? null),
            'navigationTimeoutMs' => max(30000, ((int) ($storedSettings['navigation_timeout_seconds'] ?? 120)) * 1000),
            'postLoginWaitMs' => max(500, (int) ($storedSettings['post_login_wait_ms'] ?? 2500)),
            'typingDelayMs' => max(0, (int) ($storedSettings['typing_delay_ms'] ?? 35)),
        ];
    }

    private function decryptRuntimePassword(mixed $encryptedPassword): ?string
    {
        if (! is_string($encryptedPassword) || trim($encryptedPassword) === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encryptedPassword);
        } catch (\Throwable) {
            return null;
        }
    }

    private function writeRuntimeConfigFile(string $baseProjectPath, array $runtimeConfig): string
    {
        $directory = $baseProjectPath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'tmp';
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.'instagram-scraper-session-'.Str::uuid().'.json';
        File::put($path, json_encode($runtimeConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }

    private function resolveBaseProjectPath(): string
    {
        return dirname(base_path()).DIRECTORY_SEPARATOR.'webaidetective-base';
    }

    private function resolveStorageAwarePath(string $configuredPath): string
    {
        $configuredPath = trim($configuredPath);

        if ($configuredPath === '') {
            return $this->resolveBaseProjectPath().DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app';
        }

        if ($this->isAbsolutePath($configuredPath)) {
            return $configuredPath;
        }

        return $this->resolveBaseProjectPath().DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configuredPath), DIRECTORY_SEPARATOR);
    }

    private function resolveNodeBinary(): string
    {
        $candidates = [
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe',
            '/usr/bin/node',
            '/usr/local/bin/node',
        ];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Node.js wurde fuer den Session-Aufbau nicht gefunden.');
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:\\\\/', $path) === 1
            || preg_match('/^[A-Za-z]:\//', $path) === 1;
    }
}
