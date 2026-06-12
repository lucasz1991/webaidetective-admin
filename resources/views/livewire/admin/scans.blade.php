<div class="space-y-6">
    <section class="overflow-hidden rounded-3xl bg-slate-950 px-6 py-7 text-white shadow-lg sm:px-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="text-xs font-bold uppercase tracking-[0.24em] text-indigo-300">Administration</div>
                <h1 class="mt-3 text-3xl font-black tracking-tight">Alle Scans</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                    Zentrale Uebersicht der laufenden und abgeschlossenen Profil-, Listen-, Beitrags-, Vorschlags- und Verbindungsscans.
                </p>
            </div>

            <a
                href="{{ route('admin.index') }}"
                wire:navigate
                class="inline-flex w-fit items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/15"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="m15 18-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Zum Dashboard
            </a>
        </div>
    </section>

    <livewire:admin.dashboard.scan-monitor :display-limit="100" :show-load-more="true" />
</div>
