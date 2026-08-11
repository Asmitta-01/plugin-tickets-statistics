<?php

use GlpiPlugin\Ticketsstatistics\TicketsStatistics;

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

$misscTable = 'glpi_plugin_cfaomobility_misscs';
$includeMissc = $DB->tableExists($misscTable) && \Plugin::isPluginActive('cfaomobility');

// Get the period and category filter from the request
$period = $_GET['period'] ?? 'thismonth';
$categoryId = (int) ($_GET['category'] ?? 0);

$dateFrom = $_GET['date_from'] ?? null;
$dateTo   = $_GET['date_to']   ?? null;
$openStatusesGlobal = !isset($_GET['open_statuses_global']) || (int) $_GET['open_statuses_global'] === 1;

$isValidCustomDate = static function (?string $date): bool {
    if (!is_string($date)) {
        return false;
    }

    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    return $dt !== false && $dt->format('Y-m-d') === $date;
};

\GlpiPlugin\Ticketsstatistics\PeriodFilter::apply($where, $table, $period, $dateFrom, $dateTo);
\GlpiPlugin\Ticketsstatistics\CategoryFilter::apply($where, $table, $categoryId);

// --- Counters by status ---
$counters = [];
foreach (
    [
        'incoming' => \Ticket::INCOMING,
        'assigned' => \Ticket::ASSIGNED,
        'waiting'  => \Ticket::WAITING,
        'solved'   => \Ticket::SOLVED,
        'closed'   => \Ticket::CLOSED,
    ] as $key => $status
) {
    $iter = $DB->request([
        'COUNT' => 'cpt',
        'FROM'  => $table,
        'WHERE' => $where + ["$table.status" => $status],
    ]);
    $counters[$key] = (int) $iter->current()['cpt'];
}
$totalIter = $DB->request([
    'COUNT' => 'cpt',
    'FROM'  => $table,
    'WHERE' => $where,
]);
$counters['total'] = (int) $totalIter->current()['cpt'];
$counters['solved_closed'] = $counters['solved'] + $counters['closed'];

if ($openStatusesGlobal) {
    $globalWhere = ["$table.is_deleted" => 0] + getEntitiesRestrictCriteria($table);
    \GlpiPlugin\Ticketsstatistics\CategoryFilter::apply($globalWhere, $table, $categoryId);

    foreach (
        [
            'incoming' => \Ticket::INCOMING,
            'assigned' => \Ticket::ASSIGNED,
            'waiting'  => \Ticket::WAITING,
        ] as $key => $status
    ) {
        $iter = $DB->request([
            'COUNT' => 'cpt',
            'FROM'  => $table,
            'WHERE' => $globalWhere + ["$table.status" => $status],
        ]);
        $counters[$key] = (int) $iter->current()['cpt'];
    }
}

if ($includeMissc) {
    $iter = $DB->request([
        'COUNT' => 'cpt',
        'FROM'  => $table,
        'LEFT JOIN' => [
            $misscTable => ['ON' => [$misscTable => 'tickets_id', $table => 'id']]
        ],
        'WHERE' => $where + [
            "NOT" => ["$misscTable.missc_number" => null],
            "$misscTable.missc_number" => ['<>', '']
        ],
    ]);
    $counters['missc'] = (int) $iter->current()['cpt'];
}

if ($includeMissc) {
    // Total tickets with a missc number + breakdown by status
    $misscs = [
        'labels' => ['new', 'in_progress', 'resolved'],
        'values' => [0, 0, 0],
    ];
    foreach (
        $DB->request([
            'SELECT' => [
                "$table.status",
                'COUNT DISTINCT'  => "$table.id AS cpt",
            ],
            'FROM'  => $table,
            'LEFT JOIN' => [
                $misscTable => ['ON' => [$misscTable => 'tickets_id', $table => 'id']]
            ],
            'WHERE' => $where + [
                "NOT" => ["$misscTable.missc_number" => null],
                "$misscTable.missc_number" => ['<>', '']
            ],
            'GROUPBY' => "$table.status",
        ]) as $row
    ) {
        $status = (int) $row['status'];
        $count  = (int) $row['cpt'];
        $label = 'in_progress';
        if (in_array($status, [\Ticket::INCOMING], true)) {
            $label = 'new';
        } elseif (in_array($status, [\Ticket::SOLVED, \Ticket::CLOSED], true)) {
            $label = 'resolved';
        }

        $labelIndex = array_search($label, $misscs['labels']);
        $misscs['values'][$labelIndex] += $count;
    }
}

