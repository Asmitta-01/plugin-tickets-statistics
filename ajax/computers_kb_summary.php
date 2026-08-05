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

$townId = (int) ($_GET['town_id'] ?? 0);
$entityId = (int) ($_GET['entity_id'] ?? 0);
$rows = ComputersStatistics::getKbInstallationsSummary($townId, $entityId);

$totalInstallations = 0;
foreach ($rows as $row) {
    $totalInstallations += (int) ($row['installations'] ?? 0);
}

echo json_encode([
    'title' => __('Total KB patches deployed', 'ticketsstatistics'),
    'count' => count($rows),
    'total_installations' => $totalInstallations,
    'kbs' => array_values($rows),
]);
