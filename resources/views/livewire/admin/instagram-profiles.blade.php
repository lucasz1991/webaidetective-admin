<div wire:loading.class="cursor-wait">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-700">Instagram Profile</h1>
        <p class="text-gray-500">Es gibt insgesamt {{ $profiles->total() }} getrackte Instagram-Profile.</p>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
        <div x-data="{ focused: false }" @click.away="focused = false" x-cloak class="relative">
            <div class="flex items-center border border-gray-300 rounded-full ring ring-offset-4 transition-all duration-300"
                :class="{
                    'w-[300px]': (focused || @json($search).length > 0),
                    'w-[40px]': !(focused || @json($search).length > 0),
                }">
                <input type="text" wire:model.live.debounce.500ms="search" placeholder="Username, Displayname oder Bio..."
                    class="w-full px-2 py-1 text-sm focus:ring-none bg-transparent border-none ring-none"
                    x-ref="searchInput" 
                    @click="focused = true" 
                    :class="(focused || @json($search).length > 0) ? 'block' : 'hidden'" />
                <div @click="focused = true; $refs.searchInput.focus()"
                    class="flex items-center justify-center w-[40px] h-[40px] text-gray-400 hover:text-gray-500 cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192.904 192.904" class="h-4 w-4">
                        <path d="m190.707 180.101-47.078-47.077c11.702-14.072 18.752-32.142 18.752-51.831C162.381 36.423 125.959 0 81.191 0 36.422 0 0 36.423 0 81.193c0 44.767 36.422 81.187 81.191 81.187 19.688 0 37.759-7.049 51.831-18.751l47.079 47.078a7.474 7.474 0 0 0 5.303 2.197 7.498 7.498 0 0 0 5.303-12.803zM15 81.193C15 44.694 44.693 15 81.191 15c36.497 0 66.189 29.694 66.189 66.193 0 36.496-29.692 66.187-66.189 66.187C44.693 147.38 15 117.689 15 81.193z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <select wire:model.live="filterByUser" class="border border-gray-300 rounded px-3 py-1 text-sm">
            <option value="">Alle Benutzer</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid grid-cols-12 bg-gray-100 p-2 font-semibold text-gray-700 border-b border-gray-300">
        <div class="col-span-1 flex items-center">
            <button wire:click="toggleSelectAll" class="btn-xs">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />
                </svg>
            </button>
        </div>
        <div class="col-span-3">
            <button wire:click="sortByField('username')" class="text-left flex items-center">
                Username
                @if ($sortBy === 'username')
                    <svg class="w-4 h-4 ml-2" style="transform: rotate({{ $sortDirection === 'asc' ? '0deg' : '180deg' }});" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m19 9-7 7-7-7" />
                    </svg>
                @endif
            </button>
        </div>
        <div class="col-span-2">
            <button wire:click="sortByField('followers_count')" class="text-left flex items-center">
                Follower
                @if ($sortBy === 'followers_count')
                    <svg class="w-4 h-4 ml-2" style="transform: rotate({{ $sortDirection === 'asc' ? '0deg' : '180deg' }});" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m19 9-7 7-7-7" />
                    </svg>
                @endif
            </button>
        </div>
        <div class="col-span-2">
            <button wire:click="sortByField('user_id')" class="text-left flex items-center">
                Benutzer
            </button>
        </div>
        <div class="col-span-2">
            <button wire:click="sortByField('created_at')" class="text-left flex items-center">
                Hinzugefügt
                @if ($sortBy === 'created_at')
                    <svg class="w-4 h-4 ml-2" style="transform: rotate({{ $sortDirection === 'asc' ? '0deg' : '180deg' }});" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m19 9-7 7-7-7" />
                    </svg>
                @endif
            </button>
        </div>
        <div class="col-span-2">Aktionen</div>
    </div>

    <div>
        @forelse ($profiles as $profile)
            <div class="grid grid-cols-12 items-center p-3 border text-left hover:bg-blue-50 text-sm">
                <div class="col-span-1">
                    <input type="checkbox" wire:click="toggleProfileSelection({{ $profile->id }})" 
                        {{ in_array($profile->id, $selectedProfiles) ? 'checked' : '' }} class="rounded">
                </div>
                <div class="col-span-3 flex items-center space-x-3">
                    @if($profile->profile_picture_url)
                        <img src="{{ $profile->profile_picture_url }}" alt="{{ $profile->username }}" class="h-8 w-8 rounded-full">
                    @endif
                    <div>
                        <div class="font-semibold">{{ $profile->username }}</div>
                        <div class="text-xs text-gray-500">{{ $profile->display_name }}</div>
                    </div>
                </div>
                <div class="col-span-2 text-gray-600">
                    {{ number_format($profile->followers_count ?? 0) }}
                </div>
                <div class="col-span-2 text-gray-600">
                    <a href="{{ route('admin.user-profile', ['userId' => $profile->user_id]) }}" class="text-blue-600 hover:underline">
                        {{ $profile->user?->name ?? 'N/A' }}
                    </a>
                </div>
                <div class="col-span-2 text-gray-500 text-xs">
                    {{ $profile->created_at->format('d.m.Y') }}
                </div>
                <div class="col-span-2 flex items-center space-x-2">
                    <a href="https://instagram.com/{{ $profile->username }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM5.838 12a6.162 6.162 0 1 1 12.324 0 6.162 6.162 0 0 1-12.324 0zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm4.965-10.322a1.44 1.44 0 1 1 2.881.001 1.44 1.44 0 0 1-2.881-.001z"/>
                        </svg>
                    </a>
                    <button wire:click="deleteProfile({{ $profile->id }})" class="text-red-600 hover:text-red-800" onclick="confirm('Wirklich löschen?') || event.stopImmediatePropagation()">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        @empty
            <div class="p-6 text-center text-gray-500">
                Keine Instagram-Profile gefunden.
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $profiles->links() }}
    </div>
</div>
