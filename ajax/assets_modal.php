<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

use GlpiPlugin\Ticketsstatistics\AssetStatistics;

\Session::checkCentralAccess();
header('Content-Type: application/json');

if (!\Session::haveRight('dashboard', READ)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

const TICKETSSTATISTICS_ASSETS_MODAL_LIMIT = 100;

$resolved = AssetStatistics::resolveAssetsScope([
    'counter_key'     => $_GET['counter_key'] ?? 'total',
    'town_id'         => (int) ($_GET['town_id'] ?? 0),
    'manufacturer_id' => (int) ($_GET['manufacturer_id'] ?? 0),
]);

$allRows = $resolved['rows'];
$count = count($allRows);
$rows = array_slice($allRows, 0, TICKETSSTATISTICS_ASSETS_MODAL_LIMIT);

echo json_encode([
    'title'     => $resolved['title'],
    'count'     => $count,
    'truncated' => $count > TICKETSSTATISTICS_ASSETS_MODAL_LIMIT,
    'limit'     => TICKETSSTATISTICS_ASSETS_MODAL_LIMIT,
    'assets'    => array_values($rows),
]);
