<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

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

$softwareId = (int) ($_GET['software'] ?? 0);
$townId = (int) ($_GET['town'] ?? 0);
$manufacturerId = (int) ($_GET['manufacturer'] ?? 0);
$coverage = (string) ($_GET['coverage'] ?? '');
$coverage = in_array($coverage, ['with', 'without'], true) ? $coverage : '';

if ($softwareId <= 0 || $coverage === '') {
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

$softwareName = '';
$softwareRow = $DB->request([
    'SELECT' => ['name'],
    'FROM'   => 'glpi_softwares',
    'WHERE'  => [
        'id'         => $softwareId,
        'is_deleted' => 0,
    ],
])->current();
if ($softwareRow) {
    $softwareName = (string) ($softwareRow['name'] ?? '');
}

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

$softwareExistsSql = sprintf(
    "EXISTS (SELECT 1 FROM glpi_items_softwareversions isv INNER JOIN glpi_softwareversions sv ON sv.id = isv.softwareversions_id WHERE isv.items_id = %s.id AND isv.itemtype = 'Computer' AND isv.is_deleted = 0 AND sv.softwares_id = %d)",
    $computerTable,
    $softwareId
);

if ($coverage === 'with') {
    $where[] = new \QueryExpression($softwareExistsSql);
} else {
    $where[] = new \QueryExpression('NOT (' . $softwareExistsSql . ')');
}

$title = __('Computers', 'ticketsstatistics') . ' - ' . ($coverage === 'with'
    ? __('Computers with software', 'ticketsstatistics')
    : __('Computers without software', 'ticketsstatistics'));
if ($softwareName !== '') {
    $title .= ' - ' . $softwareName;
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
        'software_name'    => $softwareName,
        'software_url'     => $CFG_GLPI['root_doc'] . '/front/software.form.php?id=' . $softwareId,
        'manufacturer'     => (string) (($row['manufacturer_name'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
        'town'             => (string) (($row['town_name'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
        'state'            => (string) (($row['state_name'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
        'last_update'      => \Html::convDateTime((string) ($row['date_mod'] ?? '')),
        'url'              => $CFG_GLPI['root_doc'] . '/front/computer.form.php?id=' . $computerId,
    ];
}

ticketsstatistics_assets_json([
    'title'      => $title,
    'count'      => $count,
    'truncated'  => $count > TICKETSSTATISTICS_ASSETS_COMPUTERS_MODAL_LIMIT,
    'limit'      => TICKETSSTATISTICS_ASSETS_COMPUTERS_MODAL_LIMIT,
    'computers'  => $computers,
]);
