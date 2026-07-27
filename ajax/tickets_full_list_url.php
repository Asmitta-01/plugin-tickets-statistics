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
$period     = (string) ($_GET['period'] ?? 'thismonth');
$dateFrom   = $_GET['date_from'] ?? null;
$dateTo     = $_GET['date_to'] ?? null;

$statusGroups = [
    'new'           => [\Ticket::INCOMING],
    'incoming'      => [\Ticket::INCOMING],
    'assigned'      => [\Ticket::ASSIGNED],
    'waiting'       => [\Ticket::WAITING],
    'resolved'      => [\Ticket::SOLVED, \Ticket::CLOSED],
    'solved_closed' => [\Ticket::SOLVED, \Ticket::CLOSED],
    'in_progress'   => [\Ticket::ASSIGNED, \Ticket::WAITING, \Ticket::ACCEPTED, \Ticket::OBSERVED],
    'missc'         => [\Ticket::INCOMING, \Ticket::ASSIGNED, \Ticket::WAITING, \Ticket::SOLVED, \Ticket::CLOSED],
    'total'         => [],
    ''              => [],
];

$group = array_key_exists($counterKey, $statusGroups) ? $counterKey : '';
$statuses = $statusGroups[$group];

[$from, $toExclusive] = ticketsstatistics_get_period_bounds($period, $dateFrom, $dateTo);

$criteria = [];

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

$url = '/front/ticket.php?' . \Toolbox::append_params([
    'criteria' => $criteria,
    'reset'    => 'reset',
]);

echo json_encode([
    'ok'  => true,
    'url' => $url,
]);
