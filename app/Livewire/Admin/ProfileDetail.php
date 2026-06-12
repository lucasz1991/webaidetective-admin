<?php

namespace App\Livewire\Admin;

use App\Support\PublicAssetUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class ProfileDetail extends Component
{
    private const SCAN_LIMIT = 100;

    public int $profileId;

    public string $scanFilter = 'all';

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

        $profile->image_url = PublicAssetUrl::fromStorageOrRemote($profile->profile_image_path, $profile->profile_image_url);
        $profile->visibility = $profile->profile_visibility ?: ($profile->is_private ? 'private' : 'unknown');
        $profile->display_label = $profile->display_name ?: $profile->full_name ?: '@'.ltrim((string) $profile->username, '@');

        $linkedPeople = $this->loadLinkedPeople();
        $allScans = $this->loadScans($profile, $linkedPeople->pluck('tracked_person_id'));
        $scanCounts = [
            'all' => $allScans->count(),
            'running' => $allScans->where('is_running', true)->count(),
            'analysis' => $allScans->whereIn('scan_type', ['mini', 'full', 'analysis'])->count(),
            'lists' => $allScans->whereIn('scan_type', ['followers', 'following'])->count(),
            'posts' => $allScans->where('scan_type', 'posts')->count(),
            'suggestions' => $allScans->where('scan_type', 'suggestions')->count(),
            'connections' => $allScans->where('scan_type', 'public_connections')->count(),
            'errors' => $allScans->where('status_level', 'error')->count(),
        ];
        $scans = $allScans
            ->filter(fn (object $scan): bool => match ($this->scanFilter) {
                'running' => $scan->is_running,
                'analysis' => in_array($scan->scan_type, ['mini', 'full', 'analysis'], true),
                'lists' => in_array($scan->scan_type, ['followers', 'following'], true),
                'posts' => $scan->scan_type === 'posts',
                'suggestions' => $scan->scan_type === 'suggestions',
                'connections' => $scan->scan_type === 'public_connections',
                'errors' => $scan->status_level === 'error',
                default => true,
            })
            ->values();
        $relationships = $this->loadRelationships();

        return view('livewire.admin.profile-detail', [
            'profile' => $profile,
            'linkedPeople' => $linkedPeople,
            'scans' => $scans,
            'scanCounts' => $scanCounts,
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

    private function loadScans(object $profile, Collection $trackedPersonIds): Collection
    {
        $trackedPersonIds = $trackedPersonIds
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        return collect()
            ->concat($this->loadRunningScans($profile, $trackedPersonIds))
            ->concat($this->loadSnapshotScans($profile))
            ->concat($this->loadProfileListScans($profile))
            ->concat($this->loadPostScans($profile))
            ->concat($this->loadSuggestionScans($profile, $trackedPersonIds))
            ->concat($this->loadPublicConnectionScans($profile, $trackedPersonIds))
            ->unique('scan_key')
            ->sortByDesc(fn (object $scan): int => $scan->scanned_at
                ? (strtotime((string) $scan->scanned_at) ?: 0)
                : 0)
            ->take(self::SCAN_LIMIT)
            ->values();
    }

    private function loadRunningScans(object $profile, Collection $trackedPersonIds): Collection
    {
        if (
            $trackedPersonIds->isEmpty()
            || ! Schema::hasTable('tracked_people')
            || ! Schema::hasColumn('tracked_people', 'last_instagram_status_level')
        ) {
            return collect();
        }

        $query = DB::table('tracked_people')
            ->leftJoin('users', 'users.id', '=', 'tracked_people.user_id');

        if (Schema::hasTable('tracked_person_instagram_snapshots')) {
            $latestSnapshots = DB::table('tracked_person_instagram_snapshots')
                ->selectRaw('tracked_person_id, MAX(id) as snapshot_id')
                ->whereIn('tracked_person_id', $trackedPersonIds)
                ->groupBy('tracked_person_id');

            $query
                ->leftJoinSub($latestSnapshots, 'latest_snapshots', function ($join): void {
                    $join->on('latest_snapshots.tracked_person_id', '=', 'tracked_people.id');
                })
                ->leftJoin(
                    'tracked_person_instagram_snapshots as snapshot',
                    'snapshot.id',
                    '=',
                    'latest_snapshots.snapshot_id',
                );
        }

        $snapshotColumns = Schema::hasTable('tracked_person_instagram_snapshots')
            ? ['snapshot.screenshot_path', 'snapshot.raw_payload']
            : [DB::raw('NULL as screenshot_path'), DB::raw('NULL as raw_payload')];

        return $query
            ->whereIn('tracked_people.id', $trackedPersonIds)
            ->where('tracked_people.last_instagram_status_level', 'partial')
            ->where('tracked_people.updated_at', '>=', now()->subHours(6))
            ->orderByDesc('tracked_people.updated_at')
            ->get([
                'tracked_people.id as tracked_person_id',
                'tracked_people.last_instagram_status_message as status_message',
                'tracked_people.updated_at as scanned_at',
                'tracked_people.instagram_posts_count as posts_count',
                'tracked_people.instagram_followers_count as followers_count',
                'tracked_people.instagram_following_count as following_count',
                'users.id as user_id',
                'users.name as user_name',
                ...$snapshotColumns,
            ])
            ->filter(fn (object $scan): bool => $this->messageRepresentsRunningScan(
                (string) ($scan->status_message ?? ''),
            ) || $this->payloadRepresentsRunningScan($this->decodePayload($scan->raw_payload ?? null)))
            ->map(function (object $scan) use ($profile): object {
                $payload = $this->decodePayload($scan->raw_payload ?? null);
                $scanType = $this->resolveScanType($payload, (string) $scan->status_message);

                return $this->makeScan($profile, [
                    'scan_key' => 'running-'.$scan->tracked_person_id,
                    'scan_type' => $scanType,
                    'status_level' => 'partial',
                    'status_message' => $scan->status_message,
                    'scanned_at' => $scan->scanned_at,
                    'screenshot_url' => $this->storageUrl($scan->screenshot_path)
                        ?: $this->screenshotUrlFromPayload($payload),
                    'is_running' => true,
                    'user_id' => $scan->user_id,
                    'user_name' => $scan->user_name,
                    'metrics' => $this->profileMetrics(
                        $scan->posts_count,
                        $scan->followers_count,
                        $scan->following_count,
                    ),
                ]);
            });
    }

    private function loadSnapshotScans(object $profile): Collection
    {
        if (! Schema::hasTable('tracked_person_instagram_snapshots')) {
            return collect();
        }

        $query = DB::table('tracked_person_instagram_snapshots as snapshot')
            ->leftJoin('tracked_people', 'tracked_people.id', '=', 'snapshot.tracked_person_id')
            ->leftJoin('users', 'users.id', '=', 'tracked_people.user_id');

        if (Schema::hasColumn('tracked_person_instagram_snapshots', 'instagram_profile_id')) {
            $query->where('snapshot.instagram_profile_id', $this->profileId);
        } elseif (Schema::hasColumn('tracked_people', 'current_instagram_profile_id')) {
            $query->where('tracked_people.current_instagram_profile_id', $this->profileId);
        } else {
            return collect();
        }

        return $query
            ->orderByDesc('snapshot.analyzed_at')
            ->limit(self::SCAN_LIMIT)
            ->get([
                'snapshot.id as scan_id',
                'snapshot.status_level',
                'snapshot.status_message',
                'snapshot.analyzed_at as scanned_at',
                'snapshot.posts_count',
                'snapshot.followers_count',
                'snapshot.following_count',
                'snapshot.screenshot_path',
                'snapshot.raw_payload',
                'users.id as user_id',
                'users.name as user_name',
            ])
            ->map(function (object $scan) use ($profile): ?object {
                $payload = $this->decodePayload($scan->raw_payload);

                if ($this->payloadRepresentsRunningScan($payload)) {
                    return null;
                }

                $scanType = $this->resolveScanType($payload, (string) $scan->status_message);

                if (in_array($scanType, ['followers', 'following'], true)) {
                    return null;
                }

                return $this->makeScan($profile, [
                    'scan_key' => 'snapshot-'.$scan->scan_id,
                    'scan_type' => $scanType,
                    'status_level' => $scan->status_level,
                    'status_message' => $scan->status_message,
                    'scanned_at' => $scan->scanned_at,
                    'screenshot_url' => $this->storageUrl($scan->screenshot_path)
                        ?: $this->screenshotUrlFromPayload($payload),
                    'user_id' => $scan->user_id,
                    'user_name' => $scan->user_name,
                    'metrics' => $this->profileMetrics(
                        $scan->posts_count,
                        $scan->followers_count,
                        $scan->following_count,
                    ),
                ]);
            })
            ->filter()
            ->values();
    }

    private function loadProfileListScans(object $profile): Collection
    {
        if (! Schema::hasTable('instagram_profile_list_scans')) {
            return collect();
        }

        $query = DB::table('instagram_profile_list_scans as scan')
            ->leftJoin('users', 'users.id', '=', 'scan.user_id')
            ->where('scan.instagram_profile_id', $this->profileId);

        if (Schema::hasColumn('instagram_profile_list_scans', 'deleted_at')) {
            $query->whereNull('scan.deleted_at');
        }

        return $query
            ->orderByDesc('scan.scanned_at')
            ->limit(self::SCAN_LIMIT)
            ->get([
                'scan.id as scan_id',
                'scan.list_type',
                'scan.scan_mode',
                'scan.status_level',
                'scan.status_message',
                'scan.active_count',
                'scan.observed_count',
                'scan.added_count',
                'scan.raw_payload',
                'scan.scanned_at',
                'users.id as user_id',
                'users.name as user_name',
            ])
            ->map(function (object $scan) use ($profile): object {
                $payload = $this->decodePayload($scan->raw_payload);

                return $this->makeScan($profile, [
                    'scan_key' => 'list-'.$scan->scan_id,
                    'scan_type' => $scan->list_type === 'following' ? 'following' : 'followers',
                    'status_level' => $scan->status_level,
                    'status_message' => $scan->status_message,
                    'scanned_at' => $scan->scanned_at,
                    'screenshot_url' => $this->screenshotUrlFromPayload($payload),
                    'user_id' => $scan->user_id,
                    'user_name' => $scan->user_name,
                    'context_label' => $scan->scan_mode
                        ? 'Modus: '.str_replace('_', ' ', (string) $scan->scan_mode)
                        : null,
                    'metrics' => [
                        $this->metric('Beobachtet', $scan->observed_count),
                        $this->metric('Aktiv', $scan->active_count),
                        $this->metric('Neu', $scan->added_count),
                    ],
                ]);
            });
    }

    private function loadPostScans(object $profile): Collection
    {
        if (! Schema::hasTable('instagram_post_scans')) {
            return collect();
        }

        return DB::table('instagram_post_scans as scan')
            ->leftJoin('users', 'users.id', '=', 'scan.user_id')
            ->where('scan.instagram_profile_id', $this->profileId)
            ->orderByDesc('scan.scanned_at')
            ->limit(self::SCAN_LIMIT)
            ->get([
                'scan.id as scan_id',
                'scan.status_level',
                'scan.status_message',
                'scan.observed_count',
                'scan.new_count',
                'scan.updated_count',
                'scan.raw_payload',
                'scan.scanned_at',
                'users.id as user_id',
                'users.name as user_name',
            ])
            ->map(function (object $scan) use ($profile): object {
                $payload = $this->decodePayload($scan->raw_payload);

                return $this->makeScan($profile, [
                    'scan_key' => 'posts-'.$scan->scan_id,
                    'scan_type' => 'posts',
                    'status_level' => $scan->status_level,
                    'status_message' => $scan->status_message,
                    'scanned_at' => $scan->scanned_at,
                    'screenshot_url' => $this->screenshotUrlFromPayload($payload),
                    'user_id' => $scan->user_id,
                    'user_name' => $scan->user_name,
                    'metrics' => [
                        $this->metric('Beobachtet', $scan->observed_count),
                        $this->metric('Neu', $scan->new_count),
                        $this->metric('Aktualisiert', $scan->updated_count),
                    ],
                ]);
            });
    }

    private function loadSuggestionScans(object $profile, Collection $trackedPersonIds): Collection
    {
        if (
            $trackedPersonIds->isEmpty()
            || ! Schema::hasTable('tracked_person_instagram_suggestion_scans')
        ) {
            return collect();
        }

        return DB::table('tracked_person_instagram_suggestion_scans as scan')
            ->leftJoin('users', 'users.id', '=', 'scan.user_id')
            ->whereIn('scan.tracked_person_id', $trackedPersonIds)
            ->orderByDesc('scan.analyzed_at')
            ->limit(self::SCAN_LIMIT)
            ->get([
                'scan.id as scan_id',
                'scan.status_level',
                'scan.status_message',
                'scan.suggestions_observed_count',
                'scan.suggestions_checked_count',
                'scan.suggestion_matches_count',
                'scan.raw_payload',
                'scan.analyzed_at as scanned_at',
                'users.id as user_id',
                'users.name as user_name',
            ])
            ->map(function (object $scan) use ($profile): object {
                $payload = $this->decodePayload($scan->raw_payload);

                return $this->makeScan($profile, [
                    'scan_key' => 'suggestions-'.$scan->scan_id,
                    'scan_type' => 'suggestions',
                    'status_level' => $scan->status_level,
                    'status_message' => $scan->status_message,
                    'scanned_at' => $scan->scanned_at,
                    'screenshot_url' => $this->screenshotUrlFromPayload($payload),
                    'user_id' => $scan->user_id,
                    'user_name' => $scan->user_name,
                    'metrics' => [
                        $this->metric('Gefunden', $scan->suggestions_observed_count),
                        $this->metric('Geprueft', $scan->suggestions_checked_count),
                        $this->metric('Treffer', $scan->suggestion_matches_count),
                    ],
                ]);
            });
    }

    private function loadPublicConnectionScans(object $profile, Collection $trackedPersonIds): Collection
    {
        if (
            $trackedPersonIds->isEmpty()
            || ! Schema::hasTable('tracked_person_instagram_public_profile_scans')
        ) {
            return collect();
        }

        return DB::table('tracked_person_instagram_public_profile_scans as scan')
            ->leftJoin('users', 'users.id', '=', 'scan.user_id')
            ->whereIn('scan.tracked_person_id', $trackedPersonIds)
            ->orderByDesc('scan.analyzed_at')
            ->limit(self::SCAN_LIMIT)
            ->get([
                'scan.id as scan_id',
                'scan.public_username',
                'scan.relation_type',
                'scan.followers_observed_count',
                'scan.following_observed_count',
                'scan.status_level',
                'scan.status_message',
                'scan.raw_payload',
                'scan.analyzed_at as scanned_at',
                'users.id as user_id',
                'users.name as user_name',
            ])
            ->map(function (object $scan) use ($profile): object {
                $payload = $this->decodePayload($scan->raw_payload);
                $found = (int) data_get($payload, 'foundFollowers', 0)
                    + (int) data_get($payload, 'foundFollowing', 0);

                return $this->makeScan($profile, [
                    'scan_key' => 'connections-'.$scan->scan_id,
                    'scan_type' => 'public_connections',
                    'status_level' => $scan->status_level,
                    'status_message' => $scan->status_message,
                    'scanned_at' => $scan->scanned_at,
                    'screenshot_url' => $this->screenshotUrlFromPayload($payload),
                    'user_id' => $scan->user_id,
                    'user_name' => $scan->user_name,
                    'context_label' => $scan->public_username
                        ? 'Pruefprofil @'.ltrim((string) $scan->public_username, '@')
                        : null,
                    'metrics' => [
                        $this->metric('Follower gepr.', $scan->followers_observed_count),
                        $this->metric('Gefolgt gepr.', $scan->following_observed_count),
                        $this->metric('Treffer', $found),
                    ],
                ]);
            });
    }

    private function makeScan(object $profile, array $attributes): object
    {
        $scanType = (string) ($attributes['scan_type'] ?? 'analysis');

        return (object) [
            'scan_key' => (string) $attributes['scan_key'],
            'scan_type' => $scanType,
            'scan_type_label' => $this->scanTypeLabel($scanType),
            'scan_type_classes' => $this->scanTypeClasses($scanType),
            'status_level' => $this->normalizeStatusLevel((string) ($attributes['status_level'] ?? 'unknown')),
            'status_message' => $attributes['status_message'] ?? null,
            'scanned_at' => $attributes['scanned_at'] ?? null,
            'is_running' => (bool) ($attributes['is_running'] ?? false),
            'screenshot_url' => $attributes['screenshot_url'] ?? null,
            'profile_image_url' => $profile->image_url,
            'display_name' => $profile->display_label,
            'username' => ltrim((string) $profile->username, '@'),
            'context_label' => $attributes['context_label'] ?? null,
            'user_id' => is_numeric($attributes['user_id'] ?? null) ? (int) $attributes['user_id'] : null,
            'user_name' => $attributes['user_name'] ?? null,
            'metrics' => collect($attributes['metrics'] ?? [])->take(3)->values(),
        ];
    }

    private function resolveScanType(array $payload, string $message): string
    {
        $mode = strtolower(trim((string) data_get($payload, 'analysisPolicy.scanMode', '')));

        if (in_array($mode, ['mini', 'full', 'followers', 'following'], true)) {
            return $mode;
        }

        $message = strtolower($message.' '.(string) data_get($payload, 'statusMessage', ''));

        return match (true) {
            str_contains($message, 'verbindungsscan') => 'public_connections',
            str_contains($message, 'vorschlag') => 'suggestions',
            str_contains($message, 'beitrag'), str_contains($message, 'postscan') => 'posts',
            str_contains($message, 'gefolgt') => 'following',
            str_contains($message, 'follower') => 'followers',
            str_contains($message, 'voll') => 'full',
            str_contains($message, 'mini') => 'mini',
            default => 'analysis',
        };
    }

    private function scanTypeLabel(string $scanType): string
    {
        return match ($scanType) {
            'mini' => 'Mini-Scan',
            'full' => 'Vollanalyse',
            'followers' => 'Followerlisten-Scan',
            'following' => 'Gefolgt-Listen-Scan',
            'posts' => 'Beitragsscan',
            'suggestions' => 'Vorschlagsscan',
            'public_connections' => 'Verbindungsscan',
            default => 'Profilanalyse',
        };
    }

    private function scanTypeClasses(string $scanType): string
    {
        return match ($scanType) {
            'mini' => 'bg-sky-100 text-sky-800 ring-sky-200',
            'full' => 'bg-pink-100 text-pink-800 ring-pink-200',
            'followers' => 'bg-blue-100 text-blue-800 ring-blue-200',
            'following' => 'bg-cyan-100 text-cyan-800 ring-cyan-200',
            'posts' => 'bg-violet-100 text-violet-800 ring-violet-200',
            'suggestions' => 'bg-fuchsia-100 text-fuchsia-800 ring-fuchsia-200',
            'public_connections' => 'bg-amber-100 text-amber-800 ring-amber-200',
            default => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }

    private function profileMetrics(mixed $posts, mixed $followers, mixed $following): array
    {
        return [
            $this->metric('Posts', $posts),
            $this->metric('Follower', $followers),
            $this->metric('Folgt', $following),
        ];
    }

    private function metric(string $label, mixed $value): object
    {
        return (object) [
            'label' => $label,
            'value' => is_numeric($value) ? (int) $value : $value,
        ];
    }

    private function screenshotUrlFromPayload(array $payload): ?string
    {
        $paths = [];

        array_walk_recursive($payload, function (mixed $value, mixed $key) use (&$paths): void {
            if (
                in_array($key, ['liveScreenshotUrl', 'screenshotPath', 'screenshot_path'], true)
                && is_scalar($value)
                && trim((string) $value) !== ''
            ) {
                $paths[] = (string) $value;
            }
        });

        foreach (array_reverse(array_values(array_unique($paths))) as $path) {
            $url = $this->storageUrl($path);

            if ($url) {
                return $url;
            }
        }

        return null;
    }

    private function storageUrl(mixed $path): ?string
    {
        if (! is_scalar($path)) {
            return null;
        }

        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $urlPath = parse_url($path, PHP_URL_PATH);

            if (is_string($urlPath) && str_contains($urlPath, '/storage/')) {
                $path = ltrim(substr($urlPath, strpos($urlPath, '/storage/') + 9), '/');
            } else {
                return $path;
            }
        }

        return PublicAssetUrl::storage($path);
    }

    private function payloadRepresentsRunningScan(array $payload): bool
    {
        return (bool) data_get($payload, 'analysisPolicy.progressSnapshot', false)
            && ! in_array(
                strtolower((string) data_get($payload, 'analysisPolicy.lastProgressPhase', '')),
                ['done', 'error'],
                true,
            )
            && data_get($payload, 'analysisPolicy.terminalStatus') === null;
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

    private function normalizeStatusLevel(string $status): string
    {
        $status = strtolower(trim($status));

        return in_array($status, ['success', 'error', 'cancelled', 'partial'], true)
            ? $status
            : 'unknown';
    }

    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
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
