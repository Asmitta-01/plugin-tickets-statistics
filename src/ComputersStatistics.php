<?php

namespace GlpiPlugin\Ticketsstatistics;

class ComputersStatistics
{
    /**
     * @return array<int, int>
     */
    private static function getEntitiesRestrictCriteria(string $table, int $entityId = 0): array
    {
        $dbu = new \DbUtils();
        $currentEntityId = $entityId > 0 ? $entityId : \Session::getActiveEntity();
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

    private static function isProcessorGenerationLowerThan8(string $processor): bool
    {
        if (preg_match('/\\b(11th|12th|13th)\\b/i', $processor) || stripos($processor, 'Ultra') !== false) {
            return false;
        }

        if (preg_match('/\\b(Core\\s+m3-8100Y|N200)\\b/i', $processor)) {
            return true;
        }

        if (preg_match('/i[3579]-([0-9]{4,5})/i', $processor, $matches)) {
            $model = $matches[1];
            $generation = strlen($model) === 4
                ? (int) $model[0]
                : (int) substr($model, 0, 2);

            return $generation < 8;
        }

        if (preg_match('/Xeon\\s+\\w+\\s+([0-9]{4})/i', $processor, $matches)) {
            $generation = (int) $matches[1][0];
            return $generation < 8;
        }

        return false;
    }

    /**
     * @return array<int, bool>
     */
    private static function getObsoleteWindowsComputerIds(int $townId, int $entityId = 0): array
    {
        $DB = \DBConnection::getReadConnection();

        $where = [
            'glpi_computers.is_deleted'                    => 0,
            'glpi_computers.is_template'                   => 0,
            'glpi_items_softwareversions.itemtype'         => 'Computer',
            'glpi_items_softwareversions.is_deleted'       => 0,
            'glpi_items_softwareversions.is_deleted_item'  => 0,
            'glpi_softwares.name'                          => ['LIKE', 'Microsoft Windows 11%'],
            'glpi_items_deviceprocessors.itemtype'         => 'Computer',
        ] + self::getEntitiesRestrictCriteria('glpi_computers', $entityId);

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        $obsoleteIds = [];
        foreach (
            $DB->request([
                'SELECT'     => [
                    'glpi_computers.id AS computer_id',
                    'glpi_deviceprocessors.designation AS processor_name',
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
                    'glpi_items_deviceprocessors' => [
                        'ON' => [
                            'glpi_items_deviceprocessors' => 'items_id',
                            'glpi_computers'              => 'id',
                        ],
                    ],
                    'glpi_deviceprocessors' => [
                        'ON' => [
                            'glpi_deviceprocessors'       => 'id',
                            'glpi_items_deviceprocessors' => 'deviceprocessors_id',
                        ],
                    ],
                ],
                'WHERE'      => $where,
            ]) as $row
        ) {
            $computerId = (int) ($row['computer_id'] ?? 0);
            $processorName = (string) ($row['processor_name'] ?? '');
            if ($computerId <= 0 || $processorName === '') {
                continue;
            }

            if (self::isProcessorGenerationLowerThan8($processorName)) {
                $obsoleteIds[$computerId] = true;
            }
        }

        return $obsoleteIds;
    }

    public static function countObsoleteWindowsComputers(int $townId, int $entityId = 0): int
    {
        return count(self::getObsoleteWindowsComputerIds($townId, $entityId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getLatestWindowsComputers(int $townId, int $entityId = 0): array
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
        ] + self::getEntitiesRestrictCriteria('glpi_computers', $entityId);

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        $rows = $DB->request([
            'SELECT'     => [
                'glpi_computers.id AS computer_id',
                'glpi_computers.name AS computer_name',
                'glpi_users.realname AS owner_realname',
                'glpi_users.firstname AS owner_firstname',
                'glpi_computers.serial',
                'glpi_computers.otherserial',
                'glpi_computers.date_mod',
                'glpi_computers.entities_id AS entity_id',
                'glpi_locations.town',
                'glpi_entities.completename AS entity_name',
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
            ],
            'LEFT JOIN'  => [
                'glpi_users' => [
                    'ON' => [
                        'glpi_users'     => 'id',
                        'glpi_computers' => 'users_id',
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
                'user_name' => (string) ($row['owner_firstname'] ?? '') . ' ' . ($row['owner_realname'] ?? ''),
                'serial' => (string) ($row['serial'] ?? ''),
                'inventory_number' => (string) ($row['otherserial'] ?? ''),
                'version_os' => (string) ($row['version_os'] ?? ''),
                'town' => (string) (($row['town'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
                'entity' => (string) (($row['entity_name'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
                'entity_id' => (int) ($row['entity_id'] ?? 0),
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

    public static function getEntityIdByCompleteName(string $name): int
    {
        global $DB;
        $iterator = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_entities',
            'WHERE'  => [
                'completename' => $name,
            ],
        ]);
        $id = count($iterator) ? $iterator->current()['id'] : -1;
        return $id;
    }

    /**
     * Classifies a computer type name into a category key.
     *
     * @param string $typeName The name of the computer type.
     * @return string The category key ('laptop', 'desktop', 'server', 'vmware', 'other').
     */
    public static function classifyComputerType(string $typeName): string
    {
        $name = mb_strtolower(trim($typeName));
        if ($name === '') {
            return 'other';
        }

        if (str_contains($name, 'vmware') || str_contains($name, 'virtual') || preg_match('/\bvm\b/', $name) === 1) {
            return 'vmware';
        }

        if (str_contains($name, 'server') || str_contains($name, 'serveur')) {
            return 'server';
        }

        if (str_contains($name, 'laptop') || str_contains($name, 'portable') || str_contains($name, 'notebook')) {
            return 'laptop';
        }

        if (str_contains($name, 'desktop') || str_contains($name, 'bureau')) {
            return 'desktop';
        }

        return 'other';
    }

    /**
     * Returns the IDs of computer types based on its classification key.
     * 
     * @param string $typeKey The classification key ('laptop', 'desktop', 'server', 'vmware', 'other').
     * @return int[] The IDs of the computer types, or [] if not found.
     */
    public static function getComputerTypeIdsByKey(string $typeKey): array
    {
        global $DB;

        $ids = [];
        foreach (
            $DB->request([
                'SELECT' => ['id', 'name'],
                'FROM'   => 'glpi_computertypes',
            ]) as $row
        ) {
            $currentId = (int) ($row['id'] ?? 0);
            if ($currentId <= 0) {
                continue;
            }

            if (self::classifyComputerType((string) ($row['name'] ?? '')) === $typeKey) {
                $ids[] = $currentId;
            }
        }

        return $ids;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getComputersByTownAndType(string $town, string $typeKey, int $townId, int $entityId = 0): array
    {
        $DB = \DBConnection::getReadConnection();
        global $CFG_GLPI;

        $where = [
            'glpi_computers.is_deleted'       => 0,
            'glpi_computers.is_template'      => 0,
            'glpi_computers.manufacturers_id' => ['<>', 0],
        ] + self::getEntitiesRestrictCriteria('glpi_computers', $entityId);

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        $rows = [];
        foreach (
            $DB->request([
                'SELECT'    => [
                    'glpi_computers.id AS computer_id',
                    'glpi_computers.name AS computer_name',
                    'glpi_computers.serial',
                    'glpi_computers.otherserial',
                    'glpi_computers.date_mod',
                    'glpi_computers.entities_id AS entity_id',
                    'glpi_users.realname AS owner_realname',
                    'glpi_users.firstname AS owner_firstname',
                    'glpi_locations.town',
                    'glpi_computertypes.name AS computer_type_name',
                ],
                'FROM'      => 'glpi_computers',
                'LEFT JOIN' => [
                    'glpi_users' => [
                        'ON' => [
                            'glpi_users'     => 'id',
                            'glpi_computers' => 'users_id',
                        ],
                    ],
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
                'WHERE'     => $where,
                'ORDER'     => ['glpi_computers.id ASC'],
            ]) as $row
        ) {
            $currentTown = trim((string) ($row['town'] ?? ''));
            if ($currentTown === '') {
                $currentTown = __('Unknown', 'ticketsstatistics');
            }

            if ($townId <= 0 && $town !== '' && $town !== $currentTown) {
                continue;
            }

            $currentTypeKey = self::classifyComputerType((string) ($row['computer_type_name'] ?? ''));
            if ($typeKey !== '' && $currentTypeKey !== $typeKey) {
                continue;
            }

            $computerId = (int) ($row['computer_id'] ?? 0);
            if ($computerId <= 0) {
                continue;
            }

            $computerName = (string) ($row['computer_name'] ?? '');
            $rows[] = [
                'id' => $computerId,
                'name' => $computerName !== '' ? $computerName : sprintf(__('Computer #%d', 'ticketsstatistics'), $computerId),
                'user_name' => trim((string) (($row['owner_firstname'] ?? '') . ' ' . ($row['owner_realname'] ?? ''))),
                'serial' => (string) ($row['serial'] ?? ''),
                'inventory_number' => (string) ($row['otherserial'] ?? ''),
                'version_os' => '',
                'town' => $currentTown,
                'entity_id' => (int) ($row['entity_id'] ?? 0),
                'last_update' => \Html::convDateTime((string) ($row['date_mod'] ?? '')),
                'kb_codes' => [],
                'url' => $CFG_GLPI['root_doc'] . '/front/computer.form.php?id=' . $computerId,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function getKbMapForComputers(int $townId, string $kbCode = '', bool $installed = true, int $entityId = 0): array
    {
        $DB = \DBConnection::getReadConnection();

        $baseWhere = [
            'glpi_computers.is_deleted'  => 0,
            'glpi_computers.is_template' => 0,
        ] + self::getEntitiesRestrictCriteria('glpi_computers', $entityId);

        if ($townId > 0) {
            $baseWhere['glpi_computers.locations_id'] = $townId;
        }

        if (!$installed) {
            if ($kbCode === '') {
                // Un KB précis est nécessaire pour définir "ne l'a pas installé"
                return [];
            }

            // 1) Tous les PC éligibles (périmètre de base, sans jointure logiciel)
            $allComputerIds = [];
            foreach (
                $DB->request([
                    'SELECT' => ['glpi_computers.id AS computer_id'],
                    'FROM'   => 'glpi_computers',
                    'WHERE'  => $baseWhere,
                ]) as $row
            ) {
                $id = (int) ($row['computer_id'] ?? 0);
                if ($id > 0) {
                    $allComputerIds[$id] = true;
                }
            }

            // 2) PC ayant installé ce KB précis
            $installedWhere = $baseWhere + [
                'glpi_items_softwareversions.itemtype'        => 'Computer',
                'glpi_items_softwareversions.is_deleted'      => 0,
                'glpi_items_softwareversions.is_deleted_item' => 0,
                'glpi_softwares.name'                         => $kbCode,
            ];

            $installedComputerIds = [];
            foreach (
                $DB->request([
                    'SELECT'     => ['glpi_computers.id AS computer_id'],
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
                    'WHERE'      => $installedWhere,
                    'GROUPBY'    => ['glpi_computers.id'],
                ]) as $row
            ) {
                $id = (int) ($row['computer_id'] ?? 0);
                if ($id > 0) {
                    $installedComputerIds[$id] = true;
                }
            }

            // 3) Différence : éligibles mais pas dans "installés"
            $map = [];
            foreach (array_keys($allComputerIds) as $computerId) {
                if (!isset($installedComputerIds[$computerId])) {
                    $map[$computerId] = [$kbCode];
                }
            }

            return $map;
        }

        $where = $baseWhere + [
            'glpi_items_softwareversions.itemtype'        => 'Computer',
            'glpi_items_softwareversions.is_deleted'      => 0,
            'glpi_items_softwareversions.is_deleted_item' => 0,
        ];

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
            $currentKb  = (string) ($row['kb_code'] ?? '');
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
    public static function getKbInstallationsSummary(int $townId, int $entityId = 0): array
    {
        $DB = \DBConnection::getReadConnection();

        $where = [
            'glpi_computers.is_deleted'                    => 0,
            'glpi_computers.is_template'                   => 0,
            'glpi_items_softwareversions.itemtype'         => 'Computer',
            'glpi_items_softwareversions.is_deleted'       => 0,
            'glpi_items_softwareversions.is_deleted_item'  => 0,
        ] + self::getEntitiesRestrictCriteria('glpi_computers', $entityId);

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
        $kbDataset = trim((string) ($input['kb_dataset'] ?? 'installed'));
        $townId = (int) ($input['town_id'] ?? 0);
        $entityId = (int) ($input['entity_id'] ?? 0);
        $typeKey = trim((string) ($input['type_key'] ?? ''));
        $entityScopeId = (int) ($input['entity_scope_id'] ?? 0);
        $entityScopeName = trim((string) ($input['entity'] ?? ''));

        $latestWindows = self::getLatestWindowsComputers($townId, $entityId);
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
            } elseif ($counterKey === 'obsolete') {
                $obsoleteIds = self::getObsoleteWindowsComputerIds($townId, $entityId);
                foreach ($latestWindows as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    if ($id > 0 && isset($obsoleteIds[$id])) {
                        $rows[] = $row;
                    }
                }
                $title .= ' - ' . __('Obsolete computers', 'ticketsstatistics');
            } elseif ($counterKey === 'kb_total') {
                $kbMap = self::getKbMapForComputers($townId, '', true, $entityId);
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
        } elseif ($scope === 'town_type') {
            $rows = self::getComputersByTownAndType($town, $typeKey, $townId, $entityId);
            $title .= ' - ' . $town . ' - ' . __('Type', 'ticketsstatistics') . ': ' . $typeKey;
        } elseif ($scope === 'entity_version') {
            foreach ($latestWindows as $row) {
                if ((string) ($row['version_os'] ?? '') !== $version) {
                    continue;
                }

                if ($entityScopeId > 0) {
                    if ((int) ($row['entity_id'] ?? 0) !== $entityScopeId) {
                        continue;
                    }
                } elseif ($entityScopeName !== '' && (string) ($row['entity'] ?? '') !== $entityScopeName) {
                    continue;
                }

                $rows[] = $row;
            }

            $entityLabel = $entityScopeName !== '' ? $entityScopeName : __('Unknown', 'ticketsstatistics');
            $title .= ' - ' . $entityLabel . ' - ' . __('OS version', 'ticketsstatistics') . ': ' . $version;
        } elseif ($scope === 'kb') {
            $installed = $kbDataset === 'installed';
            $kbMap = self::getKbMapForComputers($townId, $kbCode, $installed, $entityId);
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
    public static function showGroupButtons(string $id, string $successLabel = '', string $dangerLabel = '', ?string $successTooltip = null, ?string $dangerTooltip = null): void
    {
        echo '<div class="btn-group btn-group-sm" role="group" aria-label="" id="' . $id . '">';
        if ($successLabel != '') {
            echo '<div class="btn">' . __('Status', 'ticketsstatistics') . '</div>';
            echo '<div class="btn"' . ($successTooltip ? ' data-bs-toggle="tooltip" title="' . $successTooltip . '"' : '') . ' data-bs-placement="bottom">';
            echo '<span class="badge me-1" style="background-color: #22c55e;"></span>';
            echo $successLabel;
            echo '</div>';
        }
        if ($dangerLabel != '') {
            echo '<div class="btn"' . ($dangerTooltip ? ' data-bs-toggle="tooltip" title="' . $dangerTooltip . '"' : '') . ' data-bs-placement="bottom">';
            echo '<span class="badge me-1" style="background-color: #ef4444;"></span>';
            echo $dangerLabel;
            echo '</div>';
        }
        echo '</div>';
    }
}
