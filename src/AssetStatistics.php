<?php

namespace GlpiPlugin\Ticketsstatistics;

use DbUtils;

class AssetStatistics
{
    private const ASSET_TABLES = [
        'computers' => 'glpi_computers',
        'network_devices' => 'glpi_networkequipments',
        'monitors' => 'glpi_monitors',
    ];

    /**
     * Get entity restriction criteria for a given table, optionally using recursive entity restrictions.
     * If is_recursive is true, the criteria will include all entities under the current entity in the hierarchy.
     */
    private static function getEntitiesRestrictCriteria(string $table, bool $isRecursive = false): array
    {
        if ($isRecursive) {
            $dbu = new DbUtils();
            $current_entity_id = \Session::getActiveEntity();
            $children = $dbu->getSonsOf('glpi_entities', $current_entity_id);
            return getEntitiesRestrictCriteria($table, value: $children);
        }

        return getEntitiesRestrictCriteria($table);
    }

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
     * @param array<int, int|string> $softwareIds
     * @return array<int, int>
     */
    private static function normalizeSoftwareIds(array $softwareIds): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn($id): int => (int) $id,
            $softwareIds
        ), static fn(int $id): bool => $id > 0)));
    }

    /**
     * @param array<int, int|string> $softwareIds
     */
    public static function getComputerSoftwareMatchSql(string $computerTable, array $softwareIds, bool $matchAll): string
    {
        $normalizedIds = self::normalizeSoftwareIds($softwareIds);

        if ($normalizedIds === []) {
            return '1 = 0';
        }

        $softwareIdsSql = implode(',', $normalizedIds);

        if ($matchAll) {
            return sprintf(
                "EXISTS (SELECT 1 FROM glpi_items_softwareversions isv INNER JOIN glpi_softwareversions sv ON sv.id = isv.softwareversions_id WHERE isv.items_id = %s.id AND isv.itemtype = 'Computer' AND isv.is_deleted = 0 AND sv.softwares_id IN (%s) GROUP BY isv.items_id HAVING COUNT(DISTINCT sv.softwares_id) = %d)",
                $computerTable,
                $softwareIdsSql,
                count($normalizedIds)
            );
        }

        return sprintf(
            "EXISTS (SELECT 1 FROM glpi_items_softwareversions isv INNER JOIN glpi_softwareversions sv ON sv.id = isv.softwareversions_id WHERE isv.items_id = %s.id AND isv.itemtype = 'Computer' AND isv.is_deleted = 0 AND sv.softwares_id IN (%s))",
            $computerTable,
            $softwareIdsSql
        );
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
     * Get how many computers have / don't have the selected software installed.
     *
     * @param array<int, int|string> $softwareIds
     * @return array{with: int, without: int, total: int, name: string, names: array<int, string>, software_ids: array<int, int>}
     */
    public static function getSoftwareCoverageForSelection(array $softwareIds, int $townId, int $manufacturerId, bool $matchAll = true): array
    {
        global $DB;

        $normalizedIds = self::normalizeSoftwareIds($softwareIds);

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

        $softwareRows = [];
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
            $softwareRows[(int) $row['id']] = (string) ($row['name'] ?? '');
        }

        $softwareNames = array_values($softwareRows);
        $normalizedIds = array_keys($softwareRows);

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
            'glpi_computers.is_deleted'  => 0,
            'glpi_computers.is_template' => 0,
        ] + getEntitiesRestrictCriteria('glpi_computers');

        if ($manufacturerId > 0) {
            $where['glpi_computers.manufacturers_id'] = $manufacturerId;
        }

        $joins = [];

        if ($townId > 0) {
            $joins['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_computers' => 'locations_id',
                ],
            ];
            $where['glpi_locations.id'] = $townId;
        }

        $where[] = new \QueryExpression(self::getComputerSoftwareMatchSql('glpi_computers', $normalizedIds, $matchAll));

        $query = [
            'SELECT' => ['COUNT DISTINCT' => 'glpi_computers.id AS cpt'],
            'FROM'   => 'glpi_computers',
            'WHERE'  => $where,
        ];

        if ($joins !== []) {
            $query['LEFT JOIN'] = $joins;
        }

        $iter = $DB->request($query);

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
        return self::getSoftwareCoverageForSelection([$softwareId], $townId, $manufacturerId, true);
    }

    /**
     * Compute OS counters for computers using the latest Windows 11 softwareversion per computer.
     *
     * @return array{windows: int, latest_version_count: int, latest_version: string, to_update: int, kb_total: int}
     */
    public static function getWindowsOsCounters(int $townId): array
    {
        $latestWindows = self::getLatestWindowsByComputer($townId);
        $windows = 0;
        $latestVersion = '';
        $latestVersionCount = 0;
        $countsByVersion = [];

        foreach ($latestWindows as $row) {
            $windows++;
            $versionOs = trim((string) ($row['version_os'] ?? ''));
            if ($versionOs === '') {
                continue;
            }

            if (!isset($countsByVersion[$versionOs])) {
                $countsByVersion[$versionOs] = 0;
            }
            $countsByVersion[$versionOs]++;
        }

        foreach ($countsByVersion as $version => $count) {
            if ($latestVersion === '' || self::compareWindowsVersions($version, $latestVersion) > 0) {
                $latestVersion = $version;
                $latestVersionCount = (int) $count;
            }
        }

        return [
            'windows' => $windows,
            'latest_version_count' => $latestVersionCount,
            'latest_version' => $latestVersion,
            'to_update' => max(0, $windows - $latestVersionCount),
            'kb_total' => self::countDeployedKb($townId),
        ];
    }

    private static function compareWindowsVersions(string $left, string $right): int
    {
        $leftRank = self::extractWindowsVersionRank($left);
        $rightRank = self::extractWindowsVersionRank($right);

        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        return strnatcasecmp($left, $right);
    }

    private static function extractWindowsVersionRank(string $version): int
    {
        if (preg_match('/\b(\d{2})H(\d)\b/i', $version, $matches) === 1) {
            $year = (int) $matches[1];
            $half = (int) $matches[2];
            return ($year * 10) + $half;
        }

        return -1;
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public static function getWindowsVersionsBreakdown(int $townId): array
    {
        $counts = [];
        foreach (self::getLatestWindowsByComputer($townId) as $row) {
            $version = trim((string) ($row['version_os'] ?? ''));
            if ($version === '') {
                $version = __('Unknown', 'ticketsstatistics');
            }

            if (!isset($counts[$version])) {
                $counts[$version] = 0;
            }
            $counts[$version]++;
        }

        arsort($counts);

        return [
            'labels' => array_keys($counts),
            'values' => array_values($counts),
        ];
    }

    /**
     * @return array{labels: array<int, string>, versions: array<int, string>, values: array<string, array<int, int>>}
     */
    public static function getWindowsVersionsByTown(int $townId): array
    {
        $byTown = [];
        $versionsSet = [];

        foreach (self::getLatestWindowsByComputer($townId) as $row) {
            $town = trim((string) ($row['town'] ?? ''));
            if ($town === '') {
                $town = __('Unknown', 'ticketsstatistics');
            }

            $version = trim((string) ($row['version_os'] ?? ''));
            if ($version === '') {
                $version = __('Unknown', 'ticketsstatistics');
            }

            if (!isset($byTown[$town])) {
                $byTown[$town] = [];
            }
            if (!isset($byTown[$town][$version])) {
                $byTown[$town][$version] = 0;
            }

            $byTown[$town][$version]++;
            $versionsSet[$version] = true;
        }

        uasort($byTown, static function (array $left, array $right): int {
            $leftTotal = array_sum($left);
            $rightTotal = array_sum($right);
            $byTotal = $rightTotal <=> $leftTotal;
            if ($byTotal !== 0) {
                return $byTotal;
            }

            return 0;
        });

        $labels = array_keys($byTown);
        $versions = array_keys($versionsSet);
        natcasesort($versions);
        $versions = array_values($versions);

        $values = [];
        foreach ($versions as $version) {
            $values[$version] = [];
            foreach ($labels as $town) {
                $values[$version][] = (int) ($byTown[$town][$version] ?? 0);
            }
        }

        return [
            'labels' => $labels,
            'versions' => $versions,
            'values' => $values,
        ];
    }

    /**
     * Return latest KB patches with their installation counts.
     *
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public static function getLatestKbInstallations(int $townId, int $limit = 10): array
    {
        global $DB;

        $where = [
            'glpi_computers.is_deleted'                    => 0,
            'glpi_computers.is_template'                   => 0,
            'glpi_items_softwareversions.itemtype'         => 'Computer',
            'glpi_items_softwareversions.is_deleted'       => 0,
            'glpi_items_softwareversions.is_deleted_item'  => 0,
        ] + self::getEntitiesRestrictCriteria('glpi_computers', true);

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        $where[] = new \QueryExpression("glpi_softwares.name REGEXP '^KB[0-9]+$'");

        $labels = [];
        $values = [];
        foreach (
            $DB->request([
                'SELECT'     => [
                    'glpi_softwares.name AS kb_code',
                    'COUNT DISTINCT' => 'glpi_computers.id AS installs',
                    'MAX' => 'glpi_items_softwareversions.id AS last_rel_id',
                ],
                'FROM'       => 'glpi_computers',
                'INNER JOIN' => [
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
                ],
                'WHERE'      => $where,
                'GROUPBY'    => ['glpi_softwares.name'],
                'ORDER'      => ['last_rel_id DESC'],
                'LIMIT'      => max(1, $limit),
            ]) as $row
        ) {
            $labels[] = (string) ($row['kb_code'] ?? '');
            $values[] = (int) ($row['installs'] ?? 0);
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * @return array<int, array{computer_id: int, version_os: string, town: string}>
     */
    private static function getLatestWindowsByComputer(int $townId): array
    {
        global $DB;

        $where = [
            'glpi_computers.is_deleted'                    => 0,
            'glpi_computers.is_template'                   => 0,
            'glpi_items_softwareversions.itemtype'         => 'Computer',
            'glpi_items_softwareversions.is_deleted'       => 0,
            'glpi_items_softwareversions.is_deleted_item'  => 0,
            'glpi_softwares.name'                          => ['LIKE', 'Microsoft Windows 11%'],
        ] + self::getEntitiesRestrictCriteria('glpi_computers', true);

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        $rows = $DB->request([
            'SELECT'     => [
                'glpi_computers.id AS computer_id',
                'glpi_softwareversions.name AS version_os',
                'glpi_locations.town',
                'glpi_items_softwareversions.id AS rel_id',
            ],
            'FROM'       => 'glpi_computers',
            'INNER JOIN' => [
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
            ],
            'LEFT JOIN' => [
                'glpi_locations' => [
                    'ON' => [
                        'glpi_locations' => 'id',
                        'glpi_computers' => 'locations_id',
                    ],
                ],
            ],
            'WHERE'      => $where,
            'ORDER'      => [
                'glpi_computers.id ASC',
                'glpi_items_softwareversions.id DESC',
            ],
        ]);

        $result = [];
        $seenComputers = [];

        foreach ($rows as $row) {
            $computerId = (int) ($row['computer_id'] ?? 0);
            if ($computerId <= 0 || isset($seenComputers[$computerId])) {
                continue;
            }

            $seenComputers[$computerId] = true;
            $result[] = [
                'computer_id' => $computerId,
                'version_os'  => (string) ($row['version_os'] ?? ''),
                'town'        => (string) ($row['town'] ?? ''),
            ];
        }

        return $result;
    }

    private static function countDeployedKb(int $townId): int
    {
        global $DB;

        $where = [
            'glpi_computers.is_deleted'                    => 0,
            'glpi_computers.is_template'                   => 0,
            'glpi_items_softwareversions.itemtype'         => 'Computer',
            'glpi_items_softwareversions.is_deleted'       => 0,
            'glpi_items_softwareversions.is_deleted_item'  => 0,
        ] + self::getEntitiesRestrictCriteria('glpi_computers', true);

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        $where[] = new \QueryExpression("glpi_softwares.name REGEXP '^KB[0-9]+$'");

        $iter = $DB->request([
            'SELECT'     => [
                'COUNT DISTINCT' => 'glpi_softwares.name AS cpt',
            ],
            'FROM'       => 'glpi_computers',
            'INNER JOIN' => [
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
            ],
            'WHERE'      => $where,
        ]);

        return (int) ($iter->current()['cpt'] ?? 0);
    }
}
