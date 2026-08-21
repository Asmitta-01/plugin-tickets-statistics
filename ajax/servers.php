<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

use GlpiPlugin\Ticketsstatistics\ServersStatistics;

\Session::checkCentralAccess();
header('Content-Type: application/json');

if (!\Session::haveRight('dashboard', READ)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

const TICKETSSTATISTICS_SERVERS_MODAL_LIMIT = 100;

$resolved = ServersStatistics::resolveServersScope([
    'counter_key' => $_GET['counter_key'] ?? 'total',
    'town_id'     => (int) ($_GET['town_id'] ?? 0),
    'entity_id'   => (int) ($_GET['entity_id'] ?? 0),
]);

$allRows = $resolved['rows'];
$count = count($allRows);
$rows = array_slice($allRows, 0, TICKETSSTATISTICS_SERVERS_MODAL_LIMIT);

echo json_encode([
    'title'     => $resolved['title'],
    'count'     => $count,
    'truncated' => $count > TICKETSSTATISTICS_SERVERS_MODAL_LIMIT,
    'limit'     => TICKETSSTATISTICS_SERVERS_MODAL_LIMIT,
    'servers'   => array_values($rows),
]);
