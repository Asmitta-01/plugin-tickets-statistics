<?php

namespace GlpiPlugin\Ticketsstatistics;

class ServersStatistics
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getAllServers(int $townId = 0, int $entityId = 0): array
    {
        $DB = \DBConnection::getReadConnection();
        global $CFG_GLPI;

        $where = [
            'glpi_computers.is_deleted'  => 0,
            'glpi_computers.is_template' => 0,
        ] + self::getEntitiesRestrictCriteria('glpi_computers', $entityId);

        if ($townId > 0) {
            $where['glpi_computers.locations_id'] = $townId;
        }

        // Match computers that are Servers or VMware
        $where[] = new \QueryExpression("
            (
                glpi_computers.computertypes_id IN (4, 5)
                OR glpi_computertypes.name LIKE '%server%'
                OR glpi_computertypes.name LIKE '%serveur%'
                OR glpi_computertypes.name LIKE '%vmware%'
                OR glpi_computertypes.name LIKE '%virtu%'
                OR glpi_operatingsystems.name LIKE '%Windows Server%'
                OR glpi_operatingsystems.name LIKE '%VMware ESXi%'
            )
        ");

        $rows = $DB->request([
            'SELECT'    => [
                'glpi_computers.id AS computer_id',
                'glpi_computers.name AS computer_name',
                'glpi_computers.serial',
                'glpi_computers.otherserial',
                'glpi_computers.date_mod',
                'glpi_computers.entities_id AS entity_id',
                'glpi_computers.computertypes_id AS type_id',
                'glpi_computertypes.name AS type_name',
                'glpi_manufacturers.name AS manufacturer_name',
                'glpi_computermodels.name AS model_name',
                'glpi_operatingsystems.name AS os_name',
                'glpi_operatingsystemversions.name AS os_version',
                'glpi_users.realname AS owner_realname',
                'glpi_users.firstname AS owner_firstname',
                'glpi_locations.town',
                'glpi_locations.completename AS location_name',
                'glpi_entities.completename AS entity_name',
            ],
            'FROM'      => 'glpi_computers',
            'LEFT JOIN' => [
                'glpi_computertypes' => [
                    'ON' => [
                        'glpi_computertypes' => 'id',
                        'glpi_computers'     => 'computertypes_id',
                    ],
                ],
                'glpi_manufacturers' => [
                    'ON' => [
                        'glpi_manufacturers' => 'id',
                        'glpi_computers'     => 'manufacturers_id',
                    ],
                ],
                'glpi_computermodels' => [
                    'ON' => [
                        'glpi_computermodels' => 'id',
                        'glpi_computers'      => 'computermodels_id',
                    ],
                ],
                'glpi_items_operatingsystems' => [
                    'ON' => [
                        'glpi_items_operatingsystems' => 'items_id',
                        'glpi_computers'              => 'id',
                        [
                            'AND' => [
                                'glpi_items_operatingsystems.itemtype'   => 'Computer',
                                'glpi_items_operatingsystems.is_deleted' => 0,
                            ],
                        ],
                    ],
                ],
                'glpi_operatingsystems' => [
                    'ON' => [
                        'glpi_operatingsystems'       => 'id',
                        'glpi_items_operatingsystems' => 'operatingsystems_id',
                    ],
                ],
                'glpi_operatingsystemversions' => [
                    'ON' => [
                        'glpi_operatingsystemversions' => 'id',
                        'glpi_items_operatingsystems'  => 'operatingsystemversions_id',
                    ],
                ],
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
            'WHERE'     => $where,
            'ORDER'     => ['glpi_computers.id ASC'],
        ]);

        // Get count of hosted VMs per computer
        $hostedVmsMap = [];
        foreach (
            $DB->request([
                'SELECT'  => [
                    'glpi_computervirtualmachines.computers_id AS computer_id',
                    'COUNT' => '* AS vm_count',
                ],
                'FROM'    => 'glpi_computervirtualmachines',
                'GROUPBY' => ['glpi_computervirtualmachines.computers_id'],
            ]) as $vmRow
        ) {
            $cId = (int) ($vmRow['computer_id'] ?? 0);
            if ($cId > 0) {
                $hostedVmsMap[$cId] = (int) ($vmRow['vm_count'] ?? 0);
            }
        }

        // Get names/uuids of guest VMs
        $guestVmNames = [];
        foreach (
            $DB->request([
                'SELECT' => ['name', 'uuid'],
                'FROM'   => 'glpi_computervirtualmachines',
            ]) as $guestRow
        ) {
            $gName = trim((string) ($guestRow['name'] ?? ''));
            if ($gName !== '') {
                $guestVmNames[mb_strtolower($gName)] = true;
            }
        }

        $result = [];
        $seen = [];

        foreach ($rows as $row) {
            $computerId = (int) ($row['computer_id'] ?? 0);
            if ($computerId <= 0 || isset($seen[$computerId])) {
                continue;
            }
            $seen[$computerId] = true;

            $computerName = (string) ($row['computer_name'] ?? '');
            $typeName = (string) ($row['type_name'] ?? '');
            $modelName = (string) ($row['model_name'] ?? '');
            $manufacturerName = (string) ($row['manufacturer_name'] ?? '');
            $osName = (string) ($row['os_name'] ?? '');
            $osVersion = (string) ($row['os_version'] ?? '');
            $hostedCount = $hostedVmsMap[$computerId] ?? 0;

            $lowerType = mb_strtolower($typeName);
            $lowerModel = mb_strtolower($modelName);
            $lowerName = mb_strtolower($computerName);

            $isGuest = isset($guestVmNames[$lowerName]);

            $isVirtual = (
                $lowerType === 'vmware'
                || $isGuest
                || str_contains($lowerModel, 'vmware')
                || str_contains($lowerModel, 'virtual')
                || str_contains($lowerName, 'docker')
                || str_contains($lowerName, 'ubuntu on')
                || str_contains($lowerName, 'nouvel ordinateur virtuel')
                || ($modelName === '' && $hostedCount === 0)
            );

            if ($hostedCount > 0) {
                $isVirtual = false;
            }

            $serverTypeLabel = $hostedCount > 0
                ? __('Virtualization host', 'ticketsstatistics')
                : ($isVirtual ? __('Virtual', 'ticketsstatistics') : __('Physical', 'ticketsstatistics'));

            $result[] = [
                'id'                => $computerId,
                'name'              => $computerName !== '' ? $computerName : sprintf(__('Server #%d', 'ticketsstatistics'), $computerId),
                'serial'            => (string) ($row['serial'] ?? ''),
                'inventory_number'  => (string) ($row['otherserial'] ?? ''),
                'type_id'           => (int) ($row['type_id'] ?? 0),
                'type_name'         => $typeName !== '' ? $typeName : __('Unknown', 'ticketsstatistics'),
                'is_virtual'        => $isVirtual,
                'is_hypervisor'     => $hostedCount > 0,
                'hosted_vms_count'  => $hostedCount,
                'server_type_label' => $serverTypeLabel,
                'manufacturer'      => $manufacturerName !== '' ? $manufacturerName : '-',
                'model'             => $modelName !== '' ? $modelName : '-',
                'os_name'           => $osName !== '' ? $osName : '-',
                'os_version'        => $osVersion !== '' ? $osVersion : '-',
                'user_name'         => trim((string) (($row['owner_firstname'] ?? '') . ' ' . ($row['owner_realname'] ?? ''))),
                'town'              => (string) (($row['town'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
                'location'          => (string) (($row['location_name'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
                'entity'            => (string) (($row['entity_name'] ?? '') ?: __('Unknown', 'ticketsstatistics')),
                'entity_id'         => (int) ($row['entity_id'] ?? 0),
                'last_update'       => \Html::convDateTime((string) ($row['date_mod'] ?? '')),
                'url'               => ($CFG_GLPI['root_doc'] ?? '') . '/front/computer.form.php?id=' . $computerId,
            ];
        }

        return $result;
    }

    /**
     * @return array{total: int, physical: int, virtual: int, hypervisors: int}
     */
    public static function getServersCounters(int $townId = 0, int $entityId = 0): array
    {
        $servers = self::getAllServers($townId, $entityId);

        $total = count($servers);
        $physical = 0;
        $virtual = 0;
        $hypervisors = 0;

        foreach ($servers as $s) {
            if (!empty($s['is_hypervisor'])) {
                $hypervisors++;
            }

            if (!empty($s['is_virtual'])) {
                $virtual++;
            } else {
                $physical++;
            }
        }

        return [
            'total'       => $total,
            'physical'    => $physical,
            'virtual'     => $virtual,
            'hypervisors' => $hypervisors,
        ];
    }

    /**
     * @return array{labels: string[], values: int[], colors: string[], keys: string[]}
     */
    public static function getServersNatureBreakdown(int $townId = 0, int $entityId = 0): array
    {
        $servers = self::getAllServers($townId, $entityId);
        $counts = [
            'physical'    => 0,
            'virtual'     => 0,
            'hypervisors' => 0,
        ];

        foreach ($servers as $s) {
            if (!empty($s['is_hypervisor'])) {
                $counts['hypervisors']++;
            } elseif (!empty($s['is_virtual'])) {
                $counts['virtual']++;
            } else {
                $counts['physical']++;
            }
        }

        return [
            'labels' => [
                __('Physical', 'ticketsstatistics'),
                __('Virtual', 'ticketsstatistics'),
                __('Virtualization host', 'ticketsstatistics'),
            ],
            'values' => [
                $counts['physical'],
                $counts['virtual'],
                $counts['hypervisors'],
            ],
            'colors' => [
                '#22c55e',
                '#f76707',
                '#ae3ec9 ',
            ],
            'keys'   => [
                'physical_only',
                'virtual',
                'hypervisors',
            ],
        ];
    }

    /**
     * @return array{labels: string[], values: int[], colors: string[]}
     */
    public static function getServersModelBreakdown(int $townId = 0, int $entityId = 0): array
    {
        $servers = self::getAllServers($townId, $entityId);
        $models = [];

        foreach ($servers as $s) {
            $hw = trim(($s['manufacturer'] !== '-' ? $s['manufacturer'] : '') . ' ' . ($s['model'] !== '-' ? $s['model'] : ''));
            if ($hw === '') {
                $hw = !empty($s['is_virtual']) ? __('VM / Container', 'ticketsstatistics') : __('Unspecified', 'ticketsstatistics');
            }
            $models[$hw] = ($models[$hw] ?? 0) + 1;
        }

        arsort($models);

        $palette = [
            '#0ea5e9',
            '#6366f1',
            '#8b5cf6',
            '#ec4899',
            '#f43f5e',
            '#f59e0b',
            '#10b981',
            '#14b8a6',
            '#06b6d4',
            '#3b82f6',
        ];

        $labels = array_keys($models);
        $values = array_values($models);
        $colors = [];
        foreach ($labels as $idx => $lbl) {
            $colors[] = $palette[$idx % count($palette)];
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'colors' => $colors,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{title: string, count: int, rows: array<int, array<string, mixed>>}
     */
    public static function resolveServersScope(array $input): array
    {
        $scope = (string) ($input['scope'] ?? '');
        $counterKey = (string) ($input['counter_key'] ?? '');
        $natureKey = (string) ($input['nature_key'] ?? '');
        $modelKey = (string) ($input['model'] ?? '');
        $townId = (int) ($input['town_id'] ?? 0);
        $entityId = (int) ($input['entity_id'] ?? 0);

        $allServers = self::getAllServers($townId, $entityId);
        $rows = [];
        $title = __('Servers', 'ticketsstatistics');

        if ($scope === 'model' && $modelKey !== '') {
            foreach ($allServers as $s) {
                $hw = trim(($s['manufacturer'] !== '-' ? $s['manufacturer'] : '') . ' ' . ($s['model'] !== '-' ? $s['model'] : ''));
                if ($hw === '') {
                    $hw = !empty($s['is_virtual']) ? __('VM / Container', 'ticketsstatistics') : __('Unspecified', 'ticketsstatistics');
                }
                if (mb_strtolower($hw) === mb_strtolower($modelKey)) {
                    $rows[] = $s;
                }
            }
            $title .= ' - ' . sprintf(__('Hardware / Model: %s', 'ticketsstatistics'), $modelKey);
        } elseif ($counterKey === 'physical' || $natureKey === 'physical') {
            foreach ($allServers as $s) {
                if (empty($s['is_virtual'])) {
                    $rows[] = $s;
                }
            }
            $title .= ' - ' . __('Physical servers', 'ticketsstatistics');
        } elseif ($natureKey === 'physical_only') {
            foreach ($allServers as $s) {
                if (empty($s['is_virtual']) && empty($s['is_hypervisor'])) {
                    $rows[] = $s;
                }
            }
            $title .= ' - ' . __('Physical servers', 'ticketsstatistics');
        } elseif ($counterKey === 'virtual' || $natureKey === 'virtual') {
            foreach ($allServers as $s) {
                if (!empty($s['is_virtual'])) {
                    $rows[] = $s;
                }
            }
            $title .= ' - ' . __('Virtual servers', 'ticketsstatistics');
        } elseif ($counterKey === 'hypervisors' || $natureKey === 'hypervisors') {
            foreach ($allServers as $s) {
                if (!empty($s['is_hypervisor'])) {
                    $rows[] = $s;
                }
            }
            $title .= ' - ' . __('Virtualization hosts', 'ticketsstatistics');
        } else {
            $rows = $allServers;
            $title .= ' - ' . __('Total servers', 'ticketsstatistics');
        }

        return [
            'title' => $title,
            'count' => count($rows),
            'rows'  => $rows,
        ];
    }
}
