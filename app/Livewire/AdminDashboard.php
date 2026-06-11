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

        $statistics['scans_today'] = $this->scanRecordCountSince(now()->startOfDay());
        $statistics['failed_scans'] = $this->scanRecordCountSince(now()->subDay(), 'error');

        return $statistics;
    }

    private function runningScanCount(): int
    {
        $trackedPersonIds = collect();
        $activeSince = now()->subHours(6);

        if (
            Schema::hasTable('tracked_people')
            && Schema::hasColumn('tracked_people', 'last_instagram_status_level')
        ) {
            $trackedPersonIds = $trackedPersonIds->concat(
                DB::table('tracked_people')
                    ->where('last_instagram_status_level', 'partial')
                    ->where('updated_at', '>=', $activeSince)
                    ->get(['id', 'last_instagram_status_message'])
                    ->filter(fn (object $person): bool => $this->messageRepresentsRunningScan(
                        (string) ($person->last_instagram_status_message ?? ''),
                    ))
                    ->pluck('id'),
            );
        }

        if (Schema::hasTable('tracked_person_instagram_public_profile_scans')) {
            $trackedPersonIds = $trackedPersonIds->concat(
                DB::table('tracked_person_instagram_public_profile_scans')
                    ->where('status_level', 'partial')
                    ->where('analyzed_at', '>=', $activeSince)
                    ->get(['tracked_person_id', 'raw_payload'])
                    ->filter(function (object $scan): bool {
                        $payload = json_decode((string) ($scan->raw_payload ?? ''), true);

                        return is_array($payload)
                            && data_get($payload, 'progressStatus') === 'in_progress'
                            && ! (bool) data_get($payload, 'gracefullyStopped', false)
                            && ! (bool) data_get($payload, 'stoppedForRateLimit', false);
                    })
                    ->pluck('tracked_person_id'),
            );
        }

        return $trackedPersonIds
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->count();
    }

    private function scanRecordCountSince(\DateTimeInterface $since, ?string $statusLevel = null): int
    {
        return collect($this->scanSources())->sum(function (string $timestampColumn, string $table) use ($since, $statusLevel): int {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $timestampColumn)) {
                return 0;
            }

            $query = DB::table($table)->where($timestampColumn, '>=', $since);

            if ($statusLevel !== null && Schema::hasColumn($table, 'status_level')) {
                $query->where('status_level', $statusLevel);
            }

            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            return $query->count();
        });
    }

    private function messageRepresentsRunningScan(string $message): bool
    {
        $message = strtolower(trim($message));

        if ($message === '') {
            return false;
        }

        foreach (['fehlgeschlagen', 'abgebrochen', 'beendet', 'abgeschlossen'] as $terminalTerm) {
            if (str_contains($message, $terminalTerm)) {
                return false;
            }
        }

        return str_contains($message, 'laeuft')
            || str_contains($message, 'läuft')
            || str_contains($message, 'wird vorbereitet')
            || str_contains($message, 'fortgesetzt');
    }

    private function scanSources(): array
    {
        return [
            'tracked_person_instagram_snapshots' => 'analyzed_at',
            'instagram_profile_list_scans' => 'scanned_at',
            'instagram_post_scans' => 'scanned_at',
            'tracked_person_instagram_suggestion_scans' => 'analyzed_at',
            'tracked_person_instagram_public_profile_scans' => 'analyzed_at',
        ];
    }
}
