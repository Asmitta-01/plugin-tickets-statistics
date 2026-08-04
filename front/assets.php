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

$selectedSoftwareIds = array_values(array_unique(array_filter(array_map(
    static fn($id): int => (int) $id,
    (array) ($_GET['software'] ?? [])
), static fn(int $id): bool => $id > 0)));
$matchAllSelected   = !isset($_GET['match_all']) || (int) $_GET['match_all'] !== 0;
$topSoftwares       = AssetStatistics::getTopSoftwaresByComputers($townId, $manufacturerId);
$softwareCoverage   = $selectedSoftwareIds !== []
    ? AssetStatistics::getSoftwareCoverageForSelection($selectedSoftwareIds, $townId, $manufacturerId, $matchAllSelected)
    : ['with' => 0, 'without' => 0, 'total' => 0, 'name' => '', 'names' => [], 'software_ids' => []];
$coverageHasData    = $selectedSoftwareIds !== [] && (int) $softwareCoverage['total'] > 0;
$coverageMessage    = $selectedSoftwareIds !== []
    ? __('No data available', 'ticketsstatistics')
    : __('No software selected', 'ticketsstatistics');
$coverageAjaxUrl    = $CFG_GLPI['root_doc'] . '/plugins/ticketsstatistics/ajax/assets_software_coverage.php';
$coverageComputersAjaxUrl = $CFG_GLPI['root_doc'] . '/plugins/ticketsstatistics/ajax/assets_software_computers.php';

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

<div class="container-fluid my-3" id="ts-assets-content">
    <div class="d-flex justify-content-between align-items-center g-3 mb-3">
        <h2 class="page-title mb-0">
            <i class="ti ti-devices me-2"></i>
            <?= __('Assets Statistics', 'ticketsstatistics') ?>
        </h2>
        <a href="/plugins/ticketsstatistics/front/computers.php" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= __('View computers OS statistics', 'ticketsstatistics') ?>">
            <i class="ti ti-device-laptop me-1"></i> <?= __('Computers Stats', 'ticketsstatistics') ?>
        </a>
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

    <div class="row g-3 mt-2">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header"><?= __('Top installed softwares', 'ticketsstatistics') ?></div>
                <div class="card-body">
                    <?php if ($topSoftwares !== []): ?>
                        <canvas id="ts-assets-top-software-chart"></canvas>
                    <?php else: ?>
                        <div class="text-muted text-center py-5"><?= __('No data available', 'ticketsstatistics') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-2">
        <div class="col-md-5">
            <div class="card shadow-sm h-100">
                <div class="card-header"><?= __('Software coverage', 'ticketsstatistics') ?></div>
                <div class="card-body">
                    <form id="ts-software-coverage-form" method="get" class="mb-3" data-ajax-url="<?= htmlspecialchars($coverageAjaxUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="town" value="<?= $townId ?>">
                        <input type="hidden" name="manufacturer" value="<?= $manufacturerId ?>">
                        <input type="hidden" name="match_all" value="0">
                        <label class="form-label fw-semibold"><?= __('Select software', 'ticketsstatistics') ?></label>
                        <div class="d-flex gap-2 align-items-center">
                            <?php
                            \Software::dropdown([
                                'name'                => 'software[]',
                                'value'               => $selectedSoftwareIds,
                                'display_emptychoice' => true,
                                'emptylabel'          => __('Select software', 'ticketsstatistics'),
                                'addicon'             => false,
                                'comments'            => false,
                                'class'               => 'form-select form-select-sm',
                                'width'               => '100%',
                                'multiple'            => true,
                            ]);
                            ?>
                            <button type="submit" class="btn btn-primary btn-sm"><?= __('Filter', 'ticketsstatistics') ?></button>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="ts-assets-match-all" name="match_all" value="1" <?= $matchAllSelected ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ts-assets-match-all"><?= __('Match all selected software', 'ticketsstatistics') ?></label>
                            <div class="form-text"><?= __('If unchecked, the search matches computers having at least one selected software.', 'ticketsstatistics') ?></div>
                        </div>
                    </form>

                    <div id="ts-assets-software-coverage-summary" class="row g-2 text-center mt-2 <?= $coverageHasData ? '' : 'd-none' ?>">
                        <div class="col-6">
                            <div class="p-3 rounded" style="background:#dcfce7">
                                <div id="ts-assets-software-with" class="fs-4 fw-bold text-success"><?= (int) $softwareCoverage['with'] ?></div>
                                <div class="text-muted"><?= __('Computers with software', 'ticketsstatistics') ?></div>
                                <button class="btn btn-sm btn-outline-secondary w-100 mt-3" onclick="openSoftwareCoverageComputersModal('with')"><?= __('View details', 'ticketsstatistics') ?></button>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded" style="background:#fee2e2">
                                <div id="ts-assets-software-without" class="fs-4 fw-bold text-danger"><?= (int) $softwareCoverage['without'] ?></div>
                                <div class="text-muted"><?= __('Computers without software', 'ticketsstatistics') ?></div>
                                <button class="btn btn-sm btn-outline-secondary w-100 mt-3" onclick="openSoftwareCoverageComputersModal('without')"><?= __('View details', 'ticketsstatistics') ?></button>
                            </div>
                        </div>
                    </div>

                    <div id="ts-assets-software-coverage-message" class="text-muted text-center py-3 <?= $coverageHasData ? 'd-none' : '' ?>"><?= $coverageMessage ?></div>
                </div>
            </div>
        </div>

        <div id="ts-assets-software-coverage-chart-col" class="col-md-7 <?= $coverageHasData ? '' : 'd-none' ?>">
            <div class="card shadow-sm h-100">
                <div id="ts-assets-software-coverage-title" class="card-header">
                    <?= __('Software coverage', 'ticketsstatistics') ?><?= $coverageHasData ? ' &mdash; ' . htmlspecialchars($softwareCoverage['name'], ENT_QUOTES, 'UTF-8') : '' ?>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center" style="min-height: 220px">
                    <canvas
                        id="ts-assets-software-coverage-chart"
                        data-label-with="<?= __('Computers with software', 'ticketsstatistics') ?>"
                        data-label-without="<?= __('Computers without software', 'ticketsstatistics') ?>"
                        style="max-width: 280px; max-height: 280px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ts-assets-computers-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="py-md-3">
                    <h5 class="modal-title mb-0" id="ts-assets-computers-modal-title"><?= __('Computers', 'ticketsstatistics') ?></h5>
                    <div class="text-muted small" id="ts-assets-computers-modal-count"></div>
                </div>
                <button class="btn btn-secondary btn-sm ms-auto" disabled id="ts-assets-computers-download-btn" data-bs-toggle="tooltip" title="<?= __('Download as CSV', 'ticketsstatistics') ?>">
                    <i class="ti ti-file-spreadsheet me-1"></i>
                    <?= __('Download', 'ticketsstatistics') ?>
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <div id="ts-assets-computers-modal-alert" class="alert alert-info d-none mb-3"></div>
                <div id="ts-assets-computers-modal-body"></div>
            </div>
        </div>
    </div>
