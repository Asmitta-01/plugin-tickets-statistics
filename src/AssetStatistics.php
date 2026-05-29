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

    /**
     * Get top N softwares installed on computers, with optional town/manufacturer filters.
     *
     * @return array<int, array{id: int, name: string, count: int}>
     */
    public static function getTopSoftwaresByComputers(int $townId, int $manufacturerId, int $limit = 20): array
    {
        global $DB;

        $where = [
            'glpi_computers.is_deleted'               => 0,
            'glpi_computers.is_template'              => 0,
            'glpi_items_softwareversions.is_deleted'  => 0,
            'glpi_items_softwareversions.itemtype'    => 'Computer',
            'glpi_softwares.is_deleted'               => 0,
            'glpi_softwares.is_template'              => 0,
        ] + getEntitiesRestrictCriteria('glpi_computers');

        if ($manufacturerId > 0) {
            $where['glpi_computers.manufacturers_id'] = $manufacturerId;
        }

        $joins = [
            'glpi_items_softwareversions' => [
                'ON' => [
                    'glpi_items_softwareversions' => 'items_id',
                    'glpi_computers'              => 'id',
                ],
            ],
            'glpi_softwareversions' => [
                'ON' => [
                    'glpi_softwareversions'       => 'id',
                    'glpi_items_softwareversions' => 'softwareversions_id',
                ],
            ],
            'glpi_softwares' => [
                'ON' => [
                    'glpi_softwares'        => 'id',
                    'glpi_softwareversions' => 'softwares_id',
                ],
            ],
        ];

        if ($townId > 0) {
            $joins['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_computers' => 'locations_id',
                ],
            ];
            $where['glpi_locations.id'] = $townId;
        }

        $results = [];
        foreach (
            $DB->request([
                'SELECT'     => [
                    'glpi_softwares.id',
                    'glpi_softwares.name',
                    'COUNT DISTINCT' => 'glpi_computers.id AS cpt',
                ],
                'FROM'       => 'glpi_computers',
                'INNER JOIN' => $joins,
                'WHERE'      => $where,
                'GROUPBY'    => ['glpi_softwares.id', 'glpi_softwares.name'],
                'ORDER'      => ['cpt DESC'],
                'LIMIT'      => $limit,
            ]) as $row
        ) {
            $results[] = [
                'id'    => (int) $row['id'],
                'name'  => (string) $row['name'],
                'count' => (int) $row['cpt'],
            ];
        }

        return $results;
    }

    /**
     * Get how many computers have / don't have at least one selected software installed.
     *
     * @param array<int, int|string> $softwareIds
     * @return array{with: int, without: int, total: int, name: string, names: array<int, string>, software_ids: array<int, int>}
     */
    public static function getSoftwareCoverageForSelection(array $softwareIds, int $townId, int $manufacturerId): array
    {
        global $DB;

        $normalizedIds = array_values(array_unique(array_filter(array_map(
            static fn($id): int => (int) $id,
            $softwareIds
        ), static fn(int $id): bool => $id > 0)));

        $total = self::countAssets('glpi_computers', $townId, $manufacturerId);

        if ($normalizedIds === [] || $total <= 0) {
            return [
                'with'         => 0,
                'without'      => $total,
                'total'        => $total,
                'name'         => '',
                'names'        => [],
                'software_ids' => $normalizedIds,
            ];
        }

        $softwareNames = [];
        foreach (
            $DB->request([
                'SELECT' => ['id', 'name'],
                'FROM'   => 'glpi_softwares',
                'WHERE'  => [
                    'id'         => $normalizedIds,
                    'is_deleted' => 0,
                ],
                'ORDER'  => ['name ASC'],
            ]) as $row
        ) {
            $softwareNames[] = (string) ($row['name'] ?? '');
        }

        if ($softwareNames === []) {
            return [
                'with'         => 0,
                'without'      => $total,
                'total'        => $total,
                'name'         => '',
                'names'        => [],
                'software_ids' => $normalizedIds,
            ];
        }

        $softwareLabel = count($softwareNames) === 1
            ? $softwareNames[0]
            : implode(', ', array_slice($softwareNames, 0, 3)) . (count($softwareNames) > 3 ? '...' : '');

        $where = [
            'glpi_computers.is_deleted'              => 0,
            'glpi_computers.is_template'             => 0,
            'glpi_items_softwareversions.is_deleted' => 0,
            'glpi_items_softwareversions.itemtype'   => 'Computer',
            'glpi_softwareversions.softwares_id'     => $normalizedIds,
        ] + getEntitiesRestrictCriteria('glpi_computers');

        if ($manufacturerId > 0) {
            $where['glpi_computers.manufacturers_id'] = $manufacturerId;
        }

        $joins = [
            'glpi_items_softwareversions' => [
                'ON' => [
                    'glpi_items_softwareversions' => 'items_id',
                    'glpi_computers'              => 'id',
                ],
            ],
            'glpi_softwareversions' => [
                'ON' => [
                    'glpi_softwareversions'       => 'id',
                    'glpi_items_softwareversions' => 'softwareversions_id',
                ],
            ],
        ];

        if ($townId > 0) {
            $joins['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_computers' => 'locations_id',
                ],
            ];
            $where['glpi_locations.id'] = $townId;
        }

        $iter = $DB->request([
            'SELECT'     => ['COUNT DISTINCT' => 'glpi_computers.id AS cpt'],
            'FROM'       => 'glpi_computers',
            'INNER JOIN' => $joins,
            'WHERE'      => $where,
        ]);

        $with    = (int) ($iter->current()['cpt'] ?? 0);
        $without = max(0, $total - $with);

        return [
            'with'         => $with,
            'without'      => $without,
            'total'        => $total,
            'name'         => $softwareLabel,
            'names'        => $softwareNames,
            'software_ids' => $normalizedIds,
        ];
    }

    /**
     * Backward-compatible single-software wrapper.
     *
     * @return array{with: int, without: int, total: int, name: string, names: array<int, string>, software_ids: array<int, int>}
     */
    public static function getSoftwareCoverage(int $softwareId, int $townId, int $manufacturerId): array
    {
        return self::getSoftwareCoverageForSelection([$softwareId], $townId, $manufacturerId);
    }
}
