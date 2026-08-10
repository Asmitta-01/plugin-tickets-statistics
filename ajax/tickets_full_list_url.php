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

/**
 * Returns true only for strict Y-m-d H:i:s dates.
 */
function ticketsstatistics_is_valid_ymd(?string $date): bool
{
    if (!is_string($date) || $date === '') {
        return false;
    }

    $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $date);
    return $dt !== false && $dt->format('Y-m-d H:i:s') === $date;
}

/**
 * Build [from, toExclusive] bounds for creation date criteria.
 */
function ticketsstatistics_get_period_bounds(string $period, ?string $dateFrom, ?string $dateTo): array
{
    $today = new \DateTimeImmutable('today');
    $dateFormat = 'Y-m-d H:i:s';

    switch ($period) {
        case 'last7':
            return [$today->modify('-7 days')->format($dateFormat), null];

        case 'thismonth':
            return [$today->modify('first day of this month')->format($dateFormat), null];

        case 'last30':
            return [$today->modify('-30 days')->format($dateFormat), null];

        case 'lastmonth':
            return [
                $today->modify('first day of last month')->format($dateFormat),
                $today->modify('first day of this month')->format($dateFormat),
            ];

        case 'last90':
            return [$today->modify('-90 days')->format($dateFormat), null];

        case 'thisyear':
            return [
                $today->setDate((int) $today->format('Y'), 1, 1)->format($dateFormat),
                $today->modify('first day of january next year')->format($dateFormat),
            ];

        case 'lastyear':
            $lastYear = (int) $today->format('Y') - 1;
            return [
                sprintf('%04d-01-01', $lastYear),
                sprintf('%04d-01-01', $lastYear + 1),
            ];

        case 'custom':
            $from = ticketsstatistics_is_valid_ymd($dateFrom) ? $dateFrom : null;
            $toExclusive = null;
            if (ticketsstatistics_is_valid_ymd($dateTo)) {
                $toExclusive = (new \DateTimeImmutable($dateTo))->modify('+1 day')->format($dateFormat);
            }
            return [$from, $toExclusive];

        default:
            return [$today->modify('-30 days')->format($dateFormat), null];
    }
}

$counterKey = (string) ($_GET['counter_key'] ?? '');
$type       = (string) ($_GET['type'] ?? '');
$label      = trim((string) ($_GET['label'] ?? ''));
$statusGroup = (string) ($_GET['status_group'] ?? '');
$period     = (string) ($_GET['period'] ?? 'thismonth');
$dateFrom   = $_GET['date_from'] ?? null;
$dateTo     = $_GET['date_to'] ?? null;
$openStatusesGlobal = !isset($_GET['open_statuses_global']) || (int) $_GET['open_statuses_global'] === 1;

$statusGroups = [
    'new'           => [\Ticket::INCOMING],
    'incoming'      => [\Ticket::INCOMING],
    'assigned'      => [\Ticket::ASSIGNED],
    'waiting'       => [\Ticket::WAITING],
    'resolved'      => [\Ticket::SOLVED, \Ticket::CLOSED],
    'solved_closed' => [\Ticket::SOLVED, \Ticket::CLOSED],
    'in_progress'   => [\Ticket::ASSIGNED, \Ticket::WAITING, \Ticket::ACCEPTED, \Ticket::OBSERVED],
    'missc'         => [],
    'total'         => [],
    ''              => [],
];

$effectiveGroup = $counterKey !== '' ? $counterKey : $statusGroup;
$group = array_key_exists($effectiveGroup, $statusGroups) ? $effectiveGroup : '';
$statuses = $statusGroups[$group];
$isMissc = ($group === 'missc');
$isOpenStatusCounter = in_array($group, ['new', 'incoming', 'assigned', 'waiting'], true);

[$from, $toExclusive] = ticketsstatistics_get_period_bounds($period, $dateFrom, $dateTo);

$criteria = [];

