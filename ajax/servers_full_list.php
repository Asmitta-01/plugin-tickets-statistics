<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

use GlpiPlugin\Ticketsstatistics\ServersStatistics;

\Session::checkCentralAccess();

if (!\Session::haveRight('dashboard', READ)) {
    http_response_code(403);
    exit;
}

global $CFG_GLPI;
$counterKey = (string) ($_GET['counter_key'] ?? 'total');
$townId = (int) ($_GET['town_id'] ?? 0);
$entityId = (int) ($_GET['entity_id'] ?? 0);

$resolved = ServersStatistics::resolveServersScope([
    'scope'       => $_GET['scope'] ?? '',
    'counter_key' => $_GET['counter_key'] ?? '',
    'nature_key'  => $_GET['nature_key'] ?? '',
    'model'       => $_GET['model'] ?? '',
    'town_id'     => $townId,
    'entity_id'   => $entityId,
]);

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
        'field'      => $field,
        'searchtype' => $searchtype,
        'value'      => $value,
        'link'       => $link,
    ];
};

if ($entityId > 0) {
    $addCriterion(80, 'under', $entityId);
}

if ($townId > 0) {
    $addCriterion(3, 'equals', $townId);
}

$first = true;
foreach ($resolved['rows'] as $row) {
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }

    $addCriterion(2, 'equals', $id, $first ? 'AND' : 'OR');
    $first = false;
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

$target = ($CFG_GLPI['root_doc'] ?? '') . '/front/computer.php?' . http_build_query($params);
\Html::redirect($target);
