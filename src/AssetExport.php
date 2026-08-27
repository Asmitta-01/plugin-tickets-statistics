<?php

namespace GlpiPlugin\Ticketsstatistics;

use Glpi\Csv\ExportToCsvInterface;

class AssetExport implements ExportToCsvInterface
{
    protected array $assets;
    private string $city;

    public function __construct(array $assets, int $townId = 0)
    {
        $this->assets = $assets;
        if ($townId > 0 && isset($assets[0]['town'])) {
            $this->city = (string) $assets[0]['town'];
        } else {
            $this->city = '';
        }
    }

    public function getFileName(): string
    {
        return __('Assets', 'ticketsstatistics') . ($this->city !== '' ? ' - ' . $this->city : '') . ' - ' . date('Y-m-d') . '.csv';
    }

    public function getFileHeader(): array
    {
        return [
            __('ID', 'ticketsstatistics'),
            __('Itemtype', 'ticketsstatistics'),
            __('Name', 'ticketsstatistics'),
            __('Type', 'ticketsstatistics'),
            __('Manufacturer', 'ticketsstatistics'),
            __('Model', 'ticketsstatistics'),
            __('Serial number', 'ticketsstatistics'),
            __('Inventory number', 'ticketsstatistics'),
            __('Town', 'ticketsstatistics'),
            __('Location', 'ticketsstatistics'),
            __('Entity', 'ticketsstatistics'),
            __('Last update', 'ticketsstatistics'),
        ];
    }

    public function getFileContent(): array
    {
        $rows = [];
        foreach ($this->assets as $row) {
            $rows[] = [
                (string) ((int) ($row['id'] ?? 0)),
                (string) ($row['itemtype'] ?? ''),
                (string) ($row['name'] ?? ''),
                (string) ($row['type_name'] ?? ''),
                (string) ($row['manufacturer'] ?? ''),
                (string) ($row['model'] ?? ''),
                (string) ($row['serial'] ?? ''),
                (string) ($row['inventory_number'] ?? ''),
                (string) ($row['town'] ?? ''),
                (string) ($row['location'] ?? ''),
                (string) ($row['entity'] ?? ''),
                (string) ($row['last_update'] ?? ''),
            ];
        }

        return $rows;
    }
}