</div>

<?php
if ($showTownChart || $showManufacturerChart):
?>
    <div
        id="ts-assets-chart-data"
        data-town-chart="<?= htmlspecialchars(json_encode($townChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
        data-manufacturer-chart="<?= htmlspecialchars(json_encode($manufacturerChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
        data-top-softwares-chart="<?= htmlspecialchars(json_encode(['labels' => array_column($topSoftwares, 'name'), 'values' => array_column($topSoftwares, 'count')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
        data-software-coverage-chart="<?= htmlspecialchars(json_encode($softwareCoverage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
        data-software-coverage-title="<?= __('Software coverage', 'ticketsstatistics') ?>"
        data-no-software-selected-label="<?= __('No software selected', 'ticketsstatistics') ?>"
        data-no-data-label="<?= __('No data available', 'ticketsstatistics') ?>"
        data-processing-label="<?= __('Processing...', 'ticketsstatistics') ?>"
        data-software-computers-url="<?= htmlspecialchars($coverageComputersAjaxUrl, ENT_QUOTES, 'UTF-8') ?>"
        data-loading-computers-label="<?= __('Loading computers...', 'ticketsstatistics') ?>"
        data-no-computers-label="<?= __('No computers found for this selection.', 'ticketsstatistics') ?>"
        data-unable-load-computers-label="<?= __('Unable to load computers.', 'ticketsstatistics') ?>"
        data-showing-first-computers-label="<?= __('Showing the first %d computers only.', 'ticketsstatistics') ?>"
        hidden></div>
<?php
else:
?>
    <div
        id="ts-assets-chart-data"
        data-top-softwares-chart="<?= htmlspecialchars(json_encode(['labels' => array_column($topSoftwares, 'name'), 'values' => array_column($topSoftwares, 'count')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
        data-software-coverage-chart="<?= htmlspecialchars(json_encode($softwareCoverage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
        data-software-coverage-title="<?= __('Software coverage', 'ticketsstatistics') ?>"
        data-no-software-selected-label="<?= __('No software selected', 'ticketsstatistics') ?>"
        data-no-data-label="<?= __('No data available', 'ticketsstatistics') ?>"
        data-processing-label="<?= __('Processing...', 'ticketsstatistics') ?>"
        data-software-computers-url="<?= htmlspecialchars($coverageComputersAjaxUrl, ENT_QUOTES, 'UTF-8') ?>"
        data-loading-computers-label="<?= __('Loading computers...', 'ticketsstatistics') ?>"
        data-no-computers-label="<?= __('No computers found for this selection.', 'ticketsstatistics') ?>"
        data-unable-load-computers-label="<?= __('Unable to load computers.', 'ticketsstatistics') ?>"
        data-showing-first-computers-label="<?= __('Showing the first %d computers only.', 'ticketsstatistics') ?>"
        hidden></div>
<?php
endif;
\Html::footer();
