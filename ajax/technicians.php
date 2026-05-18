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

$DB    = \DBConnection::getReadConnection();
$table = \Ticket::getTable();
$where = ["$table.is_deleted" => 0] + getEntitiesRestrictCriteria($table);

// Get the period and category filter from the request
$period = $_GET['period'] ?? 'last30';
$categoryId = (int) ($_GET['category'] ?? 0);

$dateFrom = $_GET['date_from'] ?? null;
$dateTo   = $_GET['date_to']   ?? null;

\GlpiPlugin\Ticketsstatistics\PeriodFilter::apply($where, $table, $period, $dateFrom, $dateTo);
\GlpiPlugin\Ticketsstatistics\CategoryFilter::apply($where, $table, $categoryId);

// Status groups for categorization
$resolvedStatuses = [\Ticket::SOLVED, \Ticket::CLOSED];
$inProgressStatuses = [\Ticket::ASSIGNED, \Ticket::INCOMING];

$tuTable = 'glpi_tickets_users';
$usersTable = 'glpi_users';

// Fetch all technicians with their tickets
$techniciansData = [];

foreach (
    $DB->request([
        'SELECT'    => [
            "$usersTable.id AS user_id",
            "$usersTable.name",
            "$usersTable.firstname",
            "$usersTable.realname",
            'COUNT DISTINCT' => ["$table.id AS total_tickets"],
        ],
        'FROM'      => $table,
        'INNER JOIN' => [
            $tuTable => [
                'ON' => [
                    $tuTable => 'tickets_id',
                    $table => 'id',
                ]
            ],
            $usersTable => [
                'ON' => [
                    $usersTable => 'id',
                    $tuTable => 'users_id',
                ]
            ]
        ],
        'WHERE'     => $where + ["$tuTable.type" => \CommonITILActor::ASSIGN],
        'GROUPBY'   => "$usersTable.id",
    ]) as $row
) {
    $userId = (int) $row['user_id'];
    $userName = $row['firstname'] ? trim($row['firstname'] . ' ' . $row['realname']) : ($row['name'] ?? '');

    $techniciansData[$userId] = [
        'name'         => $userName,
        'total'        => (int) $row['total_tickets'],
        'resolved'     => 0,
        'in_progress'  => 0,
        'waiting'      => 0,
        'resolution_time_sum' => 0,
        'resolution_time_count' => 0,
        'assign_time_sum' => 0,
        'assign_time_count' => 0,
    ];
}

// Get status breakdown per technician
if (count($techniciansData)) {
    $userIds = array_keys($techniciansData);

    foreach (
        $DB->request([
            'SELECT'    => [
                "$usersTable.id AS user_id",
                "$table.status AS status",
                'COUNT' => ["$table.id AS cpt"],
            ],
            'FROM'      => $table,
            'INNER JOIN' => [
                $tuTable => [
                    'ON' => [
                        $tuTable => 'tickets_id',
                        $table => 'id',
                    ]
                ],
                $usersTable => [
                    'ON' => [
                        $usersTable => 'id',
                        $tuTable => 'users_id',
                    ]
                ]
            ],
            'WHERE'     => $where + ["$tuTable.type" => \CommonITILActor::ASSIGN, "$usersTable.id" => $userIds],
            'GROUPBY'   => ["$usersTable.id", "$table.status"],
        ]) as $row
    ) {
        $userId = (int) $row['user_id'];
        $status = (int) $row['status'];
        $count = (int) $row['cpt'];

        // Determine status group
        if (in_array($status, $resolvedStatuses, true)) {
            $group = 'resolved';
        } elseif (in_array($status, $inProgressStatuses, true)) {
            $group = 'in_progress';
        } else {
            $group = 'waiting';
        }

        if (isset($techniciansData[$userId])) {
            $techniciansData[$userId][$group] += $count;
        }
    }
}