// --- By priority ---
$priority = ['labels' => [], 'values' => []];
foreach (
    $DB->request([
        'SELECT'  => ['COUNT DISTINCT' => "$table.id AS cpt", "$table.priority"],
        'FROM'    => $table,
        'WHERE'   => $where,
        'GROUPBY' => "$table.priority",
        'ORDER'   => "$table.priority ASC",
    ]) as $row
) {
    $priority['labels'][] = \Ticket::getPriorityName($row['priority']);
    $priority['values'][] = (int) $row['cpt'];
}

// --- By category (top 10) ---
$category = ['labels' => [], 'ids' => [], 'values' => []];
$categoryStats = [];
$catTable = 'glpi_itilcategories';

// Regroupement des statuts en 3 groupes
$statusGroups = [
    'new'        => [\Ticket::INCOMING],
    'resolved'   => [\Ticket::SOLVED, \Ticket::CLOSED],
    'in_progress' => [\Ticket::ASSIGNED, \Ticket::WAITING, \Ticket::ACCEPTED, \Ticket::OBSERVED],
];

foreach (
    $DB->request([
        'SELECT'    => [
            "$catTable.id AS cat_id",
            "$catTable.completename AS cat_name",
            "$table.status AS status",
            'COUNT'  => ["$table.id AS cpt"],
        ],
        'FROM'      => $table,
        'LEFT JOIN' => [
            $catTable => ['ON' => [$catTable => 'id', $table => 'itilcategories_id']]
        ],
        'WHERE'     => $where,
        'GROUPBY'   => ["$catTable.id", "$table.status"],
        'ORDER'     => ['cpt DESC'],
    ]) as $row
) {
    $catId = (int) ($row['cat_id'] ?? 0);
    $catName = (string) ($row['cat_name'] ?? '');
    if ($catName === '') {
        $catName = __('None');
    }

    $catKey = $catId > 0 ? 'id_' . $catId : 'none';
    $status  = (int) $row['status'];
    $count   = (int) $row['cpt'];

    // Résout le groupe
    $group = 'in_progress'; // fallback
    foreach ($statusGroups as $groupName => $statuses) {
        if (in_array($status, $statuses, true)) {
            $group = $groupName;
            break;
        }
    }

    if (!isset($categoryStats[$catKey])) {
        $categoryStats[$catKey] = [
            'id'          => $catId > 0 ? $catId : 0,
            'label'       => $catName,
            'new'         => 0,
            'resolved'    => 0,
            'in_progress' => 0,
        ];
    }

    $categoryStats[$catKey][$group] += $count;
}

// Trie par total décroissant, garde top 10 et supprime les catégories à 0
uasort($categoryStats, static function (array $a, array $b): int {
    $totalA = (int) $a['new'] + (int) $a['resolved'] + (int) $a['in_progress'];
    $totalB = (int) $b['new'] + (int) $b['resolved'] + (int) $b['in_progress'];
    return $totalB <=> $totalA;
});
$categoryStats = array_slice(array_filter($categoryStats, static function (array $v): bool {
    return ((int) $v['new'] + (int) $v['resolved'] + (int) $v['in_progress']) > 0;
}), 0, 10, true);

// Formate pour Chart.js
$category['labels']               = array_column($categoryStats, 'label');
$category['ids']                  = array_column($categoryStats, 'id');
$category['values']['new']        = array_column($categoryStats, 'new');
$category['values']['resolved']   = array_column($categoryStats, 'resolved');
$category['values']['in_progress'] = array_column($categoryStats, 'in_progress');


// -- Per town ---
$cityData = ['labels' => [], 'values' => []];
$cityStats = [];
$locTable  = 'glpi_locations';

