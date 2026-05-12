<?php

namespace App\Livewire\Admin\Config;

use Illuminate\Encryption\Encrypter;
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

    public int $relationshipListProcessTimeoutSeconds = 14400;

    public int $relationshipListMaxScrollRounds = 100000;

    public int $followerListMaxItems = 0;

    public int $followingListMaxItems = 0;

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
        $this->relationshipListProcessTimeoutSeconds = max(14400, (int) ($settings['relationship_list_process_timeout_seconds'] ?? $this->relationshipListProcessTimeoutSeconds));
        $this->relationshipListMaxScrollRounds = max(20, (int) ($settings['relationship_list_max_scroll_rounds'] ?? $this->relationshipListMaxScrollRounds));
        $this->followerListMaxItems = max(0, (int) ($settings['follower_list_max_items'] ?? $this->followerListMaxItems));
        $this->followingListMaxItems = max(0, (int) ($settings['following_list_max_items'] ?? $this->followingListMaxItems));
        $this->hasStoredPassword = filled($settings['login_password_encrypted'] ?? null)
            || filled($settings['login_password_base_encrypted'] ?? null);
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
            $nodeScript = $this->resolveBaseNodeScriptPath($baseProjectPath);

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

        if (! $result->successful()) {
            $warnings = array_values(array_filter([
                trim($result->errorOutput()),
                trim($result->output()),
            ]));

            $this->sessionBuildResult = [
                'ok' => false,
                'statusMessage' => 'Der Session-Aufbau ist beim Start des Node-Skripts fehlgeschlagen.',
                'warnings' => $warnings !== [] ? $warnings : ['Der Node-Prozess wurde mit einem Fehler beendet.'],
                'notes' => [],
            ];

            $this->dispatch('showAlert', 'Der Session-Aufbau ist fehlgeschlagen.', 'error');

            return;
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
        $settings['login_password_base_encrypted'] = null;
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
            'relationshipListProcessTimeoutSeconds' => ['required', 'integer', 'min:14400', 'max:21600'],
            'relationshipListMaxScrollRounds' => ['required', 'integer', 'min:20', 'max:1000000'],
            'followerListMaxItems' => ['required', 'integer', 'min:0', 'max:1000000'],
            'followingListMaxItems' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $existingSettings = Setting::getValue('scraper', 'instagram_profile');
        $existingPassword = is_array($existingSettings)
            ? ($existingSettings['login_password_encrypted'] ?? null)
            : null;
        $existingBasePassword = is_array($existingSettings)
            ? ($existingSettings['login_password_base_encrypted'] ?? null)
            : null;

        $encryptedPassword = $existingPassword;
        $runtimePassword = $this->decryptRuntimePassword($existingPassword);

        if (filled($validated['loginPassword'] ?? null)) {
            $runtimePassword = $validated['loginPassword'];
            $encryptedPassword = Crypt::encryptString($runtimePassword);
        }

        $baseEncryptedPassword = $existingBasePassword;

        if (filled($runtimePassword)) {
            $baseEncryptedPassword = $this->encryptPasswordForBaseProject($runtimePassword);
        }

        if ($validated['autoLoginEnabled'] && (blank($validated['loginUsername']) || blank($runtimePassword))) {
            $this->addError('loginUsername', 'Bitte hinterlege fuer den Auto-Login einen Instagram-Benutzernamen und ein Passwort.');

            throw new \RuntimeException('Auto-Login-Konfiguration unvollstaendig.');
        }

        if ($validated['autoLoginEnabled'] && blank($baseEncryptedPassword)) {
            $this->addError('loginPassword', 'Das gespeicherte Passwort konnte nicht fuer die Base-Installation aufbereitet werden.');

            throw new \RuntimeException('Base-Passwortverschluesselung fehlgeschlagen.');
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
            'login_password_base_encrypted' => $baseEncryptedPassword,
            'navigation_timeout_seconds' => (int) $validated['navigationTimeoutSeconds'],
            'post_login_wait_ms' => (int) $validated['postLoginWaitMs'],
            'typing_delay_ms' => (int) $validated['typingDelayMs'],
            'relationship_list_process_timeout_seconds' => (int) $validated['relationshipListProcessTimeoutSeconds'],
            'relationship_list_max_scroll_rounds' => (int) $validated['relationshipListMaxScrollRounds'],
            'follower_list_max_items' => (int) $validated['followerListMaxItems'],
            'following_list_max_items' => (int) $validated['followingListMaxItems'],
            'updated_at' => now()->toIso8601String(),
        ];

        Setting::setValue('scraper', 'instagram_profile', $settings);

        $this->loginPassword = '';
        $this->hasStoredPassword = filled($encryptedPassword) || filled($baseEncryptedPassword);
        $this->headlessEnabled = true;

        return $settings;
    }

    private function buildRuntimeConfig(array $storedSettings): array
    {
        $decryptedPassword = $this->decryptRuntimePassword($storedSettings['login_password_encrypted'] ?? null);
        $passwordConfigured = filled($storedSettings['login_password_encrypted'] ?? null)
            || filled($storedSettings['login_password_base_encrypted'] ?? null);

        return [
            'profileLabel' => (string) ($storedSettings['profile_label'] ?? 'instagram-default'),
            'persistentProfileEnabled' => (bool) ($storedSettings['persistent_profile_enabled'] ?? true),
            'browserProfilePath' => $this->resolveStorageAwarePath($storedSettings['browser_profile_path'] ?? 'browser-profiles/instagram/default'),
            'cookieFilePath' => $this->resolveStorageAwarePath($storedSettings['cookie_file_path'] ?? 'cookies/instagram-cookies.json'),
            'headlessEnabled' => false,
            'autoLoginEnabled' => (bool) ($storedSettings['auto_login_enabled'] ?? false),
            'loginUsername' => trim((string) ($storedSettings['login_username'] ?? '')),
            'loginPassword' => $decryptedPassword,
            'loginPasswordConfigured' => $passwordConfigured,
            'loginPasswordDecryptable' => $decryptedPassword !== null || ! $passwordConfigured,
            'navigationTimeoutMs' => max(30000, ((int) ($storedSettings['navigation_timeout_seconds'] ?? 120)) * 1000),
            'postLoginWaitMs' => max(500, (int) ($storedSettings['post_login_wait_ms'] ?? 2500)),
            'typingDelayMs' => max(0, (int) ($storedSettings['typing_delay_ms'] ?? 35)),
            'followerListMaxItems' => max(0, (int) ($storedSettings['follower_list_max_items'] ?? 0)),
            'followingListMaxItems' => max(0, (int) ($storedSettings['following_list_max_items'] ?? 0)),
            'relationshipListMaxScrollRounds' => max(20, (int) ($storedSettings['relationship_list_max_scroll_rounds'] ?? 100000)),
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
        $configuredPath = trim((string) (
            env('SCRAPER_BASE_PROJECT_PATH')
            ?? getenv('SCRAPER_BASE_PROJECT_PATH')
            ?? ''
        ));

        $candidates = array_filter([
            $configuredPath,
            dirname(base_path()).DIRECTORY_SEPARATOR.'webaidetective',
        ], static fn (mixed $candidate): bool => is_string($candidate) && trim($candidate) !== '');

        foreach ($candidates as $candidate) {
            if (File::isDirectory($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Das Base-Projekt wurde nicht gefunden. Setze bei Bedarf `SCRAPER_BASE_PROJECT_PATH` auf den absoluten Pfad der Base-Installation.');
    }

    private function resolveBaseNodeScriptPath(string $baseProjectPath): string
    {
        $nodeScript = $baseProjectPath.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'node'.DIRECTORY_SEPARATOR.'scraper'.DIRECTORY_SEPARATOR.'scrape-instagram.cjs';

        if (! File::exists($nodeScript)) {
            throw new \RuntimeException(sprintf(
                'Das Node-Skript fuer den Session-Aufbau wurde nicht gefunden: %s',
                $nodeScript
            ));
        }

        return $nodeScript;
    }

    private function encryptPasswordForBaseProject(?string $plainPassword): ?string
    {
        $plainPassword = trim((string) $plainPassword);

        if ($plainPassword === '') {
            return null;
        }

        $baseAppKey = $this->resolveBaseAppKey();

        if (! $baseAppKey) {
            return null;
        }

        return $this->makeBaseProjectEncrypter($baseAppKey)->encryptString($plainPassword);
    }

    private function resolveBaseAppKey(): ?string
    {
        $environmentPath = $this->resolveBaseProjectPath().DIRECTORY_SEPARATOR.'.env';

        if (! File::exists($environmentPath)) {
            return null;
        }

        foreach (preg_split('/\r\n|\n|\r/', File::get($environmentPath)) as $line) {
            $trimmedLine = trim($line);

            if (! str_starts_with($trimmedLine, 'APP_KEY=')) {
                continue;
            }

            return trim(substr($trimmedLine, 8), " \t\n\r\0\x0B\"'");
        }

        return null;
    }

    private function makeBaseProjectEncrypter(string $appKey): Encrypter
    {
        $key = str_starts_with($appKey, 'base64:')
            ? base64_decode(substr($appKey, 7), true)
            : $appKey;

        if (! is_string($key) || strlen($key) !== 32) {
            throw new \RuntimeException('Der APP_KEY der Base-Installation ist ungueltig.');
        }

        return new Encrypter($key, config('app.cipher', 'AES-256-CBC'));
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
        $environmentCandidates = array_filter([
            env('SCRAPER_NODE_BINARY'),
            env('NODE_BINARY'),
            getenv('SCRAPER_NODE_BINARY') ?: null,
            getenv('NODE_BINARY') ?: null,
        ], static fn (mixed $candidate): bool => is_string($candidate) && trim($candidate) !== '');

        $candidates = array_merge($environmentCandidates, [
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe',
            '/usr/bin/node',
            '/usr/local/bin/node',
            '/bin/node',
            '/snap/bin/node',
            '/usr/bin/nodejs',
            '/usr/local/bin/nodejs',
        ]);

        foreach (glob('/opt/plesk/node/*/bin/node') ?: [] as $pleskCandidate) {
            $candidates[] = $pleskCandidate;
        }

        $homeDirectory = getenv('HOME') ?: null;

        if (is_string($homeDirectory) && trim($homeDirectory) !== '') {
            foreach (glob($homeDirectory.'/.nvm/versions/node/*/bin/node') ?: [] as $nvmCandidate) {
                $candidates[] = $nvmCandidate;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '' && is_executable($candidate)) {
                return $candidate;
            }
        }

        foreach (['node', 'nodejs'] as $binaryName) {
            $resolvedBinary = Process::run(['sh', '-lc', sprintf('command -v %s 2>/dev/null', $binaryName)]);

            if (! $resolvedBinary->successful()) {
                continue;
            }

            $candidate = trim($resolvedBinary->output());

            if ($candidate !== '' && is_executable($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Node.js wurde fuer den Session-Aufbau nicht gefunden. Geprueft wurden feste Pfade, Plesk-/NVM-Installationen sowie `command -v node` und `command -v nodejs`.');
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:\\\\/', $path) === 1
            || preg_match('/^[A-Za-z]:\//', $path) === 1;
    }
}
