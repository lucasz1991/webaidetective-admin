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
            $statistics['running_scans'] = Schema::hasColumn('tracked_people', 'last_instagram_status_level')
                ? DB::table('tracked_people')->where('last_instagram_status_level', 'partial')->count()
                : 0;
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
}