// Fetch ticket counts grouped by status and city
foreach (
    $DB->request([
        'SELECT'    => [
            "$locTable.town AS city",
            "$table.status AS status",
            'COUNT'  => ["$table.id AS cpt"],
        ],
        'FROM'      => $table,
        'LEFT JOIN' => [
            $locTable => ['ON' => [$locTable => 'id', $table => 'locations_id']]
        ],
        'WHERE'     => array_merge($where, [
            'NOT' => ["$locTable.town" => null],
            ["$locTable.town" => ['!=', '']],
        ]),
        'GROUPBY'   => ["$locTable.town", "$table.status"],
        'ORDER'     => ['cpt DESC'],
    ]) as $row
) {
    $city   = $row['city'];
    $status = (int) $row['status'];
    $count  = (int) $row['cpt'];

    // Resolve status group
    if (in_array($status, [\Ticket::INCOMING], true)) {
        $group = 'new';
    } elseif (in_array($status, [\Ticket::SOLVED, \Ticket::CLOSED], true)) {
        $group = 'resolved';
    } else {
        $group = 'in_progress';
    }

    if (!isset($cityStats[$city])) {
        $cityStats[$city] = ['new' => 0, 'resolved' => 0, 'in_progress' => 0];
    }

    $cityStats[$city][$group] += $count;
}

// Sort by total desc, keep top 10
uasort($cityStats, fn($a, $b) => array_sum($b) - array_sum($a));
$cityStats = array_slice($cityStats, 0, 10, true);

// Format for Chart.js polar area
// One dataset per status group, value = total tickets for that city+group
$cityData['labels']          = array_keys($cityStats);
$cityData['values']['new']        = array_column($cityStats, 'new');
$cityData['values']['resolved']   = array_column($cityStats, 'resolved');
$cityData['values']['in_progress'] = array_column($cityStats, 'in_progress');


// --- Per day ---
$perday = ['labels' => [], 'opened' => [], 'closed' => []];

// Tickets ouverts par jour
$openedByDay = [];
foreach (
    $DB->request([
        'SELECT'  => [
            'COUNT DISTINCT' => "$table.id AS cpt",
            new \QueryExpression("DATE($table.`date`) AS `day`"),
        ],
        'FROM'    => $table,
        'WHERE'   => $where,
        'GROUPBY' => new \QueryExpression('`day`'),
        'ORDER'   => new \QueryExpression('`day` ASC'),
    ]) as $row
) {
    $openedByDay[$row['day']] = (int) $row['cpt'];
}

// Agrégation par mois (Volume mensuel: tickets ouverts par mois)
$perMonth = ['labels' => [], 'values' => [], 'keys' => []];
$openedByMonth = [];
foreach ($openedByDay as $day => $count) {
    $month = substr($day, 0, 7); // 'YYYY-MM-DD' -> 'YYYY-MM'
    $openedByMonth[$month] = ($openedByMonth[$month] ?? 0) + $count;
}
$perMonth['keys']   = array_keys($openedByMonth);
$perMonth['labels'] = array_map(fn($month) => mb_ucfirst(TicketsStatistics::formatMonthLocalized($month)), $perMonth['keys']);
$perMonth['values'] = array_values($openedByMonth);


// Tickets clôturés par jour
$closedWhere = array_merge($where, [
    new \QueryExpression(
        "COALESCE(NULLIF($table.`solvedate`, '0000-00-00 00:00:00'), NULLIF($table.`closedate`, '0000-00-00 00:00:00')) IS NOT NULL"
    ),
]);

