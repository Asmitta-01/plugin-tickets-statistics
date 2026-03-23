<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

Session::checkCentralAccess();

header('Content-Type: application/json');

$DB    = DBConnection::getReadConnection();
$table = Ticket::getTable();
$where = ["$table.is_deleted" => 0] + getEntitiesRestrictCriteria($table);

// --- Counters by status ---
$counters = [];
foreach (
    [
        'incoming' => Ticket::INCOMING,
        'assigned' => Ticket::ASSIGNED,
        'waiting'  => Ticket::WAITING,
        'solved'   => Ticket::SOLVED,
        'closed'   => Ticket::CLOSED,
    ] as $key => $status
) {
    $iter = $DB->request([
        'COUNT' => 'cpt',
        'FROM'  => $table,
        'WHERE' => $where + ["$table.status" => $status],
    ]);
    $counters[$key] = (int) $iter->current()['cpt'];
}

// --- By priority ---
$priority = ['labels' => [], 'values' => []];
foreach (
    $DB->request([
        'SELECT'  => ['COUNT DISTINCT' => "$table.id AS cpt", "$table.priority"],
        'FROM'    => $table,
        'WHERE'   => $where,
        'GROUPBY' => "$table.priority",
        'ORDER'   => "$table.priority ASC",
    ]) as $row
) {
    $priority['labels'][] = Ticket::getPriorityName($row['priority']);
    $priority['values'][] = (int) $row['cpt'];
}

// --- By category (top 10) ---
$category = ['labels' => [], 'values' => []];
$catTable = 'glpi_itilcategories';
foreach (
    $DB->request([
        'SELECT'    => [
            'COUNT DISTINCT' => "$table.id AS cpt",
            "$catTable.completename AS cat_name",
        ],
        'FROM'      => $table,
        'LEFT JOIN' => [$catTable => ['ON' => [$catTable => 'id', $table => 'itilcategories_id']]],
        'WHERE'     => $where,
        'GROUPBY'   => "$catTable.id",
        'ORDER'     => 'cpt DESC',
        'LIMIT'     => 10,
    ]) as $row
) {
    $category['labels'][] = $row['cat_name'] ?? __('None');
    $category['values'][] = (int) $row['cpt'];
}

// --- Per day (last 30 days) ---
$perday = ['labels' => [], 'values' => []];
foreach (
    $DB->request([
        'SELECT'  => [
            'COUNT DISTINCT' => "$table.id AS cpt",
            new \Glpi\DBAL\QueryExpression("DATE($table.`date`) AS `day`"),
        ],
        'FROM'    => $table,
        'WHERE'   => $where + [
            new \Glpi\DBAL\QueryExpression("$table.`date` >= DATE_SUB(NOW(), INTERVAL 30 DAY)"),
        ],
        'GROUPBY' => new \Glpi\DBAL\QueryExpression('`day`'),
        'ORDER'   => new \Glpi\DBAL\QueryExpression('`day` ASC'),
    ]) as $row
) {
    $perday['labels'][] = $row['day'];
    $perday['values'][] = (int) $row['cpt'];
}

echo json_encode(compact('counters', 'priority', 'category', 'perday'));
