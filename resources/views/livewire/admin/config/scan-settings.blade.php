<form wire:submit.prevent="saveSettings" class="space-y-6" wire:loading.class="opacity-60 pointer-events-none">
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
        Diese Werte gelten zentral fuer die Instagram-Scans der Base-Anwendung. Erhoehte Versuchs- und Rundenwerte verlaengern Scans und koennen Instagram-Rate-Limits wahrscheinlicher machen.
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900">Prozessueberwachung</h3>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">PHP-Prozess ohne Ausgabe beenden nach Sekunden</label>
                <input type="number" min="60" wire:model.defer="policies.global.process_stall_timeout_seconds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('policies.global.process_stall_timeout_seconds') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Node-Watchdog ohne Fortschritt nach Sekunden</label>
                <input type="number" min="60" wire:model.defer="policies.global.node_watchdog_timeout_seconds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('policies.global.node_watchdog_timeout_seconds') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Navigation-Timeout je Instagram-Seite in Sekunden</label>
                <input type="number" min="30" max="3600" wire:model.defer="policies.global.navigation_timeout_seconds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('policies.global.navigation_timeout_seconds') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Extra-Versuche bei Scraper-Profilwechsel</label>
                <input type="number" min="0" max="10" wire:model.defer="policies.global.profile_switch_extra_attempts" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <p class="mt-1 text-xs text-gray-500">Wird genutzt, wenn ein Profil wegen Rate-Limit/Challenge blockiert wurde.</p>
                @error('policies.global.profile_switch_extra_attempts') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Wartezeit nach Login in ms</label>
                <input type="number" min="500" max="60000" wire:model.defer="policies.global.post_login_wait_ms" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('policies.global.post_login_wait_ms') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Tipp-Verzoegerung beim Login in ms</label>
                <input type="number" min="0" max="1000" wire:model.defer="policies.global.typing_delay_ms" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('policies.global.typing_delay_ms') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                <input type="checkbox" wire:model="policies.global.script_watchdog_enabled" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-800">Node-Watchdog aktiv</span>
            </label>
            <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                <input type="checkbox" wire:model="policies.global.browser_disconnect_abort" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-800">Bei Browser-/Puppeteer-Abbruch Scan stoppen</span>
            </label>
            <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                <input type="checkbox" wire:model="policies.global.live_preview_enabled" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-800">Live-Preview-Screenshots aktiv</span>
            </label>
            <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                <input type="checkbox" wire:model="policies.global.skip_debug_artifacts" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-800">Debug-HTML-Artefakte nicht speichern</span>
            </label>
            <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                <input type="checkbox" wire:model="policies.global.block_heavy_resources" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-800">Bilder/Medien/Fonts im Browser blockieren</span>
            </label>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 p-5">
            <h3 class="text-lg font-semibold text-gray-900">Fehlerbehandlung je Scanart</h3>
            <p class="mt-1 text-sm text-gray-500">Ein Versuch ist der normale Lauf. Hoehere Werte starten den jeweiligen Scraper bei einem echten Fehler erneut.</p>
        </div>
        <div class="divide-y divide-gray-200">
            @foreach($scanLabels as $scanType => $scanLabel)
                <div wire:key="scan-policy-{{ $scanType }}" class="grid gap-4 p-5 md:grid-cols-3 md:items-end">
                    <div>
                        <p class="font-semibold text-gray-800">{{ $scanLabel }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Max. Fehlerversuche</label>
                        <input type="number" min="1" max="10" wire:model.defer="policies.{{ $scanType }}.error_attempts" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error("policies.$scanType.error_attempts") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Pause vor Wiederholung in Sekunden</label>
                        <input type="number" min="0" max="300" wire:model.defer="policies.{{ $scanType }}.retry_delay_seconds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @error("policies.$scanType.retry_delay_seconds") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900">Profil- und Listen-Scans</h3>
            <div class="mt-4 space-y-4">
                <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                    <input type="checkbox" wire:model="policies.mini.session_fallback_enabled" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span>
                        <span class="block text-sm font-medium text-gray-800">Mini-Scan bei Bedarf mit gespeicherter Session wiederholen</span>
                        <span class="block text-xs text-gray-500">Ist der oeffentliche Lauf unvollstaendig, folgt ein Session-Lauf.</span>
                    </span>
                </label>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Profil-Kennzahlen maximal laden</label>
                    <input type="number" min="1" max="10" wire:model.defer="policies.profile.visible_count_attempts" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Max. Eintraege pro Liste</label>
                        <input type="number" min="0" wire:model.defer="policies.lists.max_items" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p class="mt-1 text-xs text-gray-500">0 bedeutet unbegrenzt.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Max. Scroll-Runden pro Liste</label>
                        <input type="number" min="20" wire:model.defer="policies.lists.max_scroll_rounds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3 sm:col-span-2">
                        <input type="checkbox" wire:model="policies.lists.partition_large_lists" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>
                            <span class="block text-sm font-medium text-gray-800">Grosse Listen alphabetisch partitionieren</span>
                            <span class="block text-xs text-gray-500">Nutzt Suchabfragen pro Dialog, wenn normale Scroll-Listen zu gross werden.</span>
                        </span>
                    </label>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Partition ab Listen-Groesse</label>
                        <input type="number" min="1" wire:model.defer="policies.lists.partition_threshold" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Suchabfragen pro Dialog</label>
                        <input type="number" min="1" max="100" wire:model.defer="policies.lists.search_queries_per_dialog" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Max. Treffer pro Suchpartition</label>
                        <input type="number" min="25" wire:model.defer="policies.lists.search_partition_max_items" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Fortschritt speichern alle X Eintraege</label>
                        <input type="number" min="25" wire:model.defer="policies.lists.progress_checkpoint_size" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Zielsuche: Max. Treffer</label>
                        <input type="number" min="0" wire:model.defer="policies.lists.search_target_max_items" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p class="mt-1 text-xs text-gray-500">0 bedeutet nicht begrenzen.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Zielsuche: Max. Scroll-Runden</label>
                        <input type="number" min="1" wire:model.defer="policies.lists.search_target_max_scroll_rounds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Suchfeld-Versuche</label>
                        <input type="number" min="1" max="10" wire:model.defer="policies.lists.search_input_max_attempts" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Wartezeit nach Suchabfrage in ms</label>
                        <input type="number" min="250" max="60000" wire:model.defer="policies.lists.search_wait_ms" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-900">Beitragsscan</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Max. Beitraege</label>
                    <input type="number" min="1" wire:model.defer="policies.posts.max_items" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Max. Scroll-Runden</label>
                    <input type="number" min="1" wire:model.defer="policies.posts.max_scroll_rounds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Max. Likes pro Beitrag</label>
                    <input type="number" min="1" wire:model.defer="policies.posts.max_likes_per_post" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Max. Kommentare pro Beitrag</label>
                    <input type="number" min="1" wire:model.defer="policies.posts.max_comments_per_post" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3 sm:col-span-2">
                    <input type="checkbox" wire:model="policies.posts.open_likes_dialog_enabled" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span>
                        <span class="block text-sm font-medium text-gray-800">Likes-Dialog pro Beitrag oeffnen</span>
                        <span class="block text-xs text-gray-500">Sammelt Liker-Profile direkt aus dem sichtbaren Instagram-Dialog und speichert Likes inkl. Profil-Verknuepfung.</span>
                    </span>
                </label>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Likes-Dialog: Max. Scroll-Runden</label>
                    <input type="number" min="1" max="1000" wire:model.defer="policies.posts.like_dialog_max_scroll_rounds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Kommentar-Dialog: Max. Scroll-Runden</label>
                    <input type="number" min="1" max="1000" wire:model.defer="policies.posts.comment_dialog_max_scroll_rounds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900">Vorschlaege und DeepSearch</h3>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">Max. sichtbare Vorschlaege</label>
                <input type="number" min="1" wire:model.defer="policies.suggestions.max_items" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Inline-Runden</label>
                <input type="number" min="1" wire:model.defer="policies.suggestions.inline_max_rounds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Dialog-Runden</label>
                <input type="number" min="1" wire:model.defer="policies.suggestions.dialog_max_rounds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <label class="flex items-start gap-3 rounded-md border border-blue-200 bg-blue-50 p-4">
                <input type="checkbox" wire:model="policies.suggestion_deep_search.skip_previously_checked" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span>
                    <span class="block text-sm font-semibold text-blue-900">Frueher gepruefte Vorschlaege auslassen</span>
                    <span class="block text-xs text-blue-700">Deaktivieren, damit bei jedem DeepSearch alle sichtbaren Vorschlaege erneut geprueft werden.</span>
                </span>
            </label>
            <div>
                <label class="block text-sm font-medium text-gray-700">Nach Nichttreffern dauerhaft auslassen</label>
                <input type="number" min="1" max="100" wire:model.defer="policies.suggestion_deep_search.no_match_skip_after" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <p class="mt-1 text-xs text-gray-500">Nur wirksam, wenn fruehere Pruefungen ausgelassen werden.</p>
            </div>
        </div>

        <div class="mt-5 grid gap-4 md:grid-cols-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Max. Kandidaten</label>
                <input type="number" min="1" wire:model.defer="policies.suggestion_deep_search.candidate_max_items" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Versuche je Kandidat</label>
                <input type="number" min="1" max="10" wire:model.defer="policies.suggestion_deep_search.candidate_error_attempts" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Kandidaten-Pause in Sekunden</label>
                <input type="number" min="0" max="300" wire:model.defer="policies.suggestion_deep_search.candidate_retry_delay_seconds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Max. Account-Wechsel</label>
                <input type="number" min="0" max="10" wire:model.defer="policies.suggestion_deep_search.max_scraper_profile_switches" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Kandidaten Inline-Runden</label>
                <input type="number" min="1" wire:model.defer="policies.suggestion_deep_search.candidate_inline_max_rounds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Kandidaten Dialog-Runden</label>
                <input type="number" min="1" wire:model.defer="policies.suggestion_deep_search.candidate_dialog_max_rounds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Listen-Suchrunden je Kandidat</label>
                <input type="number" min="1" wire:model.defer="policies.suggestion_deep_search.public_list_max_scroll_rounds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                <input type="checkbox" wire:model="policies.suggestion_deep_search.profile_hover_cards_enabled" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-800">Profil-Hovercards fuer Kandidaten nutzen</span>
            </label>
            <div>
                <label class="block text-sm font-medium text-gray-700">Hovercard-Wartezeit in ms</label>
                <input type="number" min="250" max="60000" wire:model.defer="policies.suggestion_deep_search.profile_hover_card_wait_ms" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-900">Public-Profile-Verbindungsscan</h3>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                <input type="checkbox" wire:model="policies.public_connections.resume_previous" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-800">Unterbrochenen Kandidatenstand fortsetzen</span>
            </label>
            <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                <input type="checkbox" wire:model="policies.public_connections.skip_completed_candidates" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-800">Bereits abgeschlossene Kandidaten beim Fortsetzen auslassen</span>
            </label>
        </div>
        <div class="mt-5 grid gap-4 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">Versuche je Kandidat</label>
                <input type="number" min="1" max="10" wire:model.defer="policies.public_connections.candidate_max_attempts" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Erste Retry-Pause in Sekunden</label>
                <input type="number" min="2" wire:model.defer="policies.public_connections.candidate_retry_delay_seconds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Max. Retry-Pause in Sekunden</label>
                <input type="number" min="2" wire:model.defer="policies.public_connections.candidate_retry_max_delay_seconds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Max. Dauer je Kandidat in Sekunden</label>
                <input type="number" min="60" wire:model.defer="policies.public_connections.candidate_max_duration_seconds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Fehlender Dialog: max. Versuche</label>
                <input type="number" min="1" max="10" wire:model.defer="policies.public_connections.dialog_missing_max_attempts" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <label class="flex items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                <input type="checkbox" wire:model="policies.public_connections.rate_limit_account_switch_enabled" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm font-medium text-gray-800">Bei Rate-Limit Scraper-Account wechseln</span>
            </label>
            <div>
                <label class="block text-sm font-medium text-gray-700">Max. Scraper-Profilwechsel je Kandidat</label>
                <input type="number" min="0" max="10" wire:model.defer="policies.public_connections.max_scraper_profile_switches" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <p class="mt-1 text-xs text-gray-500">0 deaktiviert Wechsel auch dann, wenn der Schalter aktiv ist.</p>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap justify-end gap-3">
        <button type="button" wire:click="resetToDefaults" wire:confirm="Scan-Konfiguration wirklich auf Standardwerte zuruecksetzen?" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
            Standardwerte
        </button>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
            Scan-Einstellungen speichern
        </button>
    </div>
</form>
