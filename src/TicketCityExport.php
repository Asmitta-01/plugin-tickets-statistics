<?php

namespace GlpiPlugin\Ticketsstatistics;

use Glpi\Csv\ExportToCsvInterface;
use Ticket;

class TicketCityExport implements ExportToCsvInterface
{
    protected array $tickets;
    protected string $city;

    public function __construct(array $tickets, string $city)
    {
        $this->tickets = $tickets;
        $this->city    = $city;
    }

    public function getFileName(): string
    {
        $slug = mb_strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $this->city));
        return "tickets_{$slug}_" . date('Y-m-d') . ".csv";
    }

    public function getFileHeader(): array
    {
        return [
            __('ID'),
            __('Title'),
            __('Status'),
            __('Priority'),
            __('Category'),
            __('Town'),
            __('Assigned to'),
            __('Opening date'),
            __('Last update'),
            __('Resolution date'),
            __('Closing date'),
        ];
    }

    public function getFileContent(): array
    {
        $statusMap = [
            Ticket::INCOMING => __('New'),
            Ticket::ASSIGNED => __('Assigned'),
            Ticket::WAITING  => __('Pending'),
            Ticket::SOLVED   => __('Solved'),
            Ticket::CLOSED   => __('Closed'),
        ];

        $priorityMap = [
            1 => __('Very low'),
            2 => __('Low'),
            3 => __('Medium'),
            4 => __('High'),
            5 => __('Very high'),
        ];

        $rows = [];
        foreach ($this->tickets as $t) {
            $rows[] = [
                $t['id'],
                $t['name']        ?? '',
                $statusMap[$t['status']]     ?? $t['status'],
                $priorityMap[$t['priority']] ?? $t['priority'],
                $t['category']    ?? '',
                $t['town']        ?? '',
                $t['assigned_to'] ?? '',
                $t['date']        ?? '',
                $t['date_mod']    ?? '',
                $t['solvedate']   ?? '',
                $t['closedate']   ?? '',
            ];
        }

        return $rows;
    }
}
