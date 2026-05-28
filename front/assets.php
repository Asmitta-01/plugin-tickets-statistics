<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

use GlpiPlugin\Ticketsstatistics\AssetStatistics;

\Session::checkCentralAccess();

$DB = \DBConnection::getReadConnection();
global $CFG_GLPI;

if (!\Session::haveRight('dashboard', READ)) {
    \Html::displayRightError();
}

$townId = (int) ($_GET['town'] ?? 0);
$manufacturerId = (int) ($_GET['manufacturer'] ?? 0);

$manufacturers = [];
foreach (
    $DB->request([
        'SELECT' => ['id', 'name'],
        'FROM'   => 'glpi_manufacturers',
        'ORDER'  => ['name ASC'],
    ]) as $row
) {
    $manufacturers[] = [
        'id'   => (int) $row['id'],
        'name' => (string) $row['name'],
    ];
}

$counts = [
    'computers'       => AssetStatistics::countAssets('glpi_computers', $townId, $manufacturerId),
    'network_devices' => AssetStatistics::countAssets('glpi_networkequipments', $townId, $manufacturerId),
    'monitors'        => AssetStatistics::countAssets('glpi_monitors', $townId, $manufacturerId),
];
$totalAssets = array_sum($counts);

$showTownChart = $townId <= 0;
$showManufacturerChart = $manufacturerId <= 0;

$assetDatasets = [
    [
        'key'   => 'computers',
        'label' => __('Computers', 'ticketsstatistics'),
        'color' => '#C00000',
    ],
    [
        'key'   => 'network_devices',
        'label' => __('Network devices', 'ticketsstatistics'),
        'color' => '#16a34a',
    ],
    [
        'key'   => 'monitors',
        'label' => __('Monitors', 'ticketsstatistics'),
        'color' => '#f59e0b',
    ],
];

$townBreakdown = $showTownChart ? AssetStatistics::getCountsByTown($manufacturerId) : [];
$manufacturerBreakdown = $showManufacturerChart ? AssetStatistics::getCountsByManufacturer($townId) : [];

$townChart = ['labels' => [], 'datasets' => []];
if ($showTownChart) {
    $townChart['labels'] = array_keys($townBreakdown);
    foreach ($assetDatasets as $dataset) {
        $values = [];
        foreach ($townBreakdown as $row) {
            $values[] = (int) ($row[$dataset['key']] ?? 0);
        }

        $townChart['datasets'][] = [
            'label'           => $dataset['label'],
            'data'            => $values,
            'backgroundColor' => $dataset['color'],
        ];
    }
}

$manufacturerChart = ['labels' => [], 'datasets' => []];
if ($showManufacturerChart) {
    $manufacturerChart['labels'] = array_keys($manufacturerBreakdown);
    foreach ($assetDatasets as $dataset) {
        $values = [];
        foreach ($manufacturerBreakdown as $row) {
            $values[] = (int) ($row[$dataset['key']] ?? 0);
        }

        $manufacturerChart['datasets'][] = [
            'label'           => $dataset['label'],
            'data'            => $values,
            'backgroundColor' => $dataset['color'],
        ];
    }
}

\Html::header(__('Assets Statistics', 'ticketsstatistics'), '', 'assets');
?>

<div class="container-fluid mt-3" id="ts-assets-content">
    <div class="d-flex justify-content-between align-items-center g-3 mb-3">
        <h2 class="page-title mb-0">
            <i class="ti ti-devices me-2"></i>
            <?= __('Assets Statistics', 'ticketsstatistics') ?>
        </h2>
    </div>

    <div class="alert alert-secondary mb-3">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label for="ts-assets-town" class="form-label mb-1 fw-semibold"><?= __('Town', 'ticketsstatistics') ?></label>
                <div id="ts-assets-town">
                    <?php \Location::dropdown([
                        'name' => 'town',
                        'display_emptychoice' => true,
                        'emptylabel' => __('All towns', 'ticketsstatistics'),
                        'value' => $_GET['town'] ?? 0,
                        'addicon' => false,
                        'comments' => false,
                        'class' => 'form-select form-select-sm',
                    ]); ?>
                </div>
            </div>

            <div class="col-md-4">
                <label for="ts-assets-manufacturer" class="form-label mb-1 fw-semibold"><?= __('Manufacturer', 'ticketsstatistics') ?></label>
                <select class="form-select form-select-sm" id="ts-assets-manufacturer" name="manufacturer">
                    <option value="0"><?= __('All manufacturers', 'ticketsstatistics') ?></option>
                    <?php foreach ($manufacturers as $manufacturer): ?>
                        <option value="<?= $manufacturer['id'] ?>" <?= $manufacturerId === $manufacturer['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($manufacturer['name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <?= __('Filter', 'ticketsstatistics') ?>
                </button>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm h-100 text-center">
                <div class="card-body">
                    <i class="ti ti-devices fs-1 text-secondary"></i>
                    <div class="display-6 fw-bold"><?= $totalAssets ?></div>
                    <div class="text-muted"><?= __('Total assets', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100 text-center" style="border-top:3px solid #C00000">
                <div class="card-body">
                    <i class="ti ti-device-laptop fs-1" style="color:#C00000"></i>
                    <div class="display-6 fw-bold"><?= $counts['computers'] ?></div>
                    <div class="text-muted"><?= __('Computers', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100 text-center" style="border-top:3px solid #16a34a">
                <div class="card-body">
                    <i class="ti ti-network fs-1" style="color:#16a34a"></i>
                    <div class="display-6 fw-bold"><?= $counts['network_devices'] ?></div>
                    <div class="text-muted"><?= __('Network devices', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100 text-center" style="border-top:3px solid #f59e0b">
                <div class="card-body">
                    <i class="ti ti-device-desktop fs-1" style="color:#f59e0b"></i>
                    <div class="display-6 fw-bold"><?= $counts['monitors'] ?></div>
                    <div class="text-muted"><?= __('Monitors', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($showTownChart || $showManufacturerChart): ?>
        <div class="row g-3">
            <?php if ($showTownChart): ?>
                <div class="<?= $showManufacturerChart ? 'col-lg-6' : 'col-12' ?>">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex align-items-center justify-content-between"><?= __('Assets by Town', 'ticketsstatistics') ?></div>
                        <div class="card-body">
                            <?php if ($townChart['labels'] !== []): ?>
                                <canvas id="ts-assets-town-chart" style="height: 360px; max-height: 360px;"></canvas>
                            <?php else: ?>
                                <div class="text-muted text-center py-5"><?= __('No data available', 'ticketsstatistics') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($showManufacturerChart): ?>
                <div class="<?= $showTownChart ? 'col-lg-6' : 'col-12' ?>">
                    <div class="card shadow-sm h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <span><?= __('Assets by Manufacturer', 'ticketsstatistics') ?></span>
                        </div>
                        <div class="card-body">
                            <?php if ($manufacturerChart['labels'] !== []): ?>
                                <canvas id="ts-assets-manufacturer-chart" style="height: 360px; max-height: 360px;"></canvas>
                            <?php else: ?>
                                <div class="text-muted text-center py-5"><?= __('No data available', 'ticketsstatistics') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
if ($showTownChart || $showManufacturerChart):
?>
    <div
        id="ts-assets-chart-data"
        data-town-chart="<?= htmlspecialchars(json_encode($townChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
        data-manufacturer-chart="<?= htmlspecialchars(json_encode($manufacturerChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
        hidden></div>
<?php
endif;
\Html::footer();
