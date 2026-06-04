<?php

namespace App\Livewire\Admin;

use Livewire\Component;

class ScraperProfiles extends Component
{
    public function render()
    {
        return view('livewire.admin.scraper-profiles')
            ->layout('layouts.master');
    }
}
