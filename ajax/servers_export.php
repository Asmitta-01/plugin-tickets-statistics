<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

use Glpi\Csv\CsvResponse;
use GlpiPlugin\Ticketsstatistics\ServerExport;
use GlpiPlugin\Ticketsstatistics\ServersStatistics;

\Session::checkCentralAccess();

if (!\Session::haveRight('dashboard', READ)) {
    http_response_code(403);
    exit;
}

$townId = (int) ($_GET['town_id'] ?? 0);
$entityId = (int) ($_GET['entity_id'] ?? 0);
$counterKey = (string) ($_GET['counter_key'] ?? 'total');

$resolved = ServersStatistics::resolveServersScope([
    'scope'       => $_GET['scope'] ?? '',
    'counter_key' => $_GET['counter_key'] ?? '',
    'nature_key'  => $_GET['nature_key'] ?? '',
    'model'       => $_GET['model'] ?? '',
    'town_id'     => $townId,
    'entity_id'   => $entityId,
]);

$rows = $resolved['rows'];

CsvResponse::output(new ServerExport($rows, $townId));
