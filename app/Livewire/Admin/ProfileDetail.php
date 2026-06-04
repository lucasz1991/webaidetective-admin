<?php

namespace App\Livewire\Admin;

use App\Support\PublicAssetUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class ProfileDetail extends Component
{
    public int $profileId;

    public function mount(int $profileId): void
    {
        $this->profileId = $profileId;
    }

    public function render()
    {
        abort_unless(Schema::hasTable('instagram_profiles'), 404);

        $profile = DB::table('instagram_profiles')
            ->where('id', $this->profileId)
            ->whereNull('deleted_at')
            ->first([
                'id',
                'username',
                'display_name',
                'full_name',
                'biography',
                'profile_url',
                'profile_image_url',
                'profile_image_path',
                'profile_visibility',
                'is_private',
                'followers_count',
                'following_count',
                'posts_count',
                'last_status_level',
                'last_status_message',
                'last_scanned_at',
                'created_at',
                'updated_at',
            ]);

        abort_unless($profile, 404);

        $linkedPeople = $this->loadLinkedPeople();
        $recentScans = $this->loadRecentScans();
        $relationships = $this->loadRelationships();

        $profile->image_url = PublicAssetUrl::fromStorageOrRemote($profile->profile_image_path, $profile->profile_image_url);
        $profile->visibility = $profile->profile_visibility ?: ($profile->is_private ? 'private' : 'unknown');
        $profile->display_label = $profile->display_name ?: $profile->full_name ?: '@'.ltrim((string) $profile->username, '@');

        return view('livewire.admin.profile-detail', [
            'profile' => $profile,
            'linkedPeople' => $linkedPeople,
            'recentScans' => $recentScans,
            'relationships' => $relationships,
        ])->layout('layouts.master');
    }

    private function loadLinkedPeople(): Collection
    {
        $items = collect();

        if (Schema::hasTable('tracked_people') && Schema::hasColumn('tracked_people', 'current_instagram_profile_id')) {
            $items = $items->concat(
                DB::table('tracked_people')
                    ->leftJoin('users', 'users.id', '=', 'tracked_people.user_id')
                    ->where('tracked_people.current_instagram_profile_id', $this->profileId)
                    ->get([
                        'tracked_people.id as tracked_person_id',
                        'tracked_people.user_id',
                        'tracked_people.first_name',
                        'tracked_people.last_name',
                        'tracked_people.alias',
                        'tracked_people.monitoring_enabled',
                        'users.name as user_name',
                        DB::raw("'Aktuelles Monitoring' as relation_label"),
                    ])
            );
        }

        if (Schema::hasTable('tracked_person_instagram_profile_links')) {
            $items = $items->concat(
                DB::table('tracked_person_instagram_profile_links')
                    ->join('tracked_people', 'tracked_people.id', '=', 'tracked_person_instagram_profile_links.tracked_person_id')
                    ->leftJoin('users', 'users.id', '=', 'tracked_person_instagram_profile_links.user_id')
                    ->where('tracked_person_instagram_profile_links.instagram_profile_id', $this->profileId)
                    ->whereNull('tracked_person_instagram_profile_links.deleted_at')
                    ->get([
                        'tracked_people.id as tracked_person_id',
                        'tracked_person_instagram_profile_links.user_id',
                        'tracked_people.first_name',
                        'tracked_people.last_name',
                        'tracked_people.alias',
                        'tracked_people.monitoring_enabled',
                        'users.name as user_name',
                        'tracked_person_instagram_profile_links.relation_type as relation_label',
                    ])
            );
        }

        return $items
            ->map(function ($item) {
                $displayName = trim(collect([$item->first_name, $item->last_name])->filter()->implode(' '));

                return (object) [
                    'tracked_person_id' => (int) $item->tracked_person_id,
                    'user_id' => $item->user_id ? (int) $item->user_id : null,
                    'display_name' => $displayName !== '' ? $displayName : ($item->alias ?: 'Unbenannte Person'),
                    'user_name' => $item->user_name,
                    'relation_label' => $this->formatRelationLabel($item->relation_label),
                    'monitoring_enabled' => (bool) ($item->monitoring_enabled ?? false),
                ];
            })
            ->unique(fn ($item) => $item->tracked_person_id.'|'.$item->relation_label)
            ->values();
    }

    private function loadRecentScans(): Collection
    {
        if (! Schema::hasTable('instagram_profile_list_scans')) {
            return collect();
        }

        return DB::table('instagram_profile_list_scans')
            ->where('instagram_profile_id', $this->profileId)
            ->whereNull('deleted_at')
            ->orderByDesc('scanned_at')
            ->limit(10)
            ->get([
                'list_type',
                'status_level',
                'status_message',
                'attempted',
                'available',
                'complete',
                'active_count',
                'observed_count',
                'known_count',
                'added_count',
                'removed_count',
                'scanned_at',
            ]);
    }

    private function loadRelationships(): Collection
    {
        if (! Schema::hasTable('instagram_profile_relationships')) {
            return collect();
        }

        return DB::table('instagram_profile_relationships')
            ->join('instagram_profiles as related_profile', 'related_profile.id', '=', 'instagram_profile_relationships.related_instagram_profile_id')
            ->where('instagram_profile_relationships.source_instagram_profile_id', $this->profileId)
            ->whereNull('instagram_profile_relationships.deleted_at')
            ->whereNull('related_profile.deleted_at')
            ->orderByDesc('instagram_profile_relationships.last_seen_at')
            ->limit(50)
            ->get([
                'instagram_profile_relationships.list_type',
                'instagram_profile_relationships.status',
                'instagram_profile_relationships.first_seen_at',
                'instagram_profile_relationships.last_seen_at',
                'related_profile.id as related_profile_id',
                'related_profile.username as related_username',
                'related_profile.display_name as related_display_name',
                'related_profile.full_name as related_full_name',
            ]);
    }

    private function formatRelationLabel(?string $label): string
    {
        $label = trim((string) $label);

        if ($label === '') {
            return 'Verknuepfung';
        }

        return match ($label) {
            'current', 'observed' => 'Beobachtet',
            default => str_replace('_', ' ', ucfirst($label)),
        };
    }
}
