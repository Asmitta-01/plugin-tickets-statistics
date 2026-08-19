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
$town = trim((string) ($_GET['town'] ?? ''));
$typeKey = trim((string) ($_GET['type_key'] ?? ''));
$entityScopeId = (int) ($_GET['entity_scope_id'] ?? 0);
$entityScopeName = trim((string) ($_GET['entity'] ?? ''));
$townId = (int) ($_GET['town_id'] ?? 0);
$entityId = (int) ($_GET['entity_id'] ?? 0);

$criteria = [];
$addCriterion = static function (int|array $field, string $searchtype = '', string|int $value = 0, string $link = 'AND') use (&$criteria): void {
    if (is_array($field)) {
        $criteria[] = $field;
        return;
    }

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

if ($entityId > 0 && !($scope === 'counter' && $counterKey === 'obsolete') && $scope !== 'town_type') {
    // Entity field in Computer search options (tree-compatible search).
    $addCriterion(80, 'under', $entityId);
}

if ($townId > 0 && !($scope === 'counter' && $counterKey === 'obsolete') && $scope !== 'town_type') {
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
    } elseif ($counterKey === 'obsolete') {
        $resolved = ComputersStatistics::resolveComputersScope([
            'scope' => $scope,
            'counter_key' => $counterKey,
            'version' => $version,
            'town' => $town,
            'entity' => $entityScopeName,
            'type_key' => $typeKey,
            'entity_scope_id' => $entityScopeId,
            'kb_code' => $_GET['kb_code'] ?? '',
            'kb_dataset' => $_GET['kb_dataset'] ?? '',
            'town_id' => $townId,
            'entity_id' => $entityId,
        ]);

        $first = true;
        foreach ($resolved['rows'] as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $addCriterion(2, 'equals', $id, $first ? 'AND' : 'OR');
            $first = false;
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
} elseif ($scope === 'town_type') {
    $townId = ComputersStatistics::getTownIdByName($town);
    $addCriterion(3, 'equals', $townId);
    $addCriterion(23, 'notequals', 0); // Fabricant non nul

    $computerTypeIds = ComputersStatistics::getComputerTypeIdsByKey($typeKey);
    if (count($computerTypeIds) === 1) {
        $addCriterion(4, 'equals', $computerTypeIds[0]);
    } else {
        $groupCriteria = ['link' => 'AND', 'criteria' => []];
        foreach ($computerTypeIds as $i => $computerTypeId) {
            $groupCriteria['criteria'][] = [
                'field' => 4,
                'searchtype' => 'equals',
                'value' => $computerTypeId,
                'link' => $i === 0 ? 'AND' : 'OR',
            ];
        }
        $addCriterion($groupCriteria);
    }
} elseif ($scope === 'entity_version') {
    $addCriterion(45, 'contains', 'Microsoft Windows 11');
    $osVersionId = ComputersStatistics::getOperatingSystemIDByName($version);
    $addCriterion(46, 'equals', $osVersionId);

    if ($entityScopeId <= 0 && $entityScopeName !== '') {
        $entityScopeId = ComputersStatistics::getEntityIdByCompleteName($entityScopeName);
    }

    if ($entityScopeId > 0) {
        $addCriterion(80, 'equals', $entityScopeId);
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

    if (isset($criterion['criteria']) && is_array($criterion['criteria'])) {
        foreach ($criterion['criteria'] as $j => $subCriterion) {
            $params[sprintf('criteria[%d][criteria][%d][field]', (int) $i, (int) $j)] = (string) $subCriterion['field'];
            $params[sprintf('criteria[%d][criteria][%d][searchtype]', (int) $i, (int) $j)] = (string) $subCriterion['searchtype'];
            $params[sprintf('criteria[%d][criteria][%d][value]', (int) $i, (int) $j)] = (string) $subCriterion['value'];
            $params[sprintf('criteria[%d][criteria][%d][link]', (int) $i, (int) $j)] = (string) $subCriterion['link'];
        }
    }
}

$target = $CFG_GLPI['root_doc'] . '/front/computer.php?' . http_build_query($params);
\Html::redirect($target);
