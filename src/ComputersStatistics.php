<?php

namespace GlpiPlugin\Ticketsstatistics;

class ComputersStatistics
{
    /**
     * @return array<int, int>
     */
    private static function getEntitiesRestrictCriteria(string $table): array
    {
        $dbu = new \DbUtils();
        $currentEntityId = \Session::getActiveEntity();
        $children = $dbu->getSonsOf('glpi_entities', $currentEntityId);

        return getEntitiesRestrictCriteria($table, value: $children);
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
        if (preg_match('/\\b(\\d{2})H(\\d)\\b/i', $version, $matches) === 1) {
            return ((int) $matches[1] * 10) + (int) $matches[2];
        }

        return -1;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getLatestWindowsComputers(int $townId): array
    {
        $DB = \DBConnection::getReadConnection();
        global $CFG_GLPI;

        $where = [
            'glpi_computers.is_deleted'                    => 0,
            'glpi_computers.is_template'                   => 0,
            'glpi_items_softwareversions.itemtype'         => 'Computer',
            'glpi_items_softwareversions.is_deleted'       => 0,
            'glpi_items_softwareversions.is_deleted_item'  => 0,
            'glpi_softwares.name'                          => ['LIKE', 'Microsoft Windows 11%'],
        ] + self::getEntitiesRestrictCriteria('glpi_computers');

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        $rows = $DB->request([
            'SELECT'     => [
                'glpi_computers.id AS computer_id',
                'glpi_computers.name AS computer_name',
                'glpi_computers.serial',
                'glpi_computers.otherserial',
                'glpi_computers.date_mod',
                'glpi_locations.town',
                'glpi_softwareversions.name AS version_os',
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
        $seen = [];
        foreach ($rows as $row) {
            $computerId = (int) ($row['computer_id'] ?? 0);
            if ($computerId <= 0 || isset($seen[$computerId])) {
                continue;
            }

            $seen[$computerId] = true;
            $computerName = (string) ($row['computer_name'] ?? '');
            $result[] = [
                'id' => $computerId,
                'name' => $computerName !== '' ? $computerName : sprintf(__('Computer #%d', 'ticketsstatistics'), $computerId),
                'serial' => (string) ($row['serial'] ?? ''),
                'inventory_number' => (string) ($row['otherserial'] ?? ''),
                'version_os' => (string) ($row['version_os'] ?? ''),
                'town' => (string) (($row['town'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
                'last_update' => \Html::convDateTime((string) ($row['date_mod'] ?? '')),
                'kb_codes' => [],
                'url' => $CFG_GLPI['root_doc'] . '/front/computer.form.php?id=' . $computerId,
            ];
        }

        return $result;
    }

    public static function getLatestWindowsVersion(array $rows): string
    {
        $latest = '';
        foreach ($rows as $row) {
            $version = trim((string) ($row['version_os'] ?? ''));
            if ($version === '') {
                continue;
            }

            if ($latest === '' || self::compareWindowsVersions($version, $latest) > 0) {
                $latest = $version;
            }
        }

        return $latest;
    }

    public static function getOperatingSystemIDByName(string $name): int
    {
        global $DB;
        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_operatingsystemversions',
            'WHERE'  => [
                'name' => $name,
            ],
        ]);
        $id = count($iterator) ? $iterator->current()['id'] : -1;
        return $id;
    }

    public static function getTownIdByName(string $name): int
    {
        global $DB;
        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_locations',
            'WHERE'  => [
                'name' => $name,
            ],
        ]);
        $id = count($iterator) ? $iterator->current()['id'] : -1;
        return $id;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function getKbMapForComputers(int $townId, string $kbCode = ''): array
    {
        $DB = \DBConnection::getReadConnection();

        $where = [
            'glpi_computers.is_deleted'                    => 0,
            'glpi_computers.is_template'                   => 0,
            'glpi_items_softwareversions.itemtype'         => 'Computer',
            'glpi_items_softwareversions.is_deleted'       => 0,
            'glpi_items_softwareversions.is_deleted_item'  => 0,
        ] + self::getEntitiesRestrictCriteria('glpi_computers');

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        if ($kbCode !== '') {
            $where['glpi_softwares.name'] = $kbCode;
        } else {
            $where[] = new \QueryExpression("glpi_softwares.name REGEXP '^KB[0-9]+$'");
        }

        $map = [];
        foreach (
            $DB->request([
                'SELECT'     => [
                    'glpi_computers.id AS computer_id',
                    'glpi_softwares.name AS kb_code',
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
                'GROUPBY'    => [
                    'glpi_computers.id',
                    'glpi_softwares.name',
                ],
            ]) as $row
        ) {
            $computerId = (int) ($row['computer_id'] ?? 0);
            $currentKb = (string) ($row['kb_code'] ?? '');
            if ($computerId <= 0 || $currentKb === '') {
                continue;
            }

            if (!isset($map[$computerId])) {
                $map[$computerId] = [];
            }
            $map[$computerId][] = $currentKb;
        }

        return $map;
    }

    /**
     * @return array<int, array{kb_code: string, installations: int}>
     */
    public static function getKbInstallationsSummary(int $townId): array
    {
        $DB = \DBConnection::getReadConnection();

        $where = [
            'glpi_computers.is_deleted'                    => 0,
            'glpi_computers.is_template'                   => 0,
            'glpi_items_softwareversions.itemtype'         => 'Computer',
            'glpi_items_softwareversions.is_deleted'       => 0,
            'glpi_items_softwareversions.is_deleted_item'  => 0,
        ] + self::getEntitiesRestrictCriteria('glpi_computers');

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        $where[] = new \QueryExpression("glpi_softwares.name REGEXP '^KB[0-9]+$'");

        $summary = [];
        foreach (
            $DB->request([
                'SELECT'     => [
                    'glpi_softwares.name AS kb_code',
                    'COUNT DISTINCT' => 'glpi_computers.id AS installations',
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
            ]) as $row
        ) {
            $kbCode = trim((string) ($row['kb_code'] ?? ''));
            if ($kbCode === '') {
                continue;
            }

            $summary[] = [
                'kb_code' => $kbCode,
                'installations' => (int) ($row['installations'] ?? 0),
            ];
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{title: string, rows: array<int, array<string, mixed>>}
     */
    public static function resolveComputersScope(array $input): array
    {
        $scope = (string) ($input['scope'] ?? '');
        $counterKey = (string) ($input['counter_key'] ?? '');
        $version = trim((string) ($input['version'] ?? ''));
        $town = trim((string) ($input['town'] ?? ''));
        $kbCode = trim((string) ($input['kb_code'] ?? ''));
        $townId = (int) ($input['town_id'] ?? 0);

        $latestWindows = self::getLatestWindowsComputers($townId);
        $latestVersion = self::getLatestWindowsVersion($latestWindows);

        $rows = [];
        $title = __('Computers', 'ticketsstatistics');

        if ($scope === 'counter') {
            if ($counterKey === 'windows') {
                $rows = $latestWindows;
                $title .= ' - ' . __('Computers on Windows 11', 'ticketsstatistics');
            } elseif ($counterKey === 'latest_version') {
                foreach ($latestWindows as $row) {
                    if ((string) ($row['version_os'] ?? '') === $latestVersion) {
                        $rows[] = $row;
                    }
                }
                $title .= ' - ' . ($latestVersion !== ''
                    ? sprintf(__('Computers on latest Windows version (%s)', 'ticketsstatistics'), $latestVersion)
                    : __('Computers on latest Windows version', 'ticketsstatistics'));
            } elseif ($counterKey === 'to_update') {
                foreach ($latestWindows as $row) {
                    if ((string) ($row['version_os'] ?? '') !== $latestVersion) {
                        $rows[] = $row;
                    }
                }
                $title .= ' - ' . __('Computers to update', 'ticketsstatistics');
            } elseif ($counterKey === 'kb_total') {
                $kbMap = self::getKbMapForComputers($townId);
                foreach ($latestWindows as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    if ($id > 0 && isset($kbMap[$id])) {
                        $row['kb_codes'] = $kbMap[$id];
                        $rows[] = $row;
                    }
                }
                $title .= ' - ' . __('Total KB patches deployed', 'ticketsstatistics');
            }
        } elseif ($scope === 'version') {
            foreach ($latestWindows as $row) {
                if ((string) ($row['version_os'] ?? '') === $version) {
                    $rows[] = $row;
                }
            }
            $title .= ' - ' . __('OS version', 'ticketsstatistics') . ': ' . $version;
        } elseif ($scope === 'town_version') {
            foreach ($latestWindows as $row) {
                if ((string) ($row['version_os'] ?? '') === $version && (string) ($row['town'] ?? '') === $town) {
                    $rows[] = $row;
                }
            }
            $title .= ' - ' . $town . ' - ' . __('OS version', 'ticketsstatistics') . ': ' . $version;
        } elseif ($scope === 'kb') {
            $kbMap = self::getKbMapForComputers($townId, $kbCode);
            foreach ($latestWindows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0 && isset($kbMap[$id])) {
                    $row['kb_codes'] = $kbMap[$id];
                    $rows[] = $row;
                }
            }
            $title .= ' - KB: ' . $kbCode;
        }

        return [
            'title' => $title,
            'rows' => $rows,
        ];
    }
}
