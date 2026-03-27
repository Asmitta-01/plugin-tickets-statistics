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

// Get the period filter from the request
$period = $_GET['period'] ?? 'last30';

$dateFrom = $_GET['date_from'] ?? null;
$dateTo   = $_GET['date_to']   ?? null;

\GlpiPlugin\Ticketsstatistics\PeriodFilter::apply($where, $table, $period, $dateFrom, $dateTo);

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
$counters['total'] = array_sum($counters);
$counters['solved_closed'] = $counters['solved'] + $counters['closed'];

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
    $priority['labels'][] = \Ticket::getPriorityName($row['priority']);
    $priority['values'][] = (int) $row['cpt'];
}

// --- By category (top 10) ---
$category = ['labels' => [], 'values' => []];
$categoryStats = [];
$catTable = 'glpi_itilcategories';

// Regroupement des statuts en 3 groupes
$statusGroups = [
    'new'        => [\Ticket::INCOMING],
    'resolved'   => [\Ticket::SOLVED, \Ticket::CLOSED],
    'in_progress' => [\Ticket::ASSIGNED, \Ticket::WAITING, \Ticket::ACCEPTED, \Ticket::OBSERVED],
];

foreach (
    $DB->request([
        'SELECT'    => [
            "$catTable.completename AS cat_name",
            "$table.status AS status",
            'COUNT'  => ["$table.id AS cpt"],
        ],
        'FROM'      => $table,
        'LEFT JOIN' => [
            $catTable => ['ON' => [$catTable => 'id', $table => 'itilcategories_id']]
        ],
        'WHERE'     => $where,
        'GROUPBY'   => ["$catTable.id", "$table.status"],
        'ORDER'     => ['cpt DESC'],
    ]) as $row
) {
    $catName = $row['cat_name'] ?? __('None');
    $status  = (int) $row['status'];
    $count   = (int) $row['cpt'];

    // Résout le groupe
    $group = 'in_progress'; // fallback
    foreach ($statusGroups as $groupName => $statuses) {
        if (in_array($status, $statuses, true)) {
            $group = $groupName;
            break;
        }
    }

    if (!isset($categoryStats[$catName])) {
        $categoryStats[$catName] = [
            'new'         => 0,
            'resolved'    => 0,
            'in_progress' => 0,
        ];
    }

    $categoryStats[$catName][$group] += $count;
}

// Trie par total décroissant, garde top 10 et supprime les catégories à 0
uasort($categoryStats, fn($a, $b) => array_sum($b) - array_sum($a));
$categoryStats = array_slice(array_filter($categoryStats, fn($v) => array_sum($v) > 0), 0, 10, true);

// Formate pour Chart.js
$category['labels']     = array_keys($categoryStats);
$category['values']['new']        = array_column($categoryStats, 'new');
$category['values']['resolved']   = array_column($categoryStats, 'resolved');
$category['values']['in_progress'] = array_column($categoryStats, 'in_progress');


// -- Per town ---
$cityData = ['labels' => [], 'values' => []];
$cityStats = [];
$locTable  = 'glpi_locations';

// Fetch ticket counts grouped by status and city
foreach (
    $DB->request([
        'SELECT'    => [
            "$locTable.town AS city",
            "$table.status AS status",
            'COUNT'  => ["$table.id AS cpt"],
        ],
        'FROM'      => $table,
        'LEFT JOIN' => [
            $locTable => ['ON' => [$locTable => 'id', $table => 'locations_id']]
        ],
        'WHERE'     => array_merge($where, [
            'NOT' => ["$locTable.town" => null],
            ["$locTable.town" => ['!=', '']],
        ]),
        'GROUPBY'   => ["$locTable.town", "$table.status"],
        'ORDER'     => ['cpt DESC'],
    ]) as $row
) {
    $city   = $row['city'];
    $status = (int) $row['status'];
    $count  = (int) $row['cpt'];

    // Resolve status group
    if (in_array($status, [\Ticket::INCOMING], true)) {
        $group = 'new';
    } elseif (in_array($status, [\Ticket::SOLVED, \Ticket::CLOSED], true)) {
        $group = 'resolved';
    } else {
        $group = 'in_progress';
    }

    if (!isset($cityStats[$city])) {
        $cityStats[$city] = ['new' => 0, 'resolved' => 0, 'in_progress' => 0];
    }

    $cityStats[$city][$group] += $count;
}

// Sort by total desc, keep top 10
uasort($cityStats, fn($a, $b) => array_sum($b) - array_sum($a));
$cityStats = array_slice($cityStats, 0, 10, true);

// Format for Chart.js polar area
// One dataset per status group, value = total tickets for that city+group
$cityData['labels']          = array_keys($cityStats);
$cityData['values']['new']        = array_column($cityStats, 'new');
$cityData['values']['resolved']   = array_column($cityStats, 'resolved');
$cityData['values']['in_progress'] = array_column($cityStats, 'in_progress');


// --- Per day ---
$perday = ['labels' => [], 'opened' => [], 'closed' => []];

// Tickets ouverts par jour
$openedByDay = [];
foreach (
    $DB->request([
        'SELECT'  => [
            'COUNT DISTINCT' => "$table.id AS cpt",
            new \QueryExpression("DATE($table.`date`) AS `day`"),
        ],
        'FROM'    => $table,
        'WHERE'   => $where,
        'GROUPBY' => new \QueryExpression('`day`'),
        'ORDER'   => new \QueryExpression('`day` ASC'),
    ]) as $row
) {
    $openedByDay[$row['day']] = (int) $row['cpt'];
}

// Tickets clôturés par jour
$closedWhere = array_merge($where, [
    'NOT' => ["$table.closedate" => null],
]);

$closedByDay = [];
foreach (
    $DB->request([
        'SELECT'  => [
            'COUNT DISTINCT' => "$table.id AS cpt",
            new \QueryExpression("DATE($table.`closedate`) AS `day`"),
        ],
        'FROM'    => $table,
        'WHERE'   => $closedWhere,
        'GROUPBY' => new \QueryExpression('`day`'),
        'ORDER'   => new \QueryExpression('`day` ASC'),
    ]) as $row
) {
    $closedByDay[$row['day']] = (int) $row['cpt'];
}

// Fusionne les labels (union des deux ensembles de dates)
$allDays = array_unique(array_merge(array_keys($openedByDay), array_keys($closedByDay)));
sort($allDays);

foreach ($allDays as $day) {
    $perday['labels'][]  = $day;
    $perday['opened'][]  = $openedByDay[$day] ?? 0;
    $perday['closed'][]  = $closedByDay[$day] ?? 0;
}

echo json_encode(compact('counters', 'priority', 'category', 'cityData', 'perday'));
