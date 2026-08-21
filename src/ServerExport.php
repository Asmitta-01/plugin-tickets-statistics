<?php

namespace GlpiPlugin\Ticketsstatistics;

use Glpi\Csv\ExportToCsvInterface;

class ServerExport implements ExportToCsvInterface
{
    protected array $servers;
    private string $city;

    public function __construct(array $servers, int $townId = 0)
    {
        $this->servers = $servers;
        if ($townId > 0 && isset($servers[0]['town'])) {
            $this->city = (string) $servers[0]['town'];
        } else {
            $this->city = '';
        }
    }

    public function getFileName(): string
    {
        return __('Servers', 'ticketsstatistics') . ($this->city !== '' ? ' - ' . $this->city : '') . ' - ' . date('Y-m-d') . '.csv';
    }

    public function getFileHeader(): array
    {
        return [
            __('ID', 'ticketsstatistics'),
            __('Name', 'ticketsstatistics'),
            __('Type', 'ticketsstatistics'),
            __('Nature', 'ticketsstatistics'),
            __('Manufacturer', 'ticketsstatistics'),
            __('Model', 'ticketsstatistics'),
            __('Operating system', 'ticketsstatistics'),
            __('OS version', 'ticketsstatistics'),
            __('Hosted VMs', 'ticketsstatistics'),
            __('Serial number', 'ticketsstatistics'),
            __('Inventory number', 'ticketsstatistics'),
            __('User', 'ticketsstatistics'),
            __('Town', 'ticketsstatistics'),
            __('Location', 'ticketsstatistics'),
            __('Entity', 'ticketsstatistics'),
            __('Last update', 'ticketsstatistics'),
        ];
    }

    public function getFileContent(): array
    {
        $rows = [];
        foreach ($this->servers as $row) {
            $rows[] = [
                (string) ((int) ($row['id'] ?? 0)),
                (string) ($row['name'] ?? ''),
                (string) ($row['type_name'] ?? ''),
                (string) ($row['server_type_label'] ?? ''),
                (string) ($row['manufacturer'] ?? ''),
                (string) ($row['model'] ?? ''),
                (string) ($row['os_name'] ?? ''),
                (string) ($row['os_version'] ?? ''),
                (string) ((int) ($row['hosted_vms_count'] ?? 0)),
                (string) ($row['serial'] ?? ''),
                (string) ($row['inventory_number'] ?? ''),
                (string) ($row['user_name'] ?? ''),
                (string) ($row['town'] ?? ''),
                (string) ($row['location'] ?? ''),
                (string) ($row['entity'] ?? ''),
                (string) ($row['last_update'] ?? ''),
            ];
        }

        return $rows;
    }
}