if ($period === 'custom' || $period === 'lastmonth') {
    $resolvedCol = "COALESCE(NULLIF($table.`solvedate`, '0000-00-00 00:00:00'), $table.`closedate`)";
    if ($period === 'lastmonth') {
        $lastMonthStart = (new \DateTime('first day of last month'))->format('Y-m-d');
        $lastMonthEnd = (new \DateTime('last day of last month'))->format('Y-m-d');
        $closedWhere[] = new \QueryExpression("$resolvedCol >= '$lastMonthStart 00:00:00'");
        $closedWhere[] = new \QueryExpression("$resolvedCol <= '$lastMonthEnd 23:59:59'");
    } else {
        if ($isValidCustomDate($dateFrom)) {
            $closedWhere[] = new \QueryExpression("$resolvedCol >= '$dateFrom 00:00:00'");
        }
        if ($isValidCustomDate($dateTo)) {
            $closedWhere[] = new \QueryExpression("$resolvedCol <= '$dateTo 23:59:59'");
        }
    }
}

$closedByDay = [];
foreach (
    $DB->request([
        'SELECT'  => [
            'COUNT DISTINCT' => "$table.id AS cpt",
            new \QueryExpression("DATE(COALESCE(NULLIF($table.`solvedate`, '0000-00-00 00:00:00'), $table.`closedate`)) AS `day`"),
        ],
        'FROM'    => $table,
        'WHERE'   => $closedWhere,
        'GROUPBY' => new \QueryExpression('`day`'),
        'ORDER'   => new \QueryExpression('`day` ASC'),
    ]) as $row
) {
    $closedByDay[$row['day']] = (int) $row['cpt'];
}

// Fusionne les labels (union des deux ensembles de dates)
$allDays = array_unique(array_merge(array_keys($openedByDay), array_keys($closedByDay)));
sort($allDays);

foreach ($allDays as $day) {
    $perday['labels'][]  = $day;
    $perday['opened'][]  = $openedByDay[$day] ?? 0;
    $perday['closed'][]  = $closedByDay[$day] ?? 0;
}


// -- Temps de réponse moyen par jour ---
$resolution = ['labels' => [], 'values' => [], 'average' => []];
$resolutionWhere = array_merge($where, [
    new \QueryExpression(
        "($table.`solve_delay_stat` != 0 OR $table.`close_delay_stat` != 0)"
    ),
]);

if ($period === 'custom' || $period === 'lastmonth') {
    $resolvedCol = "COALESCE(NULLIF($table.`solvedate`, '0000-00-00 00:00:00'), $table.`closedate`)";
    if ($period === 'lastmonth') {
        $lastMonthStart = (new \DateTime('first day of last month'))->format('Y-m-d');
        $lastMonthEnd = (new \DateTime('last day of last month'))->format('Y-m-d');
        $resolutionWhere[] = new \QueryExpression("$resolvedCol >= '$lastMonthStart 00:00:00'");
        $resolutionWhere[] = new \QueryExpression("$resolvedCol <= '$lastMonthEnd 23:59:59'");
    } else {
        if ($isValidCustomDate($dateFrom)) {
            $resolutionWhere[] = new \QueryExpression("$resolvedCol >= '$dateFrom 00:00:00'");
        }
        if ($isValidCustomDate($dateTo)) {
            $resolutionWhere[] = new \QueryExpression("$resolvedCol <= '$dateTo 23:59:59'");
        }
    }
}

$resolutionRows = [];
foreach (
    $DB->request([
        'SELECT' => [
            "$table.id",
            new \QueryExpression("DATE(COALESCE(NULLIF($table.`solvedate`, '0000-00-00 00:00:00'), $table.`closedate`)) AS `day`"),
            "$table.solve_delay_stat",
            "$table.close_delay_stat",
        ],
        'FROM'  => $table,
        'WHERE' => $resolutionWhere,
        'ORDER' => new \QueryExpression("COALESCE(NULLIF($table.`solvedate`, '0000-00-00 00:00:00'), $table.`closedate`) ASC"),
    ]) as $row
) {
    $seconds = (int) $row['solve_delay_stat'] ?: (int) $row['close_delay_stat'];
    if ($seconds <= 0) continue;

    $hours = round($seconds / 3600, 2);
    $resolutionRows[] = ['day' => $row['day'], 'hours' => $hours];
}

// Groupe par jour — moyenne par jour
$byDay = [];
foreach ($resolutionRows as $r) {
    $byDay[$r['day']][] = $r['hours'];
}
ksort($byDay);

