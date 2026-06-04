<?php

namespace App\Livewire\Admin;

use Livewire\Component;

class Profiles extends Component
{
    public function render()
    {
        return view('livewire.admin.profiles')
            ->layout('layouts.master');
    }
}
