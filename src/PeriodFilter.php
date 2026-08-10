<?php

namespace GlpiPlugin\Ticketsstatistics;

class PeriodFilter
{

    public static function getAvailablePeriods(): array
    {
        return [
            'last7' => __('Last 7 days', 'ticketsstatistics'),
            'thismonth' => __('This month', 'ticketsstatistics'),
            'last30' => __('Last 30 days', 'ticketsstatistics'),
            'lastmonth' => __('Last month', 'ticketsstatistics'),
            'last90' => __('Last 90 days', 'ticketsstatistics'),
            'thisyear' => __('This year', 'ticketsstatistics'),
            'lastyear' => __('Last year', 'ticketsstatistics'),
            'custom' => __('Custom period', 'ticketsstatistics'),
        ];
    }

    public static function getOpenAgeBuckets(): array
    {
        return [
            '< 24h' => __('Less than 24 hours', 'ticketsstatistics'),
            '1 - 3j' => __('1 to 3 days', 'ticketsstatistics'),
            '3 - 7j' => __('3 to 7 days', 'ticketsstatistics'),
            '> 7j' => __('More than 7 days', 'ticketsstatistics'),
        ];
    }

    public static function getPeriodLabel(string $period): string
    {
        return self::getAvailablePeriods()[$period] ?? $period;
    }

