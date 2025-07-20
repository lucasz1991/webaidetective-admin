<div>
    <x-dialog-modal wire:model="showModal">
        <x-slot name="title">
            Kurs-Einstellungen
        </x-slot>

        <x-slot name="content">
            <div class="space-y-4">

                <div>
                    <label class="block text-sm font-medium text-gray-700">Titel</label>
                    <input type="text" wire:model="title" class="mt-1 block w-full border rounded px-4 py-2" />
                    @error('title') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Beschreibung</label>
                    <textarea wire:model="description" rows="3" class="mt-1 block w-full border rounded px-4 py-2"></textarea>
                    @error('description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Start</label>
                        <input type="date" wire:model="start_time" class="mt-1 block w-full border rounded px-4 py-2" />
                        @error('start_time') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Ende</label>
                        <input type="date" wire:model="end_time" class="mt-1 block w-full border rounded px-4 py-2" />
                        @error('end_time') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>



                <div>
                    <label class="block text-sm font-medium text-gray-700">Tutor</label>
                    <select wire:model="tutor_id" class="mt-1 block w-full border rounded px-4 py-2">
                        <option value="">— wählen —</option>
                        @foreach($tutors as $tutor)
                            <option value="{{ $tutor->id }}">{{ $tutor->name }}</option>
                        @endforeach
                    </select>
                    @error('tutor_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Teilnehmer</label>
                    <select wire:model="participants" multiple class="w-full border rounded px-4 py-2 mt-1 bg-white">
                        @foreach ($possibleParticipants as $participant)
                            <option value="{{ $participant->id }}">{{ $participant->name }} ({{ $participant->email }})</option>
                        @endforeach
                    </select>
                    @error('participants') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

            </div>
        </x-slot>

        <x-slot name="footer">
            <x-button wire:click="saveCourse" class="btn-xs text-sm">Speichern</x-button>
            <x-button wire:click="closeModal" class="btn-xs text-sm">Schließen</x-button>
        </x-slot>
    </x-dialog-modal>
</div>
