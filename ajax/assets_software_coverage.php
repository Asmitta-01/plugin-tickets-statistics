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
$softwareIds    = array_values(array_unique(array_filter(array_map(
    static fn($id): int => (int) $id,
    (array) ($_GET['software'] ?? [])
), static fn(int $id): bool => $id > 0)));

if ($softwareIds === []) {
    echo json_encode([
        'state'    => 'no_selection',
        'coverage' => [
            'with'    => 0,
            'without' => 0,
            'total'   => 0,
            'name'    => '',
            'names'   => [],
        ],
    ]);
    exit;
}

$coverage = AssetStatistics::getSoftwareCoverageForSelection($softwareIds, $townId, $manufacturerId);
$state = ((int) ($coverage['total'] ?? 0) > 0) ? 'has_data' : 'no_data';

echo json_encode([
    'state'    => $state,
    'coverage' => [
        'with'    => (int) ($coverage['with'] ?? 0),
        'without' => (int) ($coverage['without'] ?? 0),
        'total'   => (int) ($coverage['total'] ?? 0),
        'name'    => (string) ($coverage['name'] ?? ''),
        'names'   => (array) ($coverage['names'] ?? []),
    ],
]);
