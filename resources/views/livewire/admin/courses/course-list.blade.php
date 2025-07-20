<div class="px-2">
    <div class="flex justify-between mb-4">
        <h1 class="flex items-center text-lg font-semibold px-2 py-1">
            <span>Kurse</span>
            <span class="ml-2 bg-white text-sky-600 text-xs shadow border border-sky-200 font-bold px-2 py-1 flex items-center justify-center rounded-full h-7 leading-none">
                {{ $courses->count() }}
            </span>
        </h1>
        <x-link-button  @click="$dispatch('open-course-create-edit')" class="btn-xs py-0 leading-[0]">+</x-link-button>
    </div>

    <div class="w-full">
        <div class="grid grid-cols-12 bg-gray-100 p-2 font-semibold text-gray-700 border-b text-left text-sm">
            <div class="col-span-3">Kurs Titel</div>
            <div class="col-span-2">Tutor</div>
            <div class="col-span-2">Zeitraum</div>
            <div class="col-span-2">Status</div>
            <div class="col-span-2">Letzte Änderung</div>
        </div>

        @foreach($courses as $course)
            <div class="grid grid-cols-12 relative border-b py-2 px-2 text-sm">
                <!-- Kursname -->
                <div class="col-span-3 flex items-center space-x-2">
                    <div class="font-semibold truncate">{{ $course->title }}</div>
                    @if($course->archived)
                        <span class="px-2 py-1 text-xs text-red-700 bg-red-100 rounded">Archiviert</span>
                    @endif
                </div>

                <!-- Tutor -->
                <div class="col-span-2 text-gray-700 truncate">
                    {{ $course->tutor?->name ?? '—' }}
                </div>

                <!-- Zeitraum -->
                <div class="col-span-2 text-xs text-gray-600">
                    <span class="text-green-700">
                        {{ $course->start_time ? $course->start_time->locale('de')->isoFormat('ll') : '–' }}
                    </span>
                    <span>–</span>
                    <span class="text-red-700">
                        {{ $course->end_time ? $course->end_time->locale('de')->isoFormat('ll') : '—' }}
                    </span>
                </div>

                <!-- Status -->
                <div class="col-span-2">
                    @if ($course->status === 'draft')
                        <span class="px-2 py-1 text-xs font-semibold text-gray-700 bg-gray-100 rounded">Entwurf</span>
                    @elseif ($course->status === 'active')
                        <span class="px-2 py-1 text-xs font-semibold text-green-700 bg-green-50 rounded">Aktiv</span>
                    @elseif ($course->status === 'archived')
                        <span class="px-2 py-1 text-xs font-semibold text-red-700 bg-red-50 rounded">Archiviert</span>
                    @else
                        <span class="text-xs text-gray-400">—</span>
                    @endif
                </div>

                <!-- Zeit -->
                <div class="col-span-2 text-xs text-gray-500">
                    {{ $course->updated_at?->locale('de')->diffForHumans() }}
                </div>

                <!-- Aktionen -->
                <div class="absolute right-0 top-1/2 transform -translate-y-1/2">
                    <div x-data="{ open: false }" class="relative">
                        <button @click="open = !open" class="text-center px-4 py-2 text-xl font-semibold hover:bg-gray-100 rounded-lg">&#x22EE;</button>
                        <div x-show="open" @click.away="open = false"
                             class="absolute right-0 mt-2 w-40 bg-white border rounded-lg shadow-lg z-10">
                            <ul>
                                <li>
                                <button 
                                                        @click="$dispatch('open-course-create-edit', { courseId: {{ $course->id }} }), open = !open"
                                                        class="block w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">
                                                        Bearbeiten
                                                    </button>

                                </li>
                                <li>
                                    <a href=""
                                       class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                                        Details
                                    </a>
                                </li>
                                <li>
                                    <button  @click="open = false"
                                            class="block w-full text-left px-4 py-2 text-red-600 hover:bg-gray-100">
                                        Archivieren
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @livewire('admin.courses.course-create-edit')
</div>
