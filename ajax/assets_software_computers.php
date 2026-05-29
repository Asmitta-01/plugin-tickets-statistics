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

header('Content-Type: application/json');

if (!\Session::haveRight('dashboard', READ)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

const TICKETSSTATISTICS_ASSETS_COMPUTERS_MODAL_LIMIT = 200;

function ticketsstatistics_assets_json(array $payload): void
{
    echo json_encode($payload);
    exit;
}

$softwareIds = array_values(array_unique(array_filter(array_map(
    static fn($id): int => (int) $id,
    (array) ($_GET['software'] ?? [])
), static fn(int $id): bool => $id > 0)));
$townId = (int) ($_GET['town'] ?? 0);
$manufacturerId = (int) ($_GET['manufacturer'] ?? 0);
$matchAll = !isset($_GET['match_all']) || (int) $_GET['match_all'] !== 0;
$coverage = (string) ($_GET['coverage'] ?? '');
$coverage = in_array($coverage, ['with', 'without'], true) ? $coverage : '';

if ($softwareIds === [] || $coverage === '') {
    ticketsstatistics_assets_json([
        'title'      => __('Computers', 'ticketsstatistics'),
        'count'      => 0,
        'truncated'  => false,
        'computers'  => [],
    ]);
}

$DB = \DBConnection::getReadConnection();
$computerTable = 'glpi_computers';
$manufacturerTable = 'glpi_manufacturers';
$locationTable = 'glpi_locations';
$stateTable = 'glpi_states';
$userTable = 'glpi_users';

$softwareRows = [];
foreach (
    $DB->request([
        'SELECT' => ['id', 'name'],
        'FROM'   => 'glpi_softwares',
        'WHERE'  => [
            'id'         => $softwareIds,
            'is_deleted' => 0,
        ],
        'ORDER'  => ['name ASC'],
    ]) as $row
) {
    $softwareRows[(int) $row['id']] = (string) ($row['name'] ?? '');
}

if ($softwareRows === []) {
    ticketsstatistics_assets_json([
        'title'      => __('Computers', 'ticketsstatistics'),
        'count'      => 0,
        'truncated'  => false,
        'computers'  => [],
    ]);
}

$softwareIds = array_keys($softwareRows);
$softwareNames = array_values($softwareRows);
$softwareTitle = count($softwareNames) === 1
    ? $softwareNames[0]
    : implode(', ', array_slice($softwareNames, 0, 3)) . (count($softwareNames) > 3 ? '...' : '');

$where = [
    "$computerTable.is_deleted"  => 0,
    "$computerTable.is_template" => 0,
] + getEntitiesRestrictCriteria($computerTable);

if ($townId > 0) {
    $where["$computerTable.locations_id"] = $townId;
}
if ($manufacturerId > 0) {
    $where["$computerTable.manufacturers_id"] = $manufacturerId;
}

$softwareMatchSql = AssetStatistics::getComputerSoftwareMatchSql($computerTable, $softwareIds, $matchAll);

if ($coverage === 'with') {
    $where[] = new \QueryExpression($softwareMatchSql);
} else {
    $where[] = new \QueryExpression('NOT (' . $softwareMatchSql . ')');
}

$title = __('Computers', 'ticketsstatistics') . ' - ' . ($coverage === 'with'
    ? __('Computers with software', 'ticketsstatistics')
    : __('Computers without software', 'ticketsstatistics'));
if ($softwareTitle !== '') {
    $title .= ' - ' . $softwareTitle;
}

$countIterator = $DB->request([
    'COUNT' => 'cpt',
    'FROM'  => $computerTable,
    'WHERE' => $where,
]);
$count = (int) ($countIterator->current()['cpt'] ?? 0);

$joins = [
    $manufacturerTable => [
        'ON' => [
            $manufacturerTable => 'id',
            $computerTable     => 'manufacturers_id',
        ],
    ],
    $locationTable => [
        'ON' => [
            $locationTable => 'id',
            $computerTable => 'locations_id',
        ],
    ],
    $stateTable => [
        'ON' => [
            $stateTable    => 'id',
            $computerTable => 'states_id',
        ],
    ],
    $userTable => [
        'ON' => [
            $userTable     => 'id',
            $computerTable => 'users_id',
        ],
    ],
];

$computers = [];
global $CFG_GLPI;
foreach (
    $DB->request([
        'SELECT'    => [
            "$computerTable.id",
            "$computerTable.name",
            "$computerTable.serial",
            "$computerTable.otherserial",
            "$computerTable.date_mod",
            "$manufacturerTable.name AS manufacturer_name",
            "$locationTable.town AS town_name",
            "$stateTable.name AS state_name",
            "$userTable.firstname AS user_firstname",
            "$userTable.realname AS user_realname",
            "$userTable.name AS user_login",
        ],
        'FROM'      => $computerTable,
        'LEFT JOIN' => $joins,
        'WHERE'     => $where,
        'ORDER'     => ["$computerTable.date_mod DESC", "$computerTable.id DESC"],
        'LIMIT'     => TICKETSSTATISTICS_ASSETS_COMPUTERS_MODAL_LIMIT,
    ]) as $row
) {
    $computerId = (int) ($row['id'] ?? 0);
    $userName = trim((string) ($row['user_firstname'] ?? '') . ' ' . (string) ($row['user_realname'] ?? ''));
    if ($userName === '') {
        $userName = (string) (($row['user_login'] ?? '') ?: __('Unknown', 'ticketsstatistics'));
    }

    $computers[] = [
        'id'               => $computerId,
        'name'             => (string) ($row['name'] ?: sprintf(__('Computer #%d', 'ticketsstatistics'), $computerId)),
        'serial'           => (string) ($row['serial'] ?? ''),
        'inventory_number' => (string) ($row['otherserial'] ?? ''),
        'user'             => $userName,
        'software_items'   => [],
        'manufacturer'     => (string) (($row['manufacturer_name'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
        'town'             => (string) (($row['town_name'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
        'state'            => (string) (($row['state_name'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
        'last_update'      => \Html::convDateTime((string) ($row['date_mod'] ?? '')),
        'url'              => $CFG_GLPI['root_doc'] . '/front/computer.form.php?id=' . $computerId,
    ];
}

if ($computers !== [] && $coverage === 'with') {
    $computerIds = array_map(static fn(array $computer): int => (int) $computer['id'], $computers);
    $softwareByComputer = [];

    foreach (
        $DB->request([
            'SELECT'     => [
                'glpi_items_softwareversions.items_id',
                'glpi_softwares.id AS software_id',
                'glpi_softwares.name AS software_name',
            ],
            'FROM'       => 'glpi_items_softwareversions',
            'INNER JOIN' => [
                'glpi_softwareversions' => [
                    'ON' => [
                        'glpi_softwareversions'       => 'id',
                        'glpi_items_softwareversions' => 'softwareversions_id',
                    ],
                ],
                'glpi_softwares' => [
                    'ON' => [
                        'glpi_softwares'        => 'id',
                        'glpi_softwareversions' => 'softwares_id',
                    ],
                ],
            ],
            'WHERE'      => [
                'glpi_items_softwareversions.itemtype'   => 'Computer',
                'glpi_items_softwareversions.is_deleted' => 0,
                'glpi_items_softwareversions.items_id'   => $computerIds,
                'glpi_softwareversions.softwares_id'     => $softwareIds,
                'glpi_softwares.is_deleted'              => 0,
                'glpi_softwares.is_template'             => 0,
            ],
            'GROUPBY'    => [
                'glpi_items_softwareversions.items_id',
                'glpi_softwares.id',
                'glpi_softwares.name',
            ],
        ]) as $row
    ) {
        $currentComputerId = (int) ($row['items_id'] ?? 0);
        $currentSoftwareId = (int) ($row['software_id'] ?? 0);
        $currentSoftwareName = (string) ($row['software_name'] ?? '');
        if ($currentComputerId <= 0 || $currentSoftwareId <= 0 || $currentSoftwareName === '') {
            continue;
        }

        $softwareByComputer[$currentComputerId][] = [
            'id'   => $currentSoftwareId,
            'name' => $currentSoftwareName,
            'url'  => $CFG_GLPI['root_doc'] . '/front/software.form.php?id=' . $currentSoftwareId,
        ];
    }

    foreach ($computers as &$computer) {
        $computerId = (int) ($computer['id'] ?? 0);
        $computer['software_items'] = $softwareByComputer[$computerId] ?? [];
    }
    unset($computer);
}

ticketsstatistics_assets_json([
    'title'      => $title,
    'count'      => $count,
    'truncated'  => $count > TICKETSSTATISTICS_ASSETS_COMPUTERS_MODAL_LIMIT,
    'limit'      => TICKETSSTATISTICS_ASSETS_COMPUTERS_MODAL_LIMIT,
    'computers'  => $computers,
]);
