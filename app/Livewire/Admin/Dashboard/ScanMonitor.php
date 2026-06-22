<?php

namespace App\Livewire\Admin\Dashboard;

use App\Support\PublicAssetUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Symfony\Component\Process\Process;

class ScanMonitor extends Component
{
    private const SOURCE_LIMIT = 30;

    private const RUNNING_WINDOW_HOURS = 6;

    private const SCRAPER_PROCESS_IDLE_MINUTES = 15;

    private const SCRAPER_PROCESS_LOW_CPU_PERCENT = 0.5;

    private ?string $assetBaseUrl = null;

    private array $runtimeBrowserEngineCache = [];

    public string $filter = 'all';

    public int $displayLimit = 4;

    public bool $showLoadMore = false;

    public bool $loadAllSources = false;

    public ?string $processNotice = null;

    public bool $processAccordionOpen = false;

    public array $expandedProcessGroups = [];

    public array $expandedRuntimeDetails = [];

    public function mount(int $displayLimit = 4, bool $showLoadMore = false, bool $loadAllSources = false): void
    {
        $this->displayLimit = max(1, min(100, $displayLimit));
        $this->showLoadMore = $showLoadMore;
        $this->loadAllSources = $loadAllSources;
    }

    public function loadMore(): void
    {
        if (! $this->showLoadMore) {
            return;
        }

        $this->displayLimit += 100;
    }

    public function toggleProcessAccordion(): void
    {
        $this->processAccordionOpen = ! $this->processAccordionOpen;
    }

    public function toggleProcessGroup(int $pid): void
    {
        if ($pid <= 0) {
            return;
        }

        if ((bool) ($this->expandedProcessGroups[$pid] ?? false)) {
            unset($this->expandedProcessGroups[$pid]);

            return;
        }

        $this->expandedProcessGroups[$pid] = true;
    }

    public function toggleScanRuntimeDetails(string $scanKey): void
    {
        $scanKey = trim($scanKey);

        if ($scanKey === '') {
            return;
        }

        if ((bool) ($this->expandedRuntimeDetails[$scanKey] ?? false)) {
            unset($this->expandedRuntimeDetails[$scanKey]);

            return;
        }

        $this->expandedRuntimeDetails[$scanKey] = true;
    }

    public function render()
    {
        $tablesAvailable = Schema::hasTable('tracked_people')
            && collect($this->scanTableNames())->contains(
                fn (string $table): bool => Schema::hasTable($table),
            );
        $scraperProcesses = $this->loadScraperProcesses();
        $loadedScans = $tablesAvailable
            ? $this->attachRuntimeDetailsToScans($this->loadScans(), $scraperProcesses)
            : collect();
        $scraperProcessTrees = $this->buildScraperProcessTrees($scraperProcesses);

        return view('livewire.admin.dashboard.scan-monitor', [
            'scans' => $loadedScans->take($this->displayLimit)->values(),
            'hasMore' => $this->showLoadMore && $loadedScans->count() > $this->displayLimit,
            'scraperProcesses' => $scraperProcesses,
            'scraperProcessTrees' => $scraperProcessTrees,
            'tablesAvailable' => $tablesAvailable,
            'processAccordionOpen' => $this->processAccordionOpen,
        ]);
    }

    public function terminateScraperProcess(int $pid, bool $force = false): void
    {
        $pid = (int) $pid;
        $this->processAccordionOpen = true;

        if ($pid <= 1) {
            $this->processNotice = 'Ungueltige Prozess-ID.';

            return;
        }

        $command = $this->commandForProcess($pid);

        if (! $command || ! $this->isInstagramScraperProcessCommand($command)) {
            $this->processNotice = 'Der Prozess wurde nicht beendet, weil er kein erkannter Instagram-Scraper-Prozess ist.';

            return;
        }

        $signal = $force ? 'KILL' : 'TERM';
        $process = new Process(['kill', '-'.$signal, (string) $pid]);
        $process->setTimeout(5);

        try {
            $process->run();
        } catch (\Throwable $error) {
            Log::warning('Instagram scraper process termination failed.', [
                'pid' => $pid,
                'signal' => $signal,
                'error' => $error->getMessage(),
            ]);

            $this->processNotice = 'Prozess konnte nicht beendet werden: '.$error->getMessage();

            return;
        }

        if (! $process->isSuccessful()) {
            $this->processNotice = 'Prozess konnte nicht beendet werden: '.trim($process->getErrorOutput() ?: $process->getOutput());

            return;
        }

        Log::warning('Instagram scraper process terminated from admin scan monitor.', [
            'pid' => $pid,
            'signal' => $signal,
            'command' => $command,
        ]);

        $this->processNotice = 'Scraper-Prozess '.$pid.' wurde mit SIG'.$signal.' beendet.';
    }

    private function loadScans(): Collection
    {
        $scans = collect()
            ->concat($this->loadRunningTrackedPersonScans())
            ->concat($this->loadSnapshotScans())
            ->concat($this->loadProfileListScans())
            ->concat($this->loadPostScans())
            ->concat($this->loadSuggestionScans())
            ->concat($this->loadPublicConnectionScans())
            ->unique('scan_key')
            ->filter(fn (object $scan): bool => match ($this->filter) {
                'running' => $scan->is_running,
                'completed' => ! $scan->is_running,
                default => true,
            })
            ->sortByDesc(fn (object $scan): int => $scan->scanned_at
                ? (strtotime((string) $scan->scanned_at) ?: 0)
                : 0);

        return $scans->values();
    }

    private function sourceLimit(): ?int
    {
        if ($this->loadAllSources) {
            return null;
        }

        return max(
            self::SOURCE_LIMIT,
            $this->displayLimit + ($this->showLoadMore ? 1 : 0),
        );
    }

    private function applySourceLimit(\Illuminate\Database\Query\Builder $query): \Illuminate\Database\Query\Builder
    {
        $sourceLimit = $this->sourceLimit();

        return $sourceLimit === null ? $query : $query->limit($sourceLimit);
    }

    private function loadScraperProcesses(): Collection
    {
        $processInventory = $this->loadProcessInventory();

        if ($processInventory->isEmpty()) {
            return collect();
        }

        $childrenByParent = $processInventory->groupBy('parent_pid');
        $scraperPids = $processInventory
            ->filter(fn (object $entry): bool => (bool) ($entry->is_scraper_command ?? false))
            ->pluck('pid')
            ->map(fn (mixed $pid): int => (int) $pid)
            ->filter(fn (int $pid): bool => $pid > 0)
            ->values();

        if ($scraperPids->isEmpty()) {
            return collect();
        }

        $familyPids = [];

        foreach ($scraperPids as $pid) {
            $seen = [];
            $familyPids[$pid] = true;

            foreach ($this->descendantPidsFromInventory($pid, $childrenByParent, $seen) as $descendantPid) {
                $familyPids[$descendantPid] = true;
            }
        }

        return $this->flattenScraperProcessTree(
            $processInventory
                ->filter(fn (object $entry): bool => isset($familyPids[(int) $entry->pid]))
                ->values(),
        );
    }

    private function loadProcessInventory(): Collection
    {
        $process = Process::fromShellCommandline('ps -axo pid=,ppid=,etime=,stat=,pcpu=,pmem=,command=');
        $process->setTimeout(5);

        try {
            $process->run();
        } catch (\Throwable $error) {
            Log::warning('Unable to inspect system processes for scraper monitor.', [
                'error' => $error->getMessage(),
            ]);

            return collect();
        }

        if (! $process->isSuccessful()) {
            Log::warning('System process inspection failed for scraper monitor.', [
                'error' => trim($process->getErrorOutput() ?: $process->getOutput()),
            ]);

            return collect();
        }

        return collect(preg_split('/\R/u', trim($process->getOutput())) ?: [])
            ->map(fn (string $line): ?object => $this->parseProcessLine($line))
            ->filter(fn (?object $entry): bool => $entry !== null)
            ->values();
    }

    private function descendantPidsFromInventory(int $pid, Collection $childrenByParent, array &$seen): array
    {
        if ($pid <= 0 || isset($seen[$pid])) {
            return [];
        }

        $seen[$pid] = true;
        $descendants = [];

        foreach ($childrenByParent->get($pid, collect()) as $child) {
            $childPid = (int) ($child->pid ?? 0);

            if ($childPid <= 0 || isset($seen[$childPid])) {
                continue;
            }

            $descendants[] = $childPid;
            array_push($descendants, ...$this->descendantPidsFromInventory($childPid, $childrenByParent, $seen));
        }

        return $descendants;
    }

