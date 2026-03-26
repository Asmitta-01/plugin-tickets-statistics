<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Ticketsstatistics;

class TicketsStatisticsQuickActions
{
    public static function getTypeName($nb = 0): string
    {
        return __('Tickets Statistics Quick Actions', 'ticketsstatistics');
    }

    public static function getMenuContent(): array
    {
        return [
            'title'   => self::getTypeName(),
            'page'    => '/plugins/ticketsstatistics/front/quickactions.php',
            'icon'    => 'ti ti-code',
            'default' => '/plugins/ticketsstatistics/front/quickactions.php',
        ];
    }
}
