<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithoutUrlPagination;
use App\Models\User;
use App\Models\Mail;
use Illuminate\Support\Facades\DB;

class Users extends Component
{
    use WithPagination, WithoutUrlPagination; 

    public $search = '';
    public $sortBy = 'name'; 
    public $sortDirection = 'asc'; 
    public $openUserId = null;
    public $usersList;
    public $selectedUsers = [];
    public $selectAll = false;
    public $action = null; 
    public $hasUsers;

    public $showMailModal = false; 
    public $mailUserId = null;
    public $mailSubject = ''; 
    public $mailHeader = '';
    public $mailBody = '';
    public $mailLink = '';

    protected $queryString = ['search', 'sortBy', 'sortDirection'];

    public function updatingSearch()
    {
        $this->resetPage();
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

    public function activateUsers()
    {
        $totalUsers = count($this->selectedUsers);
        $allGood = false; // Wird true, wenn mindestens ein Benutzer aktiviert wurde
        $allActive = true; // Standardmäßig davon ausgehen, dass alle Benutzer aktiv sind
    
        foreach ($this->selectedUsers as $index => $userId) {
            $user = User::find($userId);
            if ($user && !$user->status) {
                    $allActive = false;
                    $this->progress = (($index + 1) / $totalUsers) * 100;
                    $user->update(['status' => true]);
                    $allGood = true;
            }
        }
    
        if ($allActive) {
            $this->dispatch('showAlert', 'Alle ausgewählten Benutzer sind bereits aktiv.', 'info');
        } elseif ($allGood) {
            $this->dispatch('showAlert', 'Benutzer erfolgreich aktiviert und verarbeitet.', 'success');
        } else {
            $this->dispatch('showAlert', 'Fehler beim Aktivieren der Benutzer.', 'error');
        }
        $this->progress = 0; // Fortschrittsanzeige zurücksetzen
    }
    
    public function deactivateUsers()
    {
        $totalUsers = count($this->selectedUsers);
        $allGood = false; // Wird true, wenn mindestens ein Benutzer deaktiviert wurde
        $allInactive = true; // Standardmäßig davon ausgehen, dass alle Benutzer inaktiv sind
    
        foreach ($this->selectedUsers as $index => $userId) {
            $user = User::find($userId);
            if ($user && $user->status) {
                    $allInactive = false; 
                    $this->progress = (($index + 1) / $totalUsers) * 100;
                    $user->update(['status' => false]);
                    $allGood = true;
            }
        }
    
        if ($allInactive) {
            $this->dispatch('showAlert', 'Alle ausgewählten Benutzer sind bereits inaktiv.', 'info');
        } elseif ($allGood) {
            $this->dispatch('showAlert', 'Benutzer erfolgreich deaktiviert und verarbeitet.', 'success');
        } else {
            $this->dispatch('showAlert', 'Fehler beim Deaktivieren der Benutzer.', 'error');
        }
        $this->progress = 0; // Fortschrittsanzeige zurücksetzen
    }
    public function activateUser($userId)
    {
        $user = User::find($userId);

        if ($user && !$user->status) {
                $user->update(['status' => true]);
                $this->dispatch('showAlert', 'Benutzer erfolgreich aktiviert.', 'success');
        } else {
            $this->dispatch('showAlert', 'Benutzer ist bereits aktiv.', 'info');
        }
    }

    public function deactivateUser($userId)
    {
        $user = User::find($userId);

        if ($user && $user->status) {
                $user->update(['status' => false]);
                $this->dispatch('showAlert', 'Benutzer erfolgreich deaktiviert.', 'success');
        } else {
            $this->dispatch('showAlert', 'Benutzer ist bereits inaktiv.', 'info');
        }
    }

    protected function updateHasUsers()
    {
        $this->hasUsers = User::query()
            ->where('role', 'guest')
            ->where(function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('created_at', 'like', '%' . $this->search . '%');
            })
            ->exists();
    }