if ($isMissc) {
    $criteria[] = [
        'field'      => 5200, // MISSC Number
        'searchtype' => 'notcontains',
        'value'      => '^$',
        'link'       => 'AND',
    ];
} elseif ($type === 'open_age') {
    if ($group === '') {
        $statuses = [\Ticket::INCOMING, \Ticket::ASSIGNED, \Ticket::WAITING];
    }

    if (count($statuses) === 1) {
        $criteria[] = [
            'field'      => 12, // Status
            'searchtype' => 'equals',
            'value'      => $statuses[0],
            'link'       => 'AND',
        ];
    } elseif (count($statuses) > 1) {
        $first = true;
        $criteria[] = [
            'link' => 'AND',
            'criteria' => [],
        ];
        foreach ($statuses as $status) {
            $criteria[count($criteria) - 1]['criteria'][] = [
                'field'      => 12, // Status
                'searchtype' => 'equals',
                'value'      => $status,
                'link'       => $first ? 'AND' : 'OR',
            ];
            $first = false;
        }
    }

    $now = new \DateTimeImmutable('now');
    $bucketFrom = null;
    $bucketTo = null;
    $labelKey = array_search($label, \GlpiPlugin\Ticketsstatistics\PeriodFilter::getOpenAgeBuckets(), true);

    switch ($labelKey) {
        case '< 24h':
            $bucketFrom = $now->modify('-1 day')->format('Y-m-d H:i:s');
            break;
        case '1 - 3j':
            $bucketFrom = $now->modify('-3 days')->format('Y-m-d H:i:s');
            $bucketTo = $now->modify('-1 day')->format('Y-m-d H:i:s');
            break;
        case '3 - 7j':
            $bucketFrom = $now->modify('-7 days')->format('Y-m-d H:i:s');
            $bucketTo = $now->modify('-3 days')->format('Y-m-d H:i:s');
            break;
        case '> 7j':
            $bucketTo = $now->modify('-7 days')->format('Y-m-d H:i:s');
            break;
    }

    if ($bucketFrom !== null) {
        $criteria[] = [
            'field'      => 15, // Creation date
            'searchtype' => 'morethan',
            'value'      => $bucketFrom,
            'link'       => 'AND',
        ];
    }

    if ($bucketTo !== null) {
        $criteria[] = [
            'field'      => 15, // Creation date
            'searchtype' => 'lessthan',
            'value'      => $bucketTo,
            'link'       => 'AND',
        ];
    }
} elseif ($type == 'per_month') {
    $month = $label; // Expected format: 'YYYY-MM'
    // Get the first day of the month
    $from = (new \DateTimeImmutable($month . '-01'))->format('Y-m-d H:i:s');
    // Get the first day of the next month
    $toExclusive = (new \DateTimeImmutable($month . '-01'))->modify('+1 month')->format('Y-m-d H:i:s');
} else {
    if (count($statuses) === 1) {
        $criteria[] = [
            'field'      => 12, // Status
            'searchtype' => 'equals',
            'value'      => $statuses[0],
            'link'       => 'AND',
        ];
    } elseif (count($statuses) > 1) {
        $first = true;
        foreach ($statuses as $status) {
            $criteria[] = [
                'field'      => 12, // Status
                'searchtype' => 'equals',
                'value'      => $status,
                'link'       => $first ? 'AND' : 'OR',
            ];
            $first = false;
        }
    }
}



if ($type !== 'open_age' && (!$openStatusesGlobal || !$isOpenStatusCounter)) {
    if ($from !== null) {
        $criteria[] = [
            'field'      => 15, // Creation date
            'searchtype' => 'morethan',
            'value'      => $from,
            'link'       => 'AND',
        ];
    }

    if ($toExclusive !== null) {
        $criteria[] = [
            'field'      => 15, // Creation date
            'searchtype' => 'lessthan',
            'value'      => $toExclusive,
            'link'       => 'AND',
        ];
    }
}

$url = '/front/ticket.php?' . \Toolbox::append_params([
    'criteria' => $criteria,
    'reset'    => 'reset',
]);

echo json_encode([
    'ok'  => true,
    'url' => $url,
]);
