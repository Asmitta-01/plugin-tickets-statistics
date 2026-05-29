<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

use GlpiPlugin\Ticketsstatistics\AssetStatistics;

\Session::checkCentralAccess();

if (!\Session::haveRight('dashboard', READ)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'forbidden']);
    exit;
}

header('Content-Type: application/json');

$townId         = (int) ($_GET['town'] ?? 0);
$manufacturerId = (int) ($_GET['manufacturer'] ?? 0);
$softwareId     = (int) ($_GET['software'] ?? 0);

if ($softwareId <= 0) {
    echo json_encode([
        'state'    => 'no_selection',
        'coverage' => [
            'with'    => 0,
            'without' => 0,
            'total'   => 0,
            'name'    => '',
        ],
    ]);
    exit;
}

$coverage = AssetStatistics::getSoftwareCoverage($softwareId, $townId, $manufacturerId);
$state = ((int) ($coverage['total'] ?? 0) > 0) ? 'has_data' : 'no_data';

echo json_encode([
    'state'    => $state,
    'coverage' => [
        'with'    => (int) ($coverage['with'] ?? 0),
        'without' => (int) ($coverage['without'] ?? 0),
        'total'   => (int) ($coverage['total'] ?? 0),
        'name'    => (string) ($coverage['name'] ?? ''),
    ],
]);
