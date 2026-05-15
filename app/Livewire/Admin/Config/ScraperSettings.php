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
    public string $activeProfileId = '';

    public array $activeProfileIds = [];

    public array $profileOptions = [];

    public bool $showCreateProfileModal = false;

    public bool $showProfileModal = false;

    public bool $showRuntimeSettingsModal = false;

    public string $editingProfileId = '';

    public string $newProfileLabel = '';

    public bool $newAutoLoginEnabled = false;

    public string $newLoginUsername = '';

    public string $newLoginPassword = '';

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
        $collection = $this->loadProfileCollection();

        $this->activeProfileId = $collection['active_profile_id'];
        $this->activeProfileIds = $collection['active_profile_ids'];
        $this->profileOptions = $this->buildProfileOptions($collection);
        $this->fillFormFromProfile($this->findProfile($collection, $this->activeProfileId));
    }

    public function saveSettings(): void
    {
        $this->saveProfile();
    }

    public function saveProfile(): void
    {
        if ($this->editingProfileId === '') {
            return;
        }

        $collection = $this->loadProfileCollection();

        try {
            $collection = $this->persistProfileFormInCollection($collection, $this->editingProfileId);
        } catch (\RuntimeException) {
            return;
        }

        $this->persistProfileCollection($collection);
        $this->activeProfileId = $collection['active_profile_id'];
        $this->activeProfileIds = $collection['active_profile_ids'];
        $this->profileOptions = $this->buildProfileOptions($collection);
        $this->loginPassword = '';
        $this->showProfileModal = false;
        $this->editingProfileId = '';

        session()->flash('success', 'Scraper-Account wurde gespeichert.');
        $this->dispatch('showAlert', 'Scraper-Account wurde gespeichert.', 'success');
    }

    public function addProfile(): void
    {
        $this->openCreateProfileModal();
    }

    public function openCreateProfileModal(): void
    {
        $collection = $this->loadProfileCollection();
        $nextNumber = count($collection['profiles']) + 1;

        $this->newProfileLabel = 'instagram-profil-'.$nextNumber;
        $this->newAutoLoginEnabled = false;
        $this->newLoginUsername = '';
        $this->newLoginPassword = '';
        $this->showCreateProfileModal = true;

        $this->resetErrorBag();
    }

    public function closeCreateProfileModal(): void
    {
        $this->showCreateProfileModal = false;
        $this->newProfileLabel = '';
        $this->newAutoLoginEnabled = false;
        $this->newLoginUsername = '';
        $this->newLoginPassword = '';

        $this->resetErrorBag();
    }

    public function createProfile(): void
    {
        try {
            $validated = $this->validate([
                'newProfileLabel' => ['required', 'string', 'max:120'],
                'newAutoLoginEnabled' => ['boolean'],
                'newLoginUsername' => ['nullable', 'string', 'max:255'],
                'newLoginPassword' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validated['newAutoLoginEnabled'] && (blank($validated['newLoginUsername']) || blank($validated['newLoginPassword']))) {
                $this->addError('newLoginUsername', 'Bitte hinterlege fuer den Auto-Login einen Instagram-Benutzernamen und ein Passwort.');

                return;
            }

            $collection = $this->loadProfileCollection();
        } catch (\RuntimeException) {
            $this->showCreateProfileModal = false;

            return;
        }

        try {
            $profile = $this->makeNewProfile($collection, $validated);
        } catch (\RuntimeException) {
            return;
        }

        $collection['profiles'][] = $profile;
        $collection['active_profile_id'] = $profile['id'];
        $collection['active_profile_ids'] = $this->appendActiveProfileId($collection['active_profile_ids'] ?? [], $profile['id']);
        $collection['updated_at'] = now()->toIso8601String();

        $this->persistProfileCollection($collection);
        $this->activeProfileId = $profile['id'];
        $this->activeProfileIds = $collection['active_profile_ids'];
        $this->profileOptions = $this->buildProfileOptions($collection);
        $this->fillFormFromProfile($profile);
        $this->sessionBuildResult = null;
        $this->showCreateProfileModal = false;
        $this->newLoginPassword = '';

        session()->flash('success', 'Neuer Scraper-Account wurde angelegt.');
        $this->dispatch('showAlert', 'Neuer Scraper-Account wurde angelegt.', 'success');
    }

    public function switchProfile(string $profileId): void
    {
        $this->makePrimaryProfile($profileId);
    }

    public function makePrimaryProfile(string $profileId): void
    {
        $collection = $this->loadProfileCollection();
        $profile = $this->findProfile($collection, $profileId);

        if (! $profile) {
            $this->dispatch('showAlert', 'Das ausgewaehlte Scraper-Profil wurde nicht gefunden.', 'error');

            return;
        }

        $collection['active_profile_id'] = $profile['id'];
        $collection['active_profile_ids'] = $this->appendActiveProfileId($collection['active_profile_ids'] ?? [], $profile['id']);
        $collection['updated_at'] = now()->toIso8601String();

        $this->persistProfileCollection($collection);
        $this->activeProfileId = $profile['id'];
        $this->activeProfileIds = $collection['active_profile_ids'];
        $this->profileOptions = $this->buildProfileOptions($collection);
        $this->sessionBuildResult = null;

        $this->dispatch('showAlert', 'Standard-Account wurde gewechselt.', 'success');
    }

    public function toggleProfileActive(string $profileId): void
    {
        $collection = $this->loadProfileCollection();
        $profile = $this->findProfile($collection, $profileId);

        if (! $profile) {
            $this->dispatch('showAlert', 'Der Scraper-Account wurde nicht gefunden.', 'error');

            return;
        }

        $activeProfileIds = $collection['active_profile_ids'];

        if (in_array($profileId, $activeProfileIds, true)) {
            if (count($activeProfileIds) <= 1) {
                $this->dispatch('showAlert', 'Mindestens ein Account muss fuer Analysen aktiv bleiben.', 'warning');

                return;
            }

            $activeProfileIds = array_values(array_filter(
                $activeProfileIds,
                static fn (string $activeProfileId): bool => $activeProfileId !== $profileId,
            ));
        } else {
            $activeProfileIds = $this->appendActiveProfileId($activeProfileIds, $profileId);
        }

        if ($collection['active_profile_id'] === $profileId && ! in_array($profileId, $activeProfileIds, true)) {
            $collection['active_profile_id'] = $activeProfileIds[0];
        }

        $collection['active_profile_ids'] = $activeProfileIds;
        $collection['updated_at'] = now()->toIso8601String();

        $this->persistProfileCollection($collection);
        $this->activeProfileId = $collection['active_profile_id'];
        $this->activeProfileIds = $collection['active_profile_ids'];
        $this->profileOptions = $this->buildProfileOptions($collection);
        $this->sessionBuildResult = null;

        $this->dispatch('showAlert', 'Account-Aktivierung wurde aktualisiert.', 'success');
    }

    public function editProfile(string $profileId): void
    {
        $collection = $this->loadProfileCollection();
        $profile = $this->findProfile($collection, $profileId);

        if (! $profile) {
            $this->dispatch('showAlert', 'Der Scraper-Account wurde nicht gefunden.', 'error');

            return;
        }

        $this->editingProfileId = $profile['id'];
        $this->fillFormFromProfile($profile);
        $this->showProfileModal = true;
    }

    public function closeProfileModal(): void
    {
        $this->showProfileModal = false;
        $this->editingProfileId = '';
        $this->loginPassword = '';

        $this->resetErrorBag();
    }

    public function openRuntimeSettingsModal(): void
    {
        $collection = $this->loadProfileCollection();
        $profile = $this->findProfile($collection, $this->activeProfileId);

        if (! $profile) {
            $this->dispatch('showAlert', 'Aktiver Scraper-Account wurde nicht gefunden.', 'error');

            return;
        }

        $this->fillFormFromProfile($profile);
        $this->showRuntimeSettingsModal = true;
    }

    public function closeRuntimeSettingsModal(): void
    {
        $this->showRuntimeSettingsModal = false;

        $this->resetErrorBag();
    }

    public function saveRuntimeSettings(): void
    {
        $collection = $this->loadProfileCollection();

        try {
            $collection = $this->persistRuntimeSettingsInCollection($collection, $this->activeProfileId);
        } catch (\RuntimeException) {
            return;
        }

        $this->persistProfileCollection($collection);
        $this->activeProfileId = $collection['active_profile_id'];
        $this->activeProfileIds = $collection['active_profile_ids'];
        $this->profileOptions = $this->buildProfileOptions($collection);
        $this->showRuntimeSettingsModal = false;

        session()->flash('success', 'Scraper-Einstellungen wurden gespeichert.');
        $this->dispatch('showAlert', 'Scraper-Einstellungen wurden gespeichert.', 'success');
    }

    public function deleteProfile(string $profileId): void
    {
        $collection = $this->loadProfileCollection();

        $remainingProfiles = array_values(array_filter(
            $collection['profiles'],
            static fn (array $profile): bool => ($profile['id'] ?? null) !== $profileId,
        ));

        if (count($remainingProfiles) === count($collection['profiles'])) {
            $this->dispatch('showAlert', 'Das Scraper-Profil wurde nicht gefunden.', 'error');

            return;
        }

        if ($remainingProfiles === []) {
            $remainingProfiles[] = $this->defaultProfile('default');
        }

        $collection['profiles'] = $remainingProfiles;
        $remainingProfileIds = array_column($remainingProfiles, 'id');
        $collection['active_profile_ids'] = array_values(array_intersect($collection['active_profile_ids'], $remainingProfileIds));

        if ($collection['active_profile_ids'] === []) {
            $collection['active_profile_ids'] = [$remainingProfiles[0]['id']];
        }

        if ($collection['active_profile_id'] === $profileId) {
            $collection['active_profile_id'] = $collection['active_profile_ids'][0];
        }

        $collection['updated_at'] = now()->toIso8601String();

        $this->persistProfileCollection($collection);
        $this->activeProfileId = $collection['active_profile_id'];
        $this->activeProfileIds = $collection['active_profile_ids'];
        $this->profileOptions = $this->buildProfileOptions($collection);
        $this->sessionBuildResult = null;
        $this->showProfileModal = $this->showProfileModal && $this->editingProfileId !== $profileId;
        $this->editingProfileId = $this->editingProfileId === $profileId ? '' : $this->editingProfileId;

        session()->flash('success', 'Scraper-Account wurde geloescht.');
        $this->dispatch('showAlert', 'Scraper-Account wurde geloescht.', 'success');
    }

    public function buildInstagramSession(): void
    {
        try {
            $storedSettings = $this->activeProfileSettings();
        } catch (\RuntimeException) {
            return;
        }

        try {
            $runtimeConfig = $this->buildRuntimeConfig($storedSettings);
            $baseProjectPath = $this->resolveBaseProjectPath();
            $runtimeConfigPath = $this->writeRuntimeConfigFile($baseProjectPath, $runtimeConfig);
            $nodeScript = $this->resolveBaseNodeScriptPath($baseProjectPath);

            $result = Process::path($baseProjectPath)
                ->timeout(max(240, ((int) ($storedSettings['navigation_timeout_seconds'] ?? 120)) + 180))
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
        $collection = $this->loadProfileCollection();
        $profileId = $this->editingProfileId !== '' ? $this->editingProfileId : $this->activeProfileId;
        $profile = $this->findProfile($collection, $profileId);

        if (! $profile) {
            return;
        }

        $profile['login_password_encrypted'] = null;
        $profile['login_password_base_encrypted'] = null;
        $profile['updated_at'] = now()->toIso8601String();

        $collection = $this->replaceProfile($collection, $profile);
        $collection['updated_at'] = now()->toIso8601String();

        $this->persistProfileCollection($collection);

        $this->loginPassword = '';
        $this->hasStoredPassword = false;
        $this->activeProfileIds = $collection['active_profile_ids'];
        $this->profileOptions = $this->buildProfileOptions($collection);

        session()->flash('success', 'Das gespeicherte Instagram-Passwort wurde entfernt.');
        $this->dispatch('showAlert', 'Das gespeicherte Instagram-Passwort wurde entfernt.', 'success');
    }

    public function render()
    {
        return view('livewire.admin.config.scraper-settings');
    }

    private function persistSettings(): array
    {
        return $this->activeProfileSettings();
    }

    private function activeProfileSettings(): array
    {
        $collection = $this->loadProfileCollection();
        $profile = $this->findProfile($collection, $collection['active_profile_id']);

        if (! $profile) {
            throw new \RuntimeException('Aktives Scraper-Profil wurde nicht gefunden.');
        }

        return $profile;
    }

    private function persistProfileFormInCollection(array $collection, string $profileId): array
    {
        $validated = $this->validate([
            'profileLabel' => ['required', 'string', 'max:120'],
            'persistentProfileEnabled' => ['boolean'],
            'browserProfilePath' => ['required', 'string', 'max:255'],
            'cookieFilePath' => ['required', 'string', 'max:255'],
            'autoLoginEnabled' => ['boolean'],
            'loginUsername' => ['nullable', 'string', 'max:255'],
            'loginPassword' => ['nullable', 'string', 'max:255'],
        ]);

        $profile = $this->findProfile($collection, $profileId);

        if (! $profile) {
            $this->dispatch('showAlert', 'Der Scraper-Account wurde nicht gefunden.', 'error');

            throw new \RuntimeException('Scraper-Account wurde nicht gefunden.');
        }

        $existingPassword = $profile['login_password_encrypted'] ?? null;
        $existingBasePassword = $profile['login_password_base_encrypted'] ?? null;

        $encryptedPassword = $existingPassword;
        $runtimePassword = $this->decryptRuntimePassword($existingPassword);

        if (filled($validated['loginPassword'] ?? null)) {
            $runtimePassword = $validated['loginPassword'];
            $encryptedPassword = Crypt::encryptString($runtimePassword);
        }

        $baseEncryptedPassword = $existingBasePassword;

        if (filled($runtimePassword) && (filled($validated['loginPassword'] ?? null) || blank($baseEncryptedPassword))) {
            try {
                $baseEncryptedPassword = $this->encryptPasswordForBaseProject($runtimePassword);
            } catch (\RuntimeException $exception) {
                $this->addError('loginPassword', $exception->getMessage());

                throw $exception;
            }
        }

        $passwordConfigured = filled($runtimePassword) || filled($baseEncryptedPassword);

        if ($validated['autoLoginEnabled'] && (blank($validated['loginUsername']) || ! $passwordConfigured)) {
            $this->addError('loginUsername', 'Bitte hinterlege fuer den Auto-Login einen Instagram-Benutzernamen und ein Passwort.');

            throw new \RuntimeException('Auto-Login-Konfiguration unvollstaendig.');
        }

        if ($validated['autoLoginEnabled'] && blank($baseEncryptedPassword)) {
            $this->addError('loginPassword', 'Das gespeicherte Passwort konnte nicht fuer die Base-Installation aufbereitet werden.');

            throw new \RuntimeException('Base-Passwortverschluesselung fehlgeschlagen.');
        }

        $profile = [
            ...$profile,
            'id' => $this->normalizeProfileId($profile['id'] ?? $profileId),
            'profile_label' => trim($validated['profileLabel']),
            'persistent_profile_enabled' => (bool) $validated['persistentProfileEnabled'],
            'browser_profile_path' => trim($validated['browserProfilePath']),
            'cookie_file_path' => trim($validated['cookieFilePath']),
            'headless_enabled' => true,
            'auto_login_enabled' => (bool) $validated['autoLoginEnabled'],
            'login_username' => trim((string) ($validated['loginUsername'] ?? '')),
            'login_password_encrypted' => $encryptedPassword,
            'login_password_base_encrypted' => $baseEncryptedPassword,
            'updated_at' => now()->toIso8601String(),
        ];

        $collection = $this->replaceProfile($collection, $profile);
        $collection['updated_at'] = now()->toIso8601String();

        return $collection;
    }

    private function persistRuntimeSettingsInCollection(array $collection, string $profileId): array
    {
        $validated = $this->validate([
            'navigationTimeoutSeconds' => ['required', 'integer', 'min:30', 'max:300'],
            'postLoginWaitMs' => ['required', 'integer', 'min:500', 'max:15000'],
            'typingDelayMs' => ['required', 'integer', 'min:0', 'max:500'],
            'relationshipListProcessTimeoutSeconds' => ['required', 'integer', 'min:14400', 'max:21600'],
            'relationshipListMaxScrollRounds' => ['required', 'integer', 'min:20', 'max:1000000'],
            'followerListMaxItems' => ['required', 'integer', 'min:0', 'max:1000000'],
            'followingListMaxItems' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $profile = $this->findProfile($collection, $profileId);

        if (! $profile) {
            $this->dispatch('showAlert', 'Aktiver Scraper-Account wurde nicht gefunden.', 'error');

            throw new \RuntimeException('Aktiver Scraper-Account wurde nicht gefunden.');
        }

        $profile = [
            ...$profile,
            'navigation_timeout_seconds' => (int) $validated['navigationTimeoutSeconds'],
            'post_login_wait_ms' => (int) $validated['postLoginWaitMs'],
            'typing_delay_ms' => (int) $validated['typingDelayMs'],
            'relationship_list_process_timeout_seconds' => (int) $validated['relationshipListProcessTimeoutSeconds'],
            'relationship_list_max_scroll_rounds' => (int) $validated['relationshipListMaxScrollRounds'],
            'follower_list_max_items' => (int) $validated['followerListMaxItems'],
            'following_list_max_items' => (int) $validated['followingListMaxItems'],
            'updated_at' => now()->toIso8601String(),
        ];

        $collection = $this->replaceProfile($collection, $profile);
        $collection['updated_at'] = now()->toIso8601String();

        return $collection;
    }

    private function loadProfileCollection(): array
    {
        $settings = Setting::getValue('scraper', 'instagram_profile');

        if (! is_array($settings)) {
            return $this->normalizeProfileCollection([]);
        }

        return $this->normalizeProfileCollection($settings);
    }

    private function persistProfileCollection(array $collection): void
    {
        Setting::setValue('scraper', 'instagram_profile', $this->normalizeProfileCollection($collection));
    }

    private function normalizeProfileCollection(array $settings): array
    {
        $profiles = [];

        if (isset($settings['profiles']) && is_array($settings['profiles'])) {
            foreach ($settings['profiles'] as $key => $profile) {
                if (! is_array($profile)) {
                    continue;
                }

                $profiles[] = $this->normalizeProfile($profile, is_string($key) ? $key : null);
            }
        } elseif ($settings !== []) {
            $profiles[] = $this->normalizeProfile($settings, 'default');
        }

        if ($profiles === []) {
            $profiles[] = $this->defaultProfile('default');
        }

        $activeProfileId = $this->normalizeProfileId($settings['active_profile_id'] ?? ($profiles[0]['id'] ?? 'default'));

        if (! $this->findProfile(['profiles' => $profiles], $activeProfileId)) {
            $activeProfileId = $profiles[0]['id'];
        }

        $profileIds = array_column($profiles, 'id');
        $activeProfileIds = $this->normalizeActiveProfileIds($settings['active_profile_ids'] ?? null, $profileIds);

        if ($activeProfileIds === []) {
            $activeProfileIds = [$activeProfileId];
        }

        if (! in_array($activeProfileId, $activeProfileIds, true)) {
            $activeProfileIds = $this->appendActiveProfileId($activeProfileIds, $activeProfileId);
        }

        return [
            'active_profile_id' => $activeProfileId,
            'active_profile_ids' => $activeProfileIds,
            'profiles' => array_values($profiles),
            'updated_at' => (string) ($settings['updated_at'] ?? now()->toIso8601String()),
        ];
    }

    private function normalizeProfile(array $profile, ?string $fallbackId = null): array
    {
        $id = $this->normalizeProfileId($profile['id'] ?? $fallbackId);

        return [
            'id' => $id,
            'profile_label' => $this->normalizeText($profile['profile_label'] ?? 'instagram-default', 'instagram-default'),
            'persistent_profile_enabled' => (bool) ($profile['persistent_profile_enabled'] ?? true),
            'browser_profile_path' => $this->normalizeText($profile['browser_profile_path'] ?? 'browser-profiles/instagram/default', 'browser-profiles/instagram/default'),
            'cookie_file_path' => $this->normalizeText($profile['cookie_file_path'] ?? 'cookies/instagram-cookies.json', 'cookies/instagram-cookies.json'),
            'headless_enabled' => true,
            'auto_login_enabled' => (bool) ($profile['auto_login_enabled'] ?? false),
            'login_username' => trim((string) ($profile['login_username'] ?? '')),
            'login_password_encrypted' => $this->nullableString($profile['login_password_encrypted'] ?? null),
            'login_password_base_encrypted' => $this->nullableString($profile['login_password_base_encrypted'] ?? null),
            'navigation_timeout_seconds' => max(30, min(300, (int) ($profile['navigation_timeout_seconds'] ?? 120))),
            'post_login_wait_ms' => max(500, min(15000, (int) ($profile['post_login_wait_ms'] ?? 2500))),
            'typing_delay_ms' => max(0, min(500, (int) ($profile['typing_delay_ms'] ?? 35))),
            'relationship_list_process_timeout_seconds' => max(14400, min(21600, (int) ($profile['relationship_list_process_timeout_seconds'] ?? 14400))),
            'relationship_list_max_scroll_rounds' => max(20, min(1000000, (int) ($profile['relationship_list_max_scroll_rounds'] ?? 100000))),
            'follower_list_max_items' => max(0, min(1000000, (int) ($profile['follower_list_max_items'] ?? 0))),
            'following_list_max_items' => max(0, min(1000000, (int) ($profile['following_list_max_items'] ?? 0))),
            'updated_at' => (string) ($profile['updated_at'] ?? now()->toIso8601String()),
        ];
    }

    private function defaultProfile(?string $id = null, string $label = 'instagram-default'): array
    {
        return [
            'id' => $this->normalizeProfileId($id),
            'profile_label' => $label,
            'persistent_profile_enabled' => true,
            'browser_profile_path' => 'browser-profiles/instagram/default',
            'cookie_file_path' => 'cookies/instagram-cookies.json',
            'headless_enabled' => true,
            'auto_login_enabled' => false,
            'login_username' => '',
            'login_password_encrypted' => null,
            'login_password_base_encrypted' => null,
            'navigation_timeout_seconds' => 120,
            'post_login_wait_ms' => 2500,
            'typing_delay_ms' => 35,
            'relationship_list_process_timeout_seconds' => 14400,
            'relationship_list_max_scroll_rounds' => 100000,
            'follower_list_max_items' => 0,
            'following_list_max_items' => 0,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    private function makeNewProfile(array $collection, array $validated = []): array
    {
        $profileNumber = count($collection['profiles']) + 1;
        $label = $this->normalizeText($validated['newProfileLabel'] ?? 'instagram-profil-'.$profileNumber, 'instagram-profil-'.$profileNumber);
        $username = trim((string) ($validated['newLoginUsername'] ?? ''));
        $password = (string) ($validated['newLoginPassword'] ?? '');
        $autoLoginEnabled = (bool) ($validated['newAutoLoginEnabled'] ?? false);
        $slug = Str::slug($username !== '' ? $username : $label) ?: 'instagram-profil-'.$profileNumber;

        while ($this->profilePathExists($collection, 'browser-profiles/instagram/'.$slug)) {
            $profileNumber++;
            $slug = (Str::slug($username !== '' ? $username : $label) ?: 'instagram-profil').'-'.$profileNumber;
        }

        $encryptedPassword = null;
        $baseEncryptedPassword = null;

        if (filled($password)) {
            $encryptedPassword = Crypt::encryptString($password);

            try {
                $baseEncryptedPassword = $this->encryptPasswordForBaseProject($password);
            } catch (\RuntimeException $exception) {
                $this->addError('newLoginPassword', $exception->getMessage());

                throw $exception;
            }
        }

        if ($autoLoginEnabled && blank($baseEncryptedPassword)) {
            $this->addError('newLoginPassword', 'Das gespeicherte Passwort konnte nicht fuer die Base-Installation aufbereitet werden.');

            throw new \RuntimeException('Base-Passwortverschluesselung fehlgeschlagen.');
        }

        return [
            ...$this->defaultProfile(Str::uuid()->toString(), $label),
            'browser_profile_path' => 'browser-profiles/instagram/'.$slug,
            'cookie_file_path' => 'cookies/'.$slug.'-cookies.json',
            'auto_login_enabled' => $autoLoginEnabled,
            'login_username' => $username,
            'login_password_encrypted' => $encryptedPassword,
            'login_password_base_encrypted' => $baseEncryptedPassword,
        ];
    }

    private function buildProfileOptions(array $collection): array
    {
        return array_map(function (array $profile) use ($collection): array {
            return [
                'id' => $profile['id'],
                'label' => $profile['profile_label'],
                'login_username' => $profile['login_username'],
                'browser_profile_path' => $profile['browser_profile_path'],
                'cookie_file_path' => $profile['cookie_file_path'],
                'has_stored_password' => filled($profile['login_password_encrypted'] ?? null)
                    || filled($profile['login_password_base_encrypted'] ?? null),
                'is_active' => in_array($profile['id'], $collection['active_profile_ids'], true),
                'is_primary' => $profile['id'] === $collection['active_profile_id'],
            ];
        }, $collection['profiles']);
    }

    private function normalizeActiveProfileIds(mixed $activeProfileIds, array $profileIds): array
    {
        if (! is_array($activeProfileIds)) {
            return [];
        }

        $profileIds = array_map(static fn (mixed $profileId): string => (string) $profileId, $profileIds);
        $normalizedIds = [];

        foreach ($activeProfileIds as $activeProfileId) {
            $activeProfileId = trim((string) $activeProfileId);

            if ($activeProfileId === '' || ! in_array($activeProfileId, $profileIds, true)) {
                continue;
            }

            $normalizedIds[] = $activeProfileId;
        }

        return array_values(array_unique($normalizedIds));
    }

    private function appendActiveProfileId(array $activeProfileIds, string $profileId): array
    {
        $activeProfileIds[] = $profileId;

        return array_values(array_unique(array_filter(
            $activeProfileIds,
            static fn (mixed $activeProfileId): bool => is_string($activeProfileId) && trim($activeProfileId) !== '',
        )));
    }

    private function findProfile(array $collection, ?string $profileId): ?array
    {
        $profileId = $this->normalizeProfileId($profileId);

        foreach (($collection['profiles'] ?? []) as $profile) {
            if (($profile['id'] ?? null) === $profileId) {
                return $profile;
            }
        }

        return null;
    }

    private function replaceProfile(array $collection, array $profile): array
    {
        $profile = $this->normalizeProfile($profile);
        $replaced = false;

        foreach ($collection['profiles'] as $index => $existingProfile) {
            if (($existingProfile['id'] ?? null) !== $profile['id']) {
                continue;
            }

            $collection['profiles'][$index] = $profile;
            $replaced = true;

            break;
        }

        if (! $replaced) {
            $collection['profiles'][] = $profile;
        }

        return $this->normalizeProfileCollection($collection);
    }

    private function fillFormFromProfile(?array $profile): void
    {
        $profile = $profile ? $this->normalizeProfile($profile) : $this->defaultProfile('default');

        $this->profileLabel = $profile['profile_label'];
        $this->persistentProfileEnabled = $profile['persistent_profile_enabled'];
        $this->browserProfilePath = $profile['browser_profile_path'];
        $this->cookieFilePath = $profile['cookie_file_path'];
        $this->headlessEnabled = true;
        $this->autoLoginEnabled = $profile['auto_login_enabled'];
        $this->loginUsername = $profile['login_username'];
        $this->loginPassword = '';
        $this->navigationTimeoutSeconds = $profile['navigation_timeout_seconds'];
        $this->postLoginWaitMs = $profile['post_login_wait_ms'];
        $this->typingDelayMs = $profile['typing_delay_ms'];
        $this->relationshipListProcessTimeoutSeconds = $profile['relationship_list_process_timeout_seconds'];
        $this->relationshipListMaxScrollRounds = $profile['relationship_list_max_scroll_rounds'];
        $this->followerListMaxItems = $profile['follower_list_max_items'];
        $this->followingListMaxItems = $profile['following_list_max_items'];
        $this->hasStoredPassword = filled($profile['login_password_encrypted'] ?? null)
            || filled($profile['login_password_base_encrypted'] ?? null);

        $this->resetErrorBag();
    }

    private function profilePathExists(array $collection, string $browserProfilePath): bool
    {
        foreach ($collection['profiles'] as $profile) {
            if (($profile['browser_profile_path'] ?? null) === $browserProfilePath) {
                return true;
            }
        }

        return false;
    }

    private function normalizeProfileId(mixed $profileId): string
    {
        $profileId = trim((string) $profileId);

        return $profileId !== '' ? $profileId : Str::uuid()->toString();
    }

    private function normalizeText(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
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
        ), " \t\n\r\0\x0B\"'");

        $projectParentPath = dirname(base_path());
        $workspacePath = dirname($projectParentPath);

        $candidates = array_filter([
            $configuredPath,
            $projectParentPath.DIRECTORY_SEPARATOR.'webaidetective-base',
            $projectParentPath.DIRECTORY_SEPARATOR.'webaidetective',
            $workspacePath.DIRECTORY_SEPARATOR.'webaidetective-base',
            $workspacePath.DIRECTORY_SEPARATOR.'webaidetective',
        ], static fn (mixed $candidate): bool => is_string($candidate) && trim($candidate) !== '');
        $checkedCandidates = [];

        foreach (array_unique($candidates) as $candidate) {
            $candidate = rtrim($candidate, DIRECTORY_SEPARATOR);
            $checkedCandidates[] = $candidate;

            if (File::isDirectory($candidate) && $this->baseNodeScriptExists($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        throw new \RuntimeException(
            'Das Base-Projekt wurde nicht gefunden. Geprueft wurden: '
            .implode(', ', $checkedCandidates)
            .'. Setze bei Bedarf `SCRAPER_BASE_PROJECT_PATH` auf den absoluten Pfad der Base-Installation.'
        );
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

    private function baseNodeScriptExists(string $baseProjectPath): bool
    {
        return File::exists(
            $baseProjectPath.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'node'.DIRECTORY_SEPARATOR.'scraper'.DIRECTORY_SEPARATOR.'scrape-instagram.cjs'
        );
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
