<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

use Glpi\Csv\CsvResponse;
use GlpiPlugin\Ticketsstatistics\ComputerExport;
use GlpiPlugin\Ticketsstatistics\ComputersStatistics;

\Session::checkCentralAccess();

if (!\Session::haveRight('dashboard', READ)) {
    http_response_code(403);
    exit;
}

$resolved = ComputersStatistics::resolveComputersScope([
    'scope' => $_GET['scope'] ?? '',
    'counter_key' => $_GET['counter_key'] ?? '',
    'version' => $_GET['version'] ?? '',
    'town' => $_GET['town'] ?? '',
    'entity' => $_GET['entity'] ?? '',
    'type_key' => $_GET['type_key'] ?? '',
    'entity_scope_id' => (int) ($_GET['entity_scope_id'] ?? 0),
    'kb_code' => $_GET['kb_code'] ?? '',
    'kb_dataset' => $_GET['kb_dataset'] ?? '',
    'town_id' => (int) ($_GET['town_id'] ?? 0),
    'entity_id' => (int) ($_GET['entity_id'] ?? 0),
]);

$rows = $resolved['rows'];

CsvResponse::output(new ComputerExport($rows));
