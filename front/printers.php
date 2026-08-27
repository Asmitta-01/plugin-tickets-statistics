<?php
require_once(__DIR__ . '/../../../inc/includes.php');

use GlpiPlugin\Ticketsstatistics\PrintersStatistics;

\Session::checkCentralAccess();

if (!\Session::haveRight('dashboard', READ)) {
    \Html::displayRightError();
}

$townId = (int) ($_GET['town_id'] ?? 0);
$manufacturerId = (int) ($_GET['manufacturer_id'] ?? 0);

$totalPrinters = PrintersStatistics::countPrinters($townId, $manufacturerId);
$totalPages = PrintersStatistics::countTotalPages($townId, $manufacturerId);
$cartridgeStatuses = PrintersStatistics::getCartridgesStatuses($townId, $manufacturerId);

$totalCartridges = array_sum($cartridgeStatuses);
$usedPct = $totalCartridges > 0 ? round(($cartridgeStatuses['used'] / $totalCartridges) * 100, 1) : 0;

$printersList = PrintersStatistics::getPrintersList($townId, $manufacturerId);
$printersByModel = PrintersStatistics::getPrintersByModel($townId, $manufacturerId);
$printersByTown = PrintersStatistics::getPrintersByTown($townId, $manufacturerId);
$cartridgesLevels = PrintersStatistics::getCartridgesLevelDistribution($townId, $manufacturerId);

$pluginAssetsRoot = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/ticketsstatistics/public/';

\Html::header(__('Printers Statistics', 'ticketsstatistics'), '', 'assets');
?>

