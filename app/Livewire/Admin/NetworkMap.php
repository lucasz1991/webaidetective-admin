<?php

namespace App\Livewire\Admin;

use App\Support\PublicAssetUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;

class NetworkMap extends Component
{
    public string $mapId = '';

    public bool $showProfilePreviewModal = false;

    public ?string $previewNodeId = null;

    public array $profilePreview = [];

    public function mount(): void
    {
        $this->mapId = 'admin-network-map-'.Str::uuid();
    }

    public function render()
    {
        $hasProfilesTable = Schema::hasTable('instagram_profiles');
        $hasRelationshipsTable = Schema::hasTable('instagram_profile_relationships');
        $hasTrackedPeopleTable = Schema::hasTable('tracked_people');
        $hasCurrentInstagramProfileColumn = $hasTrackedPeopleTable && Schema::hasColumn('tracked_people', 'current_instagram_profile_id');

        $tablesAvailable = $hasProfilesTable && $hasRelationshipsTable;
        $graph = ['nodes' => [], 'edges' => []];
        $stats = [
            'people' => 0,
            'profiles' => 0,
            'nodes' => 0,
            'edges' => 0,
        ];

        if ($tablesAvailable) {
            [$graph, $stats] = $this->buildGraph($hasTrackedPeopleTable, $hasCurrentInstagramProfileColumn);
        }

        return view('livewire.admin.network-map', [
            'graph' => $graph,
            'stats' => $stats,
            'tablesAvailable' => $tablesAvailable,
        ]);
    }

