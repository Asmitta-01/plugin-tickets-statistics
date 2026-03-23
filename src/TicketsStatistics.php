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
}