// Get resolution time per technician
if (count($techniciansData)) {
    $userIds = array_keys($techniciansData);

    foreach (
        $DB->request([
            'SELECT'    => [
                "$usersTable.id AS user_id",
                "$table.solve_delay_stat",
                "$table.close_delay_stat",
            ],
            'FROM'      => $table,
            'INNER JOIN' => [
                $tuTable => [
                    'ON' => [
                        $tuTable => 'tickets_id',
                        $table => 'id',
                    ]
                ],
                $usersTable => [
                    'ON' => [
                        $usersTable => 'id',
                        $tuTable => 'users_id',
                    ]
                ]
            ],
            'WHERE'     => $where + [
                "$tuTable.type" => \CommonITILActor::ASSIGN,
                "$usersTable.id" => $userIds,
                new \QueryExpression("($table.`solve_delay_stat` != 0 OR $table.`close_delay_stat` != 0)"),
            ],
        ]) as $row
    ) {
        $userId = (int) $row['user_id'];
        $seconds = (int) $row['solve_delay_stat'] ?: (int) $row['close_delay_stat'];

        if ($seconds > 0 && isset($techniciansData[$userId])) {
            $hours = round($seconds / 3600, 2);
            $techniciansData[$userId]['resolution_time_sum'] += $hours;
            $techniciansData[$userId]['resolution_time_count']++;
        }
    }
}

// Get average time to first assignment per technician
// Note: glpi_tickets_users may not have explicit date_assignment field in all GLPI versions
// Using simple assignment count instead
if (count($techniciansData)) {
    $userIds = array_keys($techniciansData);

    foreach ($techniciansData as $userId => &$data) {
        // For now, we set a default value for avg_assign_time (future enhancement)
        $data['avg_assign_time'] = 0;
    }
    unset($data);
}

// Calculate metrics
$result = [
    'technicians' => [],
    'charts' => [
        'status_by_tech' => [
            'labels' => [],
            'datasets' => [
                [
                    'label' => __('Resolved', 'ticketsstatistics'),
                    'data' => [],
                    'backgroundColor' => '#C00000',
                ],
                [
                    'label' => __('In Progress', 'ticketsstatistics'),
                    'data' => [],
                    'backgroundColor' => '#49bf4d',
                ],
                [
                    'label' => __('Waiting', 'ticketsstatistics'),
                    'data' => [],
                    'backgroundColor' => '#ffa500',
                ],
            ],
        ],
        'avg_resolution_time' => [
            'labels' => [],
            'data' => [],
        ],
        'resolution_rate' => [
            'labels' => [],
            'data' => [],
        ],
    ],
];

// Sort technicians by name
uasort($techniciansData, fn($a, $b) => strcmp($a['name'], $b['name']));

foreach ($techniciansData as $userId => $data) {
    $avgResolutionTime = $data['resolution_time_count'] > 0
        ? round($data['resolution_time_sum'] / $data['resolution_time_count'], 2)
        : 0;

    $resolutionRate = $data['total'] > 0
        ? round(($data['resolved'] / $data['total']) * 100, 2)
        : 0;

    $avgAssignTime = $data['avg_assign_time'] ?? 0;

    $result['technicians'][] = [
        'user_id'             => $userId,
        'name'                => $data['name'],
        'total'               => $data['total'],
        'resolved'            => $data['resolved'],
        'in_progress'         => $data['in_progress'],
        'waiting'             => $data['waiting'],
        'avg_resolution_time' => $avgResolutionTime,
        'resolution_rate'     => $resolutionRate,
        'avg_assign_time'     => $avgAssignTime,
    ];

    // Add to charts
    $result['charts']['status_by_tech']['labels'][] = $data['name'];
    $result['charts']['status_by_tech']['datasets'][0]['data'][] = $data['resolved'];
    $result['charts']['status_by_tech']['datasets'][1]['data'][] = $data['in_progress'];
    $result['charts']['status_by_tech']['datasets'][2]['data'][] = $data['waiting'];

    $result['charts']['avg_resolution_time']['labels'][] = $data['name'];
    $result['charts']['avg_resolution_time']['data'][] = $avgResolutionTime;

    $result['charts']['resolution_rate']['labels'][] = $data['name'];
    $result['charts']['resolution_rate']['data'][] = $resolutionRate;
}

echo json_encode($result);
