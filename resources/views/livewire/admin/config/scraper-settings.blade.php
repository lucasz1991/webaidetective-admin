<div class="space-y-6">
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
        <p class="font-semibold">Instagram-Scraper-Profil</p>
        <p class="mt-1">
            Diese Einstellungen werden vom Tracking-System fuer den Instagram-Scraper verwendet. Das Browser-Profil bleibt bei aktivierter Persistenz erhalten, damit nach einem erfolgreichen Login spaetere Analysen dieselbe Session wiederverwenden koennen.
        </p>
        <p class="mt-2">
            Normale Personen-Analysen laufen immer unsichtbar im Hintergrund. Ein sichtbares Browserfenster wird nur noch ueber den expliziten Session-Aufbau geoeffnet.
        </p>
    </div>

    @if (session()->has('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-800">Profil</h3>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="profile-label" class="block text-sm font-medium text-gray-700">Profilname</label>
                    <input id="profile-label" type="text" wire:model.defer="profileLabel" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('profileLabel')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3">
                    <input id="persistent-profile-enabled" type="checkbox" wire:model.defer="persistentProfileEnabled" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="persistent-profile-enabled" class="text-sm font-medium text-gray-700">Persistentes Browser-Profil verwenden</label>
                </div>

                <div>
                    <label for="browser-profile-path" class="block text-sm font-medium text-gray-700">Profilpfad</label>
                    <input id="browser-profile-path" type="text" wire:model.defer="browserProfilePath" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-500">Relativer Pfad innerhalb von `storage/app` oder ein absoluter Pfad.</p>
                    @error('browserProfilePath')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="cookie-file-path" class="block text-sm font-medium text-gray-700">Cookie-Datei</label>
                    <input id="cookie-file-path" type="text" wire:model.defer="cookieFilePath" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-500">Wird nach erfolgreichem Login automatisch aktualisiert.</p>
                    @error('cookieFilePath')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-800">Auto-Login</h3>

            <div class="mt-4 space-y-4">
                <div class="flex items-center gap-3">
                    <input id="auto-login-enabled" type="checkbox" wire:model.defer="autoLoginEnabled" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="auto-login-enabled" class="text-sm font-medium text-gray-700">Automatischen Instagram-Login erlauben</label>
                </div>

                <div>
                    <label for="login-username" class="block text-sm font-medium text-gray-700">Instagram-Benutzername</label>
                    <input id="login-username" type="text" wire:model.defer="loginUsername" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('loginUsername')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="login-password" class="block text-sm font-medium text-gray-700">Instagram-Passwort</label>
                    <input id="login-password" type="password" wire:model.defer="loginPassword" autocomplete="new-password" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <div class="mt-2 flex items-center justify-between gap-3 text-xs text-gray-500">
                        <span>
                            @if($hasStoredPassword)
                                Es ist bereits ein Passwort gespeichert. Leeres Feld bedeutet: vorhandenes Passwort beibehalten.
                            @else
                                Aktuell ist noch kein Passwort gespeichert.
                            @endif
                        </span>
                        @if($hasStoredPassword)
                            <button type="button" wire:click="clearStoredPassword" class="font-semibold text-red-600 hover:text-red-700">
                                Gespeichertes Passwort loeschen
                            </button>
                        @endif
                    </div>
                    @error('loginPassword')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="navigation-timeout" class="block text-sm font-medium text-gray-700">Navigation-Timeout in Sekunden</label>
                    <input id="navigation-timeout" type="number" min="30" max="300" wire:model.defer="navigationTimeoutSeconds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('navigationTimeoutSeconds')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="post-login-wait" class="block text-sm font-medium text-gray-700">Wartezeit nach Login in Millisekunden</label>
                    <input id="post-login-wait" type="number" min="500" max="15000" wire:model.defer="postLoginWaitMs" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('postLoginWaitMs')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="typing-delay" class="block text-sm font-medium text-gray-700">Tippverzoegerung in Millisekunden</label>
                    <input id="typing-delay" type="number" min="0" max="500" wire:model.defer="typingDelayMs" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('typingDelayMs')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
            <h3 class="text-lg font-semibold text-gray-800">Follower- und Gefolgt-Listen</h3>
            <p class="mt-1 text-sm text-gray-500">
                Ein Limit von 0 bedeutet: alle von Instagram ladbaren Eintraege speichern. Die Scroll-Runden sind nur eine technische Sicherung gegen Endlosschleifen.
            </p>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label for="relationship-list-process-timeout" class="block text-sm font-medium text-gray-700">Listen-Timeout in Sekunden</label>
                    <input id="relationship-list-process-timeout" type="number" min="240" max="7200" wire:model.defer="relationshipListProcessTimeoutSeconds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('relationshipListProcessTimeoutSeconds')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="relationship-list-max-scroll-rounds" class="block text-sm font-medium text-gray-700">Maximale Scroll-Runden</label>
                    <input id="relationship-list-max-scroll-rounds" type="number" min="20" max="100000" wire:model.defer="relationshipListMaxScrollRounds" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('relationshipListMaxScrollRounds')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="follower-list-max-items" class="block text-sm font-medium text-gray-700">Follower-Limit</label>
                    <input id="follower-list-max-items" type="number" min="0" max="1000000" wire:model.defer="followerListMaxItems" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('followerListMaxItems')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="following-list-max-items" class="block text-sm font-medium text-gray-700">Gefolgt-Limit</label>
                    <input id="following-list-max-items" type="number" min="0" max="1000000" wire:model.defer="followingListMaxItems" class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('followingListMaxItems')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap justify-end gap-3">
        <button type="button" wire:click="buildInstagramSession" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50">
            Instagram-Session aufbauen
        </button>
        <button type="button" wire:click="saveSettings" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
            Scraper-Profil speichern
        </button>
    </div>

    @if($sessionBuildResult)
        @php
            $sessionResultClass = ($sessionBuildResult['ok'] ?? false)
                ? 'border-emerald-200 bg-emerald-50 text-emerald-900'
                : 'border-amber-200 bg-amber-50 text-amber-950';
        @endphp

        <div class="rounded-lg border p-4 text-sm {{ $sessionResultClass }}">
            <p class="font-semibold">{{ $sessionBuildResult['statusMessage'] ?? 'Session-Aufbau abgeschlossen.' }}</p>

            @if(!empty($sessionBuildResult['debugLogPath']))
                <p class="mt-2 break-all text-xs">
                    <span class="font-semibold">Debug-Log:</span>
                    {{ $sessionBuildResult['debugLogPath'] }}
                </p>
            @endif

            @if(!empty($sessionBuildResult['cookieDiagnostics']) || !empty($sessionBuildResult['loginDiagnostics']))
                <div class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                    <div class="rounded-md border border-current/20 bg-white/40 p-3">
                        <p class="font-semibold">Cookie-Diagnose</p>
                        <p class="mt-1">sessionid in Datei: {{ data_get($sessionBuildResult, 'cookieDiagnostics.sessionCookieProvided') ? 'Ja' : 'Nein' }}</p>
                        <p>sessionid akzeptiert: {{ data_get($sessionBuildResult, 'cookieDiagnostics.sessionCookieAccepted') ? 'Ja' : 'Nein' }}</p>
                        <p>sessionid nach Reload noch da: {{ data_get($sessionBuildResult, 'cookieDiagnostics.sessionCookieRetained') ? 'Ja' : 'Nein' }}</p>
                    </div>
                    <div class="rounded-md border border-current/20 bg-white/40 p-3">
                        <p class="font-semibold">Login-Diagnose</p>
                        <p class="mt-1">Auto-Login versucht: {{ data_get($sessionBuildResult, 'loginDiagnostics.attempted') ? 'Ja' : 'Nein' }}</p>
                        <p>Formular gefunden: {{ data_get($sessionBuildResult, 'loginDiagnostics.formDetected') ? 'Ja' : 'Nein' }}</p>
                        <p>Login erfolgreich: {{ data_get($sessionBuildResult, 'loginDiagnostics.success') ? 'Ja' : 'Nein' }}</p>
                        <p>sessionid nach Login: {{ data_get($sessionBuildResult, 'loginDiagnostics.sessionCookiePresent') ? 'Ja' : 'Nein' }}</p>
                    </div>
                </div>
            @endif

            @if(!empty($sessionBuildResult['notes']))
                <ul class="mt-3 list-disc space-y-1 pl-5">
                    @foreach($sessionBuildResult['notes'] as $note)
                        <li>{{ $note }}</li>
                    @endforeach
                </ul>
            @endif

            @if(!empty($sessionBuildResult['warnings']))
                <div class="mt-3 rounded-md border border-current/20 bg-white/50 p-3">
                    <p class="font-semibold">Hinweise</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($sessionBuildResult['warnings'] as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>
