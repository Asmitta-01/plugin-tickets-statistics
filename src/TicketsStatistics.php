<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Ticketsstatistics;

class TicketsStatistics
{
    public static function getMenuContent(): array
    {
        return [
            'title'   => __('Tickets Statistics', 'ticketsstatistics'),
            'page'    => '/plugins/ticketsstatistics/front/dashboard.php',
            'icon'    => self::getIcon(),
            'default' => '/plugins/ticketsstatistics/front/dashboard.php',
        ];
    }

    public static function getTypeName($nb = 0): string
    {
        return __('Tickets Statistics', 'ticketsstatistics');
    }

    public static function getIcon(): string
    {
        return 'ti ti-chart-bar';
    }

    public static function getAvailablePeriods(): array
    {
        return [
            'last7' => __('Last 7 days', 'ticketsstatistics'),
            'last30' => __('Last 30 days', 'ticketsstatistics'),
            'last90' => __('Last 90 days', 'ticketsstatistics'),
            'thisyear' => __('This year', 'ticketsstatistics'),
            'lastyear' => __('Last year', 'ticketsstatistics'),
            'custom' => __('Custom period', 'ticketsstatistics'),
        ];
    }

    public static function getPeriodLabel(string $period): string
    {
        return self::getAvailablePeriods()[$period] ?? $period;
    }
}
