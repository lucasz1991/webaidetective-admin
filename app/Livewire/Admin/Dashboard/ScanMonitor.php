<?php

namespace App\Livewire\Admin\Dashboard;

use App\Support\PublicAssetUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class ScanMonitor extends Component
{
    public string $filter = 'all';

    public function render()
    {
        $tablesAvailable = Schema::hasTable('tracked_people')
            && Schema::hasTable('tracked_person_instagram_snapshots');

        return view('livewire.admin.dashboard.scan-monitor', [
            'scans' => $tablesAvailable ? $this->loadScans() : collect(),
            'tablesAvailable' => $tablesAvailable,
        ]);
    }

    private function loadScans(): Collection
    {
        $running = $this->filter !== 'completed'
            ? $this->loadRunningScans()
            : collect();
        $recent = $this->filter !== 'running'
            ? $this->loadRecentScans()
            : collect();

        return $running
            ->concat($recent)
            ->unique(fn (object $scan) => $scan->is_running
                ? 'running-'.$scan->tracked_person_id
                : 'snapshot-'.$scan->snapshot_id)
            ->take(12)
            ->values();
    }

    private function loadRunningScans(): Collection
    {
        $latestSnapshots = DB::table('tracked_person_instagram_snapshots')
            ->selectRaw('tracked_person_id, MAX(id) as snapshot_id')
            ->groupBy('tracked_person_id');
        $profileIdColumn = Schema::hasColumn('tracked_people', 'current_instagram_profile_id')
            ? 'tracked_people.current_instagram_profile_id as instagram_profile_id'
            : DB::raw('NULL as instagram_profile_id');

        return DB::table('tracked_people')
            ->leftJoin('users', 'users.id', '=', 'tracked_people.user_id')
            ->leftJoinSub($latestSnapshots, 'latest_snapshots', function ($join): void {
                $join->on('latest_snapshots.tracked_person_id', '=', 'tracked_people.id');
            })
            ->leftJoin('tracked_person_instagram_snapshots as snapshot', 'snapshot.id', '=', 'latest_snapshots.snapshot_id')
            ->where('tracked_people.last_instagram_status_level', 'partial')
            ->orderByDesc('tracked_people.updated_at')
            ->limit(12)
            ->get([
                'tracked_people.id as tracked_person_id',
                $profileIdColumn,
                'tracked_people.instagram_username',
                'tracked_people.first_name',
                'tracked_people.last_name',
                'tracked_people.alias',
                'tracked_people.instagram_profile_image_path',
                'tracked_people.profile_image_path',
                'tracked_people.last_instagram_status_level as status_level',
                'tracked_people.last_instagram_status_message as status_message',
                'tracked_people.updated_at as scanned_at',
                'tracked_people.instagram_followers_count as followers_count',
                'tracked_people.instagram_following_count as following_count',
                'tracked_people.instagram_posts_count as posts_count',
                'users.id as user_id',
                'users.name as user_name',
                'snapshot.id as snapshot_id',
                'snapshot.screenshot_path',
            ])
            ->map(fn (object $scan) => $this->normalizeScan($scan, true));
    }

    private function loadRecentScans(): Collection
    {
        $profileIdColumn = Schema::hasColumn('tracked_person_instagram_snapshots', 'instagram_profile_id')
            ? 'snapshot.instagram_profile_id'
            : DB::raw('NULL as instagram_profile_id');

        return DB::table('tracked_person_instagram_snapshots as snapshot')
            ->join('tracked_people', 'tracked_people.id', '=', 'snapshot.tracked_person_id')
            ->leftJoin('users', 'users.id', '=', 'tracked_people.user_id')
            ->orderByDesc('snapshot.analyzed_at')
            ->limit(12)
            ->get([
                'snapshot.id as snapshot_id',
                'snapshot.tracked_person_id',
                $profileIdColumn,
                'snapshot.instagram_username',
                'snapshot.screenshot_path',
                'snapshot.profile_image_path',
                'snapshot.profile_image_url',
                'snapshot.status_level',
                'snapshot.status_message',
                'snapshot.analyzed_at as scanned_at',
                'snapshot.followers_count',
                'snapshot.following_count',
                'snapshot.posts_count',
                'tracked_people.first_name',
                'tracked_people.last_name',
                'tracked_people.alias',
                'users.id as user_id',
                'users.name as user_name',
            ])
            ->map(fn (object $scan) => $this->normalizeScan($scan, false));
    }

    private function normalizeScan(object $scan, bool $isRunning): object
    {
        $name = trim(collect([$scan->first_name, $scan->last_name])->filter()->implode(' '));
        $profileImagePath = $scan->profile_image_path
            ?? $scan->instagram_profile_image_path
            ?? null;

        return (object) [
            'snapshot_id' => $scan->snapshot_id ? (int) $scan->snapshot_id : null,
            'tracked_person_id' => (int) $scan->tracked_person_id,
            'instagram_profile_id' => $scan->instagram_profile_id ? (int) $scan->instagram_profile_id : null,
            'username' => ltrim((string) $scan->instagram_username, '@'),
            'display_name' => $name !== '' ? $name : ($scan->alias ?: 'Unbenannte Person'),
            'user_id' => $scan->user_id ? (int) $scan->user_id : null,
            'user_name' => $scan->user_name,
            'status_level' => $isRunning ? 'partial' : ($scan->status_level ?: 'unknown'),
            'status_message' => $scan->status_message,
            'scanned_at' => $scan->scanned_at,
            'followers_count' => $scan->followers_count,
            'following_count' => $scan->following_count,
            'posts_count' => $scan->posts_count,
            'profile_image_url' => PublicAssetUrl::fromStorageOrRemote(
                $profileImagePath,
                $scan->profile_image_url ?? null,
            ),
            'screenshot_url' => PublicAssetUrl::storage($scan->screenshot_path),
            'is_running' => $isRunning,
        ];
    }
}
