<?php

namespace GlpiPlugin\Ticketsstatistics;

class AssetStatistics
{
    private const ASSET_TABLES = [
        'computers' => 'glpi_computers',
        'network_devices' => 'glpi_networkequipments',
        'monitors' => 'glpi_monitors',
    ];

    /**
     * Count assets for one asset table with optional town/manufacturer filters.
     */
    public static function countAssets(string $assetTable, int $townId, int $manufacturerId): int
    {
        global $DB;

        $where = [
            "$assetTable.is_deleted"  => 0,
            "$assetTable.is_template" => 0,
        ] + getEntitiesRestrictCriteria($assetTable);

        if ($manufacturerId > 0 && $DB->fieldExists($assetTable, 'manufacturers_id')) {
            $where["$assetTable.manufacturers_id"] = $manufacturerId;
        }

        $query = [
            'COUNT' => 'cpt',
            'FROM'  => $assetTable,
            'WHERE' => $where,
        ];

        if ($townId > 0 && $DB->fieldExists($assetTable, 'locations_id')) {
            $query['LEFT JOIN'] = [
                'glpi_locations' => [
                    'ON' => [
                        'glpi_locations' => 'id',
                        $assetTable      => 'locations_id',
                    ],
                ],
            ];
            $query['WHERE']['glpi_locations.id'] = $townId;
        }

        $iter = $DB->request($query);
        $row  = $iter->current();

        return (int) ($row['cpt'] ?? 0);
    }

    public static function getCountsByTown(int $manufacturerId): array
    {
        return self::buildBreakdown('town', 0, $manufacturerId);
    }

    public static function getCountsByManufacturer(int $townId): array
    {
        return self::buildBreakdown('manufacturer', $townId, 0);
    }

    private static function buildBreakdown(string $dimension, int $townId, int $manufacturerId): array
    {
        $breakdown = [];

        foreach (self::ASSET_TABLES as $assetType => $assetTable) {
            $rows = $dimension === 'town'
                ? self::getTownBreakdownForAsset($assetTable, $manufacturerId)
                : self::getManufacturerBreakdownForAsset($assetTable, $townId);

            foreach ($rows as $label => $count) {
                if (!isset($breakdown[$label])) {
                    $breakdown[$label] = self::getEmptyBreakdownRow($label);
                }

                $breakdown[$label][$assetType] = $count;
                $breakdown[$label]['total'] += $count;
            }
        }

        uasort($breakdown, static function (array $left, array $right): int {
            $byTotal = $right['total'] <=> $left['total'];
            if ($byTotal !== 0) {
                return $byTotal;
            }

            return strcmp((string) $left['label'], (string) $right['label']);
        });

        return $breakdown;
    }

    private static function getTownBreakdownForAsset(string $assetTable, int $manufacturerId): array
    {
        global $DB;

        if (!$DB->fieldExists($assetTable, 'locations_id')) {
            return [];
        }

        $where = [
            "$assetTable.is_deleted"  => 0,
            "$assetTable.is_template" => 0,
        ] + getEntitiesRestrictCriteria($assetTable);

        if ($manufacturerId > 0 && $DB->fieldExists($assetTable, 'manufacturers_id')) {
            $where["$assetTable.manufacturers_id"] = $manufacturerId;
        }

        $query = [
            'SELECT'    => [
                'glpi_locations.town',
                'COUNT' => ["$assetTable.id AS cpt"],
            ],
            'FROM'      => $assetTable,
            'LEFT JOIN' => [
                'glpi_locations' => [
                    'ON' => [
                        'glpi_locations' => 'id',
                        $assetTable      => 'locations_id',
                    ],
                ],
            ],
            'WHERE'     => array_merge(
                $where,
                getEntitiesRestrictCriteria('glpi_locations'),
                [
                    'NOT' => ['glpi_locations.town' => null],
                    ['glpi_locations.town' => ['!=', '']],
                ]
            ),
            'GROUPBY'   => ['glpi_locations.town'],
            'ORDER'     => ['cpt DESC', 'glpi_locations.town ASC'],
        ];

        $rows = [];
        foreach ($DB->request($query) as $row) {
            $label = trim((string) ($row['town'] ?? ''));
            if ($label === '') {
                continue;
            }

            $rows[$label] = (int) ($row['cpt'] ?? 0);
        }

        return $rows;
    }

    private static function getManufacturerBreakdownForAsset(string $assetTable, int $townId): array
    {
        global $DB;

        if (!$DB->fieldExists($assetTable, 'manufacturers_id')) {
            return [];
        }

        $where = [
            "$assetTable.is_deleted"  => 0,
            "$assetTable.is_template" => 0,
        ] + getEntitiesRestrictCriteria($assetTable);

        $leftJoin = [
            'glpi_manufacturers' => [
                'ON' => [
                    'glpi_manufacturers' => 'id',
                    $assetTable          => 'manufacturers_id',
                ],
            ],
        ];

        if ($townId > 0) {
            if (!$DB->fieldExists($assetTable, 'locations_id')) {
                return [];
            }

            $leftJoin['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    $assetTable      => 'locations_id',
                ],
            ];
            $where['glpi_locations.id'] = $townId;
            $where = array_merge($where, getEntitiesRestrictCriteria('glpi_locations'));
        }

        $query = [
            'SELECT'    => [
                'glpi_manufacturers.name',
                'COUNT' => ["$assetTable.id AS cpt"],
            ],
            'FROM'      => $assetTable,
            'LEFT JOIN' => $leftJoin,
            'WHERE'     => array_merge(
                $where,
                [
                    'NOT' => ['glpi_manufacturers.name' => null],
                    ['glpi_manufacturers.name' => ['!=', '']],
                ]
            ),
            'GROUPBY'   => ['glpi_manufacturers.name'],
            'ORDER'     => ['cpt DESC', 'glpi_manufacturers.name ASC'],
        ];

        $rows = [];
        foreach ($DB->request($query) as $row) {
            $label = trim((string) ($row['name'] ?? ''));
            if ($label === '') {
                continue;
            }

            $rows[$label] = (int) ($row['cpt'] ?? 0);
        }

        return $rows;
    }

    private static function getEmptyBreakdownRow(string $label): array
    {
        return [
            'label'           => $label,
            'computers'       => 0,
            'network_devices' => 0,
            'monitors'        => 0,
            'total'           => 0,
        ];
    }
}
