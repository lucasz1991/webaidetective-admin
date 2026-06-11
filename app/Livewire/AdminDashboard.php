<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class AdminDashboard extends Component
{
    public function render()
    {
        return view('livewire.admin-dashboard', [
            'statistics' => $this->statistics(),
        ])->layout('layouts.master');
    }

    private function statistics(): array
    {
        $statistics = [
            'users' => 0,
            'active_users' => 0,
            'tracked_people' => 0,
            'monitored_people' => 0,
            'profiles' => 0,
            'scans_today' => 0,
            'running_scans' => 0,
            'failed_scans' => 0,
        ];

        if (Schema::hasTable('users')) {
            $statistics['users'] = DB::table('users')->count();
        }

        if (Schema::hasTable('sessions')) {
            $statistics['active_users'] = DB::table('sessions')
                ->whereNotNull('user_id')
                ->where('last_activity', '>=', now()->subMinutes(15)->timestamp)
                ->distinct()
                ->count('user_id');
        }

        if (Schema::hasTable('tracked_people')) {
            $statistics['tracked_people'] = DB::table('tracked_people')->count();
            $statistics['monitored_people'] = Schema::hasColumn('tracked_people', 'monitoring_enabled')
                ? DB::table('tracked_people')->where('monitoring_enabled', true)->count()
                : 0;
            $statistics['running_scans'] = $this->runningScanCount();
        }

        if (Schema::hasTable('instagram_profiles')) {
            $profiles = DB::table('instagram_profiles');

            if (Schema::hasColumn('instagram_profiles', 'deleted_at')) {
                $profiles->whereNull('deleted_at');
            }

            $statistics['profiles'] = $profiles->count();
        }

        if (Schema::hasTable('tracked_person_instagram_snapshots')) {
            $statistics['scans_today'] = DB::table('tracked_person_instagram_snapshots')
                ->where('analyzed_at', '>=', now()->startOfDay())
                ->count();
            $statistics['failed_scans'] = DB::table('tracked_person_instagram_snapshots')
                ->where('status_level', 'error')
                ->where('analyzed_at', '>=', now()->subDay())
                ->count();
        }

        return $statistics;
    }

    private function runningScanCount(): int
    {
        if (
            ! Schema::hasTable('tracked_person_instagram_snapshots')
            || ! Schema::hasColumn('tracked_people', 'last_instagram_status_level')
        ) {
            return 0;
        }

        $latestSnapshots = DB::table('tracked_person_instagram_snapshots')
            ->selectRaw('tracked_person_id, MAX(id) as snapshot_id')
            ->groupBy('tracked_person_id');

        return DB::table('tracked_people')
            ->joinSub($latestSnapshots, 'latest_snapshots', function ($join): void {
                $join->on('latest_snapshots.tracked_person_id', '=', 'tracked_people.id');
            })
            ->join('tracked_person_instagram_snapshots as snapshot', 'snapshot.id', '=', 'latest_snapshots.snapshot_id')
            ->where('tracked_people.last_instagram_status_level', 'partial')
            ->where('snapshot.analyzed_at', '>=', now()->subMinutes(5))
            ->get([
                'snapshot.raw_payload',
                'tracked_people.last_instagram_status_message as status_message',
            ])
            ->filter(function (object $snapshot): bool {
                $payload = json_decode((string) $snapshot->raw_payload, true);
                $phase = strtolower((string) data_get($payload, 'analysisPolicy.lastProgressPhase', ''));
                $stage = strtolower((string) data_get($payload, 'analysisPolicy.lastProgressStage', ''));
                $message = strtolower((string) ($snapshot->status_message ?? ''));

                return is_array($payload)
                    && (bool) data_get($payload, 'analysisPolicy.progressSnapshot', false)
                    && ! in_array($phase, ['done', 'error'], true)
                    && $stage !== 'scan-stop-requested'
                    && ! str_contains($message, 'fehlgeschlagen')
                    && ! str_contains($message, 'abgebrochen')
                    && ! str_contains($message, 'beendet')
                    && ! str_contains($message, 'abgeschlossen');
            })
            ->count();
    }
}
