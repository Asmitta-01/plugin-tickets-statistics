<?php

namespace GlpiPlugin\Ticketsstatistics;

use Glpi\Csv\ExportToCsvInterface;

class ComputerExport implements ExportToCsvInterface
{
    protected array $computers;
    private string $city;

    public function __construct(array $computers, int $townId = 0)
    {
        $this->computers = $computers;
        if ($townId > 0 && isset($computers[0]['town'])) {
            $this->city = (string) $computers[0]['town'];
        } else {
            $this->city = '';
        }
    }

    public function getFileName(): string
    {
        return __('Computers', 'ticketsstatistics') . " - " . $this->city . " - " . date('Y-m-d') . ".csv";
    }

    public function getFileHeader(): array
    {
        return [
            __('ID', 'ticketsstatistics'),
            __('Name', 'ticketsstatistics'),
            __('User', 'ticketsstatistics'),
            __('OS version', 'ticketsstatistics'),
            __('KB patches', 'ticketsstatistics'),
            __('Serial number', 'ticketsstatistics'),
            __('Inventory number', 'ticketsstatistics'),
            __('Town', 'ticketsstatistics'),
            __('Last update', 'ticketsstatistics'),
        ];
    }

    public function getFileContent(): array
    {
        $rows = [];
        foreach ($this->computers as $row) {
            $rows[] = [
                (string) ((int) ($row['id'] ?? 0)),
                (string) ($row['name'] ?? ''),
                (string) ($row['user_name'] ?? ''),
                (string) ($row['version_os'] ?? ''),
                implode(', ', (array) ($row['kb_codes'] ?? [])),
                (string) ($row['serial'] ?? ''),
                (string) ($row['inventory_number'] ?? ''),
                (string) ($row['town'] ?? ''),
                (string) ($row['last_update'] ?? ''),
            ];
        }

        return $rows;
    }
}
