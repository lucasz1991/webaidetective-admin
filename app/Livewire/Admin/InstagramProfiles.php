<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\InstagramProfile;
use App\Models\User;

class InstagramProfiles extends Component
{
    use WithPagination;

    public $search = '';
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    public $selectedProfiles = [];
    public $filterByUser = '';

    public function render()
    {
        $query = InstagramProfile::query();

        if ($this->search) {
            $query->where('username', 'like', '%' . $this->search . '%')
                  ->orWhere('bio', 'like', '%' . $this->search . '%')
                  ->orWhere('display_name', 'like', '%' . $this->search . '%');
        }

        if ($this->filterByUser) {
            $query->where('user_id', $this->filterByUser);
        }

        $profiles = $query->orderBy($this->sortBy, $this->sortDirection)
                         ->paginate(15);

        return view('livewire.admin.instagram-profiles', [
            'profiles' => $profiles,
            'users' => User::where('status', true)->get(),
        ]);
    }

    public function sortByField($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function toggleProfileSelection($profileId)
    {
        if (in_array($profileId, $this->selectedProfiles)) {
            $this->selectedProfiles = array_diff($this->selectedProfiles, [$profileId]);
        } else {
            $this->selectedProfiles[] = $profileId;
        }
    }

    public function toggleSelectAll()
    {
        $profileIds = InstagramProfile::pluck('id')->toArray();
        $this->selectedProfiles = count($this->selectedProfiles) === count($profileIds) ? [] : $profileIds;
    }

    public function deleteProfile($profileId)
    {
        InstagramProfile::find($profileId)?->delete();
        $this->dispatch('success', message: 'Instagram-Profil gelöscht');
    }

    public function deleteSelected()
    {
        InstagramProfile::whereIn('id', $this->selectedProfiles)->delete();
        $this->selectedProfiles = [];
        $this->dispatch('success', message: count($this->selectedProfiles) . ' Profile gelöscht');
    }
}