    private function flattenScraperProcessTree(Collection $processes): Collection
    {
        if ($processes->isEmpty()) {
            return collect();
        }

        $entriesByPid = $processes->keyBy('pid');
        $childrenByParent = $processes->groupBy('parent_pid');
        $roots = $this->sortProcessSiblings(
            $processes->filter(fn (object $entry): bool => ! $entriesByPid->has((int) $entry->parent_pid)),
        );
        $flattened = collect();
        $visited = [];

        if ($roots->isEmpty()) {
            $roots = $this->sortProcessSiblings($processes);
        }

        $walk = function (object $entry, int $depth, array $ancestorPids, array $familyContext) use (
            &$walk,
            &$visited,
            $childrenByParent,
            $flattened,
        ): void {
            $pid = (int) $entry->pid;

            if ($pid <= 0 || isset($visited[$pid])) {
                return;
            }

            $visited[$pid] = true;
            $children = $this->sortProcessSiblings($childrenByParent->get($pid, collect()));
            $ownRelatedUsernames = collect($entry->related_usernames ?? [])
                ->filter()
                ->values();
            $effectiveRelatedUsernames = $ownRelatedUsernames
                ->merge($familyContext['related_usernames'] ?? [])
                ->unique()
                ->values()
                ->all();
            $effectiveScanTypes = collect([
                $entry->scan_type ?? null,
                $familyContext['scan_type'] ?? null,
            ])
                ->filter()
                ->unique()
                ->values()
                ->all();
            $row = clone $entry;

            $row->tree_depth = $depth;
            $row->ancestor_pids = $ancestorPids;
            $row->children_count = $children->count();
            $row->family_root_pid = (int) ($familyContext['root_pid'] ?? $pid);
            $row->family_script_name = $familyContext['script_name'] ?? $entry->script_name;
            $row->family_scan_type = $familyContext['scan_type'] ?? $entry->scan_type;
            $row->family_scan_type_label = $row->family_scan_type
                ? $this->scanTypeLabel((string) $row->family_scan_type)
                : null;
            $row->effective_related_usernames = $effectiveRelatedUsernames;
            $row->effective_scan_types = $effectiveScanTypes;
            $row->is_family_child = $depth > 0;

            $flattened->push($row);

            $childContext = [
                'root_pid' => $row->family_root_pid,
                'script_name' => $row->family_script_name ?: $entry->script_name,
                'scan_type' => $row->family_scan_type ?: $entry->scan_type,
                'related_usernames' => $effectiveRelatedUsernames,
            ];

            foreach ($children as $child) {
                $walk($child, $depth + 1, [...$ancestorPids, $pid], $childContext);
            }
        };

        foreach ($roots as $root) {
            $walk($root, 0, [], [
                'root_pid' => (int) $root->pid,
                'script_name' => $root->script_name,
                'scan_type' => $root->scan_type,
                'related_usernames' => $root->related_usernames ?? [],
            ]);
        }

        return $flattened;
    }

    private function sortProcessSiblings(Collection $processes): Collection
    {
        return $processes
            ->sort(function (object $left, object $right): int {
                if ((bool) $left->is_scraper_command !== (bool) $right->is_scraper_command) {
                    return $left->is_scraper_command ? -1 : 1;
                }

                return ((int) $right->elapsed_seconds <=> (int) $left->elapsed_seconds)
                    ?: ((int) $left->pid <=> (int) $right->pid);
            })
            ->values();
    }

    private function buildScraperProcessTrees(Collection $processes): Collection
    {
        if ($processes->isEmpty()) {
            return collect();
        }

        $entriesByPid = $processes
            ->mapWithKeys(function (object $process): array {
                $node = clone $process;
                $node->children = collect();
                $node->is_group_open = (bool) ($this->expandedProcessGroups[(int) $node->pid] ?? false);
                $node->has_children = false;

                return [(int) $node->pid => $node];
            });
        $roots = collect();

        foreach ($entriesByPid as $pid => $node) {
            $parentPid = (int) ($node->parent_pid ?? 0);

            if ($parentPid > 0 && $parentPid !== (int) $pid && $entriesByPid->has($parentPid)) {
                $entriesByPid->get($parentPid)->children->push($node);

                continue;
            }

            $roots->push($node);
        }

        return $this->sortProcessTreeNodes($roots);
    }

    private function sortProcessTreeNodes(Collection $nodes): Collection
    {
        return $this->sortProcessSiblings($nodes)
            ->map(function (object $node): object {
                $node->children = $this->sortProcessTreeNodes($node->children ?? collect());
                $node->children_count = $node->children->count();
                $node->has_children = $node->children_count > 0;

                return $node;
            })
            ->values();
    }

    private function parseProcessLine(string $line): ?object
    {
        if (! preg_match('/^\s*(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+([0-9.]+)\s+([0-9.]+)\s+(.+)$/u', $line, $matches)) {
            return null;
        }

        $elapsedSeconds = $this->parseProcessElapsedSeconds($matches[3]);
        $cpu = (float) $matches[5];
        $ageMinutes = (int) floor($elapsedSeconds / 60);
        $command = trim($matches[7]);
        $metadata = $this->parseScraperCommandMetadata($command);
        $isScraperCommand = (bool) ($metadata->is_scraper_command ?? false);
        $scanType = $metadata->scan_type ?? null;

        return (object) [
            'pid' => (int) $matches[1],
            'parent_pid' => (int) $matches[2],
            'elapsed' => $matches[3],
            'elapsed_seconds' => $elapsedSeconds,
            'age_minutes' => $ageMinutes,
            'state' => $matches[4],
            'cpu' => $cpu,
            'memory' => (float) $matches[6],
            'command' => $command,
            'short_command' => $this->shortenProcessCommand($command),
            'is_scraper_command' => $isScraperCommand,
            'script_name' => $metadata->script_name ?? null,
            'operation_mode' => $metadata->operation_mode ?? null,
            'scan_type' => $scanType,
            'scan_type_label' => $scanType ? $this->scanTypeLabel((string) $scanType) : null,
            'primary_username' => $metadata->primary_username ?? null,
            'public_username' => $metadata->public_username ?? null,
            'target_username' => $metadata->target_username ?? null,
            'related_usernames' => $metadata->related_usernames ?? [],
            'runtime_config_path' => $metadata->runtime_config_path ?? null,
            'browser_engine' => $this->browserEngineFromRuntimeConfigPath(
                $metadata->runtime_config_path ?? null,
            ),
            'is_idle_suspect' => $ageMinutes >= self::SCRAPER_PROCESS_IDLE_MINUTES
                && $cpu <= self::SCRAPER_PROCESS_LOW_CPU_PERCENT,
        ];
    }

    private function parseProcessElapsedSeconds(string $elapsed): int
    {
        $days = 0;
        $time = $elapsed;

        if (str_contains($elapsed, '-')) {
            [$dayPart, $time] = explode('-', $elapsed, 2);
            $days = max(0, (int) $dayPart);
        }

        $parts = array_map('intval', explode(':', $time));

        if (count($parts) === 2) {
            [$minutes, $seconds] = $parts;

            return ($days * 86400) + ($minutes * 60) + $seconds;
        }

        if (count($parts) === 3) {
            [$hours, $minutes, $seconds] = $parts;

            return ($days * 86400) + ($hours * 3600) + ($minutes * 60) + $seconds;
        }

        return $days * 86400;
    }

    private function parseScraperCommandMetadata(string $command): object
    {
        $tokens = $this->tokenizeProcessCommand($command);
        $scriptIndex = null;
        $scriptPath = null;
        $scriptName = null;

        foreach ($tokens as $index => $token) {
            $candidate = trim($token, "\"'");
            $basename = basename($candidate);

            if (preg_match('/^scrape-instagram(?:-[a-z0-9-]+)?\.cjs$/i', $basename)) {
                $scriptIndex = $index;
                $scriptPath = $candidate;
                $scriptName = $basename;

                break;
            }
        }

        if ($scriptIndex === null) {
            return (object) [
                'is_scraper_command' => $this->isInstagramScraperProcessCommand($command),
                'script_name' => null,
                'operation_mode' => null,
                'scan_type' => null,
                'primary_username' => null,
                'public_username' => null,
                'target_username' => null,
                'related_usernames' => [],
                'runtime_config_path' => null,
            ];
        }

        $args = array_values(array_slice($tokens, $scriptIndex + 1));
        $operationMode = null;
        $scanType = null;
        $primaryUsername = null;
        $publicUsername = null;
        $targetUsername = null;
        $runtimeConfigPath = null;

        if ($scriptName === 'scrape-instagram-public-profile-connections.cjs') {
            $publicUsername = $this->normalizeInstagramUsername($args[0] ?? null);
            $targetUsername = $this->normalizeInstagramUsername($args[1] ?? null);
            $runtimeConfigPath = $this->runtimeConfigPathFromArgument($args[2] ?? null);
            $operationMode = 'public-profile-connections';
            $scanType = 'public_connections';
            $primaryUsername = $targetUsername ?: $publicUsername;
        } else {
            $primaryUsername = $this->normalizeInstagramUsername($args[0] ?? null);
            $runtimeConfigPath = $this->runtimeConfigPathFromArgument($args[1] ?? null);
            $operationMode = $this->operationModeFromScriptName((string) $scriptName);

            if (isset($args[2]) && is_scalar($args[2]) && trim((string) $args[2]) !== '') {
                $operationMode = strtolower(trim((string) $args[2]));
            }

            $scanType = $this->scanTypeFromOperationMode($operationMode, (string) $scriptName);
        }

        $relatedUsernames = collect([$primaryUsername, $publicUsername, $targetUsername])
            ->filter()
            ->unique()
            ->values()
            ->all();

        return (object) [
            'is_scraper_command' => true,
            'script_path' => $scriptPath,
            'script_name' => $scriptName,
            'operation_mode' => $operationMode,
            'scan_type' => $scanType,
            'primary_username' => $primaryUsername,
            'public_username' => $publicUsername,
            'target_username' => $targetUsername,
            'related_usernames' => $relatedUsernames,
            'runtime_config_path' => $runtimeConfigPath,
        ];
    }

