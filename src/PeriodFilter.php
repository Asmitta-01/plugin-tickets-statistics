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
                $where[] = new \Glpi\DBAL\QueryExpression("MONTH($table.`date`) = MONTH(CURDATE()) - 1 AND YEAR($table.`date`) = YEAR(CURDATE())");
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
                $where[] = new \QueryExpression("MONTH($table.`date`) = MONTH(CURDATE()) - 1 AND YEAR($table.`date`) = YEAR(CURDATE())");
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
                $where[] = new \Glpi\DBAL\QueryExpression("MONTH($col) = MONTH(CURDATE()) - 1 AND YEAR($col) = YEAR(CURDATE())");
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
                $where[] = new \QueryExpression("MONTH($col) = MONTH(CURDATE()) - 1 AND YEAR($col) = YEAR(CURDATE())");
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
}
