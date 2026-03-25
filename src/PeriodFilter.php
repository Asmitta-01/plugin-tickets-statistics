<?php

namespace GlpiPlugin\Ticketsstatistics;

class PeriodFilter
{
    public static function apply(array &$where, string $table, string $period, ?string $dateFrom = null, ?string $dateTo = null): void
    {
        switch ($period) {
            case 'last7':
                $where[] = new \Glpi\DBAL\QueryExpression("$table.`date` >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                break;
            case 'last30':
                $where[] = new \Glpi\DBAL\QueryExpression("$table.`date` >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
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
}
