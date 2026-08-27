<?php

namespace GlpiPlugin\Ticketsstatistics;

use DbUtils;
use GlpiPlugin\Ticketsstatistics\ComputersStatistics;

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
    public static function getEntitiesRestrictCriteria(string $table, bool $isRecursive = false, int $entityId = 0): array
    {
        if ($isRecursive) {
            $dbu = new DbUtils();
            $current_entity_id = $entityId > 0 ? $entityId : \Session::getActiveEntity();
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
     * @return array{windows: int, latest_version_count: int, latest_version: string, to_update: int, obsolete: int, kb_total: int}
     */
    public static function getWindowsOsCounters(int $townId, int $entityId = 0): array
    {
        $latestWindows = self::getLatestWindowsByComputer($townId, $entityId);
        $windows11Count = 0;
        $totalWindows = count($latestWindows);
        $latestVersion = '';
        $latestVersionCount = 0;
        $countsByWin11Version = [];

        foreach ($latestWindows as $row) {
            $isWin11 = !empty($row['is_win11']);
            if ($isWin11) {
                $windows11Count++;
            }

            $versionOs = trim((string) ($row['version_os'] ?? ''));
            if ($isWin11 && $versionOs !== '') {
                $countsByWin11Version[$versionOs] = ($countsByWin11Version[$versionOs] ?? 0) + 1;
            }
        }

        foreach ($countsByWin11Version as $version => $count) {
            if ($latestVersion === '' || self::compareWindowsVersions($version, $latestVersion) > 0) {
                $latestVersion = $version;
                $latestVersionCount = (int) $count;
            }
        }

        return [
            'windows'              => $windows11Count,
            'windows_total'        => $totalWindows,
            'latest_version_count' => $latestVersionCount,
            'latest_version'       => $latestVersion,
            'to_update'            => max(0, $totalWindows - $latestVersionCount),
            'obsolete'             => ComputersStatistics::countObsoleteWindowsComputers($townId, $entityId),
            'kb_total'             => self::countDeployedKb($townId, $entityId),
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
     * @return array{labels: array<int, string>, type_keys: array<int, string>, type_labels: array<string, string>, values: array<string, array<int, int>>}
     */
    public static function getComputersByTownAndType(int $townId, int $entityId = 0): array
    {
        global $DB;

        $where = [
            'glpi_computers.is_deleted'       => 0,
            'glpi_computers.is_template'      => 0,
            'glpi_computers.manufacturers_id' => ['<>', 0],
        ] + self::getEntitiesRestrictCriteria('glpi_computers', true, $entityId);

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        $byTown = [];
        foreach (
            $DB->request([
                'SELECT'     => [
                    'glpi_locations.town',
                    'glpi_computertypes.name AS computer_type_name',
                    'COUNT DISTINCT' => 'glpi_computers.id AS cpt',
                ],
                'FROM'       => 'glpi_computers',
                'LEFT JOIN'  => [
                    'glpi_locations' => [
                        'ON' => [
                            'glpi_locations' => 'id',
                            'glpi_computers' => 'locations_id',
                        ],
                    ],
                    'glpi_computertypes' => [
                        'ON' => [
                            'glpi_computertypes' => 'id',
                            'glpi_computers'     => 'computertypes_id',
                        ],
                    ],
                ],
                'WHERE'      => $where,
                'GROUPBY'    => ['glpi_locations.town', 'glpi_computertypes.name'],
            ]) as $row
        ) {
            $town = trim((string) ($row['town'] ?? ''));
            if ($town === '') {
                $town = __('Unknown', 'ticketsstatistics');
            }

            $typeKey = ComputersStatistics::classifyComputerType((string) ($row['computer_type_name'] ?? ''));
            if (!isset($byTown[$town])) {
                $byTown[$town] = [
                    'laptop' => 0,
                    'desktop' => 0,
                    'server' => 0,
                    'vmware' => 0,
                    'other' => 0,
                ];
            }

            $byTown[$town][$typeKey] += (int) ($row['cpt'] ?? 0);
        }

        uasort($byTown, static function (array $left, array $right): int {
            return array_sum($right) <=> array_sum($left);
        });

        $labels = array_keys($byTown);
        $typeKeys = ['laptop', 'desktop', 'server', 'vmware', 'other'];
        $values = [];
        foreach ($typeKeys as $typeKey) {
            $values[$typeKey] = [];
            foreach ($labels as $town) {
                $values[$typeKey][] = (int) ($byTown[$town][$typeKey] ?? 0);
            }
        }

        return [
            'labels' => $labels,
            'type_keys' => $typeKeys,
            'type_labels' => [
                'laptop' => __('Laptop', 'ticketsstatistics'),
                'desktop' => __('Desktop', 'ticketsstatistics'),
                'server' => __('Server', 'ticketsstatistics'),
                'vmware' => __('VMware', 'ticketsstatistics'),
                'other' => __('Other', 'ticketsstatistics'),
            ],
            'values' => $values,
        ];
    }

    /**
     * @return array{labels: array<int, string>, entity_ids: array<int, int>, versions: array<int, string>, values: array<string, array<int, int>>}
     */
    public static function getWindowsVersionsByEntity(int $townId, int $entityId = 0): array
    {
        $byEntity = [];
        $entityLabels = [];
        $versionsSet = [];

        foreach (self::getLatestWindowsByComputer($townId, $entityId) as $row) {
            $entityRowId = (int) ($row['entity_id'] ?? 0);
            $entity = trim((string) ($row['entity'] ?? ''));
            if ($entity === '') {
                $entity = __('Unknown', 'ticketsstatistics');
            }

            $version = trim((string) ($row['version_os'] ?? ''));
            if ($version === '') {
                $version = __('Unknown', 'ticketsstatistics');
            }

            if (!isset($byEntity[$entityRowId])) {
                $byEntity[$entityRowId] = [];
                $entityLabels[$entityRowId] = $entity;
            }
            if (!isset($byEntity[$entityRowId][$version])) {
                $byEntity[$entityRowId][$version] = 0;
            }

            $byEntity[$entityRowId][$version]++;
            $versionsSet[$version] = true;
        }

        uasort($byEntity, static function (array $left, array $right): int {
            $leftTotal = array_sum($left);
            $rightTotal = array_sum($right);
            $byTotal = $rightTotal <=> $leftTotal;
            if ($byTotal !== 0) {
                return $byTotal;
            }

            return 0;
        });

        $entityIds = array_values(array_keys($byEntity));
        $labels = array_map(static function (int $currentEntityId) use ($entityLabels): string {
            return (string) ($entityLabels[$currentEntityId] ?? __('Unknown', 'ticketsstatistics'));
        }, $entityIds);
        $versions = array_values(array_keys($versionsSet));
        usort($versions, static function (string $left, string $right): int {
            return self::compareWindowsVersions($right, $left);
        });

        $values = [];
        foreach ($versions as $version) {
            $values[$version] = [];
            foreach ($entityIds as $currentEntityId) {
                $values[$version][] = (int) ($byEntity[$currentEntityId][$version] ?? 0);
            }
        }

        return [
            'labels' => $labels,
            'entity_ids' => $entityIds,
            'versions' => $versions,
            'values' => $values,
        ];
    }

    /**
     * @return array{labels: array<int, string>, values: array<int, int>}
     */
    public static function getWindowsVersionsBreakdown(int $townId, int $entityId = 0): array
    {
        $counts = [];
        foreach (self::getLatestWindowsByComputer($townId, $entityId) as $row) {
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
    public static function getWindowsVersionsByTown(int $townId, int $entityId = 0): array
    {
        $byTown = [];
        $versionsSet = [];

        foreach (self::getLatestWindowsByComputer($townId, $entityId) as $row) {
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
        // Sort versions in descending order, latest version first
        usort($versions, static function (string $left, string $right): int {
            return self::compareWindowsVersions($right, $left);
        });

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
    public static function getLatestKbInstallations(int $townId, int $limit = 10, int $entityId = 0): array
    {
        global $DB;

        $where = [
            'glpi_computers.is_deleted'                    => 0,
            'glpi_computers.is_template'                   => 0,
            'glpi_items_softwareversions.itemtype'         => 'Computer',
            'glpi_items_softwareversions.is_deleted'       => 0,
            'glpi_items_softwareversions.is_deleted_item'  => 0,
        ] + self::getEntitiesRestrictCriteria('glpi_computers', true, $entityId);

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        // Total de machines éligibles (même périmètre, sans jointure KB)
        $totalMachines = self::countWindowsComputers($townId, $entityId);

        $where[] = new \QueryExpression("glpi_softwares.name REGEXP '^KB[0-9]+$'");

        $labels = [];
        $values  = [];   // installés
        $missing = [];   // non installés

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
                'ORDER'      => ['kb_code DESC'],
                'LIMIT'      => max(1, $limit),
            ]) as $row
        ) {
            $labels[] = (string) ($row['kb_code'] ?? '');
            $values[] = (int) ($row['installs'] ?? 0);
            $missing[] =  ($row['installs'] ?? 0) - $totalMachines;
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'missing' => $missing,
        ];
    }

    /**
     * Count how many computers have Windows 10 or 11 installed, with optional town filter.
     */
    private static function countWindowsComputers(int $townId, int $entityId = 0): int
    {
        global $DB;

        $where = [
            'glpi_computers.is_deleted'                    => 0,
            'glpi_computers.is_template'                   => 0,
            'glpi_items_operatingsystems.itemtype'         => 'Computer',
            'glpi_items_operatingsystems.is_deleted'       => 0,
            'glpi_operatingsystems.name'                   => ['LIKE', 'Microsoft Windows 1%'],
        ] + self::getEntitiesRestrictCriteria('glpi_computers', true, $entityId);

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        $iter = $DB->request([
            'SELECT'     => [
                'COUNT DISTINCT' => 'glpi_computers.id AS cpt',
            ],
            'FROM'       => 'glpi_computers',
            'INNER JOIN' => [
                'glpi_items_operatingsystems' => [
                    'ON' => [
                        'glpi_items_operatingsystems' => 'items_id',
                        'glpi_computers'              => 'id',
                    ],
                ],
                'glpi_operatingsystems' => [
                    'ON' => [
                        'glpi_operatingsystems'       => 'id',
                        'glpi_items_operatingsystems' => 'operatingsystems_id',
                    ],
                ],
            ],
            'WHERE'      => $where,
        ]);

        return (int) ($iter->current()['cpt'] ?? 0);
    }

    /**
     * @return array<int, array{computer_id: int, os_name: string, is_win11: bool, version_os: string, town: string, entity: string, entity_id: int}>
     */
    private static function getLatestWindowsByComputer(int $townId, int $entityId = 0): array
    {
        global $DB;

        $where = [
            'glpi_computers.is_deleted'                    => 0,
            'glpi_computers.is_template'                   => 0,
            'glpi_items_operatingsystems.itemtype'         => 'Computer',
            'glpi_items_operatingsystems.is_deleted'       => 0,
            'glpi_operatingsystems.name'                   => ['LIKE', 'Microsoft Windows 1%'],
        ] + self::getEntitiesRestrictCriteria('glpi_computers', true, $entityId);

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        $rows = $DB->request([
            'SELECT'     => [
                'glpi_computers.id AS computer_id',
                'glpi_computers.entities_id AS entity_id',
                'glpi_operatingsystems.name AS os_name',
                'glpi_operatingsystemversions.name AS version_os',
                'glpi_locations.town',
                'glpi_entities.completename AS entity_name',
                'glpi_items_operatingsystems.id AS rel_id',
            ],
            'FROM'       => 'glpi_computers',
            'INNER JOIN' => [
                'glpi_items_operatingsystems' => [
                    'ON' => [
                        'glpi_items_operatingsystems' => 'items_id',
                        'glpi_computers'              => 'id',
                    ],
                ],
                'glpi_operatingsystems' => [
                    'ON' => [
                        'glpi_operatingsystems'       => 'id',
                        'glpi_items_operatingsystems' => 'operatingsystems_id',
                    ],
                ],
            ],
            'LEFT JOIN' => [
                'glpi_operatingsystemversions' => [
                    'ON' => [
                        'glpi_operatingsystemversions' => 'id',
                        'glpi_items_operatingsystems'  => 'operatingsystemversions_id',
                    ],
                ],
                'glpi_locations' => [
                    'ON' => [
                        'glpi_locations' => 'id',
                        'glpi_computers' => 'locations_id',
                    ],
                ],
                'glpi_entities' => [
                    'ON' => [
                        'glpi_entities'  => 'id',
                        'glpi_computers' => 'entities_id',
                    ],
                ],
            ],
            'WHERE'      => $where,
            'ORDER'      => [
                'glpi_computers.id ASC',
                'glpi_items_operatingsystems.id DESC',
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
            $osName = (string) ($row['os_name'] ?? '');
            $isWin11 = str_contains($osName, 'Windows 11');

            $result[] = [
                'computer_id' => $computerId,
                'os_name'     => $osName,
                'is_win11'    => $isWin11,
                'version_os'  => (string) ($row['version_os'] ?? ''),
                'town'        => (string) ($row['town'] ?? ''),
                'entity'      => (string) ($row['entity_name'] ?? ''),
                'entity_id'   => (int) ($row['entity_id'] ?? 0),
            ];
        }

        return $result;
    }

    private static function countDeployedKb(int $townId, int $entityId = 0): int
    {
        global $DB;

        $where = [
            'glpi_computers.is_deleted'                    => 0,
            'glpi_computers.is_template'                   => 0,
            'glpi_items_softwareversions.itemtype'         => 'Computer',
            'glpi_items_softwareversions.is_deleted'       => 0,
            'glpi_items_softwareversions.is_deleted_item'  => 0,
        ] + self::getEntitiesRestrictCriteria('glpi_computers', true, $entityId);

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

    /**
     * Count computers by specific workstation category ('laptop' vs 'desktop').
     */
    public static function countComputersByCategory(string $category, int $townId = 0, int $manufacturerId = 0): int
    {
        global $DB;

        $where = [
            'glpi_computers.is_deleted'  => 0,
            'glpi_computers.is_template' => 0,
        ] + getEntitiesRestrictCriteria('glpi_computers');

        if ($manufacturerId > 0) {
            $where['glpi_computers.manufacturers_id'] = $manufacturerId;
        }

        $leftJoin = [
            'glpi_computertypes' => [
                'ON' => [
                    'glpi_computertypes' => 'id',
                    'glpi_computers'     => 'computertypes_id',
                ],
            ],
        ];

        $typeIds = \GlpiPlugin\Ticketsstatistics\ComputersStatistics::getComputerTypeIdsByKey($category);
        if (count($typeIds) > 0) {
            $where['glpi_computers.computertypes_id'] = $typeIds;
        } else {
            $where['glpi_computers.computertypes_id'] = -1;
        }

        if ($townId > 0) {
            $leftJoin['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_computers' => 'locations_id',
                ],
            ];
            $where['glpi_locations.id'] = $townId;
        }

        $iter = $DB->request([
            'COUNT'     => 'cpt',
            'FROM'      => 'glpi_computers',
            'LEFT JOIN' => $leftJoin,
            'WHERE'     => $where,
        ]);
        $row = $iter->current();

        return (int) ($row['cpt'] ?? 0);
    }

    /**
     * Count physical workstation computers (laptops & desktops, excluding servers/VMware).
     */
    public static function countWorkstationComputers(int $townId = 0, int $manufacturerId = 0): int
    {
        return self::countComputersByCategory('laptop', $townId, $manufacturerId)
            + self::countComputersByCategory('desktop', $townId, $manufacturerId);
    }

    /**
     * Count server computers (servers & VMware).
     */
    public static function countServerComputers(int $townId = 0, int $manufacturerId = 0): int
    {
        global $DB;

        $where = [
            'glpi_computers.is_deleted'  => 0,
            'glpi_computers.is_template' => 0,
        ] + getEntitiesRestrictCriteria('glpi_computers');

        if ($manufacturerId > 0) {
            $where['glpi_computers.manufacturers_id'] = $manufacturerId;
        }

        $leftJoin = [
            'glpi_computertypes' => [
                'ON' => [
                    'glpi_computertypes' => 'id',
                    'glpi_computers'     => 'computertypes_id',
                ],
            ],
        ];

        $typeIds = array_merge(
            \GlpiPlugin\Ticketsstatistics\ComputersStatistics::getComputerTypeIdsByKey('server'),
            \GlpiPlugin\Ticketsstatistics\ComputersStatistics::getComputerTypeIdsByKey('vmware')
        );
        $typeIds = array_unique($typeIds);

        if (count($typeIds) > 0) {
            $where['glpi_computers.computertypes_id'] = $typeIds;
        } else {
            $where['glpi_computers.computertypes_id'] = -1;
        }

        if ($townId > 0) {
            $leftJoin['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_computers' => 'locations_id',
                ],
            ];
            $where['glpi_locations.id'] = $townId;
        }

        $iter = $DB->request([
            'COUNT'     => 'cpt',
            'FROM'      => 'glpi_computers',
            'LEFT JOIN' => $leftJoin,
            'WHERE'     => $where,
        ]);
        $row = $iter->current();

        return (int) ($row['cpt'] ?? 0);
    }

    /**
     * Count network equipment by type key ('switch', 'firewall').
     */
    public static function countNetworkEquipmentsByType(string $typeKey, int $townId = 0, int $manufacturerId = 0): int
    {
        global $DB;

        $where = [
            'glpi_networkequipments.is_deleted'  => 0,
            'glpi_networkequipments.is_template' => 0,
        ] + getEntitiesRestrictCriteria('glpi_networkequipments');

        if ($manufacturerId > 0) {
            $where['glpi_networkequipments.manufacturers_id'] = $manufacturerId;
        }

        $leftJoin = [
            'glpi_networkequipmenttypes' => [
                'ON' => [
                    'glpi_networkequipmenttypes' => 'id',
                    'glpi_networkequipments'     => 'networkequipmenttypes_id',
                ],
            ],
        ];

        if ($typeKey === 'switch') {
            $where[] = new \QueryExpression("
                (
                    glpi_networkequipmenttypes.name LIKE '%switch%'
                    OR glpi_networkequipments.name LIKE '%switch%'
                )
            ");
        } elseif ($typeKey === 'firewall') {
            $where[] = new \QueryExpression("
                (
                    glpi_networkequipmenttypes.name LIKE '%pare-feu%'
                    OR glpi_networkequipmenttypes.name LIKE '%firewall%'
                    OR glpi_networkequipments.name LIKE '%firewall%'
                    OR glpi_networkequipments.name LIKE '%pare-feu%'
                )
            ");
        }

        if ($townId > 0) {
            $leftJoin['glpi_locations'] = [
                'ON' => [
                    'glpi_locations'         => 'id',
                    'glpi_networkequipments' => 'locations_id',
                ],
            ];
            $where['glpi_locations.id'] = $townId;
        }

        $iter = $DB->request([
            'COUNT'     => 'cpt',
            'FROM'      => 'glpi_networkequipments',
            'LEFT JOIN' => $leftJoin,
            'WHERE'     => $where,
        ]);
        $row = $iter->current();

        return (int) ($row['cpt'] ?? 0);
    }

    /**
     * Resolve assets list for modal drilldown and CSV export based on counter_key.
     *
     * @param array<string, mixed> $input
     * @return array{title: string, count: int, rows: array<int, array<string, mixed>>}
     */
    public static function resolveAssetsScope(array $input): array
    {
        $counterKey = (string) ($input['counter_key'] ?? 'total');
        $townId = (int) ($input['town_id'] ?? 0);
        $manufacturerId = (int) ($input['manufacturer_id'] ?? 0);

        $DB = \DBConnection::getReadConnection();
        global $CFG_GLPI;

        $rows = [];

        $tablesToFetch = [];
        if (in_array($counterKey, ['laptops', 'desktops', 'servers', 'computers'], true)) {
            $tablesToFetch[] = 'glpi_computers';
        } elseif ($counterKey === 'monitors') {
            $tablesToFetch[] = 'glpi_monitors';
        } elseif ($counterKey === 'printers') {
            $tablesToFetch[] = 'glpi_printers';
        } elseif (in_array($counterKey, ['switches', 'firewalls'], true)) {
            $tablesToFetch[] = 'glpi_networkequipments';
        } else {
            // 'total' -> fetch all asset types
            $tablesToFetch = ['glpi_computers', 'glpi_monitors', 'glpi_printers', 'glpi_networkequipments'];
        }

        foreach ($tablesToFetch as $assetTable) {
            $itemtype = match ($assetTable) {
                'glpi_computers'         => 'Computer',
                'glpi_monitors'          => 'Monitor',
                'glpi_printers'          => 'Printer',
                'glpi_networkequipments' => 'NetworkEquipment',
                default                  => 'Computer',
            };

            $formScript = match ($itemtype) {
                'Computer'         => 'computer.form.php',
                'Monitor'          => 'monitor.form.php',
                'Printer'          => 'printer.form.php',
                'NetworkEquipment' => 'networkequipment.form.php',
                default            => 'computer.form.php',
            };

            $where = [
                "$assetTable.is_deleted"  => 0,
                "$assetTable.is_template" => 0,
            ] + getEntitiesRestrictCriteria($assetTable);

            if ($manufacturerId > 0 && $DB->fieldExists($assetTable, 'manufacturers_id')) {
                $where["$assetTable.manufacturers_id"] = $manufacturerId;
            }

            $leftJoin = [];

            if ($DB->fieldExists($assetTable, 'manufacturers_id')) {
                $leftJoin['glpi_manufacturers'] = [
                    'ON' => [
                        'glpi_manufacturers' => 'id',
                        $assetTable          => 'manufacturers_id',
                    ],
                ];
            }

            if ($DB->fieldExists($assetTable, 'locations_id')) {
                $leftJoin['glpi_locations'] = [
                    'ON' => [
                        'glpi_locations' => 'id',
                        $assetTable      => 'locations_id',
                    ],
                ];
                if ($townId > 0) {
                    $where['glpi_locations.id'] = $townId;
                }
            }

            $leftJoin['glpi_entities'] = [
                'ON' => [
                    'glpi_entities' => 'id',
                    $assetTable     => 'entities_id',
                ],
            ];

            if ($assetTable === 'glpi_computers') {
                $leftJoin['glpi_computertypes'] = [
                    'ON' => [
                        'glpi_computertypes' => 'id',
                        'glpi_computers'     => 'computertypes_id',
                    ],
                ];
                $leftJoin['glpi_computermodels'] = [
                    'ON' => [
                        'glpi_computermodels' => 'id',
                        'glpi_computers'      => 'computermodels_id',
                    ],
                ];

                if (in_array($counterKey, ['laptops', 'desktops', 'servers'], true)) {
                    $typeKey = rtrim($counterKey, 's'); // 'laptops' -> 'laptop', etc.
                    $typeIds = \GlpiPlugin\Ticketsstatistics\ComputersStatistics::getComputerTypeIdsByKey($typeKey);

                    if ($counterKey === 'servers') {
                        $vmwareIds = \GlpiPlugin\Ticketsstatistics\ComputersStatistics::getComputerTypeIdsByKey('vmware');
                        $typeIds = array_unique(array_merge($typeIds, $vmwareIds));
                    }

                    if (count($typeIds) > 0) {
                        $where['glpi_computers.computertypes_id'] = $typeIds;
                    } else {
                        // Force no match if no types found
                        $where['glpi_computers.computertypes_id'] = -1;
                    }
                }
            } elseif ($assetTable === 'glpi_networkequipments') {
                $leftJoin['glpi_networkequipmenttypes'] = [
                    'ON' => [
                        'glpi_networkequipmenttypes' => 'id',
                        'glpi_networkequipments'     => 'networkequipmenttypes_id',
                    ],
                ];
                $leftJoin['glpi_networkequipmentmodels'] = [
                    'ON' => [
                        'glpi_networkequipmentmodels' => 'id',
                        'glpi_networkequipments'       => 'networkequipmentmodels_id',
                    ],
                ];

                if ($counterKey === 'switches') {
                    $where[] = new \QueryExpression("
                        (
                            glpi_networkequipmenttypes.name LIKE '%switch%'
                            OR glpi_networkequipments.name LIKE '%switch%'
                        )
                    ");
                } elseif ($counterKey === 'firewalls') {
                    $where[] = new \QueryExpression("
                        (
                            glpi_networkequipmenttypes.name LIKE '%pare-feu%'
                            OR glpi_networkequipmenttypes.name LIKE '%firewall%'
                            OR glpi_networkequipments.name LIKE '%firewall%'
                            OR glpi_networkequipments.name LIKE '%pare-feu%'
                        )
                    ");
                }
            } elseif ($assetTable === 'glpi_monitors') {
                $leftJoin['glpi_monitortypes'] = [
                    'ON' => [
                        'glpi_monitortypes' => 'id',
                        'glpi_monitors'     => 'monitortypes_id',
                    ],
                ];
                $leftJoin['glpi_monitormodels'] = [
                    'ON' => [
                        'glpi_monitormodels' => 'id',
                        'glpi_monitors'      => 'monitormodels_id',
                    ],
                ];
            } elseif ($assetTable === 'glpi_printers') {
                $leftJoin['glpi_printertypes'] = [
                    'ON' => [
                        'glpi_printertypes' => 'id',
                        'glpi_printers'     => 'printertypes_id',
                    ],
                ];
                $leftJoin['glpi_printermodels'] = [
                    'ON' => [
                        'glpi_printermodels' => 'id',
                        'glpi_printers'      => 'printermodels_id',
                    ],
                ];
            }

            $select = [
                "$assetTable.id",
                "$assetTable.name",
                "$assetTable.serial",
                "$assetTable.otherserial",
                "$assetTable.date_mod",
                "glpi_entities.completename AS entity_name",
            ];

            if ($DB->fieldExists($assetTable, 'manufacturers_id')) {
                $select[] = "glpi_manufacturers.name AS manufacturer_name";
            }
            if ($DB->fieldExists($assetTable, 'locations_id')) {
                $select[] = "glpi_locations.town";
                $select[] = "glpi_locations.completename AS location_name";
            }

            if ($assetTable === 'glpi_computers') {
                $select[] = "glpi_computertypes.name AS type_name";
                $select[] = "glpi_computermodels.name AS model_name";
            } elseif ($assetTable === 'glpi_networkequipments') {
                $select[] = "glpi_networkequipmenttypes.name AS type_name";
                $select[] = "glpi_networkequipmentmodels.name AS model_name";
            } elseif ($assetTable === 'glpi_monitors') {
                $select[] = "glpi_monitortypes.name AS type_name";
                $select[] = "glpi_monitormodels.name AS model_name";
            } elseif ($assetTable === 'glpi_printers') {
                $select[] = "glpi_printertypes.name AS type_name";
                $select[] = "glpi_printermodels.name AS model_name";
            }

            $dbRows = $DB->request([
                'SELECT'    => $select,
                'FROM'      => $assetTable,
                'LEFT JOIN' => $leftJoin,
                'WHERE'     => $where,
                'ORDER'     => ["$assetTable.id ASC"],
            ]);

            foreach ($dbRows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $rows[] = [
                    'id'               => $id,
                    'itemtype'         => $itemtype,
                    'name'             => (string) ($row['name'] ?? ''),
                    'serial'           => (string) ($row['serial'] ?? ''),
                    'inventory_number' => (string) ($row['otherserial'] ?? ''),
                    'type_name'        => (string) ($row['type_name'] ?? $itemtype),
                    'manufacturer'     => (string) ($row['manufacturer_name'] ?? '-'),
                    'model'            => (string) ($row['model_name'] ?? '-'),
                    'town'             => (string) (($row['town'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
                    'location'         => (string) (($row['location_name'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
                    'entity'           => (string) (($row['entity_name'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
                    'last_update'      => \Html::convDateTime((string) ($row['date_mod'] ?? '')),
                    'url'              => ($CFG_GLPI['root_doc'] ?? '') . '/front/' . $formScript . '?id=' . $id,
                ];
            }
        }

        $titles = [
            'total'     => __('Total assets', 'ticketsstatistics'),
            'laptops'   => __('Laptops', 'ticketsstatistics'),
            'desktops'  => __('Desktops', 'ticketsstatistics'),
            'servers'   => __('Servers', 'ticketsstatistics'),
            'monitors'  => __('Monitors', 'ticketsstatistics'),
            'printers'  => __('Printers', 'ticketsstatistics'),
            'switches'  => __('Network switches', 'ticketsstatistics'),
            'firewalls' => __('Firewalls', 'ticketsstatistics'),
        ];

        return [
            'title' => $titles[$counterKey] ?? __('Assets', 'ticketsstatistics'),
            'count' => count($rows),
            'rows'  => $rows,
        ];
    }
}
