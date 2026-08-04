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

const TICKETSSTATISTICS_MODAL_LIMIT = 100;


function ticketsstatistics_get_status_groups(): array
{
    return [
        'new'           => [\Ticket::INCOMING],
        'incoming'      => [\Ticket::INCOMING],
        'assigned'      => [\Ticket::ASSIGNED],
        'waiting'       => [\Ticket::WAITING],
        'missc'         => [\Ticket::INCOMING, \Ticket::ASSIGNED, \Ticket::WAITING, \Ticket::SOLVED, \Ticket::CLOSED], // Statuses of tickets sent to MISSC
        'solved_closed' => [\Ticket::SOLVED, \Ticket::CLOSED],
        'resolved'      => [\Ticket::SOLVED, \Ticket::CLOSED],
        'in_progress'   => [\Ticket::ASSIGNED, \Ticket::WAITING, \Ticket::ACCEPTED, \Ticket::OBSERVED],
    ];
}

function ticketsstatistics_resolve_priority(?string $label): ?int
{
    if ($label === null || $label === '') {
        return null;
    }

    for ($priority = 0; $priority <= 6; $priority++) {
        if (\Ticket::getPriorityName($priority) === $label) {
            return $priority;
        }
    }

    return null;
}

function ticketsstatistics_build_title(string $type, string $label, string $status_group): string
{
    $parts = [__('Tickets', 'ticketsstatistics')];

    switch ($type) {
        case 'priority':
            $parts[] = __('Priority', 'ticketsstatistics');
            break;
        case 'category':
            $parts[] = __('Category', 'ticketsstatistics');
            break;
        case 'city':
            $parts[] = __('Town', 'ticketsstatistics');
            break;
        case 'perday':
            $parts[] = __('Date', 'ticketsstatistics');
            break;
    }

    if ($label !== '') {
        $parts[] = $label;
    }

    if ($status_group !== '' && $type !== 'counter') {
        $parts[] = match ($status_group) {
            'new', 'incoming' => __('New', 'ticketsstatistics'),
            'assigned' => __('Assigned', 'ticketsstatistics'),
            'waiting' => __('Pending', 'ticketsstatistics'),
            'resolved', 'solved_closed' => __('Resolved', 'ticketsstatistics'),
            'in_progress' => __('In progress', 'ticketsstatistics'),
            default => $status_group,
        };
    }

    return implode(' - ', $parts);
}

function ticketsstatistics_json(array $payload): void
{
    echo json_encode($payload);
    exit;
}

$DB = \DBConnection::getReadConnection();
$table = \Ticket::getTable();
$cat_table = 'glpi_itilcategories';
$loc_table = 'glpi_locations';
$type = (string) ($_GET['type'] ?? '');
$rawLabel = (string) ($_GET['label'] ?? '');
$label = $type === 'category' ? $rawLabel : trim($rawLabel);
$clickedCategoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : null;
$status_group = (string) ($_GET['status_group'] ?? '');
$openStatusesGlobal = !isset($_GET['open_statuses_global']) || (int) $_GET['open_statuses_global'] === 1;

$where = ["$table.is_deleted" => 0] + getEntitiesRestrictCriteria($table);
$period = $_GET['period'] ?? 'thismonth';
$categoryId = (int) ($_GET['category'] ?? 0);
$dateFrom = $_GET['date_from'] ?? null;
$dateTo   = $_GET['date_to']   ?? null;

$isOpenStatusCounter = $type === 'counter' && in_array($status_group, ['new', 'incoming', 'assigned', 'waiting'], true);
if (!$openStatusesGlobal || !$isOpenStatusCounter) {
    \GlpiPlugin\Ticketsstatistics\PeriodFilter::apply($where, $table, $period, $dateFrom, $dateTo);
}
\GlpiPlugin\Ticketsstatistics\CategoryFilter::apply($where, $table, $categoryId);

$joins = [
    $cat_table => ['ON' => [$cat_table => 'id', $table => 'itilcategories_id']],
    $loc_table => ['ON' => [$loc_table => 'id', $table => 'locations_id']],
];

