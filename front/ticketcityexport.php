<?php

include('../../../inc/includes.php');

use Glpi\Csv\CsvResponse;
use GlpiPlugin\Ticketsstatistics\PeriodFilter;
use GlpiPlugin\Ticketsstatistics\TicketCityExport;

\Session::checkLoginUser();

$city = $_GET['city'] ?? '';
if (!$city) {
    http_response_code(404);
    exit;
}

global $DB;

$table    = 'glpi_tickets';
$catTable = 'glpi_itilcategories';
$locTable = 'glpi_locations';
$userTable = 'glpi_users';

// Filtre période — réutilise la même logique que data.php
$where = ["$table.is_deleted" => 0] + getEntitiesRestrictCriteria($table);
$period   = $_GET['period']    ?? 'last30';
$dateFrom = $_GET['date_from'] ?? null;
$dateTo   = $_GET['date_to']   ?? null;

PeriodFilter::apply($where, $table, $period, $dateFrom, $dateTo);

// Filtre ville
$where["$locTable.town"] = $city;

$iterator = $DB->request([
    'SELECT'    => [
        "$table.id",
        "$table.name",
        "$table.status",
        "$table.priority",
        "$table.date",
        "$table.date_mod",
        "$table.solvedate",
        "$table.closedate",
        "$catTable.completename AS category",
        "$locTable.town AS town",
        "$userTable.name AS assigned_to",
    ],
    'FROM'      => $table,
    'LEFT JOIN' => [
        $catTable => ['ON' => [$catTable => 'id', $table => 'itilcategories_id']],
        $locTable => ['ON' => [$locTable => 'id', $table => 'locations_id']],
        'glpi_tickets_users AS tu' => [
            'ON' => [
                'tu' => 'tickets_id',
                $table => 'id',
                [
                    'AND' => ['tu.type' => \CommonITILActor::ASSIGN], // type 2 = assigné
                ]
            ]
        ],
        $userTable => ['ON' => [$userTable => 'id', 'tu' => 'users_id']],
    ],
    'WHERE' => $where,
    'ORDER' => ["$table.date DESC"],
]);

$tickets = iterator_to_array($iterator);

CsvResponse::output(new TicketCityExport($tickets, $city));
