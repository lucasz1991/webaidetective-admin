<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class Profiles extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterByUser = '';

    public string $sortBy = 'tracked_people.updated_at';

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
            'tracked_people.updated_at',
            'tracked_people.created_at',
            'tracked_people.instagram_username',
            'instagram_profiles.followers_count',
            'tracked_people.last_instagram_analyzed_at',
            'users.name',
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
        $tablesAvailable = Schema::hasTable('tracked_people')
            && Schema::hasTable('instagram_profiles')
            && Schema::hasTable('users');

        $profiles = collect();
        $users = collect();

        if ($tablesAvailable) {
            $profiles = DB::table('tracked_people')
                ->leftJoin('instagram_profiles', 'instagram_profiles.id', '=', 'tracked_people.current_instagram_profile_id')
                ->leftJoin('users', 'users.id', '=', 'tracked_people.user_id')
                ->select([
                    'tracked_people.id',
                    'tracked_people.user_id',
                    'tracked_people.first_name',
                    'tracked_people.last_name',
                    'tracked_people.alias',
                    'tracked_people.instagram_username',
                    'tracked_people.monitoring_enabled',
                    'tracked_people.is_primary',
                    'tracked_people.last_instagram_status_level',
                    'tracked_people.last_instagram_status_message',
                    'tracked_people.last_instagram_analyzed_at',
                    'tracked_people.created_at',
                    'tracked_people.updated_at',
                    'users.name as user_name',
                    'instagram_profiles.username as profile_username',
                    'instagram_profiles.display_name as profile_display_name',
                    'instagram_profiles.full_name as profile_full_name',
                    'instagram_profiles.profile_image_url',
                    'instagram_profiles.profile_image_path',
                    'instagram_profiles.profile_visibility',
                    'instagram_profiles.followers_count',
                    'instagram_profiles.following_count',
                    'instagram_profiles.posts_count',
                    'instagram_profiles.last_scanned_at',
                ])
                ->when($this->search !== '', function ($query): void {
                    $search = '%'.$this->search.'%';

                    $query->where(function ($subQuery) use ($search): void {
                        $subQuery
                            ->where('tracked_people.first_name', 'like', $search)
                            ->orWhere('tracked_people.last_name', 'like', $search)
                            ->orWhere('tracked_people.alias', 'like', $search)
                            ->orWhere('tracked_people.instagram_username', 'like', $search)
                            ->orWhere('instagram_profiles.username', 'like', $search)
                            ->orWhere('instagram_profiles.display_name', 'like', $search)
                            ->orWhere('instagram_profiles.full_name', 'like', $search)
                            ->orWhere('users.name', 'like', $search);
                    });
                })
                ->when($this->filterByUser !== '', function ($query): void {
                    $query->where('tracked_people.user_id', (int) $this->filterByUser);
                })
                ->orderBy($this->sortBy, $this->sortDirection)
                ->paginate(15);

            $users = User::query()
                ->whereIn('id', function ($query): void {
                    $query->select('user_id')->from('tracked_people')->whereNotNull('user_id');
                })
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return view('livewire.admin.profiles', [
            'profiles' => $profiles,
            'users' => $users,
            'tablesAvailable' => $tablesAvailable,
        ])
            ->layout('layouts.master');
    }
}