    /**
     * Applies a date filter to the provided where clause array based on the specified period.
     *
     * Supported periods:
     * - 'last7':   Last 7 days
     * - 'thismonth': Current month
     * - 'last30':  Last 30 days
     * - 'lastmonth': Previous month
     * - 'last90':  Last 90 days
     * - 'thisyear': Current year
     * - 'lastyear': Previous year
     * - 'custom':  Custom date range (requires $dateFrom and/or $dateTo in 'Y-m-d' format)
     * - default:   Last 30 days
     *
     * @param array  $where    Reference to the array of where conditions to be modified.
     * @param string $table    The table name to use in the date condition.
     * @param string $period   The period to filter by.
     * @param string|null $dateFrom Optional start date for 'custom' period (format: 'Y-m-d').
     * @param string|null $dateTo   Optional end date for 'custom' period (format: 'Y-m-d').
     *
     * @return void
     */
    public static function apply(array &$where, string $table, string $period, ?string $dateFrom = null, ?string $dateTo = null): void
    {
        if (!class_exists('\Glpi\DBAL\QueryExpression')) {
            self::applyBackward($where, $table, $period, $dateFrom, $dateTo);
            return;
        }

        switch ($period) {
            case 'last7':
                $where[] = new \Glpi\DBAL\QueryExpression("$table.`date` >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                break;
            case 'thismonth':
                $where[] = new \Glpi\DBAL\QueryExpression("MONTH($table.`date`) = MONTH(CURDATE()) AND YEAR($table.`date`) = YEAR(CURDATE())");
                break;
            case 'last30':
                $where[] = new \Glpi\DBAL\QueryExpression("$table.`date` >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                break;
            case 'lastmonth':
                $where[] = new \Glpi\DBAL\QueryExpression("$table.`date` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND $table.`date` < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
                break;
            case 'last90':
                $where[] = new \Glpi\DBAL\QueryExpression("$table.`date` >= DATE_SUB(NOW(), INTERVAL 90 DAY)");
                break;
            case 'thisyear':
                $where[] = new \Glpi\DBAL\QueryExpression("YEAR($table.`date`) = YEAR(CURDATE())");
                break;
            case 'lastyear':
                $where[] = new \Glpi\DBAL\QueryExpression("YEAR($table.`date`) = YEAR(CURDATE()) - 1");
                break;
            case 'custom':
                if ($dateFrom && \DateTime::createFromFormat('Y-m-d', $dateFrom) !== false) {
                    $where[] = new \Glpi\DBAL\QueryExpression("$table.`date` >= '$dateFrom'");
                }
                if ($dateTo && \DateTime::createFromFormat('Y-m-d', $dateTo) !== false) {
                    $where[] = new \Glpi\DBAL\QueryExpression("$table.`date` <= '$dateTo 23:59:59'");
                }
                break;
            default:
                $where[] = new \Glpi\DBAL\QueryExpression("$table.`date` >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                break;
        }
    }

    /**
     * Backward compatibility method for applying date filters without using QueryExpression (for older GLPI versions = 10.x.x).
     *
     * @param array  $where    Reference to the array of where conditions to be modified.
     * @param string $table    The table name to use in the date condition.
     * @param string $period   The period to filter by.
     * @param string|null $dateFrom Optional start date for 'custom' period (format: 'Y-m-d').
     * @param string|null $dateTo   Optional end date for 'custom' period (format: 'Y-m-d').
     *
     * @return void
     */
    public static function applyBackward(array &$where, string $table, string $period, ?string $dateFrom = null, ?string $dateTo = null): void
    {
        switch ($period) {
            case 'last7':
                $where[] = new \QueryExpression("$table.`date` >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                break;
            case 'thismonth':
                $where[] = new \QueryExpression("MONTH($table.`date`) = MONTH(CURDATE()) AND YEAR($table.`date`) = YEAR(CURDATE())");
                break;
            case 'last30':
                $where[] = new \QueryExpression("$table.`date` >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                break;
            case 'lastmonth':
                $where[] = new \QueryExpression("$table.`date` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND $table.`date` < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
                break;
            case 'last90':
                $where[] = new \QueryExpression("$table.`date` >= DATE_SUB(NOW(), INTERVAL 90 DAY)");
                break;
            case 'thisyear':
                $where[] = new \QueryExpression("YEAR($table.`date`) = YEAR(CURDATE())");
                break;
            case 'lastyear':
                $where[] = new \QueryExpression("YEAR($table.`date`) = YEAR(CURDATE()) - 1");
                break;
            case 'custom':
                if ($dateFrom && self::isValidDate($dateFrom)) {
                    $where[] = new \QueryExpression("$table.`date` >= '$dateFrom'");
                }
                if ($dateTo && self::isValidDate($dateTo)) {
                    $where[] = new \QueryExpression("$table.`date` <= '$dateTo 23:59:59'");
                }
                break;
            default:
                $where[] = new \QueryExpression("$table.`date` >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                break;
        }
    }

    /**
     * Same as `apply()` but filters on the resolved/closed date instead of the creation date.
     * Uses COALESCE(NULLIF(solvedate, '0000-00-00 00:00:00'), closedate) as the date column.
     */
    public static function applySolvedDate(array &$where, string $table, string $period, ?string $dateFrom = null, ?string $dateTo = null): void
    {
        if (!class_exists('\Glpi\DBAL\QueryExpression')) {
            self::applySolvedDateBackward($where, $table, $period, $dateFrom, $dateTo);
            return;
        }

        $col = "COALESCE(NULLIF($table.`solvedate`, '0000-00-00 00:00:00'), $table.`closedate`)";

        switch ($period) {
            case 'last7':
                $where[] = new \Glpi\DBAL\QueryExpression("$col >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                break;
            case 'thismonth':
                $where[] = new \Glpi\DBAL\QueryExpression("MONTH($col) = MONTH(CURDATE()) AND YEAR($col) = YEAR(CURDATE())");
                break;
            case 'last30':
                $where[] = new \Glpi\DBAL\QueryExpression("$col >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                break;
            case 'lastmonth':
                $where[] = new \Glpi\DBAL\QueryExpression("$col >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND $col < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
                break;
            case 'last90':
                $where[] = new \Glpi\DBAL\QueryExpression("$col >= DATE_SUB(NOW(), INTERVAL 90 DAY)");
                break;
            case 'thisyear':
                $where[] = new \Glpi\DBAL\QueryExpression("YEAR($col) = YEAR(CURDATE())");
                break;
            case 'lastyear':
                $where[] = new \Glpi\DBAL\QueryExpression("YEAR($col) = YEAR(CURDATE()) - 1");
                break;
            case 'custom':
                if ($dateFrom && self::isValidDate($dateFrom)) {
                    $where[] = new \Glpi\DBAL\QueryExpression("$col >= '$dateFrom'");
                }
                if ($dateTo && self::isValidDate($dateTo)) {
                    $where[] = new \Glpi\DBAL\QueryExpression("$col <= '$dateTo 23:59:59'");
                }
                break;
            default:
                $where[] = new \Glpi\DBAL\QueryExpression("$col >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                break;
        }
    }

    public static function applySolvedDateBackward(array &$where, string $table, string $period, ?string $dateFrom = null, ?string $dateTo = null): void
    {
        $col = "COALESCE(NULLIF(`$table`.`solvedate`, '0000-00-00 00:00:00'), `$table`.`closedate`)";

        switch ($period) {
            case 'last7':
                $where[] = new \QueryExpression("$col >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                break;
            case 'thismonth':
                $where[] = new \QueryExpression("MONTH($col) = MONTH(CURDATE()) AND YEAR($col) = YEAR(CURDATE())");
                break;
            case 'last30':
                $where[] = new \QueryExpression("$col >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                break;
            case 'lastmonth':
                $where[] = new \QueryExpression("$col >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m-01') AND $col < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
                break;
            case 'last90':
                $where[] = new \QueryExpression("$col >= DATE_SUB(NOW(), INTERVAL 90 DAY)");
                break;
            case 'thisyear':
                $where[] = new \QueryExpression("YEAR($col) = YEAR(CURDATE())");
                break;
            case 'lastyear':
                $where[] = new \QueryExpression("YEAR($col) = YEAR(CURDATE()) - 1");
                break;
            case 'custom':
                if ($dateFrom && self::isValidDate($dateFrom)) {
                    $where[] = new \QueryExpression("$col >= '$dateFrom'");
                }
                if ($dateTo && self::isValidDate($dateTo)) {
                    $where[] = new \QueryExpression("$col < DATE_ADD('$dateTo', INTERVAL 1 DAY)");
                }
                break;
            default:
                $where[] = new \QueryExpression("$col >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                break;
        }
    }

    private static function isValidDate(string $date): bool
    {
        return \DateTime::createFromFormat('Y-m-d', $date) !== false;
    }

    /**
     * Resolves the [from, to] DateTime boundaries (inclusive) for a given period.
     * Centralizes the date logic so both apply() and the "previous period"
     * calculation stay in sync.
     *
     * @return array{0: \DateTime, 1: \DateTime}
     */
    private static function resolveBounds(string $period, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $now = new \DateTime();

        switch ($period) {
            case 'last7':
                $to   = (clone $now)->setTime(23, 59, 59);
                $from = (clone $to)->modify('-6 days')->setTime(0, 0, 0);
                break;
            case 'thismonth':
                $from = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
                $to   = (clone $now)->setTime(23, 59, 59);
                break;
            case 'last30':
                $to   = (clone $now)->setTime(23, 59, 59);
                $from = (clone $to)->modify('-29 days')->setTime(0, 0, 0);
                break;
            case 'lastmonth':
                $from = (clone $now)->modify('first day of last month')->setTime(0, 0, 0);
                $to   = (clone $now)->modify('last day of last month')->setTime(23, 59, 59);
                break;
            case 'last90':
                $to   = (clone $now)->setTime(23, 59, 59);
                $from = (clone $to)->modify('-89 days')->setTime(0, 0, 0);
                break;
            case 'thisyear':
                $from = (clone $now)->modify('first day of january this year')->setTime(0, 0, 0);
                $to   = (clone $now)->setTime(23, 59, 59);
                break;
            case 'lastyear':
                $from = (clone $now)->modify('first day of january last year')->setTime(0, 0, 0);
                $to   = (clone $now)->modify('last day of december last year')->setTime(23, 59, 59);
                break;
            case 'custom':
                $from = ($dateFrom && \DateTime::createFromFormat('Y-m-d', $dateFrom) !== false)
                    ? \DateTime::createFromFormat('Y-m-d', $dateFrom)->setTime(0, 0, 0)
                    : (clone $now)->modify('-29 days')->setTime(0, 0, 0);
                $to = ($dateTo && \DateTime::createFromFormat('Y-m-d', $dateTo) !== false)
                    ? \DateTime::createFromFormat('Y-m-d', $dateTo)->setTime(23, 59, 59)
                    : (clone $now)->setTime(23, 59, 59);
                break;
            default:
                $to   = (clone $now)->setTime(23, 59, 59);
                $from = (clone $to)->modify('-29 days')->setTime(0, 0, 0);
                break;
        }

        return [$from, $to];
    }

    /**
     * Computes the [from, to] boundaries of the period immediately preceding
     * the given one, of equal length (for day-based periods) or the matching
     * calendar unit (for month/year-based periods).
     *
     * Examples:
     * - 'last7'  -> the 7 days right before the current "last 7 days"
     * - 'thismonth' -> the previous calendar month
     * - 'custom' with a 10-day range -> the 10 days right before that range
     *
     * @return array{0: \DateTime, 1: \DateTime}
     */
    private static function resolvePreviousBounds(string $period, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        [$from, $to] = self::resolveBounds($period, $dateFrom, $dateTo);

        switch ($period) {
            case 'thismonth':
            case 'lastmonth':
                $prevTo   = (clone $from)->modify('-1 second');
                $prevFrom = (clone $prevTo)->modify('first day of this month')->setTime(0, 0, 0);
                break;
            case 'thisyear':
            case 'lastyear':
                $prevTo   = (clone $from)->modify('-1 second');
                $prevFrom = (clone $prevTo)->modify('first day of january this year')->setTime(0, 0, 0);
                break;
            default:
                // Périodes basées sur un nombre de jours (last7, last30, last90, custom, ...):
                // même durée, immédiatement avant la période courante.
                $lengthInSeconds = $to->getTimestamp() - $from->getTimestamp();
                $prevTo   = (clone $from)->modify('-1 second');
                $prevFrom = (clone $prevTo)->modify('-' . $lengthInSeconds . ' seconds');
                break;
        }

        return [$prevFrom, $prevTo];
    }

    /**
     * Same as apply(), but filters on the period immediately preceding
     * the given one (same length, or matching calendar unit for
     * month/year-based periods). Useful for period-over-period comparisons.
     */
    public static function applyPrevious(array &$where, string $table, string $period, ?string $dateFrom = null, ?string $dateTo = null): void
    {
        [$prevFrom, $prevTo] = self::resolvePreviousBounds($period, $dateFrom, $dateTo);

        $expressionClass = class_exists('\Glpi\DBAL\QueryExpression')
            ? '\Glpi\DBAL\QueryExpression'
            : '\QueryExpression';

        $fromStr = $prevFrom->format('Y-m-d H:i:s');
        $toStr   = $prevTo->format('Y-m-d H:i:s');

        $where[] = new $expressionClass("$table.`date` >= '$fromStr' AND $table.`date` <= '$toStr'");
    }

    /**
     * Same as applySolvedDate(), but filters on the period immediately preceding
     * the given one.
     */
    public static function applyPreviousSolvedDate(array &$where, string $table, string $period, ?string $dateFrom = null, ?string $dateTo = null): void
    {
        [$prevFrom, $prevTo] = self::resolvePreviousBounds($period, $dateFrom, $dateTo);

        $expressionClass = class_exists('\Glpi\DBAL\QueryExpression')
            ? '\Glpi\DBAL\QueryExpression'
            : '\QueryExpression';

        $fromStr = $prevFrom->format('Y-m-d H:i:s');
        $toStr   = $prevTo->format('Y-m-d H:i:s');

        $col = "COALESCE(NULLIF($table.`solvedate`, '0000-00-00 00:00:00'), $table.`closedate`)";

        $where[] = new $expressionClass("$col >= '$fromStr' AND $col <= '$toStr'");
    }
}
