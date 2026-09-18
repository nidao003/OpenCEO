<?php

namespace Leantime\Domain\OpenCEO;

use Illuminate\Console\Scheduling\Schedule;
use Leantime\Core\Events\EventDispatcher;

EventDispatcher::add_event_listener('leantime.core.console.consolekernel.schedule.cron', function ($params): void {
    $scheduler = $params['schedule'] ?? null;

    if (! $scheduler instanceof Schedule) {
        return;
    }

    $scheduler->call(function (): void {
        if (! \Illuminate\Support\Facades\Schema::hasTable('openceo_company_snapshots')) {
            return;
        }

        $periodKey = now()->format('o-\\WW');
        app(\Leantime\Domain\OpenCEO\Services\State::class)->snapshot('weekly', $periodKey);
    })->name('openceo:weekly-snapshot')->weeklyOn(1, '06:00');
});

EventDispatcher::add_filter_listener(
    'leantime.domain.menu.repositories.menu.getMenuStructure.menuStructures.company',
    function (array $menu): array {
        $menu[1] = [
            'type' => 'item',
            'module' => 'openceo',
            'title' => 'openceo.menu.ceo_desk',
            'icon' => 'fa fa-fw fa-building',
            'tooltip' => 'openceo.menu.ceo_desk_tooltip',
            'href' => '/openceo/desk',
            'role' => 'manager',
        ];

        ksort($menu);

        return $menu;
    },
    50
);

EventDispatcher::add_filter_listener(
    'leantime.domain.menu.repositories.menu.getSectionMenuType.menuSections',
    function (array $sections): array {
        $sections['openceo.desk'] = 'company';

        return $sections;
    },
    50
);