    private function tokenizeProcessCommand(string $command): array
    {
        return collect(str_getcsv($command, ' '))
            ->map(fn (mixed $token): string => trim((string) $token))
            ->filter(fn (string $token): bool => $token !== '')
            ->values()
            ->all();
    }

    private function runtimeConfigPathFromArgument(mixed $argument): ?string
    {
        if (! is_scalar($argument)) {
            return null;
        }

        $path = trim((string) $argument, "\"' \t\n\r\0\x0B");

        if ($path === '') {
            return null;
        }

        return str_ends_with($path, '.json') || str_contains($path, 'instagram-scraper-config-')
            ? $path
            : null;
    }

    private function operationModeFromScriptName(string $scriptName): ?string
    {
        return match ($scriptName) {
            'scrape-instagram-mini.cjs' => 'mini',
            'scrape-instagram-full.cjs',
            'scrape-instagram.cjs' => 'analyze',
            'scrape-instagram-posts.cjs' => 'posts',
            'scrape-instagram-stories.cjs' => 'stories',
            'scrape-instagram-suggestions-basic.cjs',
            'scrape-instagram-suggestions.cjs',
            'scrape-instagram-suggestions-router.cjs' => 'suggestions',
            'scrape-instagram-suggestions-deepsearch.cjs' => 'suggestion-connections',
            'scrape-instagram-public-profile-connections.cjs' => 'public-profile-connections',
            default => null,
        };
    }

    private function scanTypeFromOperationMode(?string $operationMode, string $scriptName): ?string
    {
        $mode = strtolower(trim((string) $operationMode));
        $mode = str_replace('_', '-', $mode);

        return match ($mode) {
            'mini' => 'mini',
            'followers' => 'followers',
            'following' => 'following',
            'posts', 'post-scan' => 'posts',
            'suggestions', 'suggestion-connections' => 'suggestions',
            'public-profile-connections' => 'public_connections',
            'stories' => 'stories',
            'analyze', 'profile', 'full' => 'full',
            default => str_contains($scriptName, 'list') ? null : 'analysis',
        };
    }

    private function normalizeInstagramUsername(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $username = strtolower(trim((string) $value));
        $username = preg_replace('/^https?:\/\/(www\.)?instagram\.com\//i', '', $username) ?? $username;
        $username = preg_replace('/[?#].*$/', '', $username) ?? $username;
        $username = trim(ltrim($username, '@'), "/ \t\n\r\0\x0B");

        if ($username === '' || ! preg_match('/^[a-z0-9._]+$/', $username)) {
            return null;
        }

        return mb_substr($username, 0, 64);
    }

