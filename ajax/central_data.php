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
    Ticket::INCOMING => __('New'),
    Ticket::ASSIGNED => __('Assigned'),
    Ticket::WAITING  => __('Pending'),
    Ticket::SOLVED   => __('Solved'),
    Ticket::CLOSED   => __('Closed'),
];
foreach ($statusMap as $statusId => $label) {
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
        $name = $row['login'] ?? __('Unknown');
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
$assetEntityWhere = getEntitiesRestrictCriteria('');
$assetTypes = [
    __('Computers')          => 'glpi_computers',
    __('Monitors')           => 'glpi_monitors',
    __('Printers')           => 'glpi_printers',
    __('Peripherals')        => 'glpi_peripherals',
    __('Phones')             => 'glpi_phones',
    __('Network Equipment')  => 'glpi_networkequipments',
    __('Racks')              => 'glpi_racks',
];
$assetsByType = ['labels' => [], 'values' => []];
foreach ($assetTypes as $label => $assetTable) {
    $entityWhere = getEntitiesRestrictCriteria($assetTable);
    $countWhere  = ["$assetTable.is_deleted" => 0, "$assetTable.is_template" => 0] + $entityWhere;
    $iter = $DB->request([
        'COUNT' => 'cpt',
        'FROM'  => $assetTable,
        'WHERE' => $countWhere,
    ]);
    $assetsByType['labels'][] = $label;
    $assetsByType['values'][] = (int) $iter->current()['cpt'];
}

echo json_encode(compact('ticketStatus', 'topRequesters', 'ticketsByTown', 'assetsByType'));
