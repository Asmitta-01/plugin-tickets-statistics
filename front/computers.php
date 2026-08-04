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

if (!\Session::haveRight('dashboard', READ)) {
    \Html::displayRightError();
}

$townId = (int) ($_GET['town'] ?? 0);
$counters = AssetStatistics::getWindowsOsCounters($townId);
$versionsBreakdown = AssetStatistics::getWindowsVersionsBreakdown($townId);
$versionsByTown = AssetStatistics::getWindowsVersionsByTown($townId);
$latestKb = AssetStatistics::getLatestKbInstallations($townId, 12);

global $CFG_GLPI;
$computersAjaxUrl = $CFG_GLPI['root_doc'] . '/plugins/ticketsstatistics/ajax/computers.php';
$computersExportUrl = $CFG_GLPI['root_doc'] . '/plugins/ticketsstatistics/ajax/computers_export.php';
$computersFullListUrl = $CFG_GLPI['root_doc'] . '/plugins/ticketsstatistics/ajax/computers_full_list.php';

$latestVersionLabel = $counters['latest_version'] !== ''
    ? sprintf(__('Computers on latest Windows version (%s)', 'ticketsstatistics'), $counters['latest_version'])
    : __('Computers on latest Windows version', 'ticketsstatistics');

\Html::header(__('Computers Statistics', 'ticketsstatistics'), '', 'assets');
?>

<div class="container-fluid my-3" id="ts-computers-content">
    <div class="d-flex justify-content-between align-items-center g-3 mb-3">
        <h2 class="page-title mb-0">
            <i class="ti ti-device-laptop me-2"></i>
            <?= __('Computers Statistics', 'ticketsstatistics') ?>
        </h2>

        <a href="/plugins/ticketsstatistics/front/assets.php" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> <?= __('Back to assets dashboard', 'ticketsstatistics') ?>
        </a>
    </div>

    <div class="alert alert-secondary mb-3">
        <form class="row g-2 align-items-end" method="get" id="ts-computers-filter-form">
            <div class="col-md-4">
                <label for="ts-computers-town" class="form-label mb-1 fw-semibold"><?= __('Town', 'ticketsstatistics') ?></label>
                <div id="ts-computers-town">
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

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <?= __('Filter', 'ticketsstatistics') ?>
                </button>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm h-100 text-center ts-computers-card" data-counter-key="windows" style="border-top:3px solid #0ea5e9; cursor: pointer;">
                <div class="card-body py-3">
                    <i class="ti ti-brand-windows fs-1" style="color:#0ea5e9"></i>
                    <div class="display-6 fw-bold"><?= (int) $counters['windows'] ?></div>
                    <div class="text-muted"><?= __('Computers on Windows 11', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100 text-center ts-computers-card" data-counter-key="latest_version" style="border-top:3px solid #22c55e; cursor: pointer;">
                <div class="card-body py-3">
                    <i class="ti ti-checkup-list fs-1" style="color:#22c55e"></i>
                    <div class="display-6 fw-bold"><?= (int) $counters['latest_version_count'] ?></div>
                    <div class="text-muted text-truncate" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?= $latestVersionLabel ?>"><?= $latestVersionLabel ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100 text-center ts-computers-card" data-counter-key="to_update" style="border-top:3px solid #ef4444; cursor: pointer;">
                <div class="card-body py-3">
                    <i class="ti ti-alert-triangle fs-1" style="color:#ef4444"></i>
                    <div class="display-6 fw-bold"><?= (int) $counters['to_update'] ?></div>
                    <div class="text-muted"><?= __('Computers to update', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100 text-center ts-computers-card" data-counter-key="kb_total" style="border-top:3px solid #7c3aed; cursor: pointer;">
                <div class="card-body py-3">
                    <i class="ti ti-shield-check fs-1" style="color:#7c3aed"></i>
                    <div class="display-6 fw-bold"><?= (int) $counters['kb_total'] ?></div>
                    <div class="text-muted"><?= __('Total KB patches deployed', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header"><?= __('Windows machines by OS version', 'ticketsstatistics') ?></div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <?php if (!empty($versionsBreakdown['labels'])): ?>
                        <canvas id="ts-computers-version-chart" style="height:320px; max-height:320px"></canvas>
                    <?php else: ?>
                        <div class="text-muted text-center py-5"><?= __('No data available', 'ticketsstatistics') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header"><?= __('Windows machines by site and OS version', 'ticketsstatistics') ?></div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <?php if (!empty($versionsByTown['labels']) && !empty($versionsByTown['versions'])): ?>
                        <canvas id="ts-computers-town-version-chart" style="height:320px; max-height:320px"></canvas>
                    <?php else: ?>
                        <div class="text-muted text-center py-5"><?= __('No data available', 'ticketsstatistics') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header"><?= __('Latest KB patches and installations', 'ticketsstatistics') ?></div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <?php if (!empty($latestKb['labels'])): ?>
                        <canvas id="ts-computers-kb-chart" style="height:320px; max-height:320px"></canvas>
                    <?php else: ?>
                        <div class="text-muted text-center py-5"><?= __('No data available', 'ticketsstatistics') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ts-computers-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="py-md-3">
                    <h5 class="modal-title mb-0" id="ts-computers-modal-title"><?= __('Computers', 'ticketsstatistics') ?></h5>
                    <div class="text-muted small" id="ts-computers-modal-count"></div>
                </div>
                <div class="d-flex align-items-center gap-2 ms-auto me-2">
                    <button class="btn btn-sm btn-outline-primary" id="ts-computers-modal-full-btn">
                        <i class="ti ti-external-link me-1"></i>
                        <?= __('Open full list', 'ticketsstatistics') ?>
                    </button>
                    <button class="btn btn-secondary btn-sm" id="ts-computers-modal-download-btn" data-bs-toggle="tooltip" title="<?= __('Download as CSV', 'ticketsstatistics') ?>">
                        <i class="ti ti-file-spreadsheet me-1"></i>
                        <?= __('Download', 'ticketsstatistics') ?>
                    </button>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <div id="ts-computers-modal-alert" class="alert alert-info d-none mb-3"></div>
                <div id="ts-computers-modal-body"></div>
            </div>
        </div>
    </div>
</div>

<div
    id="ts-computers-chart-data"
    data-version-chart="<?= htmlspecialchars(json_encode($versionsBreakdown, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
    data-town-version-chart="<?= htmlspecialchars(json_encode($versionsByTown, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
    data-kb-chart="<?= htmlspecialchars(json_encode($latestKb, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
    data-computers-ajax-url="<?= htmlspecialchars($computersAjaxUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-computers-export-url="<?= htmlspecialchars($computersExportUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-computers-full-list-url="<?= htmlspecialchars($computersFullListUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-loading-computers-label="<?= __('Loading computers...', 'ticketsstatistics') ?>"
    data-no-computers-label="<?= __('No computers found for this selection.', 'ticketsstatistics') ?>"
    data-unable-load-computers-label="<?= __('Unable to load computers.', 'ticketsstatistics') ?>"
    data-showing-first-computers-label="<?= __('Showing the first %d computers only.', 'ticketsstatistics') ?>"
    hidden></div>

<?php
\Html::footer();