$totalSum   = 0;
$totalCount = 0;

foreach ($byDay as $day => $hours) {
    $avg         = round(array_sum($hours) / count($hours), 2);
    $totalSum   += array_sum($hours);
    $totalCount += count($hours);

    $resolution['labels'][] = $day;
    $resolution['values'][] = $avg;
}

$globalAvg = $totalCount > 0 ? round($totalSum / $totalCount, 2) : 0;
$resolution['average'] = array_fill(0, count($resolution['labels']), $globalAvg);

// --- Solved-date view: counters scoped to solve/close date instead of creation date ---
$solvedWhere = ["$table.is_deleted" => 0] + getEntitiesRestrictCriteria($table);
\GlpiPlugin\Ticketsstatistics\PeriodFilter::applySolvedDate($solvedWhere, $table, $period, $dateFrom, $dateTo);
\GlpiPlugin\Ticketsstatistics\CategoryFilter::apply($solvedWhere, $table, $categoryId);

// Tickets resolved/closed whose solve/close date falls in the selected period
$solvedInPeriodIter = $DB->request([
    'COUNT' => 'cpt',
    'FROM'  => $table,
    'WHERE' => $solvedWhere + ["$table.status" => [\Ticket::SOLVED, \Ticket::CLOSED]],
]);
$resolvedInPeriod = (int) $solvedInPeriodIter->current()['cpt'];

// Average TTR for tickets resolved in the selected period
$solvedTTRWhere = array_merge($solvedWhere, [
    new \QueryExpression("($table.`solve_delay_stat` != 0 OR $table.`close_delay_stat` != 0)"),
]);

$solvedResolutionRows = [];
$solvedResolutionIter = $DB->request([
    'SELECT' => [
        "$table.id",
        "$table.solve_delay_stat",
        "$table.close_delay_stat",
    ],
    'FROM'  => $table,
    'WHERE' => $solvedTTRWhere,
]);
foreach ($solvedResolutionIter as $row) {
    $seconds = (int) $row['solve_delay_stat'] ?: (int) $row['close_delay_stat'];
    if ($seconds <= 0) continue;
    $solvedResolutionRows[] = round($seconds / 3600, 2);
}

$solvedAvgTtr = count($solvedResolutionRows) > 0
    ? round(array_sum($solvedResolutionRows) / count($solvedResolutionRows), 2)
    : 0;

$solvedView = [
    'resolved_in_period' => $resolvedInPeriod,
    'opened_in_period'   => $counters['total'],
    'avg_ttr'            => $solvedAvgTtr,
];

// --- Tickets by resolution time bucket (résolus/fermés uniquement, base = date de création,
// alignée sur le compteur "Resolved / Closed" de la rangée "Big numbers — creation-date view") ---
$ttrBuckets = [
    't < 2h'    => 0,
    '2h <= t < 4h'  => 0,
    '4h <= t < 8h'  => 0,
    '8h <= t < 16h' => 0,
    't >= 16h'   => 0,
];

// Même base que $counters['solved_closed'] (ligne ~60-70) : $where = date de création
// + entité + catégorie, avec le statut résolu/fermé. Aucun filtre de durée supplémentaire,
// pour garantir que la somme des buckets == $counters['solved_closed'].
$ttrWhere = array_merge($where, [
    "$table.status" => [\Ticket::SOLVED, \Ticket::CLOSED],
]);