    private function commandForProcess(int $pid): ?string
    {
        $process = new Process(['ps', '-p', (string) $pid, '-o', 'command=']);
        $process->setTimeout(5);

        try {
            $process->run();
        } catch (\Throwable) {
            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        $command = trim($process->getOutput());

        return $command !== '' ? $command : null;
    }

    private function isInstagramScraperProcessCommand(string $command): bool
    {
        $normalized = strtolower($command);

        return str_contains($normalized, 'node')
            && (
                str_contains($normalized, 'resources/node/scraper/')
                || (bool) preg_match('/(?:^|\s|\/)scrape-instagram(?:-[a-z0-9-]+)?\.cjs(?:\s|$)/i', $command)
            );
    }

    private function shortenProcessCommand(string $command): string
    {
        $command = preg_replace('#/Users/[^/\s]+/#', '~/', $command) ?: $command;
        $command = preg_replace('#\s+#', ' ', $command) ?: $command;

        return mb_strlen($command) > 180
            ? mb_substr($command, 0, 177).'...'
            : $command;
    }

    private function loadRunningTrackedPersonScans(): Collection
    {
        if (
            ! Schema::hasTable('tracked_people')
            || ! Schema::hasColumn('tracked_people', 'last_instagram_status_level')
        ) {
            return collect();
        }

        $query = DB::table('tracked_people')
            ->leftJoin('users', 'users.id', '=', 'tracked_people.user_id');

        if (Schema::hasTable('tracked_person_instagram_snapshots')) {
            $latestSnapshots = DB::table('tracked_person_instagram_snapshots')
                ->selectRaw('tracked_person_id, MAX(id) as snapshot_id')
                ->groupBy('tracked_person_id');

            $query
                ->leftJoinSub($latestSnapshots, 'latest_snapshots', function ($join): void {
                    $join->on('latest_snapshots.tracked_person_id', '=', 'tracked_people.id');
                })
                ->leftJoin(
                    'tracked_person_instagram_snapshots as snapshot',
                    'snapshot.id',
                    '=',
                    'latest_snapshots.snapshot_id',
                );
        }

        $profileIdColumn = Schema::hasColumn('tracked_people', 'current_instagram_profile_id')
            ? 'tracked_people.current_instagram_profile_id as instagram_profile_id'
            : DB::raw('NULL as instagram_profile_id');
        $snapshotColumns = Schema::hasTable('tracked_person_instagram_snapshots')
            ? [
                'snapshot.id as snapshot_id',
                'snapshot.screenshot_path',
                'snapshot.raw_payload',
            ]
            : [
                DB::raw('NULL as snapshot_id'),
                DB::raw('NULL as screenshot_path'),
                DB::raw('NULL as raw_payload'),
            ];

        $query
            ->where('tracked_people.last_instagram_status_level', 'partial')
            ->where('tracked_people.updated_at', '>=', now()->subHours(self::RUNNING_WINDOW_HOURS))
            ->orderByDesc('tracked_people.updated_at');

        return $this->applySourceLimit($query)
            ->get([
                'tracked_people.id as tracked_person_id',
                $profileIdColumn,
                'tracked_people.instagram_username',
                'tracked_people.first_name',
                'tracked_people.last_name',
                'tracked_people.alias',
                'tracked_people.instagram_profile_image_path',
                'tracked_people.profile_image_path',
                'tracked_people.last_instagram_status_message as status_message',
                'tracked_people.updated_at as scanned_at',
                'tracked_people.instagram_followers_count as followers_count',
                'tracked_people.instagram_following_count as following_count',
                'tracked_people.instagram_posts_count as posts_count',
                'users.id as user_id',
                'users.name as user_name',
                ...$snapshotColumns,
            ])
            ->filter(function (object $scan): bool {
                $payload = $this->decodePayload($scan->raw_payload ?? null);

                return $this->payloadRepresentsRunningScan($payload)
                    || $this->messageRepresentsRunningScan((string) ($scan->status_message ?? ''));
            })
            ->map(function (object $scan): object {
                $payload = $this->decodePayload($scan->raw_payload ?? null);
                $scanType = $this->resolveScanType($payload, (string) ($scan->status_message ?? ''));
                $name = $this->trackedPersonDisplayName($scan);
                $profileImagePath = $scan->instagram_profile_image_path
                    ?? $scan->profile_image_path
                    ?? null;

                return $this->makeScan([
                    'scan_key' => 'active-person-'.$scan->tracked_person_id,
                    'scan_type' => $scanType,
                    'source_scan_id' => $scan->snapshot_id,
                    'event_scan_type' => 'tracked_person_instagram_snapshot',
                    'snapshot_id' => $scan->snapshot_id,
                    'tracked_person_id' => $scan->tracked_person_id,
                    'instagram_profile_id' => $scan->instagram_profile_id,
                    'username' => $scan->instagram_username,
                    'display_name' => $name,
                    'user_id' => $scan->user_id,
                    'user_name' => $scan->user_name,
                    'status_level' => 'partial',
                    'status_message' => $scan->status_message,
                    'scanned_at' => $scan->scanned_at,
                    'profile_image_url' => $this->storageUrl($profileImagePath),
                    'screenshot_url' => $this->payloadRepresentsRunningScan($payload)
                        ? $this->storageUrl($scan->screenshot_path)
                            ?: $this->screenshotUrlFromPayload($payload)
                        : null,
                    'is_running' => true,
                    'payload' => $payload,
                    'metrics' => $this->profileMetrics(
                        $scan->posts_count,
                        $scan->followers_count,
                        $scan->following_count,
                    ),
                ]);
            });
    }

    private function loadSnapshotScans(): Collection
    {
        if (
            ! Schema::hasTable('tracked_person_instagram_snapshots')
            || ! Schema::hasTable('tracked_people')
        ) {
            return collect();
        }

        $profileIdColumn = Schema::hasColumn('tracked_person_instagram_snapshots', 'instagram_profile_id')
            ? 'snapshot.instagram_profile_id'
            : (Schema::hasColumn('tracked_people', 'current_instagram_profile_id')
                ? 'tracked_people.current_instagram_profile_id'
                : DB::raw('NULL as instagram_profile_id'));

        $query = DB::table('tracked_person_instagram_snapshots as snapshot')
            ->join('tracked_people', 'tracked_people.id', '=', 'snapshot.tracked_person_id')
            ->leftJoin('users', 'users.id', '=', 'tracked_people.user_id')
            ->orderByDesc('snapshot.analyzed_at');

        return $this->applySourceLimit($query)
            ->get([
                'snapshot.id as snapshot_id',
                'snapshot.tracked_person_id',
                $profileIdColumn,
                'snapshot.instagram_username',
                'snapshot.screenshot_path',
                'snapshot.profile_image_path',
                'snapshot.profile_image_url',
                'snapshot.status_level',
                'snapshot.status_message',
                'snapshot.analyzed_at as scanned_at',
                'snapshot.followers_count',
                'snapshot.following_count',
                'snapshot.posts_count',
                'snapshot.raw_payload',
                'tracked_people.first_name',
                'tracked_people.last_name',
                'tracked_people.alias',
                'users.id as user_id',
                'users.name as user_name',
            ])
            ->map(function (object $scan): ?object {
                $payload = $this->decodePayload($scan->raw_payload);

                if ($this->payloadRepresentsRunningScan($payload)) {
                    return null;
                }

                $scanType = $this->resolveScanType($payload, (string) $scan->status_message);

                if (in_array($scanType, ['followers', 'following'], true)) {
                    return null;
                }

                return $this->makeScan([
                    'scan_key' => 'snapshot-'.$scan->snapshot_id,
                    'scan_type' => $scanType,
                    'source_scan_id' => $scan->snapshot_id,
                    'event_scan_type' => 'tracked_person_instagram_snapshot',
                    'snapshot_id' => $scan->snapshot_id,
                    'tracked_person_id' => $scan->tracked_person_id,
                    'instagram_profile_id' => $scan->instagram_profile_id,
                    'username' => $scan->instagram_username,
                    'display_name' => $this->trackedPersonDisplayName($scan),
                    'user_id' => $scan->user_id,
                    'user_name' => $scan->user_name,
                    'status_level' => $scan->status_level,
                    'status_message' => $scan->status_message,
                    'scanned_at' => $scan->scanned_at,
                    'profile_image_url' => $this->storageOrRemoteUrl(
                        $scan->profile_image_path,
                        $scan->profile_image_url,
                    ),
                    'screenshot_url' => $this->storageUrl($scan->screenshot_path)
                        ?: $this->screenshotUrlFromPayload($payload),
                    'is_running' => false,
                    'payload' => $payload,
                    'metrics' => $this->profileMetrics(
                        $scan->posts_count,
                        $scan->followers_count,
                        $scan->following_count,
                    ),
                ]);
            })
            ->filter()
            ->values();
    }

    private function loadProfileListScans(): Collection
    {
        if (
            ! Schema::hasTable('instagram_profile_list_scans')
            || ! Schema::hasTable('instagram_profiles')
        ) {
            return collect();
        }

        $query = DB::table('instagram_profile_list_scans as scan')
            ->join('instagram_profiles as profile', 'profile.id', '=', 'scan.instagram_profile_id')
            ->leftJoin('tracked_people', 'tracked_people.id', '=', 'scan.tracked_person_id')
            ->leftJoin('users', 'users.id', '=', 'scan.user_id');

        if (Schema::hasColumn('instagram_profile_list_scans', 'deleted_at')) {
            $query->whereNull('scan.deleted_at');
        }

        $query->orderByDesc('scan.scanned_at');

        return $this->applySourceLimit($query)
            ->get([
                'scan.id as scan_id',
                'scan.snapshot_id',
                'scan.tracked_person_id',
                'scan.instagram_profile_id',
                'scan.list_type',
                'scan.scan_mode',
                'scan.status_level',
                'scan.status_message',
                'scan.expected_count',
                'scan.observed_count',
                'scan.active_count',
                'scan.added_count',
                'scan.removed_count',
                'scan.raw_payload',
                'scan.scanned_at',
                'profile.username as instagram_username',
                'profile.display_name as profile_display_name',
                'profile.full_name as profile_full_name',
                'profile.profile_image_path',
                'profile.profile_image_url',
                'tracked_people.first_name',
                'tracked_people.last_name',
                'tracked_people.alias',
                'users.id as user_id',
                'users.name as user_name',
            ])
            ->map(function (object $scan): object {
                $payload = $this->decodePayload($scan->raw_payload);
                $scanType = $scan->list_type === 'following' ? 'following' : 'followers';

                return $this->makeScan([
                    'scan_key' => 'profile-list-'.$scan->scan_id,
                    'scan_type' => $scanType,
                    'source_scan_id' => $scan->scan_id,
                    'event_scan_type' => 'instagram_profile_list_scan',
                    'snapshot_id' => $scan->snapshot_id,
                    'tracked_person_id' => $scan->tracked_person_id,
                    'instagram_profile_id' => $scan->instagram_profile_id,
                    'username' => $scan->instagram_username,
                    'display_name' => $this->instagramProfileDisplayName($scan),
                    'user_id' => $scan->user_id,
                    'user_name' => $scan->user_name,
                    'status_level' => $scan->status_level,
                    'status_message' => $scan->status_message,
                    'scanned_at' => $scan->scanned_at,
                    'profile_image_url' => $this->storageOrRemoteUrl(
                        $scan->profile_image_path,
                        $scan->profile_image_url,
                    ),
                    'screenshot_url' => $this->screenshotUrlFromPayload($payload),
                    'is_running' => false,
                    'payload' => $payload,
                    'context_label' => $scan->scan_mode
                        ? 'Modus: '.str_replace('_', ' ', (string) $scan->scan_mode)
                        : null,
                    'metrics' => [
                        $this->metric('Beobachtet', $scan->observed_count),
                        $this->metric('Aktiv', $scan->active_count),
                        $this->metric('Neu', $scan->added_count),
                    ],
                ]);
            });
    }

    private function loadPostScans(): Collection
    {
        if (
            ! Schema::hasTable('instagram_post_scans')
            || ! Schema::hasTable('instagram_profiles')
        ) {
            return collect();
        }

        $query = DB::table('instagram_post_scans as scan')
            ->join('instagram_profiles as profile', 'profile.id', '=', 'scan.instagram_profile_id')
            ->leftJoin('tracked_people', 'tracked_people.id', '=', 'scan.tracked_person_id')
            ->leftJoin('users', 'users.id', '=', 'scan.user_id')
            ->orderByDesc('scan.scanned_at');

        return $this->applySourceLimit($query)
            ->get([
                'scan.id as scan_id',
                'scan.snapshot_id',
                'scan.tracked_person_id',
                'scan.instagram_profile_id',
                'scan.status_level',
                'scan.status_message',
                'scan.observed_count',
                'scan.new_count',
                'scan.updated_count',
                'scan.raw_payload',
                'scan.scanned_at',
                'profile.username as instagram_username',
                'profile.display_name as profile_display_name',
                'profile.full_name as profile_full_name',
                'profile.profile_image_path',
                'profile.profile_image_url',
                'tracked_people.first_name',
                'tracked_people.last_name',
                'tracked_people.alias',
                'users.id as user_id',
                'users.name as user_name',
            ])
            ->map(function (object $scan): object {
                $payload = $this->decodePayload($scan->raw_payload);

                return $this->makeScan([
                    'scan_key' => 'posts-'.$scan->scan_id,
                    'scan_type' => 'posts',
                    'source_scan_id' => $scan->scan_id,
                    'event_scan_type' => 'instagram_post_scan',
                    'snapshot_id' => $scan->snapshot_id,
                    'tracked_person_id' => $scan->tracked_person_id,
                    'instagram_profile_id' => $scan->instagram_profile_id,
                    'username' => $scan->instagram_username,
                    'display_name' => $this->instagramProfileDisplayName($scan),
                    'user_id' => $scan->user_id,
                    'user_name' => $scan->user_name,
                    'status_level' => $scan->status_level,
                    'status_message' => $scan->status_message,
                    'scanned_at' => $scan->scanned_at,
                    'profile_image_url' => $this->storageOrRemoteUrl(
                        $scan->profile_image_path,
                        $scan->profile_image_url,
                    ),
                    'screenshot_url' => $this->screenshotUrlFromPayload($payload),
                    'is_running' => false,
                    'payload' => $payload,
                    'metrics' => [
                        $this->metric('Beobachtet', $scan->observed_count),
                        $this->metric('Neu', $scan->new_count),
                        $this->metric('Aktualisiert', $scan->updated_count),
                    ],
                ]);
            });
    }

    private function loadSuggestionScans(): Collection
    {
        if (
            ! Schema::hasTable('tracked_person_instagram_suggestion_scans')
            || ! Schema::hasTable('tracked_people')
        ) {
            return collect();
        }

        $profileIdColumn = Schema::hasColumn('tracked_people', 'current_instagram_profile_id')
            ? 'tracked_people.current_instagram_profile_id as instagram_profile_id'
            : DB::raw('NULL as instagram_profile_id');

        $query = DB::table('tracked_person_instagram_suggestion_scans as scan')
            ->join('tracked_people', 'tracked_people.id', '=', 'scan.tracked_person_id')
            ->leftJoin('users', 'users.id', '=', 'scan.user_id')
            ->orderByDesc('scan.analyzed_at');

        return $this->applySourceLimit($query)
            ->get([
                'scan.id as scan_id',
                'scan.tracked_person_id',
                $profileIdColumn,
                'scan.target_username as instagram_username',
                'scan.status_level',
                'scan.status_message',
                'scan.suggestions_observed_count',
                'scan.suggestions_checked_count',
                'scan.suggestion_matches_count',
                'scan.raw_payload',
                'scan.analyzed_at as scanned_at',
                'tracked_people.first_name',
                'tracked_people.last_name',
                'tracked_people.alias',
                'tracked_people.instagram_profile_image_path',
                'tracked_people.profile_image_path',
                'users.id as user_id',
                'users.name as user_name',
            ])
            ->map(function (object $scan): object {
                $payload = $this->decodePayload($scan->raw_payload);

                return $this->makeScan([
                    'scan_key' => 'suggestions-'.$scan->scan_id,
                    'scan_type' => 'suggestions',
                    'source_scan_id' => $scan->scan_id,
                    'event_scan_type' => 'tracked_person_instagram_suggestion_scan',
                    'snapshot_id' => null,
                    'tracked_person_id' => $scan->tracked_person_id,
                    'instagram_profile_id' => $scan->instagram_profile_id,
                    'username' => $scan->instagram_username,
                    'display_name' => $this->trackedPersonDisplayName($scan),
                    'user_id' => $scan->user_id,
                    'user_name' => $scan->user_name,
                    'status_level' => $scan->status_level,
                    'status_message' => $scan->status_message,
                    'scanned_at' => $scan->scanned_at,
                    'profile_image_url' => $this->storageUrl(
                        $scan->instagram_profile_image_path ?? $scan->profile_image_path,
                    ),
                    'screenshot_url' => $this->screenshotUrlFromPayload($payload),
                    'is_running' => false,
                    'payload' => $payload,
                    'metrics' => [
                        $this->metric('Gefunden', $scan->suggestions_observed_count),
                        $this->metric('Geprueft', $scan->suggestions_checked_count),
                        $this->metric('Treffer', $scan->suggestion_matches_count),
                    ],
                ]);
            });
    }

    private function loadPublicConnectionScans(): Collection
    {
        if (
            ! Schema::hasTable('tracked_person_instagram_public_profile_scans')
            || ! Schema::hasTable('tracked_people')
        ) {
            return collect();
        }

        $profileIdColumn = Schema::hasColumn('tracked_people', 'current_instagram_profile_id')
            ? 'tracked_people.current_instagram_profile_id as instagram_profile_id'
            : DB::raw('NULL as instagram_profile_id');

        $query = DB::table('tracked_person_instagram_public_profile_scans as scan')
            ->join('tracked_people', 'tracked_people.id', '=', 'scan.tracked_person_id')
            ->leftJoin('users', 'users.id', '=', 'scan.user_id')
            ->orderByDesc('scan.analyzed_at');

        return $this->applySourceLimit($query)
            ->get([
                'scan.id as scan_id',
                'scan.tracked_person_id',
                $profileIdColumn,
                'scan.target_username as instagram_username',
                'scan.public_username',
                'scan.relation_type',
                'scan.followers_observed_count',
                'scan.following_observed_count',
                'scan.status_level',
                'scan.status_message',
                'scan.raw_payload',
                'scan.analyzed_at as scanned_at',
                'tracked_people.first_name',
                'tracked_people.last_name',
                'tracked_people.alias',
                'tracked_people.instagram_profile_image_path',
                'tracked_people.profile_image_path',
                'users.id as user_id',
                'users.name as user_name',
            ])
            ->map(function (object $scan): object {
                $payload = $this->decodePayload($scan->raw_payload);
                $isRunning = $this->publicConnectionScanIsRunning($scan, $payload);
                $foundConnections = (int) data_get($payload, 'foundFollowers', 0)
                    + (int) data_get($payload, 'foundFollowing', 0);

                if ($foundConnections === 0 && ! in_array($scan->relation_type, ['none', 'unknown', 'candidate_search'], true)) {
                    $foundConnections = 1;
                }

                return $this->makeScan([
                    'scan_key' => 'public-connections-'.$scan->scan_id,
                    'scan_type' => 'public_connections',
                    'source_scan_id' => $scan->scan_id,
                    'event_scan_type' => 'tracked_person_instagram_public_profile_scan',
                    'snapshot_id' => null,
                    'tracked_person_id' => $scan->tracked_person_id,
                    'instagram_profile_id' => $scan->instagram_profile_id,
                    'username' => $scan->instagram_username,
                    'display_name' => $this->trackedPersonDisplayName($scan),
                    'user_id' => $scan->user_id,
                    'user_name' => $scan->user_name,
                    'status_level' => $scan->status_level,
                    'status_message' => $scan->status_message,
                    'scanned_at' => $scan->scanned_at,
                    'profile_image_url' => $this->storageUrl(
                        $scan->instagram_profile_image_path ?? $scan->profile_image_path,
                    ),
                    'screenshot_url' => $this->screenshotUrlFromPayload($payload),
                    'is_running' => $isRunning,
                    'payload' => $payload,
                    'context_label' => $scan->public_username
                        ? 'Pruefprofil @'.ltrim((string) $scan->public_username, '@')
                        : null,
                    'metrics' => [
                        $this->metric('Follower gepr.', $scan->followers_observed_count),
                        $this->metric('Gefolgt gepr.', $scan->following_observed_count),
                        $this->metric('Treffer', $foundConnections),
                    ],
                ]);
            });
    }

    private function attachRuntimeDetailsToScans(Collection $scans, Collection $scraperProcesses): Collection
    {
        if ($scans->isEmpty()) {
            return $scans;
        }

        $activeStates = $this->loadActiveScanStates($scans);
        $eventsByScanKey = $this->loadRecentScanEventsForScans($scans);

        return $scans
            ->map(function (object $scan) use ($activeStates, $eventsByScanKey, $scraperProcesses): object {
                $activeState = $scan->tracked_person_id
                    ? $activeStates->get((int) $scan->tracked_person_id)
                    : null;

                $scan->active_scan_state = $activeState;
                $scan->events = $eventsByScanKey->get($scan->scan_key, collect());
                $scan->processes = $this->matchingProcessesForScan($scan, $scraperProcesses, $activeState);
                $scan->process_tree = $this->buildScraperProcessTrees($scan->processes);
                $scan->runtime_details_open = (bool) ($this->expandedRuntimeDetails[$scan->scan_key] ?? false);
                $eventBrowserEngine = $scan->events
                    ->map(fn (object $event): ?string => $this->browserEngineFromPayload($event->payload ?? []))
                    ->filter()
                    ->first();
                $processBrowserEngine = $scan->processes
                    ->pluck('browser_engine')
                    ->filter()
                    ->first();
                $this->setScanBrowserEngine(
                    $scan,
                    $eventBrowserEngine
                        ?: $scan->browser_engine
                        ?: $processBrowserEngine,
                );

                return $scan;
            })
            ->values();
    }

    private function loadActiveScanStates(Collection $scans): Collection
    {
        return $scans
            ->pluck('tracked_person_id')
            ->filter()
            ->map(fn (mixed $trackedPersonId): int => (int) $trackedPersonId)
            ->filter(fn (int $trackedPersonId): bool => $trackedPersonId > 0)
            ->unique()
            ->mapWithKeys(function (int $trackedPersonId): array {
                try {
                    $active = Cache::get($this->activeScanCacheKey($trackedPersonId), []);
                } catch (\Throwable $error) {
                    Log::debug('Active Instagram scan cache lookup failed for admin monitor.', [
                        'tracked_person_id' => $trackedPersonId,
                        'error' => $error->getMessage(),
                    ]);

                    $active = [];
                }

                $normalized = is_array($active)
                    ? $this->normalizeActiveScanState($trackedPersonId, $active)
                    : null;

                return $normalized ? [$trackedPersonId => $normalized] : [];
            });
    }

    private function normalizeActiveScanState(int $trackedPersonId, array $active): ?object
    {
        $generation = (int) ($active['generation'] ?? 0);

        if ($generation <= 0) {
            return null;
        }

        $processes = collect($active['processes'] ?? [])
            ->filter(fn (mixed $process): bool => is_array($process))
            ->map(fn (array $process): object => (object) [
                'pid' => (int) ($process['pid'] ?? 0),
                'label' => trim((string) ($process['label'] ?? 'Instagram-Scan')),
                'registered_at' => $process['registeredAt'] ?? null,
            ])
            ->filter(fn (object $process): bool => $process->pid > 0)
            ->values();
        $lastOutputAt = $active['lastProcessOutputAt'] ?? null;
        $updatedAt = $active['updatedAt'] ?? null;
        $heartbeatAt = is_string($lastOutputAt) && $lastOutputAt !== '' ? $lastOutputAt : $updatedAt;
        $heartbeatTimestamp = is_string($heartbeatAt) ? strtotime($heartbeatAt) : false;

        return (object) [
            'tracked_person_id' => $trackedPersonId,
            'generation' => $generation,
            'label' => trim((string) ($active['label'] ?? 'Instagram-Scan')),
            'started_at' => $active['startedAt'] ?? null,
            'updated_at' => $updatedAt,
            'last_output_at' => $lastOutputAt,
            'graceful_stop_requested' => (bool) ($active['gracefulStopRequested'] ?? false),
            'graceful_stop_reason' => $active['gracefulStopReason'] ?? null,
            'processes' => $processes,
            'process_pids' => $processes->pluck('pid')->values()->all(),
            'process_count' => $processes->count(),
            'is_responsive' => $heartbeatTimestamp !== false
                && $heartbeatTimestamp >= now()->subSeconds(45)->timestamp,
        ];
    }

    private function activeScanCacheKey(int $trackedPersonId): string
    {
        return 'tracked-person-instagram-active-scan:'.$trackedPersonId;
    }

    private function loadRecentScanEventsForScans(Collection $scans): Collection
    {
        if (! Schema::hasTable('instagram_scan_events')) {
            return collect();
        }

        $eligibleScans = $scans
            ->filter(fn (object $scan): bool => is_string($scan->event_scan_type ?? null) && $scan->event_scan_type !== '')
            ->values();

        if ($eligibleScans->isEmpty()) {
            return collect();
        }

        $query = DB::table('instagram_scan_events')
            ->where('occurred_at', '>=', now('UTC')->subHours(12));
        $hasConditions = false;
        $scanIdsByType = $eligibleScans
            ->filter(fn (object $scan): bool => $scan->source_scan_id !== null)
            ->groupBy('event_scan_type');
        $runningFallbacksByType = $eligibleScans
            ->filter(fn (object $scan): bool => (bool) $scan->is_running)
            ->groupBy('event_scan_type');

        $query->where(function ($conditions) use ($scanIdsByType, $runningFallbacksByType, &$hasConditions): void {
            foreach ($scanIdsByType as $eventScanType => $items) {
                $scanIds = $items
                    ->pluck('source_scan_id')
                    ->filter()
                    ->map(fn (mixed $scanId): int => (int) $scanId)
                    ->unique()
                    ->values()
                    ->all();

                if ($scanIds === []) {
                    continue;
                }

                $hasConditions = true;
                $conditions->orWhere(function ($eventQuery) use ($eventScanType, $scanIds): void {
                    $eventQuery
                        ->where('scan_type', (string) $eventScanType)
                        ->whereIn('scan_id', $scanIds);
                });
            }

            foreach ($runningFallbacksByType as $eventScanType => $items) {
                $trackedPersonIds = $items
                    ->pluck('tracked_person_id')
                    ->filter()
                    ->map(fn (mixed $trackedPersonId): int => (int) $trackedPersonId)
                    ->filter(fn (int $trackedPersonId): bool => $trackedPersonId > 0)
                    ->unique()
                    ->values()
                    ->all();
                $usernames = $items
                    ->pluck('username')
                    ->map(fn (mixed $username): ?string => $this->normalizeInstagramUsername($username))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if ($trackedPersonIds === [] && $usernames === []) {
                    continue;
                }

                $hasConditions = true;
                $conditions->orWhere(function ($eventQuery) use ($eventScanType, $trackedPersonIds, $usernames): void {
                    $eventQuery->where('scan_type', (string) $eventScanType);
                    $eventQuery->where(function ($identityQuery) use ($trackedPersonIds, $usernames): void {
                        if ($trackedPersonIds !== []) {
                            $identityQuery->orWhereIn('tracked_person_id', $trackedPersonIds);
                        }

                        if ($usernames !== []) {
                            $identityQuery->orWhereIn('instagram_username', $usernames);
                        }
                    });
                });
            }

            if (! $hasConditions) {
                $conditions->whereRaw('1 = 0');
            }
        });

        $events = $query
            ->orderByDesc('occurred_at')
            ->limit(min(800, max(120, $eligibleScans->count() * 10)))
            ->get([
                'id',
                'scan_type',
                'scan_id',
                'instagram_username',
                'tracked_person_id',
                'user_id',
                'phase',
                'stage',
                'status_level',
                'percent',
                'message',
                'payload',
                'occurred_at',
            ])
            ->map(fn (object $event): object => $this->normalizeScanEvent($event));
        $eventsByScanKey = collect();

        foreach ($eligibleScans as $scan) {
            $eventsByScanKey->put(
                $scan->scan_key,
                $events
                    ->filter(fn (object $event): bool => $this->eventMatchesScan($event, $scan))
                    ->take(5)
                    ->values(),
            );
        }

        return $eventsByScanKey;
    }

    private function normalizeScanEvent(object $event): object
    {
        $payload = $this->decodePayload($event->payload ?? null);

        return (object) [
            'id' => (int) $event->id,
            'scan_type' => (string) $event->scan_type,
            'scan_id' => $this->nullableInteger($event->scan_id ?? null),
            'instagram_username' => $this->normalizeInstagramUsername($event->instagram_username ?? null),
            'tracked_person_id' => $this->nullableInteger($event->tracked_person_id ?? null),
            'user_id' => $this->nullableInteger($event->user_id ?? null),
            'phase' => $event->phase ?? null,
            'stage' => $event->stage ?? null,
            'status_level' => $this->normalizeStatusLevel((string) ($event->status_level ?? 'unknown')),
            'percent' => $this->nullableInteger($event->percent ?? null),
            'message' => $event->message ?? null,
            'payload' => $payload,
            'payload_summary' => $this->eventPayloadSummary($payload),
            'occurred_at' => $event->occurred_at ?? null,
        ];
    }

    private function eventMatchesScan(object $event, object $scan): bool
    {
        if ($event->scan_type !== ($scan->event_scan_type ?? null)) {
            return false;
        }

        if (
            $scan->source_scan_id !== null
            && $event->scan_id !== null
            && (int) $scan->source_scan_id === (int) $event->scan_id
        ) {
            return true;
        }

        if (! (bool) $scan->is_running) {
            return false;
        }

        if (
            $scan->tracked_person_id !== null
            && $event->tracked_person_id !== null
            && (int) $scan->tracked_person_id === (int) $event->tracked_person_id
        ) {
            return true;
        }

        $scanUsername = $this->normalizeInstagramUsername($scan->username ?? null);

        return $scanUsername !== null
            && $event->instagram_username !== null
            && $scanUsername === $event->instagram_username;
    }

    private function eventPayloadSummary(array $payload): ?string
    {
        $parts = [];
        $loaded = data_get($payload, 'loaded');
        $expected = data_get($payload, 'expected');

        if (is_numeric($loaded) && is_numeric($expected) && (int) $expected > 0) {
            $parts[] = number_format((int) $loaded, 0, ',', '.')
                .' / '
                .number_format((int) $expected, 0, ',', '.');
        }

        $candidate = $this->normalizeInstagramUsername(data_get($payload, 'candidateUsername'));

        if ($candidate) {
            $parts[] = '@'.$candidate;
        }

        $foundFollowers = data_get($payload, 'foundFollowers');
        $foundFollowing = data_get($payload, 'foundFollowing');

        if (is_numeric($foundFollowers) || is_numeric($foundFollowing)) {
            $parts[] = 'Treffer '
                .number_format((int) $foundFollowers, 0, ',', '.')
                .' / '
                .number_format((int) $foundFollowing, 0, ',', '.');
        }

        foreach (['scraperProfileLabel', 'scraperProfileLoginUsername'] as $profileField) {
            $profileValue = data_get($payload, $profileField);

            if (is_scalar($profileValue) && trim((string) $profileValue) !== '') {
                $parts[] = trim((string) $profileValue);

                break;
            }
        }

        return $parts !== [] ? implode(' | ', array_slice($parts, 0, 3)) : null;
    }

    private function matchingProcessesForScan(object $scan, Collection $processes, ?object $activeState): Collection
    {
        if ($processes->isEmpty()) {
            return collect();
        }

        $activePids = collect($activeState->process_pids ?? [])
            ->map(fn (mixed $pid): int => (int) $pid)
            ->filter(fn (int $pid): bool => $pid > 0)
            ->values();
        $matchedPids = collect();

        if ($activePids->isNotEmpty()) {
            $matchedPids = $matchedPids->merge(
                $processes
                    ->filter(fn (object $process): bool => $this->processBelongsToPids($process, $activePids))
                    ->pluck('pid'),
            );
        }

        if ((bool) $scan->is_running) {
            $matchedPids = $matchedPids->merge(
                $processes
                    ->filter(fn (object $process): bool => $this->processMatchesScan($process, $scan))
                    ->pluck('pid'),
            );
        }

        $matchedPids = $matchedPids
            ->map(fn (mixed $pid): int => (int) $pid)
            ->unique()
            ->values();

        if ($matchedPids->isEmpty()) {
            return collect();
        }

        return $processes
            ->filter(fn (object $process): bool => $matchedPids->contains((int) $process->pid))
            ->values();
    }

    private function processBelongsToPids(object $process, Collection $activePids): bool
    {
        $pid = (int) ($process->pid ?? 0);
        $familyRootPid = (int) ($process->family_root_pid ?? 0);
        $ancestorPids = collect($process->ancestor_pids ?? [])
            ->map(fn (mixed $ancestorPid): int => (int) $ancestorPid)
            ->filter(fn (int $ancestorPid): bool => $ancestorPid > 0);

        return $activePids->contains($pid)
            || ($familyRootPid > 0 && $activePids->contains($familyRootPid))
            || $ancestorPids->intersect($activePids)->isNotEmpty();
    }

    private function processMatchesScan(object $process, object $scan): bool
    {
        $scanUsername = $this->normalizeInstagramUsername($scan->username ?? null);

        if ($scanUsername === null) {
            return false;
        }

        $relatedUsernames = collect($process->effective_related_usernames ?? $process->related_usernames ?? [])
            ->map(fn (mixed $username): ?string => $this->normalizeInstagramUsername($username))
            ->filter()
            ->unique()
            ->values();

        if (! $relatedUsernames->contains($scanUsername)) {
            return false;
        }

        $processScanTypes = collect($process->effective_scan_types ?? [$process->scan_type ?? null])
            ->filter()
            ->unique()
            ->values();

        if ($processScanTypes->isEmpty()) {
            return true;
        }

        return $processScanTypes->contains(
            fn (string $processScanType): bool => $this->processScanTypeMatches($processScanType, (string) $scan->scan_type),
        );
    }

    private function processScanTypeMatches(string $processScanType, string $scanType): bool
    {
        if ($processScanType === $scanType) {
            return true;
        }

        return match ($scanType) {
            'public_connections' => $processScanType === 'public_connections',
            'suggestions' => $processScanType === 'suggestions',
            'posts' => $processScanType === 'posts',
            'followers' => $processScanType === 'followers',
            'following' => $processScanType === 'following',
            'mini' => $processScanType === 'mini',
            'full' => in_array($processScanType, ['full', 'analysis'], true),
            'analysis' => in_array($processScanType, ['analysis', 'full', 'mini'], true),
            default => false,
        };
    }

    private function makeScan(array $attributes): object
    {
        $scanType = (string) ($attributes['scan_type'] ?? 'analysis');

        $scan = (object) [
            'scan_key' => (string) $attributes['scan_key'],
            'scan_type' => $scanType,
            'scan_type_label' => $this->scanTypeLabel($scanType),
            'scan_type_classes' => $this->scanTypeClasses($scanType),
            'context_label' => $attributes['context_label'] ?? null,
            'source_scan_id' => $this->nullableInteger($attributes['source_scan_id'] ?? null),
            'event_scan_type' => $attributes['event_scan_type'] ?? null,
            'snapshot_id' => $this->nullableInteger($attributes['snapshot_id'] ?? null),
            'tracked_person_id' => $this->nullableInteger($attributes['tracked_person_id'] ?? null),
            'instagram_profile_id' => $this->nullableInteger($attributes['instagram_profile_id'] ?? null),
            'username' => ltrim(trim((string) ($attributes['username'] ?? '')), '@'),
            'display_name' => trim((string) ($attributes['display_name'] ?? '')) ?: 'Unbenanntes Profil',
            'user_id' => $this->nullableInteger($attributes['user_id'] ?? null),
            'user_name' => $attributes['user_name'] ?? null,
            'status_level' => $this->normalizeStatusLevel((string) ($attributes['status_level'] ?? 'unknown')),
            'status_message' => $attributes['status_message'] ?? null,
            'scanned_at' => $attributes['scanned_at'] ?? null,
            'profile_image_url' => $attributes['profile_image_url'] ?? null,
            'screenshot_url' => $attributes['screenshot_url'] ?? null,
            'is_running' => (bool) ($attributes['is_running'] ?? false),
            'browser_engine' => null,
            'browser_engine_label' => 'Nicht erfasst',
            'browser_engine_classes' => 'bg-slate-100 text-slate-600 ring-slate-200',
            'metrics' => collect($attributes['metrics'] ?? [])->take(3)->values(),
            'events' => collect(),
            'processes' => collect(),
            'process_tree' => collect(),
            'active_scan_state' => null,
            'runtime_details_open' => false,
        ];

        $this->setScanBrowserEngine(
            $scan,
            $attributes['browser_engine']
                ?? $this->browserEngineFromPayload($attributes['payload'] ?? []),
        );

        return $scan;
    }

    private function setScanBrowserEngine(object $scan, mixed $engine): void
    {
        $normalized = $this->normalizeBrowserEngine($engine);

        $scan->browser_engine = $normalized;
        $scan->browser_engine_label = match ($normalized) {
            'cloak' => 'Cloak',
            'chrome' => 'Chrome',
            'cloak-with-chrome-fallback' => 'Cloak bevorzugt',
            default => 'Nicht erfasst',
        };
        $scan->browser_engine_classes = match ($normalized) {
            'cloak' => 'bg-violet-100 text-violet-800 ring-violet-200',
            'chrome' => 'bg-sky-100 text-sky-800 ring-sky-200',
            'cloak-with-chrome-fallback' => 'bg-indigo-100 text-indigo-800 ring-indigo-200',
            default => 'bg-slate-100 text-slate-600 ring-slate-200',
        };
    }

    private function browserEngineFromPayload(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        foreach (['browserEngine', 'browser_engine', 'activeBrowserEngine'] as $key) {
            if (array_key_exists($key, $payload)) {
                $engine = $this->normalizeBrowserEngine($payload[$key]);

                if ($engine !== null) {
                    return $engine;
                }
            }
        }

        foreach ($payload as $value) {
            if (! is_array($value)) {
                continue;
            }

            $engine = $this->browserEngineFromPayload($value);

            if ($engine !== null) {
                return $engine;
            }
        }

        return null;
    }

    private function browserEngineFromRuntimeConfigPath(mixed $path): ?string
    {
        if (! is_scalar($path) || trim((string) $path) === '') {
            return null;
        }

        $path = trim((string) $path);

        if (array_key_exists($path, $this->runtimeBrowserEngineCache)) {
            return $this->runtimeBrowserEngineCache[$path];
        }

        if (! is_file($path) || ! is_readable($path)) {
            return $this->runtimeBrowserEngineCache[$path] = null;
        }

        try {
            $payload = json_decode((string) file_get_contents($path), true);
        } catch (\Throwable) {
            $payload = null;
        }

        return $this->runtimeBrowserEngineCache[$path] = $this->browserEngineFromPayload($payload);
    }

    private function normalizeBrowserEngine(mixed $engine): ?string
    {
        if (! is_scalar($engine)) {
            return null;
        }

        return match (strtolower(trim((string) $engine))) {
            'chrome', 'chromium', 'puppeteer' => 'chrome',
            'cloak', 'cloakbrowser' => 'cloak',
            'cloak-with-chrome-fallback', 'cloak_with_chrome_fallback', 'cloak-fallback' => 'cloak-with-chrome-fallback',
            default => null,
        };
    }

    private function resolveScanType(array $payload, string $message = ''): string
    {
        $mode = strtolower(trim((string) data_get($payload, 'analysisPolicy.scanMode', '')));

        if (in_array($mode, ['mini', 'full', 'followers', 'following'], true)) {
            return $mode;
        }

        $haystack = strtolower($message.' '.(string) data_get($payload, 'statusMessage', ''));

        return match (true) {
            str_contains($haystack, 'public-profile-verbindung'),
            str_contains($haystack, 'verbindungsscan') => 'public_connections',
            str_contains($haystack, 'vorschlag') => 'suggestions',
            str_contains($haystack, 'beitrag'),
            str_contains($haystack, 'post-scan'),
            str_contains($haystack, 'postscan') => 'posts',
            str_contains($haystack, 'gefolgt') => 'following',
            str_contains($haystack, 'follower') => 'followers',
            str_contains($haystack, 'voll') => 'full',
            str_contains($haystack, 'mini') => 'mini',
            default => 'analysis',
        };
    }

    private function scanTypeLabel(string $scanType): string
    {
        return match ($scanType) {
            'mini' => 'Mini-Scan',
            'full' => 'Vollanalyse',
            'followers' => 'Followerlisten-Scan',
            'following' => 'Gefolgt-Listen-Scan',
            'posts' => 'Beitragsscan',
            'suggestions' => 'Vorschlagsscan',
            'public_connections' => 'Verbindungsscan',
            default => 'Profilanalyse',
        };
    }

    private function scanTypeClasses(string $scanType): string
    {
        return match ($scanType) {
            'mini' => 'bg-sky-100 text-sky-800 ring-sky-200',
            'full' => 'bg-pink-100 text-pink-800 ring-pink-200',
            'followers' => 'bg-blue-100 text-blue-800 ring-blue-200',
            'following' => 'bg-cyan-100 text-cyan-800 ring-cyan-200',
            'posts' => 'bg-violet-100 text-violet-800 ring-violet-200',
            'suggestions' => 'bg-fuchsia-100 text-fuchsia-800 ring-fuchsia-200',
            'public_connections' => 'bg-amber-100 text-amber-800 ring-amber-200',
            default => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }

    private function profileMetrics(mixed $posts, mixed $followers, mixed $following): array
    {
        return [
            $this->metric('Posts', $posts),
            $this->metric('Follower', $followers),
            $this->metric('Folgt', $following),
        ];
    }

    private function metric(string $label, mixed $value): object
    {
        return (object) [
            'label' => $label,
            'value' => is_numeric($value) ? (int) $value : $value,
        ];
    }

    private function trackedPersonDisplayName(object $scan): string
    {
        $name = trim(collect([
            $scan->first_name ?? null,
            $scan->last_name ?? null,
        ])->filter()->implode(' '));

        return $name !== ''
            ? $name
            : (trim((string) ($scan->alias ?? '')) ?: 'Unbenannte Person');
    }

    private function instagramProfileDisplayName(object $scan): string
    {
        foreach (['profile_display_name', 'profile_full_name', 'instagram_username'] as $field) {
            $value = trim((string) ($scan->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return $this->trackedPersonDisplayName($scan);
    }

    private function payloadRepresentsRunningScan(array $payload): bool
    {
        if (! (bool) data_get($payload, 'analysisPolicy.progressSnapshot', false)) {
            return false;
        }

        $phase = strtolower((string) data_get($payload, 'analysisPolicy.lastProgressPhase', ''));
        $stage = strtolower((string) data_get($payload, 'analysisPolicy.lastProgressStage', ''));

        return ! in_array($phase, ['done', 'error'], true)
            && $stage !== 'scan-stop-requested'
            && data_get($payload, 'analysisPolicy.terminalStatus') === null;
    }

    private function messageRepresentsRunningScan(string $message): bool
    {
        $message = strtolower(trim($message));

        if ($message === '') {
            return false;
        }

        foreach (['fehlgeschlagen', 'abgebrochen', 'beendet', 'abgeschlossen'] as $terminalTerm) {
            if (str_contains($message, $terminalTerm)) {
                return false;
            }
        }

        return str_contains($message, 'laeuft')
            || str_contains($message, 'läuft')
            || str_contains($message, 'wird vorbereitet')
            || str_contains($message, 'fortgesetzt');
    }

    private function publicConnectionScanIsRunning(object $scan, array $payload): bool
    {
        $progressStatus = strtolower((string) data_get($payload, 'progressStatus', ''));

        return $scan->status_level === 'partial'
            && $progressStatus === 'in_progress'
            && ! (bool) data_get($payload, 'gracefullyStopped', false)
            && ! (bool) data_get($payload, 'stoppedForRateLimit', false)
            && strtotime((string) $scan->scanned_at) >= now()->subHours(self::RUNNING_WINDOW_HOURS)->timestamp;
    }

    private function screenshotUrlFromPayload(array $payload): ?string
    {
        $paths = [];

        foreach ([
            data_get($payload, 'liveScreenshotUrl'),
            data_get($payload, 'screenshotPath'),
            data_get($payload, 'screenshot_path'),
            data_get($payload, 'analysisPolicy.liveScreenshotUrl'),
            data_get($payload, 'postsScan.screenshotPath'),
            data_get($payload, 'suggestionsScan.screenshotPath'),
        ] as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                $paths[] = (string) $candidate;
            }
        }

        array_walk_recursive($payload, function (mixed $value, mixed $key) use (&$paths): void {
            if (
                in_array($key, ['liveScreenshotUrl', 'screenshotPath', 'screenshot_path'], true)
                && is_scalar($value)
                && trim((string) $value) !== ''
            ) {
                $paths[] = (string) $value;
            }
        });

        foreach (array_reverse(array_values(array_unique($paths))) as $path) {
            $url = $this->normalizeLiveScreenshotUrl($path);

            if ($url) {
                return $url;
            }
        }

        return null;
    }

    private function normalizeLiveScreenshotUrl(mixed $url): ?string
    {
        if (! is_scalar($url)) {
            return null;
        }

        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (is_string($path) && str_contains($path, '/storage/')) {
            $relativePath = ltrim(substr($path, strpos($path, '/storage/') + strlen('/storage/')), '/');
            $normalizedUrl = $this->storageUrl($relativePath);
            $query = parse_url($url, PHP_URL_QUERY);

            return $normalizedUrl && is_string($query) && $query !== ''
                ? $normalizedUrl.'?'.$query
                : $normalizedUrl;
        }

        return $this->storageUrl($url);
    }

    private function storageOrRemoteUrl(mixed $storagePath, mixed $remoteUrl = null): ?string
    {
        return $this->storageUrl($storagePath) ?: $this->publicUrl($remoteUrl);
    }

    private function storageUrl(mixed $path): ?string
    {
        if (! is_scalar($path)) {
            return null;
        }

        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if ($this->isAbsoluteUrl($path)) {
            return $path;
        }

        $prefix = str_starts_with($path, 'storage/') ? '' : 'storage/';

        return $this->assetBaseUrl().'/'.$prefix.ltrim($path, '/');
    }

    private function publicUrl(mixed $url): ?string
    {
        if (! is_scalar($url)) {
            return null;
        }

        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if ($this->isAbsoluteUrl($url)) {
            return $url;
        }

        return $this->assetBaseUrl().'/'.ltrim($url, '/');
    }

    private function assetBaseUrl(): string
    {
        return $this->assetBaseUrl ??= PublicAssetUrl::baseUrl();
    }

    private function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    private function normalizeStatusLevel(string $status): string
    {
        $status = strtolower(trim($status));

        return in_array($status, ['success', 'error', 'cancelled', 'partial'], true)
            ? $status
            : 'unknown';
    }

    private function nullableInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function scanTableNames(): array
    {
        return [
            'tracked_person_instagram_snapshots',
            'instagram_profile_list_scans',
            'instagram_post_scans',
            'tracked_person_instagram_suggestion_scans',
            'tracked_person_instagram_public_profile_scans',
        ];
    }
}
