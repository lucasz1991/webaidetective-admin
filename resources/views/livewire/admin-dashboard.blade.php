<div class="space-y-6 py-6" wire:poll.30s>
    @php
        $systemHasErrors = $statistics['failed_scans'] > 0;
    @endphp

    <section class="relative overflow-hidden rounded-3xl bg-blue-700 text-white shadow-xl">
        <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-indigo-500/20 blur-3xl"></div>
        <div class="absolute -bottom-32 left-1/3 h-64 w-64 rounded-full bg-cyan-400/10 blur-3xl"></div>

        <div class="relative grid gap-8 px-6 py-7 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end lg:px-8 lg:py-9">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.22em] text-indigo-200">
                        System Cockpit
                    </span>
                    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $systemHasErrors ? 'bg-rose-400/15 text-rose-200' : 'bg-emerald-400/15 text-emerald-200' }}">
                        <span class="h-2 w-2 rounded-full {{ $systemHasErrors ? 'bg-rose-400' : 'bg-emerald-400' }}"></span>
                        {{ $systemHasErrors ? $statistics['failed_scans'].' Fehler in 24 Std.' : 'Keine Scanfehler in 24 Std.' }}
                    </span>
                </div>
                <h1 class="mt-5 text-3xl font-black tracking-tight sm:text-4xl">Admin-Dashboard</h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                    Zentrale Sicht auf Benutzeraktivitaet, Dauerbeobachtungen und den aktuellen Zustand der Instagram-Scans.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.profiles') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-950 shadow-sm transition hover:bg-indigo-50">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="2"/>
                        <path d="M5 20c.7-4 3-6 7-6s6.3 2 7 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Profile
                </a>
                <a href="{{ route('admin.users') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/10">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M16 20v-1.5c0-2.5-2-4.5-4.5-4.5h-3C6 14 4 16 4 18.5V20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <circle cx="10" cy="7" r="3" stroke="currentColor" stroke-width="2"/>
                        <path d="M17 11a3 3 0 1 0 0-6M18 14c1.5.6 2.5 2.1 2.5 4V20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Benutzer
                </a>
            </div>
        </div>
    </section>

    @php
        $cards = [
            [
                'label' => 'Benutzer',
                'value' => $statistics['users'],
                'detail' => $statistics['active_users'].' gerade aktiv',
                'tone' => 'blue',
                'icon' => 'users',
            ],
            [
                'label' => 'Beobachtete Personen',
                'value' => $statistics['tracked_people'],
                'detail' => $statistics['monitored_people'].' dauerhaft aktiv',
                'tone' => 'indigo',
                'icon' => 'eye',
            ],
            [
                'label' => 'Instagram-Profile',
                'value' => $statistics['profiles'],
                'detail' => 'Zentral im Profilgraph',
                'tone' => 'violet',
                'icon' => 'profile',
            ],
            [
                'label' => 'Scans heute',
                'value' => $statistics['scans_today'],
                'detail' => $statistics['running_scans'].' laufen aktuell',
                'tone' => 'emerald',
                'icon' => 'scan',
            ],
            [
                'label' => 'Fehlerhafte Scans',
                'value' => $statistics['failed_scans'],
                'detail' => 'Innerhalb der letzten 24 Std.',
                'tone' => 'rose',
                'icon' => 'warning',
            ],
        ];
        $tones = [
            'blue' => ['icon' => 'bg-blue-100 text-blue-700', 'bar' => 'bg-blue-500'],
            'indigo' => ['icon' => 'bg-indigo-100 text-indigo-700', 'bar' => 'bg-indigo-500'],
            'violet' => ['icon' => 'bg-violet-100 text-violet-700', 'bar' => 'bg-violet-500'],
            'emerald' => ['icon' => 'bg-emerald-100 text-emerald-700', 'bar' => 'bg-emerald-500'],
            'rose' => ['icon' => 'bg-rose-100 text-rose-700', 'bar' => 'bg-rose-500'],
        ];
    @endphp

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach($cards as $card)
            <article class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="absolute inset-x-0 top-0 h-1 {{ $tones[$card['tone']]['bar'] }}"></div>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">{{ $card['label'] }}</div>
                        <div class="mt-3 text-3xl font-black tracking-tight text-slate-950">{{ number_format($card['value'], 0, ',', '.') }}</div>
                    </div>
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $tones[$card['tone']]['icon'] }}">
                        @switch($card['icon'])
                            @case('users')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="2"/><path d="M3 20c.6-4 2.6-6 6-6s5.4 2 6 6M17 5a3 3 0 0 1 0 6M17 14c2.4.5 3.7 2.5 4 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                @break
                            @case('eye')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="2"/></svg>
                                @break
                            @case('profile')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><rect x="4" y="3" width="16" height="18" rx="3" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="9" r="2.5" stroke="currentColor" stroke-width="2"/><path d="M8 17c.5-2.3 1.8-3.5 4-3.5s3.5 1.2 4 3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                @break
                            @case('scan')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M8 3H5a2 2 0 0 0-2 2v3M16 3h3a2 2 0 0 1 2 2v3M8 21H5a2 2 0 0 1-2-2v-3M16 21h3a2 2 0 0 0 2-2v-3M7 12h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                @break
                            @default
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none"><path d="M12 9v4M12 17h.01M10.3 4.3 2.8 17.5A2 2 0 0 0 4.5 20h15a2 2 0 0 0 1.7-2.5L13.7 4.3a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                        @endswitch
                    </div>
                </div>
                <div class="mt-3 text-sm font-medium text-slate-500">{{ $card['detail'] }}</div>
            </article>
        @endforeach
    </section>

    <livewire:admin.dashboard.scan-monitor />
</div>