foreach (
    $DB->request([
        'SELECT' => [
            new \QueryExpression("
                CASE
                    WHEN COALESCE(NULLIF($table.`solve_delay_stat`, 0), $table.`close_delay_stat`) < 7200 THEN 't < 2h'
                    WHEN COALESCE(NULLIF($table.`solve_delay_stat`, 0), $table.`close_delay_stat`) < 14400 THEN '2h <= t < 4h'
                    WHEN COALESCE(NULLIF($table.`solve_delay_stat`, 0), $table.`close_delay_stat`) < 28800 THEN '4h <= t < 8h'
                    WHEN COALESCE(NULLIF($table.`solve_delay_stat`, 0), $table.`close_delay_stat`) < 57600 THEN '8h <= t < 16h'
                    ELSE 't >= 16h'
                END AS `bucket`
            "),
            'COUNT DISTINCT' => "$table.id AS cpt",
        ],
        'FROM'    => $table,
        'WHERE'   => $ttrWhere,
        'GROUPBY' => new \QueryExpression('`bucket`'),
    ]) as $row
) {
    $bucket = $row['bucket'] ?? '';
    if (isset($ttrBuckets[$bucket])) {
        $ttrBuckets[$bucket] = (int) $row['cpt'];
    }
}

// Calculate total for previous period for comparison (même base : date de création)
$previousWhere = ["$table.is_deleted" => 0] + getEntitiesRestrictCriteria($table);
\GlpiPlugin\Ticketsstatistics\CategoryFilter::apply($previousWhere, $table, $categoryId);
\GlpiPlugin\Ticketsstatistics\PeriodFilter::applyPrevious($previousWhere, $table, $period, $dateFrom, $dateTo);

$previousTtrWhere = array_merge($previousWhere, [
    "$table.status" => [\Ticket::SOLVED, \Ticket::CLOSED],
]);

$previousTotalIter = $DB->request([
    'COUNT' => 'cpt',
    'FROM'  => $table,
    'WHERE' => $previousTtrWhere,
]);
$previousTotal = (int) ($previousTotalIter->current()['cpt'] ?? 0);

$ttrDistribution = [
    'labels'        => array_keys($ttrBuckets),
    'values'        => array_values($ttrBuckets),
    'colors'        => \GlpiPlugin\Ticketsstatistics\TicketsStatistics::getTTRColors(),
    'previousTotal' => $previousTotal,
];

// --- Open tickets age distribution (all open statuses combined) ---
$openAgeLabels = array_values(\GlpiPlugin\Ticketsstatistics\PeriodFilter::getOpenAgeBuckets());
$openAgeDistribution = [
    'labels' => $openAgeLabels,
    'values' => array_fill(0, count($openAgeLabels), 0),
    'colors' => \GlpiPlugin\Ticketsstatistics\TicketsStatistics::getOpenAgeColors(),
];
$openAgeWhere = ["$table.is_deleted" => 0] + getEntitiesRestrictCriteria($table);
\GlpiPlugin\Ticketsstatistics\CategoryFilter::apply($openAgeWhere, $table, $categoryId);

$openAgeWhere["$table.status"] = [\Ticket::INCOMING, \Ticket::ASSIGNED, \Ticket::WAITING];
$openAgeWhere[] = new \QueryExpression("$table.`date` IS NOT NULL AND $table.`date` <> '0000-00-00 00:00:00'");

foreach (
    $DB->request([
        'SELECT'  => [
            new \QueryExpression("
            CASE
                WHEN TIMESTAMPDIFF(HOUR, $table.`date`, NOW()) < 24 THEN '< 24h'
                WHEN TIMESTAMPDIFF(HOUR, $table.`date`, NOW()) < 72 THEN '1 - 3j'
                WHEN TIMESTAMPDIFF(HOUR, $table.`date`, NOW()) < 168 THEN '3 - 7j'
                ELSE '> 7j'
            END AS bucket
            "),
            'COUNT DISTINCT' => "$table.id AS cpt",
        ],
        'FROM'    => $table,
        'WHERE'   => $openAgeWhere,
        'GROUPBY' => [new \QueryExpression('bucket')],
    ]) as $row
) {
    $bucket = (string) ($row['bucket'] ?? '');
    $count = (int) ($row['cpt'] ?? 0);

    $bucketIndex = array_search($bucket, array_keys(\GlpiPlugin\Ticketsstatistics\PeriodFilter::getOpenAgeBuckets()), true);
    if ($bucketIndex === false) {
        continue;
    }

    $openAgeDistribution['values'][$bucketIndex] = $count;
}

echo json_encode(compact('counters', 'priority', 'misscs', 'category', 'cityData', 'perday', 'resolution', 'solvedView', 'ttrDistribution', 'openAgeDistribution', 'perMonth'));
