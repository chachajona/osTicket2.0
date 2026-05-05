<?php

declare(strict_types=1);

namespace App\Services\Panels;

use App\Models\Scp\StaffPreference;
use App\Models\Staff;

final class PanelLandingResolver
{
    public function resolve(Staff $staff, string $panel): string
    {
        $resolvedPanel = $this->normalizePanel($panel, $staff);
        $tabs = $this->tabsFor($resolvedPanel);

        if ($tabs === []) {
            return '/scp';
        }

        $preferenceKey = sprintf('default_%s_tab', $resolvedPanel);
        $preferences = StaffPreference::forStaff($this->staffId($staff));

        // Try candidates in order: user preference, config default, first tab
        $candidates = [
            $preferences->{$preferenceKey},
            config(sprintf('panel_landing.%s.default', $resolvedPanel)),
            array_key_first($tabs),
        ];

        foreach ($candidates as $tabId) {
            if (! is_string($tabId) || $tabId === '') {
                continue;
            }

            $url = config(sprintf('panel_landing.%s.tabs.%s', $resolvedPanel, $tabId));

            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return (string) reset($tabs);
    }

    public function defaultPanelFor(Staff $staff): string
    {
        $preferences = StaffPreference::forStaff($this->staffId($staff));
        $lastActivePanel = $preferences->last_active_panel;

        if ($lastActivePanel === 'admin' && $staff->canAccessAdminPanel()) {
            return 'admin';
        }

        if ($lastActivePanel === 'scp') {
            return 'scp';
        }

        return 'scp';
    }

    private function normalizePanel(string $panel, Staff $staff): string
    {
        if (! in_array($panel, ['scp', 'admin'], true)) {
            return 'scp';
        }

        if ($panel !== 'admin' || $staff->canAccessAdminPanel()) {
            return $panel;
        }

        $preferences = StaffPreference::forStaff($this->staffId($staff));
        $preferences->forceFill(['last_active_panel' => 'scp'])->save();

        return 'scp';
    }

    /**
     * @return array<string, string>
     */
    private function tabsFor(string $panel): array
    {
        $tabs = config(sprintf('panel_landing.%s.tabs', $panel), []);

        return is_array($tabs) ? $tabs : [];
    }

    private function staffId(Staff $staff): int
    {
        return (int) $staff->getAuthIdentifier();
    }
}
