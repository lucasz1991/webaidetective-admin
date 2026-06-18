<?php

namespace App\Livewire\Admin\Config;

use App\Models\Setting;
use Livewire\Component;

class ScanSettings extends Component
{
    public array $policies = [];

    public array $scanLabels = [
        'mini' => 'Mini-Profilscan',
        'profile' => 'Vollstaendiger Profilscan',
        'lists' => 'Follower-/Gefolgt-Listen',
        'posts' => 'Beitragsscan',
        'suggestions' => 'Vorschlaege-Scan',
        'suggestion_deep_search' => 'Vorschlaege DeepSearch',
        'public_connections' => 'Public-Profile-Verbindungen',
    ];

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function saveSettings(): void
    {
        $validated = $this->validate($this->rules());
        $defaults = config('scan-policies.defaults', []);

        $this->policies = $this->mergeWithDefaults($defaults, $validated['policies']);
        Setting::setValue('scan', 'policies', $this->policies);

        $this->dispatch('showAlert', 'Scan-Konfiguration wurde gespeichert.', 'success');
    }

    public function resetToDefaults(): void
    {
        $this->policies = config('scan-policies.defaults', []);
        Setting::setValue('scan', 'policies', $this->policies);

        $this->resetErrorBag();
        $this->dispatch('showAlert', 'Scan-Konfiguration wurde auf Standardwerte zurueckgesetzt.', 'success');
    }

    public function render()
    {
        return view('livewire.admin.config.scan-settings');
    }

    private function loadSettings(): void
    {
        $defaults = config('scan-policies.defaults', []);
        $stored = Setting::getValue('scan', 'policies');

        $this->policies = $this->mergeWithDefaults(
            $defaults,
            is_array($stored) ? $stored : [],
        );
    }

