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

if (!Session::haveRight('dashboard', READ)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$DB    = DBConnection::getReadConnection();
$table = Ticket::getTable();
$where = ["$table.is_deleted" => 0] + getEntitiesRestrictCriteria($table);

$period = $_GET['period'] ?? 'last30';
// Only predefined periods are accepted; 'custom' is not offered here.
$allowedPeriods = ['last7', 'last30', 'last90', 'thisyear', 'lastyear'];
if (!in_array($period, $allowedPeriods, true)) {
    $period = 'last30';
}

\GlpiPlugin\Ticketsstatistics\PeriodFilter::apply($where, $table, $period);

// --- 1. Ticket status counts ---
$ticketStatus = ['labels' => [], 'values' => []];
$statusMap = [
    Ticket::INCOMING                       => __('New'),
    Ticket::ASSIGNED                       => __('Assigned'),
    Ticket::WAITING                        => __('Pending'),
    Ticket::SOLVED       => __('Resolved / Closed', 'ticketsstatistics'),
];
foreach ($statusMap as $statusId => $label) {
    if ($statusId === Ticket::SOLVED) {
        // For "Resolved / Closed", we want to include both SOLVED and CLOSED statuses
        $statusId = [Ticket::SOLVED, Ticket::CLOSED];
    }
    $iter = $DB->request([
        'COUNT' => 'cpt',
        'FROM'  => $table,
        'WHERE' => $where + ["$table.status" => $statusId],
    ]);
    $ticketStatus['labels'][] = $label;
    $ticketStatus['values'][] = (int) $iter->current()['cpt'];
}

// --- 2. Top requesters (top 10 by ticket count) ---
$topRequesters = ['labels' => [], 'values' => []];
$usersTable      = 'glpi_users';
$ticketUserTable = 'glpi_tickets_users';
$requesterRows   = [];
foreach (
    $DB->request([
        'SELECT'    => [
            "$usersTable.id",
            "$usersTable.firstname",
            "$usersTable.realname",
            "$usersTable.name AS login",
            'COUNT' => ["$table.id AS cpt"],
        ],
        'FROM'      => $table,
        'LEFT JOIN' => [
            $ticketUserTable => [
                'ON' => [
                    $ticketUserTable => 'tickets_id',
                    $table           => 'id',
                    [
                        'AND' => [$ticketUserTable . '.type' => CommonITILActor::REQUESTER],
                    ],
                ],
            ],
            $usersTable => [
                'ON' => [
                    $usersTable      => 'id',
                    $ticketUserTable => 'users_id',
                ],
            ],
        ],
        'WHERE'     => $where + ["$usersTable.id" => ['>', 0]],
        'GROUPBY'   => "$usersTable.id",
        'ORDER'     => ['cpt DESC'],
        'LIMIT'     => 10,
    ]) as $row
) {
    $name = trim(($row['firstname'] ?? '') . ' ' . ($row['realname'] ?? ''));
    if ($name === '') {
        $name = $row['login'] ?? __('Unknown', 'ticketsstatistics');
    }
    $topRequesters['labels'][] = $name;
    $topRequesters['values'][] = (int) $row['cpt'];
}

// --- 3. Tickets by town (top 10) ---
$ticketsByTown = ['labels' => [], 'values' => []];
$locTable      = 'glpi_locations';
$townStats     = [];
foreach (
    $DB->request([
        'SELECT'    => [
            "$locTable.town AS city",
            'COUNT' => ["$table.id AS cpt"],
        ],
        'FROM'      => $table,
        'LEFT JOIN' => [
            $locTable => ['ON' => [$locTable => 'id', $table => 'locations_id']],
        ],
        'WHERE'     => array_merge($where, [
            'NOT' => ["$locTable.town" => null],
            ["$locTable.town" => ['!=', '']],
        ]),
        'GROUPBY'   => "$locTable.town",
        'ORDER'     => ['cpt DESC'],
        'LIMIT'     => 10,
    ]) as $row
) {
    $townStats[$row['city']] = (int) $row['cpt'];
}
$ticketsByTown['labels'] = array_keys($townStats);
$ticketsByTown['values'] = array_values($townStats);

// --- 4. Assets by type (current total inventory, no period filter) ---
// Build entity scope: active entity + all its descendants so child-entity
// assets are always counted regardless of the user's "see sub-entities" toggle.
$activeEntityId  = $_SESSION['glpiactive_entity'] ?? 0;
$assetEntityIds  = array_merge(
    [$activeEntityId],
    array_keys(getSonsOf('glpi_entities', $activeEntityId))
);
$assetTypes = [
    __('Computers',         'ticketsstatistics') => 'glpi_computers',
    __('Monitors',          'ticketsstatistics') => 'glpi_monitors',
    __('Printers',          'ticketsstatistics') => 'glpi_printers',
    __('Peripherals',       'ticketsstatistics') => 'glpi_peripherals',
    __('Phones',            'ticketsstatistics') => 'glpi_phones',
    __('Network Equipment', 'ticketsstatistics') => 'glpi_networkequipments',
    __('Racks',             'ticketsstatistics') => 'glpi_racks',
];
$assetsByType = ['labels' => [], 'values' => []];
foreach ($assetTypes as $label => $assetTable) {
    $entityWhere = getEntitiesRestrictCriteria($assetTable, '', $assetEntityIds);
    $countWhere  = ["$assetTable.is_deleted" => 0, "$assetTable.is_template" => 0] + $entityWhere;
    $iter = $DB->request([
        'COUNT' => 'cpt',
        'FROM'  => $assetTable,
        'WHERE' => $countWhere,
    ]);
    $assetsByType['labels'][] = $label;
    $assetsByType['values'][] = (int) $iter->current()['cpt'];
}

// --- 5. Solved-date view counters ---
$solvedWhere = ["$table.is_deleted" => 0] + getEntitiesRestrictCriteria($table);
\GlpiPlugin\Ticketsstatistics\PeriodFilter::applySolvedDate($solvedWhere, $table, $period);

$resolvedInPeriodIter = $DB->request([
    'COUNT' => 'cpt',
    'FROM'  => $table,
    'WHERE' => $solvedWhere + ["$table.status" => [Ticket::SOLVED, Ticket::CLOSED]],
]);
$resolvedInPeriod = (int) $resolvedInPeriodIter->current()['cpt'];

$openedIter = $DB->request([
    'COUNT' => 'cpt',
    'FROM'  => $table,
    'WHERE' => $where,
]);
$openedInPeriod = (int) $openedIter->current()['cpt'];

$solvedTTRWhere = array_merge($solvedWhere, [
    new \QueryExpression("($table.`solve_delay_stat` != 0 OR $table.`close_delay_stat` != 0)"),
]);
$solvedResolutionRows = [];
foreach (
    $DB->request([
        'SELECT' => ["$table.solve_delay_stat", "$table.close_delay_stat"],
        'FROM'   => $table,
        'WHERE'  => $solvedTTRWhere,
    ]) as $row
) {
    $seconds = (int) $row['solve_delay_stat'] ?: (int) $row['close_delay_stat'];
    if ($seconds <= 0) continue;
    $solvedResolutionRows[] = round($seconds / 3600, 2);
}
$solvedAvgTtr = count($solvedResolutionRows) > 0
    ? round(array_sum($solvedResolutionRows) / count($solvedResolutionRows), 2)
    : 0;

$solvedView = [
    'resolved_in_period' => $resolvedInPeriod,
    'opened_in_period'   => $openedInPeriod,
    'avg_ttr'            => $solvedAvgTtr,
];

echo json_encode(compact('ticketStatus', 'topRequesters', 'ticketsByTown', 'assetsByType', 'solvedView'));
