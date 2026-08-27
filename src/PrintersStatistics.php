<?php

namespace GlpiPlugin\Ticketsstatistics;


class PrintersStatistics
{
    /**
     * Get total number of printers.
     */
    public static function countPrinters(int $townId = 0, int $manufacturerId = 0): int
    {
        global $DB;

        $where = [
            'glpi_printers.is_deleted'  => 0,
            'glpi_printers.is_template' => 0,
        ] + getEntitiesRestrictCriteria('glpi_printers');

        if ($manufacturerId > 0) {
            $where['glpi_printers.manufacturers_id'] = $manufacturerId;
        }

        $leftJoin = [];
        if ($townId > 0) {
            $leftJoin['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_printers'  => 'locations_id',
                ],
            ];
            $where['glpi_locations.id'] = $townId;
        }

        $iter = $DB->request([
            'COUNT'     => 'cpt',
            'FROM'      => 'glpi_printers',
            'LEFT JOIN' => $leftJoin,
            'WHERE'     => $where,
        ]);
        $row = $iter->current();

        return (int) ($row['cpt'] ?? 0);
    }

    /**
     * Get sum of all printed pages (last_pages_counter).
     */
    public static function countTotalPages(int $townId = 0, int $manufacturerId = 0, string $period = 'thisyear', ?string $dateFrom = null, ?string $dateTo = null): int
    {
        global $DB;

        $where = [
            'glpi_printers.is_deleted'  => 0,
            'glpi_printers.is_template' => 0,
        ] + getEntitiesRestrictCriteria('glpi_printers');

        if ($manufacturerId > 0) {
            $where['glpi_printers.manufacturers_id'] = $manufacturerId;
        }

        $leftJoin = [
            'glpi_printerlogs' => [
                'ON' => [
                    'glpi_printers' => 'id',
                    'glpi_printerlogs' => 'printers_id',
                ],
            ]
        ];

        if ($townId > 0) {
            $leftJoin['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_printers'  => 'locations_id',
                ],
            ];
            $where['glpi_locations.id'] = $townId;
        }

        PeriodFilter::apply($where, 'glpi_printerlogs', $period, $dateFrom, $dateTo);

        // A simpler query that groups by printer, gets the delta, then PHP sums it up
        $totalDelta = 0;
        $hasLogs = false;
        
        $req = $DB->request([
            'SELECT' => [
                new \QueryExpression("MAX(glpi_printerlogs.total_pages) - MIN(glpi_printerlogs.total_pages) AS delta")
            ],
            'FROM' => 'glpi_printers',
            'LEFT JOIN' => $leftJoin,
            'WHERE' => $where,
            'GROUPBY' => ['glpi_printers.id'],
            'HAVING' => ['delta' => ['>', 0]]
        ]);
        
        foreach ($req as $row) {
            $hasLogs = true;
            $totalDelta += (int) $row['delta'];
        }

        if ($hasLogs) {
            return $totalDelta;
        }

        // Fallback if no logs
        $whereFallback = [
            'glpi_printers.is_deleted'  => 0,
            'glpi_printers.is_template' => 0,
        ] + getEntitiesRestrictCriteria('glpi_printers');

        if ($manufacturerId > 0) {
            $whereFallback['glpi_printers.manufacturers_id'] = $manufacturerId;
        }

        $leftJoinFallback = [];
        if ($townId > 0) {
            $leftJoinFallback['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_printers'  => 'locations_id',
                ],
            ];
            $whereFallback['glpi_locations.id'] = $townId;
        }

        $iter = $DB->request([
            'SELECT'    => ['SUM' => 'glpi_printers.last_pages_counter AS total'],
            'FROM'      => 'glpi_printers',
            'LEFT JOIN' => $leftJoinFallback,
            'WHERE'     => $whereFallback,
        ]);
        $row = $iter->current();

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Get printers count grouped by model.
     */
    public static function getPrintersByModel(int $townId = 0, int $manufacturerId = 0): array
    {
        global $DB;

        $where = [
            'glpi_printers.is_deleted'  => 0,
            'glpi_printers.is_template' => 0,
        ] + getEntitiesRestrictCriteria('glpi_printers');

        if ($manufacturerId > 0) {
            $where['glpi_printers.manufacturers_id'] = $manufacturerId;
        }

        $leftJoin = [
            'glpi_printermodels' => [
                'ON' => [
                    'glpi_printermodels' => 'id',
                    'glpi_printers'      => 'printermodels_id',
                ],
            ]
        ];

        if ($townId > 0) {
            $leftJoin['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_printers'  => 'locations_id',
                ],
            ];
            $where['glpi_locations.id'] = $townId;
        }

        $results = [];
        $count = 0;
        foreach (
            $DB->request([
                'SELECT'    => [
                    'glpi_printermodels.name AS model',
                    'COUNT' => 'glpi_printers.id AS cpt'
                ],
                'FROM'      => 'glpi_printers',
                'LEFT JOIN' => $leftJoin,
                'WHERE'     => $where,
                'GROUPBY'   => ['glpi_printermodels.name'],
                'ORDER'     => ['cpt DESC']
            ]) as $row
        ) {
            if ($count >= 8) {
                break;
            }
            $model = trim((string) ($row['model'] ?? ''));
            if ($model === '') {
                $model = __('Unknown', 'ticketsstatistics');
            }
            $results[] = [
                'model' => $model,
                'count' => (int) ($row['cpt'] ?? 0),
            ];
            $count++;
        }

        return $results;
    }

    /**
     * Get top printers by total pages printed.
     */
    public static function getTopPrintersByPages(int $townId = 0, int $manufacturerId = 0, int $limit = 8, string $period = 'thisyear', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        global $DB;

        $where = [
            'glpi_printers.is_deleted'  => 0,
            'glpi_printers.is_template' => 0,
        ] + getEntitiesRestrictCriteria('glpi_printers');

        if ($manufacturerId > 0) {
            $where['glpi_printers.manufacturers_id'] = $manufacturerId;
        }

        $leftJoin = [
            'glpi_printerlogs' => [
                'ON' => [
                    'glpi_printers' => 'id',
                    'glpi_printerlogs' => 'printers_id',
                ],
            ]
        ];

        if ($townId > 0) {
            $leftJoin['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_printers'  => 'locations_id',
                ],
            ];
            $where['glpi_locations.id'] = $townId;
        }

        PeriodFilter::apply($where, 'glpi_printerlogs', $period, $dateFrom, $dateTo);

        $results = [];
        // Calculer le delta (max - min) sur la période
        foreach (
            $DB->request([
                'SELECT'    => [
                    'glpi_printers.name AS name',
                    new \QueryExpression("MAX(glpi_printerlogs.total_pages) - MIN(glpi_printerlogs.total_pages) AS pages")
                ],
                'FROM'      => 'glpi_printers',
                'LEFT JOIN' => $leftJoin,
                'WHERE'     => $where,
                'GROUPBY'   => ['glpi_printers.id', 'glpi_printers.name'],
                'HAVING'    => ['pages' => ['>', 0]],
                'ORDER'     => ['pages DESC'],
                'LIMIT'     => $limit
            ]) as $row
        ) {
            $results[] = [
                'name'  => (string) ($row['name'] ?? __('Unknown', 'ticketsstatistics')),
                'pages' => (int) ($row['pages'] ?? 0),
            ];
        }

        // Si la table glpi_printerlogs est vide (aucun delta trouvé), on utilise last_pages_counter par défaut
        if (empty($results)) {
            $whereFallback = [
                'glpi_printers.is_deleted'  => 0,
                'glpi_printers.is_template' => 0,
                'glpi_printers.last_pages_counter' => ['>', 0],
            ] + getEntitiesRestrictCriteria('glpi_printers');
            
            if ($manufacturerId > 0) {
                $whereFallback['glpi_printers.manufacturers_id'] = $manufacturerId;
            }
            if ($townId > 0) {
                $whereFallback['glpi_printers.locations_id'] = $townId;
            }

            foreach (
                $DB->request([
                    'SELECT'    => [
                        'glpi_printers.name AS name',
                        'glpi_printers.last_pages_counter AS pages'
                    ],
                    'FROM'      => 'glpi_printers',
                    'WHERE'     => $whereFallback,
                    'ORDER'     => ['pages DESC'],
                    'LIMIT'     => $limit
                ]) as $row
            ) {
                $results[] = [
                    'name'  => (string) ($row['name'] ?? __('Unknown', 'ticketsstatistics')),
                    'pages' => (int) ($row['pages'] ?? 0),
                ];
            }
        }

        return $results;
    }

    /**
     * Get evolution of global page counters over the selected period.
     */
    public static function getPagesEvolution(int $townId = 0, int $manufacturerId = 0, string $period = 'thisyear', ?string $dateFrom = null, ?string $dateTo = null): array
    {
        global $DB;

        $where = [
            'glpi_printers.is_deleted'  => 0,
            'glpi_printers.is_template' => 0,
        ] + getEntitiesRestrictCriteria('glpi_printers');

        if ($manufacturerId > 0) {
            $where['glpi_printers.manufacturers_id'] = $manufacturerId;
        }

        $leftJoin = [
            'glpi_printers' => [
                'ON' => [
                    'glpi_printers' => 'id',
                    'glpi_printerlogs' => 'printers_id',
                ],
            ]
        ];

        if ($townId > 0) {
            $leftJoin['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_printers'  => 'locations_id',
                ],
            ];
            $where['glpi_locations.id'] = $townId;
        }

        $where[] = new \QueryExpression("glpi_printerlogs.total_pages > 0");
        PeriodFilter::apply($where, 'glpi_printerlogs', $period, $dateFrom, $dateTo);

        $printerMonthMax = [];
        
        $req = $DB->request([
            'SELECT' => [
                'glpi_printerlogs.printers_id',
                new \QueryExpression("DATE_FORMAT(glpi_printerlogs.date, '%Y-%m') AS month"),
                new \QueryExpression("MAX(glpi_printerlogs.total_pages) AS max_pages")
            ],
            'FROM' => 'glpi_printerlogs',
            'INNER JOIN' => $leftJoin,
            'WHERE' => $where,
            'GROUPBY' => [
                'glpi_printerlogs.printers_id',
                new \QueryExpression("DATE_FORMAT(glpi_printerlogs.date, '%Y-%m')")
            ]
        ]);

        foreach ($req as $row) {
            $month = $row['month'];
            if (!isset($printerMonthMax[$month])) {
                $printerMonthMax[$month] = 0;
            }
            $printerMonthMax[$month] += (int) $row['max_pages'];
        }

        ksort($printerMonthMax);
        
        $results = [];
        foreach ($printerMonthMax as $month => $total) {
            $results[] = [
                'month' => $month,
                'total' => $total,
            ];
        }
        
        // If no data, return a flat line or empty
        return $results;
    }

    /**
     * Get printers count grouped by town.
     */
    public static function getPrintersByTown(int $townId = 0, int $manufacturerId = 0): array
    {
        global $DB;

        $where = [
            'glpi_printers.is_deleted'  => 0,
            'glpi_printers.is_template' => 0,
        ] + getEntitiesRestrictCriteria('glpi_printers');

        if ($manufacturerId > 0) {
            $where['glpi_printers.manufacturers_id'] = $manufacturerId;
        }

        $leftJoin = [
            'glpi_locations' => [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_printers'  => 'locations_id',
                ],
            ]
        ];

        if ($townId > 0) {
            $where['glpi_locations.id'] = $townId;
        }

        $results = [];
        foreach (
            $DB->request([
                'SELECT'    => [
                    'glpi_locations.town AS town',
                    'COUNT' => 'glpi_printers.id AS cpt'
                ],
                'FROM'      => 'glpi_printers',
                'LEFT JOIN' => $leftJoin,
                'WHERE'     => $where,
                'GROUPBY'   => ['glpi_locations.town'],
                'ORDER'     => ['cpt DESC']
            ]) as $row
        ) {
            $town = trim((string) ($row['town'] ?? ''));
            if ($town === '') {
                $town = __('Unknown', 'ticketsstatistics');
            }
            $results[] = [
                'town'  => $town,
                'count' => (int) ($row['cpt'] ?? 0),
            ];
        }

        return $results;
    }

    /**
     * Get distribution of cartridge/ink levels from SNMP infos.
     */
    public static function getCartridgesLevelDistribution(int $townId = 0, int $manufacturerId = 0): array
    {
        global $DB;

        $where = [
            'glpi_printers.is_deleted'  => 0,
            'glpi_printers.is_template' => 0,
        ] + getEntitiesRestrictCriteria('glpi_printers');

        $where[] = new \QueryExpression("glpi_printers_cartridgeinfos.value >= 0 AND glpi_printers_cartridgeinfos.value <= 100");


        if ($manufacturerId > 0) {
            $where['glpi_printers.manufacturers_id'] = $manufacturerId;
        }

        $leftJoin = [
            'glpi_printers_cartridgeinfos' => [
                'ON' => [
                    'glpi_printers_cartridgeinfos' => 'printers_id',
                    'glpi_printers'                => 'id',
                ],
            ]
        ];

        if ($townId > 0) {
            $leftJoin['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_printers'  => 'locations_id',
                ],
            ];
            $where['glpi_locations.id'] = $townId;
        }

        $levels = [
            'critical' => 0, // < 10%
            'low'      => 0, // 10% - 30%
            'good'     => 0, // 30% - 70%
            'full'     => 0, // >= 70%
        ];

        foreach (
            $DB->request([
                'SELECT'    => ['glpi_printers_cartridgeinfos.value AS val'],
                'FROM'      => 'glpi_printers',
                'INNER JOIN' => $leftJoin,
                'WHERE'     => $where,
            ]) as $row
        ) {
            $val = (int) ($row['val'] ?? 0);
            if ($val < 10) {
                $levels['critical']++;
            } elseif ($val < 30) {
                $levels['low']++;
            } elseif ($val < 70) {
                $levels['good']++;
            } else {
                $levels['full']++;
            }
        }

        return $levels;
    }

    /**
     * Get counts of physical cartridges by status (new, in use, empty)
     */
    public static function getCartridgesStatuses(int $townId = 0, int $manufacturerId = 0): array
    {
        global $DB;

        // Note: For physical cartridges we join glpi_printers to filter by town/manufacturer of the printer they belong to, if they are attached.
        $where = getEntitiesRestrictCriteria('glpi_cartridges');

        $leftJoin = [
            'glpi_printers' => [
                'ON' => [
                    'glpi_printers' => 'id',
                    'glpi_cartridges' => 'printers_id',
                ],
            ],
            'glpi_cartridgeitems' => [
                'ON' => [
                    'glpi_cartridgeitems' => 'id',
                    'glpi_cartridges' => 'cartridgeitems_id',
                ],
            ]
        ];

        if ($manufacturerId > 0) {
            $where['glpi_cartridgeitems.manufacturers_id'] = $manufacturerId;
        }

        if ($townId > 0) {
            $leftJoin['glpi_locations'] = [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_printers'  => 'locations_id', // Town of the printer they are attached to
                ],
            ];
            $where['glpi_locations.id'] = $townId;
        }

        $statuses = [
            'new' => 0,
            'used' => 0,
            'empty' => 0,
        ];

        foreach (
            $DB->request([
                'SELECT'    => ['date_use', 'date_out'],
                'FROM'      => 'glpi_cartridges',
                'LEFT JOIN' => $leftJoin,
                'WHERE'     => $where,
            ]) as $row
        ) {
            if (!empty($row['date_out'])) {
                $statuses['empty']++;
            } elseif (!empty($row['date_use'])) {
                $statuses['used']++;
            } else {
                $statuses['new']++;
            }
        }

        return $statuses;
    }

    /**
     * Get detailed list of printers for the table.
     */
    public static function getPrintersList(int $townId = 0, int $manufacturerId = 0): array
    {
        global $DB, $CFG_GLPI;

        $where = [
            'glpi_printers.is_deleted'  => 0,
            'glpi_printers.is_template' => 0,
        ] + \GlpiPlugin\Ticketsstatistics\AssetStatistics::getEntitiesRestrictCriteria('glpi_printers');

        if ($manufacturerId > 0) {
            $where['glpi_printers.manufacturers_id'] = $manufacturerId;
        }

        $leftJoin = [
            'glpi_printermodels' => [
                'ON' => [
                    'glpi_printermodels' => 'id',
                    'glpi_printers'      => 'printermodels_id',
                ],
            ],
            'glpi_manufacturers' => [
                'ON' => [
                    'glpi_manufacturers' => 'id',
                    'glpi_printers'      => 'manufacturers_id',
                ],
            ],
            'glpi_locations' => [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_printers'  => 'locations_id',
                ],
            ],
        ];

        if ($townId > 0) {
            $where['glpi_locations.id'] = $townId;
        }

        $results = [];
        foreach (
            $DB->request([
                'SELECT'    => [
                    'glpi_printers.id',
                    'glpi_printers.name',
                    'glpi_printers.serial',
                    'glpi_printers.otherserial',
                    'glpi_printers.last_pages_counter',
                    'glpi_printermodels.name AS model_name',
                    'glpi_manufacturers.name AS manufacturer_name',
                    'glpi_locations.town AS town',
                    'glpi_locations.name AS location_name',
                ],
                'FROM'      => 'glpi_printers',
                'LEFT JOIN' => $leftJoin,
                'WHERE'     => $where,
                'ORDER'     => ['glpi_printers.name ASC']
            ]) as $row
        ) {
            $id = (int) $row['id'];
            $results[] = [
                'id'                 => $id,
                'name'               => (string) ($row['name'] ?? ''),
                'serial'             => (string) ($row['serial'] ?? ''),
                'inventory_number'   => (string) ($row['otherserial'] ?? ''),
                'pages'              => (int) ($row['last_pages_counter'] ?? 0),
                'model'              => (string) ($row['model_name'] ?? ''),
                'manufacturer'       => (string) ($row['manufacturer_name'] ?? ''),
                'town'               => (string) ($row['town'] ?? ''),
                'location'           => (string) ($row['location_name'] ?? ''),
                'url'                => ($CFG_GLPI['root_doc'] ?? '') . '/front/printer.form.php?id=' . $id,
            ];
        }

        return $results;
    }
}
