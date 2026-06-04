<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\User;
use App\Models\Mail;
use App\Support\PublicAssetUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserProfile extends Component
{
    public $userId;
    public $user;
    public Collection $trackedProfiles;
    public bool $billingTablesReady = false;
    public ?object $activeSubscription = null;
    public ?object $creditWallet = null;
    public Collection $creditTransactions;


    public $showMailModal = false; 
    public $mailUserId = null;
    public $mailSubject = ''; 
    public $mailHeader = '';
    public $mailBody = '';
    public $mailLink = '';

    public function mount($userId)
    {
        $this->userId = $userId;
        $this->trackedProfiles = collect();
        $this->creditTransactions = collect();
        $this->loadUser();
    }

    public function loadUser()
    {
        $this->user = User::findOrFail($this->userId);
        $this->trackedProfiles = $this->loadTrackedProfilesForUser();
        $this->loadBillingData();
    }

    public function activateUser()
    {

        if ($this->user && !$this->user->status) {
            $this->user->update(['status' => true]);
                $this->dispatch('showAlert', 'Benutzer erfolgreich aktiviert.', 'success');
        } else {
            $this->dispatch('showAlert', 'Benutzer ist bereits aktiv.', 'info');
        }
        $this->loadUser();
    }

    public function deactivateUser()
    {
        if ($this->user && $this->user->status) {
            $this->user->update(['status' => false]);
                $this->dispatch('showAlert', 'Benutzer erfolgreich deaktiviert.', 'success');
        } else {
            $this->dispatch('showAlert', 'Benutzer ist bereits inaktiv.', 'info');
        }
        $this->loadUser();
    }

    public function openMailModal()
    {
        // Prüfen, ob der Benutzer vorhanden ist
        if (!$this->user) {
            $this->dispatch('showAlert', 'Benutzer nicht gefunden.', 'error');
            return;
        }
    
        $this->mailUserId = $this->user->id;
        $this->showMailModal = true;
    }
    
    public function resetMailModal()
    {
        $this->showMailModal = false;
        $this->mailUserId = null;
        $this->mailSubject = '';
        $this->mailHeader = '';
        $this->mailBody = '';
        $this->mailLink = '';
    }
    
    public function sendMail()
    {
        // Validierung mit individuellen Fehlermeldungen
        $this->validate([
            'mailSubject' => 'required|string|max:255',
            'mailHeader' => 'required|string|max:255',
            'mailBody' => 'required|string',
        ], [
            'mailSubject.required' => 'Bitte geben Sie einen Betreff ein.',
            'mailSubject.max' => 'Der Betreff darf maximal 255 Zeichen lang sein.',
            'mailHeader.required' => 'Bitte geben Sie eine Überschrift ein.',
            'mailHeader.max' => 'Die Überschrift darf maximal 255 Zeichen lang sein.',
            'mailBody.required' => 'Bitte geben Sie eine Nachricht ein.',
        ]);
    
        // Inhalte für die Datenbank vorbereiten
        $content = [
            'subject' => $this->mailSubject,
            'header' => $this->mailHeader,
            'body' => $this->mailBody,
            'link' => $this->mailLink, // Link kann optional leer sein
        ];
    
        // Mail an den gespeicherten Benutzer senden
        if ($this->user) {
            Mail::create([
                'status' => false,
                'content' => $content,
                'recipients' => [
                    [
                        'user_id' => $this->user->id,
                        'email' => $this->user->email,
                        'status' => false, // Status für den Empfänger
                    ],
                ],
            ]);
    
            $this->dispatch('showAlert', 'E-Mail wurde zur Verarbeitung an ' . $this->user->email . ' vorbereitet.', 'success');
        } else {
            $this->dispatch('showAlert', 'Benutzer nicht gefunden.', 'error');
        }
    
        // Modal zurücksetzen
        $this->resetMailModal();
    }
    

    public function render()
    {
        return view('livewire.admin.user-profile', [
            'user' => $this->user,
            'trackedProfiles' => $this->trackedProfiles,
            'billingTablesReady' => $this->billingTablesReady,
            'activeSubscription' => $this->activeSubscription,
            'creditWallet' => $this->creditWallet,
            'creditTransactions' => $this->creditTransactions,
        ])->layout('layouts.master');
    }

    private function loadBillingData(): void
    {
        $this->billingTablesReady = Schema::hasTable('plans')
            && Schema::hasTable('subscriptions')
            && Schema::hasTable('credit_wallets')
            && Schema::hasTable('credit_transactions');

        $this->activeSubscription = null;
        $this->creditWallet = null;
        $this->creditTransactions = collect();

        if (! $this->billingTablesReady) {
            return;
        }

        $this->activeSubscription = DB::table('subscriptions')
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.user_id', $this->userId)
            ->where('subscriptions.status', 'active')
            ->orderByDesc('subscriptions.started_at')
            ->orderByDesc('subscriptions.id')
            ->select([
                'subscriptions.id',
                'subscriptions.status',
                'subscriptions.started_at',
                'subscriptions.ends_at',
                'plans.name as plan_name',
                'plans.max_profiles',
                'plans.max_users',
                'plans.monthly_credits',
                'plans.max_history_days',
                'plans.scan_frequency_minutes',
                'plans.priority_level',
            ])
            ->first();

        $this->creditWallet = DB::table('credit_wallets')
            ->where('user_id', $this->userId)
            ->first();

        $this->creditTransactions = DB::table('credit_transactions')
            ->where('user_id', $this->userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    private function loadTrackedProfilesForUser(): Collection
    {
        if (
            ! Schema::hasTable('tracked_people')
            || ! Schema::hasTable('instagram_profiles')
            || ! Schema::hasColumn('tracked_people', 'user_id')
        ) {
            return collect();
        }

        $profiles = collect();

        if (Schema::hasColumn('tracked_people', 'current_instagram_profile_id')) {
            $profiles = $profiles->concat(
                DB::table('tracked_people')
                    ->leftJoin('instagram_profiles', 'instagram_profiles.id', '=', 'tracked_people.current_instagram_profile_id')
                    ->where('tracked_people.user_id', $this->userId)
                    ->select([
                        'tracked_people.id as tracked_person_id',
                        'tracked_people.first_name',
                        'tracked_people.last_name',
                        'tracked_people.alias',
                        'tracked_people.monitoring_enabled',
                        'tracked_people.last_instagram_analyzed_at',
                        'instagram_profiles.id as instagram_profile_id',
                        'instagram_profiles.username',
                        'instagram_profiles.display_name',
                        'instagram_profiles.full_name',
                        'instagram_profiles.profile_image_url',
                        'instagram_profiles.profile_image_path',
                        'instagram_profiles.followers_count',
                        'instagram_profiles.following_count',
                        'instagram_profiles.posts_count',
                        DB::raw("'Aktuelles Profil' as relation_label"),
                    ])
                    ->get()
            );
        }

        if (Schema::hasTable('tracked_person_instagram_profile_links')) {
            $profiles = $profiles->concat(
                DB::table('tracked_person_instagram_profile_links')
                    ->join('tracked_people', 'tracked_people.id', '=', 'tracked_person_instagram_profile_links.tracked_person_id')
                    ->join('instagram_profiles', 'instagram_profiles.id', '=', 'tracked_person_instagram_profile_links.instagram_profile_id')
                    ->where('tracked_people.user_id', $this->userId)
                    ->whereNull('tracked_person_instagram_profile_links.deleted_at')
                    ->select([
                        'tracked_people.id as tracked_person_id',
                        'tracked_people.first_name',
                        'tracked_people.last_name',
                        'tracked_people.alias',
                        'tracked_people.monitoring_enabled',
                        'tracked_people.last_instagram_analyzed_at',
                        'instagram_profiles.id as instagram_profile_id',
                        'instagram_profiles.username',
                        'instagram_profiles.display_name',
                        'instagram_profiles.full_name',
                        'instagram_profiles.profile_image_url',
                        'instagram_profiles.profile_image_path',
                        'instagram_profiles.followers_count',
                        'instagram_profiles.following_count',
                        'instagram_profiles.posts_count',
                        'tracked_person_instagram_profile_links.relation_type as relation_label',
                    ])
                    ->get()
            );
        }

        return $profiles
            ->filter(fn ($profile) => filled($profile->instagram_profile_id))
            ->map(function ($profile) {
                $displayName = trim(collect([$profile->first_name, $profile->last_name])->filter()->implode(' '));

                return (object) [
                    'tracked_person_id' => (int) $profile->tracked_person_id,
                    'tracked_person_name' => $displayName !== '' ? $displayName : ($profile->alias ?: 'Unbenannte Person'),
                    'instagram_profile_id' => (int) $profile->instagram_profile_id,
                    'display_name' => $profile->display_name ?: $profile->full_name ?: '@'.ltrim((string) $profile->username, '@'),
                    'handle' => '@'.ltrim((string) $profile->username, '@'),
                    'image_url' => PublicAssetUrl::fromStorageOrRemote($profile->profile_image_path, $profile->profile_image_url),
                    'followers_count' => (int) ($profile->followers_count ?? 0),
                    'following_count' => (int) ($profile->following_count ?? 0),
                    'posts_count' => (int) ($profile->posts_count ?? 0),
                    'monitoring_enabled' => (bool) ($profile->monitoring_enabled ?? false),
                    'relation_label' => $this->formatRelationLabel($profile->relation_label),
                    'last_instagram_analyzed_at' => $profile->last_instagram_analyzed_at,
                ];
            })
            ->unique(fn ($profile) => $profile->tracked_person_id.'|'.$profile->instagram_profile_id.'|'.$profile->relation_label)
            ->values();
    }

    private function formatRelationLabel(?string $label): string
    {
        $label = trim((string) $label);

        if ($label === '') {
            return 'Profil';
        }

        return match ($label) {
            'current', 'observed' => 'Beobachtet',
            default => str_replace('_', ' ', ucfirst($label)),
        };
    }
}