switch ($type) {
    case 'priority':
        $priority = ticketsstatistics_resolve_priority($label);
        if ($priority === null) {
            ticketsstatistics_json([
                'title' => ticketsstatistics_build_title($type, $label, $status_group),
                'count' => 0,
                'truncated' => false,
                'tickets' => [],
            ]);
        }
        $where["$table.priority"] = $priority;
        break;

    case 'category':
        if ($clickedCategoryId !== null) {
            if ($clickedCategoryId > 0) {
                $where["$table.itilcategories_id"] = $clickedCategoryId;
            } else {
                $where[] = [
                    'OR' => [
                        ["$table.itilcategories_id" => 0],
                        ["$cat_table.id" => null],
                    ],
                ];
            }
        } elseif ($label === __('None')) {
            $where[] = [
                'OR' => [
                    ["$table.itilcategories_id" => 0],
                    ["$cat_table.id" => null],
                ],
            ];
        } else {
            $where["$cat_table.completename"] = $label;
        }
        break;

    case 'city':
        if ($label === __('Unknown', 'ticketsstatistics')) {
            $where[] = [
                'OR' => [
                    ["$loc_table.town" => null],
                    ["$loc_table.town" => ''],
                ],
            ];
        } else {
            $where["$loc_table.town"] = $label;
        }
        break;

    case 'perday-opened':
        if (\DateTime::createFromFormat('Y-m-d', $label) !== false) {
            $where[] = new \QueryExpression("DATE($table.`date`) = " . $DB->quoteValue($label));
        }
        break;
    case 'perday-closed':
        $where[] = ["$table.status" => [\Ticket::SOLVED, \Ticket::CLOSED]];
        if (\DateTime::createFromFormat('Y-m-d', $label) !== false) {
            $where[] = new \QueryExpression("DATE($table.`closedate`) = " . $DB->quoteValue($label));
        }
        break;

    case 'counter':
        if ($status_group === 'missc') {
            $misscTable = 'glpi_plugin_cfaomobility_misscs';
            if ($DB->tableExists($misscTable)) {
                $joins[$misscTable] = ['ON' => [$misscTable => 'tickets_id', $table => 'id']];
                $where["$misscTable.missc_number"] = ['<>', ''];
            }
            break;
        }
        break;
    case 'missc':
        $misscTable = 'glpi_plugin_cfaomobility_misscs';
        if ($DB->tableExists($misscTable)) {
            $joins[$misscTable] = ['ON' => [$misscTable => 'tickets_id', $table => 'id']];
            $where["$misscTable.missc_number"] = ['<>', ''];
            break;
        }

    default:
        ticketsstatistics_json([
            'title' => __('Tickets', 'ticketsstatistics'),
            'count' => 0,
            'truncated' => false,
            'tickets' => [],
        ]);
}

if (isset(ticketsstatistics_get_status_groups()[$status_group])) {
    $where["$table.status"] = ticketsstatistics_get_status_groups()[$status_group];
}

$count_iterator = $DB->request([
    'COUNT' => 'cpt',
    'FROM' => $table,
    'LEFT JOIN' => $joins,
    'WHERE' => $where,
]);

$count = (int) ($count_iterator->current()['cpt'] ?? 0);
$tickets = [];
global $CFG_GLPI;

foreach (
    $DB->request([
        'SELECT' => [
            "$table.id",
            "$table.name",
            "$table.status",
            "$table.date",
            "$table.date_mod",
            "$table.closedate",
            "$cat_table.completename AS category_name",
            "$loc_table.town AS town_name",
        ],
        'FROM' => $table,
        'LEFT JOIN' => $joins,
        'WHERE' => $where,
        'ORDER' => ["$table.date DESC"],
        'LIMIT' => TICKETSSTATISTICS_MODAL_LIMIT,
    ]) as $row
) {
    $ticket_id = (int) $row['id'];
    $tickets[] = [
        'id' => $ticket_id,
        'name' => $row['name'] ?: sprintf(__('Ticket #%d', 'ticketsstatistics'), $ticket_id),
        'status' => \Ticket::getStatus((int) $row['status']),
        'creation' => \Html::convDateTime($row['date']),
        'last_update' => \Html::convDateTime($row['date_mod']),
        'closed' => \Html::convDateTime($row['closedate']),
        'category' => $row['category_name'] ?: __('None'),
        'town' => $row['town_name'] ?: __('Unknown', 'ticketsstatistics'),
        'url' => $CFG_GLPI['root_doc'] . '/front/ticket.form.php?id=' . $ticket_id,
    ];
}

ticketsstatistics_json([
    'title' => ticketsstatistics_build_title($type, $label, $status_group),
    'count' => $count,
    'truncated' => $count > TICKETSSTATISTICS_MODAL_LIMIT,
    'tickets' => $tickets,
]);
