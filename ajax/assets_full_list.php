<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

use GlpiPlugin\Ticketsstatistics\AssetStatistics;

\Session::checkCentralAccess();

if (!\Session::haveRight('dashboard', READ)) {
    http_response_code(403);
    exit;
}

global $CFG_GLPI;
$counterKey = (string) ($_GET['counter_key'] ?? 'total');
$townId = (int) ($_GET['town_id'] ?? 0);
$manufacturerId = (int) ($_GET['manufacturer_id'] ?? 0);

$script = match ($counterKey) {
    'monitors'  => 'monitor.php?reset=reset',
    'printers'  => 'printer.php?reset=reset',
    'switches', 'firewalls' => 'networkequipment.php',
    default     => 'computer.php',
};

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

if ($townId > 0) {
    $addCriterion(3, 'equals', $townId);
}

if ($manufacturerId > 0) {
    $addCriterion(4, 'equals', $manufacturerId);
}

switch ($counterKey) {
    case 'monitors':
    case 'printers':
    case 'total':
        break;
    case 'switches':
        $addCriterion(4, 'contains', 'switch');
        $addCriterion(1, 'contains', 'switch', 'OR');
        break;
    case 'firewalls':
        $addCriterion(4, 'contains', 'firewall');
        $addCriterion(4, 'contains', 'pare-feu', 'OR');
        $addCriterion(1, 'contains', 'firewall', 'OR');
        $addCriterion(1, 'contains', 'pare-feu', 'OR');
        break;
    case 'laptops':
    case 'desktops':
    case 'servers':
        $typeKey = rtrim($counterKey, 's'); // 'laptops' -> 'laptop', etc.
        $computerTypeIds = \GlpiPlugin\Ticketsstatistics\ComputersStatistics::getComputerTypeIdsByKey($typeKey);
        
        if ($counterKey === 'servers') {
            $vmwareIds = \GlpiPlugin\Ticketsstatistics\ComputersStatistics::getComputerTypeIdsByKey('vmware');
            $computerTypeIds = array_values(array_unique(array_merge($computerTypeIds, $vmwareIds)));
        }
        
        if (count($computerTypeIds) === 1) {
            $addCriterion(4, 'equals', $computerTypeIds[0]);
        } elseif (count($computerTypeIds) > 1) {
            $groupCriteria = ['link' => 'AND', 'criteria' => []];
            foreach ($computerTypeIds as $i => $computerTypeId) {
                $groupCriteria['criteria'][] = [
                    'field'      => 4,
                    'searchtype' => 'equals',
                    'value'      => $computerTypeId,
                    'link'       => $i === 0 ? 'AND' : 'OR',
                ];
            }
            $addCriterion($groupCriteria);
        } else {
            // No type found for this category, return empty result
            $addCriterion(2, 'equals', -1);
        }
        break;
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

$target = ($CFG_GLPI['root_doc'] ?? '') . '/front/' . $script . '?' . http_build_query($params);
\Html::redirect($target);
