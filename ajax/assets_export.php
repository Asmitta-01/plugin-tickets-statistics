<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

use Glpi\Csv\CsvResponse;
use GlpiPlugin\Ticketsstatistics\AssetExport;
use GlpiPlugin\Ticketsstatistics\AssetStatistics;

\Session::checkCentralAccess();

if (!\Session::haveRight('dashboard', READ)) {
    http_response_code(403);
    exit;
}

$townId = (int) ($_GET['town_id'] ?? 0);
$manufacturerId = (int) ($_GET['manufacturer_id'] ?? 0);
$counterKey = (string) ($_GET['counter_key'] ?? 'total');

$resolved = AssetStatistics::resolveAssetsScope([
    'counter_key'     => $counterKey,
    'town_id'         => $townId,
    'manufacturer_id' => $manufacturerId,
]);

$rows = $resolved['rows'];

CsvResponse::output(new AssetExport($rows, $townId));
