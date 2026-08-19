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

    private static function getStatusColors(): array
    {
        return [
            'new' => '#49bf4d',
            'incoming' => '#49bf4d',
            'assigned' => '#49bf4d',
            'waiting' => '#ffa500',
            'missc' => '#ff8000',
            'solved_closed' => '#C00000',
            'total' => '#555555',
        ];
    }

    public static function getTTRColors(): array
    {
        return [
            '#49bf4d',
            '#0241ec',
            '#ff8000',
            '#9b59b6',
            '#C00000',
        ];
    }

    public static function getOpenAgeColors(): array
    {
        return [
            '#49bf4d',
            '#0241ec',
            '#ff8000',
            '#C00000',
        ];
    }

    public static function getStatusColor(string $status): string
    {
        return self::getStatusColors()[$status] ?? '#000000';
    }

    public static function showStatusGroupButtons(string $id): void
    {
        echo '<div class="btn-group btn-group-sm" role="group" aria-label="" id="' . $id . '">';
        echo '<div class="btn">' . __('Status', 'ticketsstatistics') . '</div>';
        echo '<div class="btn" data-bs-toggle="tooltip" title="' . \Ticket::getStatus(\Ticket::INCOMING) . '">';
        echo '<span class="badge me-1" style="background-color: ' . self::getStatusColor('new') . ';"></span>';
        echo __('New', 'ticketsstatistics');
        echo '</div>';
        echo '<div class="btn" data-bs-toggle="tooltip" data-bs-placement="bottom" title="' . \Ticket::getStatus(\Ticket::SOLVED) . ' / ' . \Ticket::getStatus(\Ticket::CLOSED) . '">';
        echo '<span class="badge me-1" style="background-color: ' . self::getStatusColor('solved_closed') . ';"></span>';
        echo __('Resolved', 'ticketsstatistics');
        echo '</div>';
        echo '<div class="btn" data-bs-toggle="tooltip" data-bs-placement="bottom" title="' . \Ticket::getStatus(\Ticket::ASSIGNED) . ' / ' . \Ticket::getStatus(\Ticket::WAITING) .  '">';
        echo '<span class="badge me-1" style="background-color: ' . self::getStatusColor('waiting') . ';"></span>';
        echo __('In progress', 'ticketsstatistics');
        echo '</div>';
        echo '</div>';
    }

    public static function getITILCategories(): array
    {
        $categories = [];
        foreach ([] as $id => $name) {
            $categories[] = ['id' => $id, 'name' => $name];
        }
        return $categories;
    }

    /**
     * Formats a month string into a localized format based on the user's language preference.
     * `date('F Y', strtotime($month))` is replaced with `IntlDateFormatter` to provide proper localization.
     */
    public static function formatMonthLocalized(string $month): string
    {
        // $month attendu au format 'YYYY-MM' ou tout ce que strtotime() comprend
        $timestamp = strtotime($month);

        $locale = $_SESSION['glpilanguage'] ?? 'en_GB'; // langue GLPI de l'utilisateur courant

        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::LONG,
            \IntlDateFormatter::NONE,
            null,
            null,
            'MMMM yyyy' // motif ICU : mois en toutes lettres + année
        );

        return $formatter->format($timestamp);
    }
}