    private function rules(): array
    {
        $rules = [
            'policies' => ['required', 'array'],
            'policies.global.process_stall_timeout_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'policies.global.node_watchdog_timeout_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'policies.global.script_watchdog_enabled' => ['required', 'boolean'],
            'policies.global.browser_disconnect_abort' => ['required', 'boolean'],
            'policies.global.navigation_timeout_seconds' => ['required', 'integer', 'min:30', 'max:3600'],
            'policies.global.post_login_wait_ms' => ['required', 'integer', 'min:500', 'max:60000'],
            'policies.global.typing_delay_ms' => ['required', 'integer', 'min:0', 'max:1000'],
            'policies.global.profile_switch_extra_attempts' => ['required', 'integer', 'min:0', 'max:10'],
            'policies.global.live_preview_enabled' => ['required', 'boolean'],
            'policies.global.skip_debug_artifacts' => ['required', 'boolean'],
            'policies.global.block_heavy_resources' => ['required', 'boolean'],
        ];

        foreach (array_keys($this->scanLabels) as $scanType) {
            $rules["policies.$scanType.error_attempts"] = ['required', 'integer', 'min:1', 'max:10'];
            $rules["policies.$scanType.retry_delay_seconds"] = ['required', 'integer', 'min:0', 'max:300'];
        }

        return [
            ...$rules,
            'policies.mini.session_fallback_enabled' => ['required', 'boolean'],
            'policies.profile.visible_count_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'policies.lists.max_items' => ['required', 'integer', 'min:0', 'max:1000000'],
            'policies.lists.max_scroll_rounds' => ['required', 'integer', 'min:20', 'max:1000000'],
            'policies.lists.partition_large_lists' => ['required', 'boolean'],
            'policies.lists.partition_threshold' => ['required', 'integer', 'min:1', 'max:1000000'],
            'policies.lists.search_queries_per_dialog' => ['required', 'integer', 'min:1', 'max:100'],
            'policies.lists.search_partition_max_items' => ['required', 'integer', 'min:25', 'max:1000000'],
            'policies.lists.progress_checkpoint_size' => ['required', 'integer', 'min:25', 'max:1000000'],
            'policies.lists.search_target_max_items' => ['required', 'integer', 'min:0', 'max:1000000'],
            'policies.lists.search_target_max_scroll_rounds' => ['required', 'integer', 'min:1', 'max:1000000'],
            'policies.lists.search_input_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'policies.lists.search_wait_ms' => ['required', 'integer', 'min:250', 'max:60000'],
            'policies.posts.max_items' => ['required', 'integer', 'min:1', 'max:10000'],
            'policies.posts.max_scroll_rounds' => ['required', 'integer', 'min:1', 'max:10000'],
            'policies.posts.max_likes_per_post' => ['required', 'integer', 'min:1', 'max:100000'],
            'policies.posts.max_comments_per_post' => ['required', 'integer', 'min:1', 'max:100000'],
            'policies.posts.open_likes_dialog_enabled' => ['required', 'boolean'],
            'policies.posts.like_dialog_max_scroll_rounds' => ['required', 'integer', 'min:1', 'max:1000'],
            'policies.posts.comment_dialog_max_scroll_rounds' => ['required', 'integer', 'min:1', 'max:1000'],
            'policies.suggestions.max_items' => ['required', 'integer', 'min:1', 'max:10000'],
            'policies.suggestions.inline_max_rounds' => ['required', 'integer', 'min:1', 'max:10000'],
            'policies.suggestions.dialog_max_rounds' => ['required', 'integer', 'min:1', 'max:10000'],
            'policies.suggestion_deep_search.candidate_max_items' => ['required', 'integer', 'min:1', 'max:10000'],
            'policies.suggestion_deep_search.candidate_error_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'policies.suggestion_deep_search.candidate_retry_delay_seconds' => ['required', 'integer', 'min:0', 'max:300'],
            'policies.suggestion_deep_search.candidate_inline_max_rounds' => ['required', 'integer', 'min:1', 'max:10000'],
            'policies.suggestion_deep_search.candidate_dialog_max_rounds' => ['required', 'integer', 'min:1', 'max:10000'],
            'policies.suggestion_deep_search.public_list_max_scroll_rounds' => ['required', 'integer', 'min:1', 'max:1000000'],
            'policies.suggestion_deep_search.skip_previously_checked' => ['required', 'boolean'],
            'policies.suggestion_deep_search.no_match_skip_after' => ['required', 'integer', 'min:1', 'max:100'],
            'policies.suggestion_deep_search.max_scraper_profile_switches' => ['required', 'integer', 'min:0', 'max:10'],
            'policies.suggestion_deep_search.profile_hover_cards_enabled' => ['required', 'boolean'],
            'policies.suggestion_deep_search.profile_hover_card_wait_ms' => ['required', 'integer', 'min:250', 'max:60000'],
            'policies.public_connections.resume_previous' => ['required', 'boolean'],
            'policies.public_connections.skip_completed_candidates' => ['required', 'boolean'],
            'policies.public_connections.candidate_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'policies.public_connections.candidate_retry_delay_seconds' => ['required', 'integer', 'min:2', 'max:300'],
            'policies.public_connections.candidate_retry_max_delay_seconds' => ['required', 'integer', 'min:2', 'max:3600'],
            'policies.public_connections.candidate_max_duration_seconds' => ['required', 'integer', 'min:60', 'max:86400'],
            'policies.public_connections.dialog_missing_max_attempts' => ['required', 'integer', 'min:1', 'max:10'],
            'policies.public_connections.rate_limit_account_switch_enabled' => ['required', 'boolean'],
            'policies.public_connections.max_scraper_profile_switches' => ['required', 'integer', 'min:0', 'max:10'],
        ];
    }

    private function mergeWithDefaults(array $defaults, array $stored): array
    {
        $merged = $defaults;

        foreach ($defaults as $group => $groupDefaults) {
            if (! is_array($groupDefaults)) {
                continue;
            }

            $storedGroup = is_array($stored[$group] ?? null) ? $stored[$group] : [];
            $merged[$group] = [
                ...$groupDefaults,
                ...array_intersect_key($storedGroup, $groupDefaults),
            ];
        }

        return $merged;
    }
}