    public function openProfilePreview(string $nodeId): void
    {
        $profileId = $this->resolveProfileIdFromNodeId($nodeId);

        if (! $profileId || ! Schema::hasTable('instagram_profiles')) {
            return;
        }

        $profile = DB::table('instagram_profiles')
            ->where('id', $profileId)
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
            ]);

        if (! $profile) {
            return;
        }

        $activeFollowersCount = 0;
        $activeFollowingCount = 0;
        $incomingCount = 0;

        if (Schema::hasTable('instagram_profile_relationships')) {
            $activeFollowersCount = DB::table('instagram_profile_relationships')
                ->where('source_instagram_profile_id', $profileId)
                ->where('list_type', 'followers')
                ->where('status', 'active')
                ->whereNull('removed_at')
                ->whereNull('deleted_at')
                ->count();

            $activeFollowingCount = DB::table('instagram_profile_relationships')
                ->where('source_instagram_profile_id', $profileId)
                ->where('list_type', 'following')
                ->where('status', 'active')
                ->whereNull('removed_at')
                ->whereNull('deleted_at')
                ->count();

            $incomingCount = DB::table('instagram_profile_relationships')
                ->where('related_instagram_profile_id', $profileId)
                ->where('status', 'active')
                ->whereNull('removed_at')
                ->whereNull('deleted_at')
                ->count();
        }

        $listScans = Schema::hasTable('instagram_profile_list_scans')
            ? DB::table('instagram_profile_list_scans')
                ->where('instagram_profile_id', $profileId)
                ->whereNull('deleted_at')
                ->orderByDesc('scanned_at')
                ->limit(5)
                ->get([
                    'list_type',
                    'status_level',
                    'status_message',
                    'active_count',
                    'observed_count',
                    'scanned_at',
                ])
                ->map(fn ($scan) => [
                    'list_type' => $scan->list_type,
                    'status_level' => $scan->status_level,
                    'status_message' => $scan->status_message,
                    'active_count' => (int) ($scan->active_count ?? 0),
                    'observed_count' => (int) ($scan->observed_count ?? 0),
                    'scanned_at' => $scan->scanned_at ? \Carbon\Carbon::parse($scan->scanned_at)->format('d.m.Y H:i') : null,
                ])
                ->all()
            : [];

        $linkedPeople = $this->loadLinkedPeopleForProfile($profileId)
            ->map(fn ($person) => [
                'display_name' => $person->display_name,
                'user_name' => $person->user_name,
                'detail_url' => $person->user_id ? route('admin.user-profile', ['userId' => $person->user_id]) : null,
            ])
            ->all();

        $this->previewNodeId = $nodeId;
        $this->profilePreview = [
            'id' => (int) $profile->id,
            'username' => ltrim((string) $profile->username, '@'),
            'handle' => '@'.ltrim((string) $profile->username, '@'),
            'display_name' => $profile->display_name ?: $profile->full_name ?: '@'.ltrim((string) $profile->username, '@'),
            'biography' => $profile->biography,
            'profile_url' => $profile->profile_url ?: 'https://www.instagram.com/'.ltrim((string) $profile->username, '@').'/',
            'image_url' => PublicAssetUrl::fromStorageOrRemote($profile->profile_image_path, $profile->profile_image_url),
            'visibility' => $profile->profile_visibility ?: ($profile->is_private ? 'private' : 'unknown'),
            'followers_count' => $profile->followers_count,
            'following_count' => $profile->following_count,
            'posts_count' => $profile->posts_count,
            'last_scanned_at' => $profile->last_scanned_at ? \Carbon\Carbon::parse($profile->last_scanned_at)->format('d.m.Y H:i') : null,
            'last_status_level' => $profile->last_status_level,
            'last_status_message' => $profile->last_status_message,
            'active_followers_count' => $activeFollowersCount,
            'active_following_count' => $activeFollowingCount,
            'incoming_count' => $incomingCount,
            'linked_people' => $linkedPeople,
            'list_scans' => $listScans,
        ];

        $this->showProfilePreviewModal = true;
    }

    public function closeProfilePreview(): void
    {
        $this->showProfilePreviewModal = false;
        $this->previewNodeId = null;
        $this->profilePreview = [];
    }

    private function buildGraph(bool $hasTrackedPeopleTable, bool $hasCurrentInstagramProfileColumn): array
    {
        $profiles = DB::table('instagram_profiles')
            ->whereNull('deleted_at')
            ->get([
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
            ]);

        $relationships = DB::table('instagram_profile_relationships')
            ->where('status', 'active')
            ->whereIn('list_type', ['followers', 'following'])
            ->whereNull('removed_at')
            ->whereNull('deleted_at')
            ->get([
                'source_instagram_profile_id',
                'related_instagram_profile_id',
                'list_type',
            ]);

        $trackedPeople = $hasTrackedPeopleTable
            ? DB::table('tracked_people')
                ->leftJoin('users', 'users.id', '=', 'tracked_people.user_id')
                ->get(array_filter([
                    'tracked_people.id',
                    'tracked_people.user_id',
                    'tracked_people.first_name',
                    'tracked_people.last_name',
                    'tracked_people.alias',
                    'tracked_people.instagram_username',
                    $hasCurrentInstagramProfileColumn ? 'tracked_people.current_instagram_profile_id' : null,
                    'tracked_people.monitoring_enabled',
                    'tracked_people.is_primary',
                    'tracked_people.instagram_profile_image_path',
                    'tracked_people.last_instagram_status_level',
                    'tracked_people.last_instagram_status_message',
                    'users.name as user_name',
                ]))
            : collect();

        $profileNodeIds = [];
        $profileIdsByUsername = [];
        $nodes = [];
        $edges = [];

        foreach ($profiles as $profile) {
            $nodeId = 'profile-'.(int) $profile->id;
            $username = $this->normalizeUsername($profile->username);
            $imageUrl = PublicAssetUrl::fromStorageOrRemote($profile->profile_image_path, $profile->profile_image_url);

            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'profile',
                'label' => $profile->display_name ?: $profile->full_name ?: '@'.$username,
                'handle' => '@'.$username,
                'username' => $username,
                'imageUrl' => $imageUrl,
                'hasImage' => filled($imageUrl),
                'isPrimary' => false,
                'role' => 'Gespeichertes Profil',
                'status' => $profile->profile_visibility ?: ($profile->is_private ? 'private' : 'unknown'),
                'detail' => $this->profileDetail($profile),
                'isKnownProfile' => true,
            ];

            $profileNodeIds[(int) $profile->id] = $nodeId;

            if ($username !== '') {
                $profileIdsByUsername[$username] = (int) $profile->id;
            }
        }

        foreach ($trackedPeople as $person) {
            $nodeId = 'person-'.(int) $person->id;
            $displayName = trim(collect([$person->first_name, $person->last_name])->filter()->implode(' '));
            $displayName = $displayName !== '' ? $displayName : ($person->alias ?: 'Unbenannte Person');
            $username = $this->normalizeUsername($person->instagram_username);
            $imageUrl = PublicAssetUrl::storage($person->instagram_profile_image_path);

            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'person',
                'label' => $displayName,
                'handle' => $username !== '' ? '@'.$username : '',
                'username' => $username,
                'imageUrl' => $imageUrl,
                'hasImage' => filled($imageUrl),
                'isPrimary' => (bool) ($person->is_primary ?? false),
                'role' => $person->monitoring_enabled ? 'Monitoring aktiv' : 'Monitoring aus',
                'status' => $person->last_instagram_status_level ?: 'neutral',
                'detail' => $this->personDetail($person),
                'isKnownProfile' => false,
                'detailUrl' => $person->user_id ? route('admin.user-profile', ['userId' => $person->user_id]) : null,
            ];

            $profileId = $hasCurrentInstagramProfileColumn
                ? ($person->current_instagram_profile_id ?: ($username !== '' ? ($profileIdsByUsername[$username] ?? null) : null))
                : ($username !== '' ? ($profileIdsByUsername[$username] ?? null) : null);

            if ($profileId && isset($profileNodeIds[(int) $profileId])) {
                $this->mergeEdge(
                    $edges,
                    'tracked-profile-rel',
                    $nodeId,
                    $profileNodeIds[(int) $profileId],
                    'Beobachtetes Profil',
                );
            }
        }

        if (Schema::hasTable('tracked_person_instagram_profile_links')) {
            $links = DB::table('tracked_person_instagram_profile_links')
                ->join('tracked_people', 'tracked_people.id', '=', 'tracked_person_instagram_profile_links.tracked_person_id')
                ->whereNull('tracked_person_instagram_profile_links.deleted_at')
                ->get([
                    'tracked_person_instagram_profile_links.tracked_person_id',
                    'tracked_person_instagram_profile_links.instagram_profile_id',
                    'tracked_person_instagram_profile_links.relation_type',
                ]);

            foreach ($links as $link) {
                $personNodeId = 'person-'.(int) $link->tracked_person_id;
                $profileNodeId = $profileNodeIds[(int) $link->instagram_profile_id] ?? null;

                if (! $profileNodeId || ! isset($nodes[$personNodeId])) {
                    continue;
                }

                $this->mergeEdge(
                    $edges,
                    'tracked-profile-rel',
                    $personNodeId,
                    $profileNodeId,
                    $this->formatRelationLabel($link->relation_type),
                );
            }
        }

        foreach ($relationships as $relationship) {
            $sourceNodeId = $profileNodeIds[(int) $relationship->source_instagram_profile_id] ?? null;
            $relatedNodeId = $profileNodeIds[(int) $relationship->related_instagram_profile_id] ?? null;

            if (! $sourceNodeId || ! $relatedNodeId || $sourceNodeId === $relatedNodeId) {
                continue;
            }

            if ($relationship->list_type === 'followers') {
                $this->mergeEdge($edges, 'tracked-list', $relatedNodeId, $sourceNodeId, 'Followerliste');
                continue;
            }

            $this->mergeEdge($edges, 'tracked-list', $sourceNodeId, $relatedNodeId, 'Gefolgt-Liste');
        }

        $graph = $this->applyLayout(array_values($nodes), array_values($edges));
        $stats = [
            'people' => $trackedPeople->count(),
            'profiles' => $profiles->count(),
            'nodes' => count($graph['nodes']),
            'edges' => count($graph['edges']),
        ];

        return [$graph, $stats];
    }

    private function loadLinkedPeopleForProfile(int $profileId): Collection
    {
        $rows = collect();

        if (Schema::hasTable('tracked_people') && Schema::hasColumn('tracked_people', 'current_instagram_profile_id')) {
            $rows = $rows->concat(
                DB::table('tracked_people')
                    ->leftJoin('users', 'users.id', '=', 'tracked_people.user_id')
                    ->where('tracked_people.current_instagram_profile_id', $profileId)
                    ->get([
                        'tracked_people.id as tracked_person_id',
                        'tracked_people.user_id',
                        'tracked_people.first_name',
                        'tracked_people.last_name',
                        'tracked_people.alias',
                        'users.name as user_name',
                    ])
            );
        }

        if (Schema::hasTable('tracked_person_instagram_profile_links')) {
            $rows = $rows->concat(
                DB::table('tracked_person_instagram_profile_links')
                    ->join('tracked_people', 'tracked_people.id', '=', 'tracked_person_instagram_profile_links.tracked_person_id')
                    ->leftJoin('users', 'users.id', '=', 'tracked_person_instagram_profile_links.user_id')
                    ->where('tracked_person_instagram_profile_links.instagram_profile_id', $profileId)
                    ->whereNull('tracked_person_instagram_profile_links.deleted_at')
                    ->get([
                        'tracked_people.id as tracked_person_id',
                        'tracked_person_instagram_profile_links.user_id',
                        'tracked_people.first_name',
                        'tracked_people.last_name',
                        'tracked_people.alias',
                        'users.name as user_name',
                    ])
            );
        }

        return $rows
            ->map(function ($row) {
                $displayName = trim(collect([$row->first_name, $row->last_name])->filter()->implode(' '));

                return (object) [
                    'tracked_person_id' => (int) $row->tracked_person_id,
                    'user_id' => $row->user_id ? (int) $row->user_id : null,
                    'display_name' => $displayName !== '' ? $displayName : ($row->alias ?: 'Unbenannte Person'),
                    'user_name' => $row->user_name,
                ];
            })
            ->unique(fn ($row) => $row->tracked_person_id.'|'.$row->user_id)
            ->values();
    }

    private function mergeEdge(array &$edges, string $type, string $from, string $to, string $label): void
    {
        $edgeId = $type.'-'.$from.'-'.$to;

        if (! isset($edges[$edgeId])) {
            $edges[$edgeId] = [
                'id' => $edgeId,
                'from' => $from,
                'to' => $to,
                'type' => $type,
                'label' => $label,
            ];

            return;
        }

        $labels = collect(explode(' + ', (string) $edges[$edgeId]['label']))
            ->push($label)
            ->filter()
            ->unique()
            ->values()
            ->implode(' + ');

        $edges[$edgeId]['label'] = $labels;
    }

    private function applyLayout(array $nodes, array $edges): array
    {
        $width = 1800;
        $height = 1200;
        $centerX = $width / 2;
        $centerY = $height / 2;
        $counts = [];

        foreach ($nodes as $node) {
            $counts[$node['id']] = 0;
        }

        foreach ($edges as $edge) {
            if (isset($counts[$edge['from']])) {
                $counts[$edge['from']]++;
            }

            if (isset($counts[$edge['to']])) {
                $counts[$edge['to']]++;
            }
        }

        $primary = collect($nodes)->firstWhere('isPrimary', true)
            ?: collect($nodes)->firstWhere('type', 'person')
            ?: collect($nodes)->first();
        $positions = [];

        if ($primary) {
            $positions[$primary['id']] = ['x' => $centerX, 'y' => $centerY];
        }

        $people = collect($nodes)
            ->filter(fn (array $node): bool => $node['type'] === 'person' && ($primary['id'] ?? null) !== $node['id'])
            ->sortByDesc(fn (array $node) => $counts[$node['id']] ?? 0)
            ->values()
            ->all();
        $profiles = collect($nodes)
            ->filter(fn (array $node): bool => $node['type'] !== 'person')
            ->sortByDesc(fn (array $node) => $counts[$node['id']] ?? 0)
            ->values()
            ->all();

        foreach ($people as $index => $node) {
            $ring = (int) floor($index / 20);
            $ringIndex = $index - ($ring * 20);
            $ringCount = min(20, count($people) - ($ring * 20));
            $angle = deg2rad(-90 + (($ringIndex / max(1, $ringCount)) * 360));
            $radius = 170 + ($ring * 110);

            $positions[$node['id']] = [
                'x' => $centerX + cos($angle) * $radius,
                'y' => $centerY + sin($angle) * $radius,
            ];
        }

        foreach ($profiles as $index => $node) {
            $ring = (int) floor($index / 42);
            $ringIndex = $index - ($ring * 42);
            $ringCount = min(42, count($profiles) - ($ring * 42));
            $angle = deg2rad(-70 + (($ringIndex / max(1, $ringCount)) * 360));
            $radius = 320 + ($ring * 125) - min(90, ($counts[$node['id']] ?? 0) * 6);

            $positions[$node['id']] = [
                'x' => $centerX + cos($angle) * $radius,
                'y' => $centerY + sin($angle) * $radius,
            ];
        }

        foreach ($nodes as $index => $node) {
            $position = $positions[$node['id']] ?? ['x' => $centerX, 'y' => $centerY];
            $nodes[$index]['x'] = round($position['x'], 1);
            $nodes[$index]['y'] = round($position['y'], 1);
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    private function profileDetail(object $profile): ?string
    {
        return collect([
            $profile->followers_count !== null ? 'Follower: '.number_format((int) $profile->followers_count, 0, ',', '.') : null,
            $profile->following_count !== null ? 'Gefolgt: '.number_format((int) $profile->following_count, 0, ',', '.') : null,
            $profile->last_scanned_at ? 'Zuletzt gescannt: '.\Carbon\Carbon::parse($profile->last_scanned_at)->format('d.m.Y H:i') : null,
        ])->filter()->implode(' | ');
    }

    private function personDetail(object $person): ?string
    {
        return collect([
            $person->user_name ? 'Benutzer: '.$person->user_name : null,
            $person->alias ? 'Alias: '.$person->alias : null,
            $person->last_instagram_status_message ?: null,
        ])->filter()->implode(' | ');
    }

    private function normalizeUsername(?string $username): string
    {
        return Str::lower(ltrim(trim((string) $username), '@'));
    }

    private function resolveProfileIdFromNodeId(string $nodeId): ?int
    {
        if (! preg_match('/^profile-(\d+)$/', $nodeId, $matches)) {
            return null;
        }

        return (int) ($matches[1] ?? 0) ?: null;
    }

    private function formatRelationLabel(?string $label): string
    {
        $label = trim((string) $label);

        if ($label === '') {
            return 'Profilbezug';
        }

        return str_replace('_', ' ', ucfirst($label));
    }
}