<div class="container-fluid my-3" id="ts-printers-content">
    <div class="d-flex justify-content-between align-items-center g-3 mb-3">
        <h2 class="page-title mb-0">
            <i class="ti ti-printer me-2"></i>
            <?= __('Printers Dashboard', 'ticketsstatistics') ?>
        </h2>
        <a href="/plugins/ticketsstatistics/front/assets.php" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= __('Back to assets dashboard', 'ticketsstatistics') ?>">
            <i class="ti ti-arrow-left me-1"></i> <?= __('Back to assets dashboard', 'ticketsstatistics') ?>
        </a>
    </div>

    <div class="alert alert-secondary mb-3">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label for="ts-printers-town" class="form-label mb-1 fw-semibold"><?= __('Town', 'ticketsstatistics') ?></label>
                <div id="ts-printers-town">
                    <?php \Location::dropdown([
                        'name' => 'town_id',
                        'display_emptychoice' => true,
                        'emptylabel' => __('All towns', 'ticketsstatistics'),
                        'value' => $townId,
                        'class' => 'form-select form-select-sm w-100'
                    ]); ?>
                </div>
            </div>
            <div class="col-md-4">
                <label for="ts-printers-manufacturer" class="form-label mb-1 fw-semibold"><?= __('Manufacturer', 'ticketsstatistics') ?></label>
                <div id="ts-printers-manufacturer">
                    <?php \Manufacturer::dropdown([
                        'name' => 'manufacturer_id',
                        'display_emptychoice' => true,
                        'emptylabel' => __('All manufacturers', 'ticketsstatistics'),
                        'value' => $manufacturerId,
                        'class' => 'form-select form-select-sm w-100'
                    ]); ?>
                </div>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100 h-100">
                    <i class="ti ti-filter me-1"></i> <?= __('Apply filters', 'ticketsstatistics') ?>
                </button>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <!-- Card 1: Total Printers -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm h-100 text-center" style="border-top:3px solid #ec4899;">
                <div class="card-body py-3">
                    <i class="ti ti-printer fs-1" style="color:#ec4899"></i>
                    <div class="display-6 fw-bold"><?= $totalPrinters ?></div>
                    <div class="text-muted fw-medium"><?= __('Total Printers', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>
        
        <!-- Card 2: Total Printed Pages -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm h-100 text-center" style="border-top:3px solid #0ea5e9;">
                <div class="card-body py-3">
                    <i class="ti ti-file-text fs-1" style="color:#0ea5e9"></i>
                    <div class="display-6 fw-bold"><?= number_format($totalPages, 0, '.', ' ') ?></div>
                    <div class="text-muted fw-medium"><?= __('Total Printed Pages', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>

        <!-- Card 3: Cartridges Stock -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm h-100 text-center" style="border-top:3px solid #10b981;">
                <div class="card-body py-3">
                    <i class="ti ti-package fs-1" style="color:#10b981"></i>
                    <div class="display-6 fw-bold"><?= $cartridgeStatuses['new'] ?></div>
                    <div class="text-muted fw-medium"><?= __('Cartridges in Stock (New)', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>

        <!-- Card 4: Cartridges in Use -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm h-100 text-center" style="border-top:3px solid #f59e0b;">
                <div class="card-body py-3">
                    <i class="ti ti-droplet-half-2 fs-1" style="color:#f59e0b"></i>
                    <div class="display-6 fw-bold">
                        <?= $cartridgeStatuses['used'] ?>
                        <span class="fs-4 text-muted ms-1">(<?= $usedPct ?>%)</span>
                    </div>
                    <div class="text-muted fw-medium"><?= __('Cartridges in Use', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row g-3 mb-4">
        <!-- Printers by Model -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 ts-chart-card" data-counter-key="model" style="cursor: pointer;">
                <div class="card-header"><?= __('Printers by Model (Top 8)', 'ticketsstatistics') ?></div>
                <div class="card-body">
                    <canvas id="ts-printers-model-chart" style="height: 320px; max-height: 320px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Printers by Town -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 ts-chart-card" data-counter-key="town" style="cursor: pointer;">
                <div class="card-header"><?= __('Printers by Town', 'ticketsstatistics') ?></div>
                <div class="card-body">
                    <canvas id="ts-printers-town-chart" style="height: 320px; max-height: 320px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row g-3 mb-4">
        <!-- Printers by Pages -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 ts-chart-card" data-counter-key="top_pages" style="cursor: pointer;">
                <div class="card-header"><?= __('Top Printers by Printed Pages', 'ticketsstatistics') ?></div>
                <div class="card-body">
                    <canvas id="ts-printers-top-pages-chart" style="height: 320px; max-height: 320px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Pages Evolution -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 ts-chart-card" data-counter-key="evolution" style="cursor: pointer;">
                <div class="card-header"><?= __('Global Page Counters Evolution (12 Months)', 'ticketsstatistics') ?></div>
                <div class="card-body">
                    <canvas id="ts-printers-evolution-chart" style="height: 320px; max-height: 320px;"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row 3 -->
    <div class="row g-3 mb-4">
        <!-- Cartridges Ink Levels -->
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 ts-chart-card" data-counter-key="ink" style="cursor: pointer;">
                <div class="card-header"><?= __('Ink/Toner Levels', 'ticketsstatistics') ?></div>
                <div class="card-body">
                    <canvas id="ts-printers-ink-chart" style="height: 320px; max-height: 320px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ts-printers-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="ts-printers-modal-title"><?= __('Printers', 'ticketsstatistics') ?></h5>
                    <span class="badge bg-blue text-white" id="ts-printers-modal-count"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Close', 'ticketsstatistics') ?>"></button>
            </div>
            <div class="modal-body p-0">
                <div id="ts-printers-modal-body" class="p-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal"><?= __('Close', 'ticketsstatistics') ?></button>
            </div>
        </div>
    </div>
</div>

<?php
$topPrinters = PrintersStatistics::getTopPrintersByPages($townId, $manufacturerId, 8);
$evolution = PrintersStatistics::getPagesEvolution($townId, $manufacturerId);
?>
<div id="ts-printers-chart-data"
    data-town-id="<?= $townId ?>"
    data-manufacturer-id="<?= $manufacturerId ?>"
    data-ajax-url="<?= htmlspecialchars(($CFG_GLPI['root_doc'] ?? '') . '/plugins/ticketsstatistics/ajax/printers.php', ENT_QUOTES, 'UTF-8') ?>"
    data-model-chart="<?= htmlspecialchars(json_encode($printersByModel, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
    data-town-chart="<?= htmlspecialchars(json_encode($printersByTown, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
    data-top-pages-chart="<?= htmlspecialchars(json_encode($topPrinters, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
    data-evolution-chart="<?= htmlspecialchars(json_encode($evolution, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
    data-ink-chart="<?= htmlspecialchars(json_encode($cartridgesLevels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
    data-lang-critical="<?= htmlspecialchars(__('< 10% (Critical)', 'ticketsstatistics'), ENT_QUOTES, 'UTF-8') ?>"
    data-lang-low="<?= htmlspecialchars(__('10-30% (Low)', 'ticketsstatistics'), ENT_QUOTES, 'UTF-8') ?>"
    data-lang-good="<?= htmlspecialchars(__('30-70% (Good)', 'ticketsstatistics'), ENT_QUOTES, 'UTF-8') ?>"
    data-lang-full="<?= htmlspecialchars(__('> 70% (Full)', 'ticketsstatistics'), ENT_QUOTES, 'UTF-8') ?>"
>
</div>

<?php \Html::footer(); ?>