    public function openMailModal($userId = null)
    {
        if ($userId) {
            // Öffne das Modal für einen einzelnen Benutzer
            $this->mailUserId = $userId;
        } else {
            // Prüfe, ob Benutzer für die Massenverarbeitung ausgewählt wurden
            if (count($this->selectedUsers) === 0) {
                $this->dispatch('showAlert', 'Bitte wähle mindestens einen Benutzer aus, um eine Mail zu senden.', 'info');
                return;
            }
        }
    
        $this->showMailModal = true;
    }

    public function resetMailModal()
    {
        $this->showMailModal = false;
        $this->mailUserId = null;
        $this->mailSubject = '';
        $this->mailHeader = '';
        $this->mailBody = '';
        $this->mailLink = '';
    }

    public function sendMail()
    {
        // Validierung mit individuellen Fehlermeldungen
        $this->validate([
            'mailSubject' => 'required|string|max:255',
            'mailHeader' => 'required|string|max:255',
            'mailBody' => 'required|string',
        ], [
            'mailSubject.required' => 'Bitte geben Sie einen Betreff ein.',
            'mailSubject.max' => 'Der Betreff darf maximal 255 Zeichen lang sein.',
            'mailHeader.required' => 'Bitte geben Sie eine Überschrift ein.',
            'mailHeader.max' => 'Die Überschrift darf maximal 255 Zeichen lang sein.',
            'mailBody.required' => 'Bitte geben Sie eine Nachricht ein.',
        ]);
    
        // Inhalte für die Datenbank vorbereiten
        $content = [
            'subject' => $this->mailSubject,
            'header' => $this->mailHeader,
            'body' => $this->mailBody,
            'link' => $this->mailLink, // Link kann optional leer sein
        ];
    
        if ($this->mailUserId) {
            // Einzelner Benutzer
            $user = User::find($this->mailUserId);
    
            if ($user) {
                // Mail speichern
                Mail::create([
                    'status' => false,
                    'content' => $content,
                    'recipients' => [
                        [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'status' => false, // Status für den Empfänger
                        ],
                    ],
                ]);
    
                $this->dispatch('showAlert', 'E-Mail wurde zur Verarbeitung an ' . $user->email . ' vorbereitet.', 'success');
            } else {
                $this->dispatch('showAlert', 'Benutzer nicht gefunden.', 'error');
            }
        } else {
            // Massenverarbeitung
            $recipients = [];
    
            foreach ($this->selectedUsers as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $recipients[] = [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'status' => false, // Status für jeden Empfänger
                    ];
                }
            }
    
            // Mail speichern
            Mail::create([
                'status' => false,
                'content' => $content,
                'recipients' => $recipients,
            ]);
    
            $this->dispatch('showAlert', 'E-Mail wurde zur Verarbeitung für ' . count($recipients) . ' Benutzer vorbereitet.', 'success');
        }
    
        // Modal zurücksetzen
        $this->resetMailModal();
    }
    
    public function toggleSelectAll()
    {
        $this->selectAll = !$this->selectAll;
    
        if ($this->selectAll) {
            // Alle Benutzer laden und IDs zur `selectedUsers`-Liste hinzufügen
            $this->selectedUsers = User::query()
                ->where('role', 'guest')
                ->where(function ($query) {
                    $query->where('name', 'like', '%' . $this->search . '%')
                          ->orWhere('email', 'like', '%' . $this->search . '%')
                          ->orWhere('created_at', 'like', '%' . $this->search . '%');
                })
                ->pluck('id')
                ->toArray();
        } else {
            // Auswahl aufheben
            $this->selectedUsers = [];
        }
    }

    public function toggleUserSelection($userId)
    {
        if (in_array($userId, $this->selectedUsers)) {
            $this->selectedUsers = array_filter($this->selectedUsers, fn($id) => $id != $userId);
        } else {
            $this->selectedUsers[] = $userId;
        }
    }

    public function render()
    {
        $usersList = User::query()
        ->where('role', 'guest')
        ->where(function($query) {
            $query->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%')
                  ->orWhere('created_at', 'like', '%' . $this->search . '%');
        })
        ->orderBy($this->sortBy, $this->sortDirection)
        ->paginate(10)
        ->withQueryString()
        ->setPath(url('/admin/users'));

        $this->updateHasUsers();

        return view('livewire.admin.users', [
            'users' => $usersList,
        ])->layout('layouts.master');
    }
}
