<?php

namespace App\Livewire\Admin;

use Livewire\Component;

class Scans extends Component
{
    public function render()
    {
        return view('livewire.admin.scans')
            ->layout('layouts.master');
    }
}
