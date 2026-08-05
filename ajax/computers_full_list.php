<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

use GlpiPlugin\Ticketsstatistics\ComputersStatistics;

\Session::checkCentralAccess();

if (!\Session::haveRight('dashboard', READ)) {
    http_response_code(403);
    exit;
}

global $CFG_GLPI;
$scope = (string) ($_GET['scope'] ?? '');
$counterKey = (string) ($_GET['counter_key'] ?? '');
$version = trim((string) ($_GET['version'] ?? ''));
$townId = (int) ($_GET['town_id'] ?? 0);
$entityId = (int) ($_GET['entity_id'] ?? 0);

$criteria = [];
$addCriterion = static function (int $field, string $searchtype, string|int $value, string $link = 'AND') use (&$criteria): void {
    if ((string) $value === '') {
        return;
    }

    $criteria[] = [
        'field' => $field,
        'searchtype' => $searchtype,
        'value' => $value,
        'link' => $link,
    ];
};

if ($entityId > 0) {
    // Entity field in Computer search options (tree-compatible search).
    $addCriterion(80, 'under', $entityId);
}

if ($townId > 0) {
    // Location field in Computer search options.
    $addCriterion(3, 'equals', $townId);
}

if ($scope === 'counter') {
    if ($counterKey === 'windows') {
        $addCriterion(45, 'contains', 'Microsoft Windows 11');
    } elseif ($counterKey === 'latest_version' || $counterKey === 'to_update') {
        $latestVersion = ComputersStatistics::getLatestWindowsVersion(
            ComputersStatistics::getLatestWindowsComputers($townId, $entityId)
        );
        $addCriterion(45, 'contains', 'Microsoft Windows 11');
        if ($latestVersion !== '') {
            // Get OperatingSystemVersion ID for the latest version to filter by.
            $osVersionId = ComputersStatistics::getOperatingSystemIDByName($latestVersion);
            $addCriterion(46, $counterKey === 'to_update' ? 'notequals' : 'equals', $osVersionId);
        }
    } elseif ($counterKey === 'kb_total') {
        // This counter opens a KB summary modal; full list does not apply directly.
        $addCriterion(2, 'equals', -1);
    }
} elseif ($scope === 'version' || $scope === 'town_version') {
    $addCriterion(45, 'contains', 'Microsoft Windows 11');
    $osVersionId = ComputersStatistics::getOperatingSystemIDByName($version);
    $addCriterion(46, 'equals', $osVersionId);
    if ($scope === 'town_version' && $townId == 0) {
        $town = $_GET['town'] ?? '';
        if ($town !== '') {
            $townId = ComputersStatistics::getTownIdByName($town);
            $addCriterion(3, 'equals', $townId);
        }
    }
} elseif ($scope === 'kb') {
    // Non pris en compte par GLPI 
}

if ($criteria === []) {
    $addCriterion(2, 'equals', -1);
}

$params = [];

foreach ($criteria as $i => $criterion) {
    $params[sprintf('criteria[%d][field]', (int) $i)] = (string) $criterion['field'];
    $params[sprintf('criteria[%d][searchtype]', (int) $i)] = (string) $criterion['searchtype'];
    $params[sprintf('criteria[%d][value]', (int) $i)] = (string) $criterion['value'];
    $params[sprintf('criteria[%d][link]', (int) $i)] = (string) $criterion['link'];
}

$target = $CFG_GLPI['root_doc'] . '/front/computer.php?' . http_build_query($params);
\Html::redirect($target);
