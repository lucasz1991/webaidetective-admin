<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class Profiles extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterByUser = '';

    public string $sortBy = 'instagram_profiles.updated_at';

    public string $sortDirection = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterByUser' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterByUser(): void
    {
        $this->resetPage();
    }

    public function sortByField(string $field): void
    {
        $allowedFields = [
            'instagram_profiles.username',
            'instagram_profiles.followers_count',
            'instagram_profiles.following_count',
            'instagram_profiles.posts_count',
            'instagram_profiles.last_scanned_at',
            'instagram_profiles.updated_at',
            'instagram_profiles.created_at',
        ];

        if (! in_array($field, $allowedFields, true)) {
            return;
        }

        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function render()
    {
        $hasProfilesTable = Schema::hasTable('instagram_profiles');
        $hasTrackedPeopleTable = Schema::hasTable('tracked_people');
        $hasUsersTable = Schema::hasTable('users');
        $hasProfileLinksTable = Schema::hasTable('tracked_person_instagram_profile_links');

        $profiles = $hasProfilesTable
            ? $this->loadProfiles($hasTrackedPeopleTable, $hasProfileLinksTable)
            : collect();
        $users = $hasUsersTable ? User::query()->orderBy('name')->get(['id', 'name']) : collect();

        return view('livewire.admin.profiles', [
            'profiles' => $profiles,
            'users' => $users,
            'tablesAvailable' => $hasProfilesTable,
            'hasUserRelations' => $hasUsersTable && ($hasTrackedPeopleTable || $hasProfileLinksTable),
        ])->layout('layouts.master');
    }

    private function loadProfiles(bool $hasTrackedPeopleTable, bool $hasProfileLinksTable): LengthAwarePaginator
    {
        $query = DB::table('instagram_profiles')
            ->whereNull('instagram_profiles.deleted_at')
            ->select([
                'instagram_profiles.id',
                'instagram_profiles.username',
                'instagram_profiles.display_name',
                'instagram_profiles.full_name',
                'instagram_profiles.biography',
                'instagram_profiles.profile_url',
                'instagram_profiles.profile_image_url',
                'instagram_profiles.profile_image_path',
                'instagram_profiles.profile_visibility',
                'instagram_profiles.is_private',
                'instagram_profiles.followers_count',
                'instagram_profiles.following_count',
                'instagram_profiles.posts_count',
                'instagram_profiles.last_status_level',
                'instagram_profiles.last_status_message',
                'instagram_profiles.last_scanned_at',
                'instagram_profiles.created_at',
                'instagram_profiles.updated_at',
            ])
            ->when($this->search !== '', function ($builder): void {
                $search = '%'.$this->search.'%';

                $builder->where(function ($query) use ($search): void {
                    $query
                        ->where('instagram_profiles.username', 'like', $search)
                        ->orWhere('instagram_profiles.display_name', 'like', $search)
                        ->orWhere('instagram_profiles.full_name', 'like', $search)
                        ->orWhere('instagram_profiles.biography', 'like', $search);
                });
            })
            ->when(
                $this->filterByUser !== '' && ($hasTrackedPeopleTable || $hasProfileLinksTable),
                function ($builder) use ($hasTrackedPeopleTable, $hasProfileLinksTable): void {
                    $userId = (int) $this->filterByUser;

                    $builder->where(function ($query) use ($userId, $hasTrackedPeopleTable, $hasProfileLinksTable): void {
                        if ($hasTrackedPeopleTable) {
                            $query->whereExists(function ($subQuery) use ($userId): void {
                                $subQuery
                                    ->selectRaw('1')
                                    ->from('tracked_people')
                                    ->whereColumn('tracked_people.current_instagram_profile_id', 'instagram_profiles.id')
                                    ->where('tracked_people.user_id', $userId);
                            });
                        }

                        if ($hasProfileLinksTable) {
                            $method = $hasTrackedPeopleTable ? 'orWhereExists' : 'whereExists';

                            $query->{$method}(function ($subQuery) use ($userId): void {
                                $subQuery
                                    ->selectRaw('1')
                                    ->from('tracked_person_instagram_profile_links')
                                    ->whereColumn('tracked_person_instagram_profile_links.instagram_profile_id', 'instagram_profiles.id')
                                    ->where('tracked_person_instagram_profile_links.user_id', $userId)
                                    ->whereNull('tracked_person_instagram_profile_links.deleted_at');
                            });
                        }
                    });
                }
            )
            ->orderBy($this->sortBy, $this->sortDirection);

        $profiles = $query->paginate(15);
        $profileIds = collect($profiles->items())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $relationsByProfile = $this->loadRelationsByProfile($profileIds, $hasTrackedPeopleTable, $hasProfileLinksTable);

        $profiles->setCollection(
            $profiles->getCollection()->map(function ($profile) use ($relationsByProfile) {
                $relations = $relationsByProfile->get((int) $profile->id, collect())->values();
                $profile->linked_people = $relations->take(4);
                $profile->linked_people_count = $relations->count();
                $profile->linked_users = $relations
                    ->pluck('user_name')
                    ->filter()
                    ->unique()
                    ->values();

                return $profile;
            })
        );

        return $profiles;
    }

    private function loadRelationsByProfile(array $profileIds, bool $hasTrackedPeopleTable, bool $hasProfileLinksTable): Collection
    {
        if ($profileIds === []) {
            return collect();
        }

        $rows = collect();

        if ($hasTrackedPeopleTable) {
            $rows = $rows->concat(
                DB::table('tracked_people')
                    ->leftJoin('users', 'users.id', '=', 'tracked_people.user_id')
                    ->whereIn('tracked_people.current_instagram_profile_id', $profileIds)
                    ->select([
                        'tracked_people.current_instagram_profile_id as instagram_profile_id',
                        'tracked_people.id as tracked_person_id',
                        'tracked_people.user_id',
                        'tracked_people.first_name',
                        'tracked_people.last_name',
                        'tracked_people.alias',
                        'tracked_people.monitoring_enabled',
                        'users.name as user_name',
                        DB::raw("'Aktuelles Monitoring' as relation_label"),
                    ])
                    ->get()
            );
        }

        if ($hasProfileLinksTable) {
            $rows = $rows->concat(
                DB::table('tracked_person_instagram_profile_links')
                    ->join('tracked_people', 'tracked_people.id', '=', 'tracked_person_instagram_profile_links.tracked_person_id')
                    ->leftJoin('users', 'users.id', '=', 'tracked_person_instagram_profile_links.user_id')
                    ->whereIn('tracked_person_instagram_profile_links.instagram_profile_id', $profileIds)
                    ->whereNull('tracked_person_instagram_profile_links.deleted_at')
                    ->select([
                        'tracked_person_instagram_profile_links.instagram_profile_id',
                        'tracked_people.id as tracked_person_id',
                        'tracked_person_instagram_profile_links.user_id',
                        'tracked_people.first_name',
                        'tracked_people.last_name',
                        'tracked_people.alias',
                        'tracked_people.monitoring_enabled',
                        'users.name as user_name',
                        'tracked_person_instagram_profile_links.relation_type as relation_label',
                    ])
                    ->get()
            );
        }

        return $rows
            ->map(function ($row) {
                $displayName = trim(collect([$row->first_name, $row->last_name])->filter()->implode(' '));

                return (object) [
                    'instagram_profile_id' => (int) $row->instagram_profile_id,
                    'tracked_person_id' => (int) $row->tracked_person_id,
                    'user_id' => $row->user_id ? (int) $row->user_id : null,
                    'display_name' => $displayName !== '' ? $displayName : ($row->alias ?: 'Unbenannte Person'),
                    'user_name' => $row->user_name,
                    'relation_label' => $this->formatRelationLabel($row->relation_label),
                    'monitoring_enabled' => (bool) ($row->monitoring_enabled ?? false),
                ];
            })
            ->groupBy('instagram_profile_id')
            ->map(function (Collection $items): Collection {
                return $items
                    ->unique(fn ($item) => $item->tracked_person_id.'|'.$item->relation_label)
                    ->values();
            });
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
