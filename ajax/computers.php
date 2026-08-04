<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

use GlpiPlugin\Ticketsstatistics\ComputersStatistics;

\Session::checkCentralAccess();
header('Content-Type: application/json');

if (!\Session::haveRight('dashboard', READ)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

const TICKETSSTATISTICS_COMPUTERS_MODAL_LIMIT = 100;

$resolved = ComputersStatistics::resolveComputersScope([
    'scope' => $_GET['scope'] ?? '',
    'counter_key' => $_GET['counter_key'] ?? '',
    'version' => $_GET['version'] ?? '',
    'town' => $_GET['town'] ?? '',
    'kb_code' => $_GET['kb_code'] ?? '',
    'town_id' => (int) ($_GET['town_id'] ?? 0),
]);

$allRows = $resolved['rows'];
$count = count($allRows);
$rows = array_slice($allRows, 0, TICKETSSTATISTICS_COMPUTERS_MODAL_LIMIT);

echo json_encode([
    'title' => $resolved['title'],
    'count' => $count,
    'truncated' => $count > TICKETSSTATISTICS_COMPUTERS_MODAL_LIMIT,
    'limit' => TICKETSSTATISTICS_COMPUTERS_MODAL_LIMIT,
    'computers' => array_values($rows),
]);
