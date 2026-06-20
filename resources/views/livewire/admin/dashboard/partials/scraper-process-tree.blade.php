@php
    $processes = collect($processes ?? []);
    $depth = (int) ($depth ?? 0);
    $keyPrefix = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($keyPrefix ?? 'process-tree')) ?: 'process-tree';
@endphp

@foreach($processes as $process)
    @php
        $depth = min(8, max(0, (int) $depth));
        $isScraperCommand = (bool) ($process->is_scraper_command ?? false);
        $relatedUsernames = collect($process->effective_related_usernames ?? $process->related_usernames ?? [])
            ->filter()
            ->take(3);
        $children = collect($process->children ?? []);
        $hasChildren = $children->isNotEmpty();
        $isOpen = (bool) ($process->is_group_open ?? false);
    @endphp

    <div class="divide-y divide-slate-100" wire:key="{{ $keyPrefix }}-scraper-process-node-{{ $process->pid }}">
        <div class="grid gap-3 px-4 py-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
            <div class="min-w-0">
                <div class="flex min-w-0 gap-3" style="padding-left: {{ $depth * 1.15 }}rem">
                    @if($depth > 0)
                        <div class="mt-2 h-10 w-4 shrink-0 border-l border-b border-slate-200"></div>
                    @endif

                    @if($hasChildren)
                        <button
                            type="button"
                            wire:click="toggleProcessGroup({{ $process->pid }})"
                            wire:loading.attr="disabled"
                            class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-xs font-black text-slate-500 transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 disabled:cursor-wait disabled:opacity-60"
                            aria-label="{{ $isOpen ? 'Kindprozesse einklappen' : 'Kindprozesse aufklappen' }}"
                        >
                            {{ $isOpen ? '-' : '+' }}
                        </button>
                    @else
                        <div class="mt-0.5 h-7 w-7 shrink-0"></div>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-lg {{ $isScraperCommand ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700' }} px-2.5 py-1 text-xs font-black">
                                PID {{ $process->pid }}
                            </span>
                            <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">PPID {{ $process->parent_pid }}</span>
                            <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">
                                {{ $isScraperCommand ? 'Scraper' : 'Kindprozess' }}
                            </span>
                            @if($process->script_name)
                                <span class="rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">{{ $process->script_name }}</span>
                            @elseif($process->family_script_name)
                                <span class="rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-bold text-indigo-700">unter {{ $process->family_script_name }}</span>
                            @endif
                            @if($process->scan_type_label || $process->family_scan_type_label)
                                <span class="rounded-lg bg-white px-2.5 py-1 text-xs font-bold text-slate-600 ring-1 ring-slate-200">
                                    {{ $process->scan_type_label ?: $process->family_scan_type_label }}
                                </span>
                            @endif
                            @foreach($relatedUsernames as $username)
                                <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">@{{ $username }}</span>
                            @endforeach
                            <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ $process->elapsed }}</span>
                            <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">CPU {{ number_format($process->cpu, 1, ',', '.') }}%</span>
                            <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">RAM {{ number_format($process->memory, 1, ',', '.') }}%</span>
                            @if($hasChildren)
                                <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ $children->count() }} Kinder</span>
                            @endif
                            @if($isScraperCommand && $process->is_idle_suspect)
                                <span class="rounded-lg bg-amber-100 px-2.5 py-1 text-xs font-black text-amber-800">Leerlauf-Verdacht</span>
                            @endif
                        </div>
                        <div class="mt-2 truncate font-mono text-xs text-slate-500" title="{{ $process->command }}">
                            {{ $process->short_command }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @if($isScraperCommand)
                    <button
                        type="button"
                        wire:click="terminateScraperProcess({{ $process->pid }})"
                        wire:confirm="Diesen Instagram-Scraper-Prozess mit SIGTERM beenden?"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2 text-xs font-black text-amber-800 transition hover:bg-amber-100 disabled:cursor-wait disabled:opacity-60"
                    >
                        Beenden
                    </button>
                    <button
                        type="button"
                        wire:click="terminateScraperProcess({{ $process->pid }}, true)"
                        wire:confirm="Diesen Instagram-Scraper-Prozess sofort mit SIGKILL beenden? Das sollte nur im Notfall passieren."
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2 text-xs font-black text-rose-700 transition hover:bg-rose-100 disabled:cursor-wait disabled:opacity-60"
                    >
                        Erzwingen
                    </button>
                @else
                    <span class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs font-bold text-slate-500">
                        Root PID {{ $process->family_root_pid }}
                    </span>
                @endif
            </div>
        </div>

        @if($hasChildren && $isOpen)
            @include('livewire.admin.dashboard.partials.scraper-process-tree', [
                'processes' => $children,
                'depth' => $depth + 1,
                'keyPrefix' => $keyPrefix,
            ])
        @endif
    </div>
@endforeach
