<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

\Session::checkCentralAccess();

\Html::header(__('Tickets Statistics', 'ticketsstatistics'), '', 'helpdesk', 'ticketsstatistics');
?>

<div class="container-fluid mt-3" id="ts-content">
    <div class="row g-3 mb-3">
        <div class="col-12">
            <h2 class="page-title">
                <i class="ti ti-chart-bar me-2"></i>
                <?= __('Tickets Statistics', 'ticketsstatistics') ?>
            </h2>
        </div>
    </div>

    <!-- Filter row -->
    <?php
    $period = $_GET['period'] ?? 'last30';
    ?>
    <div class="d-flex align-items-center justify-content-between mb-3 alert alert-secondary">
        <form class="row align-items-end" method="get" id="ts-filter-form">
            <div class="col-auto row gx-2 align-items-center">
                <label for="ts-period" class="form-label mb-1 fw-semibold"><?= __('Period', 'ticketsstatistics') ?></label>
                <select class="form-select form-select-sm" id="ts-period" name="period">
                    <?php foreach (GlpiPlugin\Ticketsstatistics\TicketsStatistics::getAvailablePeriods() as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $period === $value ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5 align-items-center" id="ts-custom-period-fields" style="display:<?= $period === 'custom' ? 'block' : 'none' ?>;">
                <label class="form-label mb-1 fw-semibold"><?= __('Custom range', 'ticketsstatistics') ?></label>
                <div class="input-group input-group-sm">
                    <input type="date" class="form-control" id="ts-date-from" name="date_from" value="<?= isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : '' ?>">
                    <span class="input-group-text">to</span>
                    <input type="date" class="form-control form" id="ts-date-to" name="date_to" value="<?= isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : '' ?>">
                </div>
            </div>
            <div class="col-md-2 align-self-center" <?= $period === 'custom' ? '' : ' style="display:none;"' ?> id="ts-apply-btn-col">
                <button type="submit" class="btn btn-primary btn-sm"><?= __('Apply', 'ticketsstatistics') ?></button>
            </div>
        </form>

        <button id='ticketsstatisticsDownloadPdfBtn' class='btn btn-primary btn-sm'>
            <i class='ti ti-download'></i> <?= __('Download PDF', 'ticketsstatistics') ?>
        </button>
    </div>

    <!-- Big numbers row -->
    <div class="row g-3 mb-4" id="ts-counters">
        <?php foreach (
            [
                ['id' => 'incoming', 'label' => __('New'),      'color' => '#3bc519', 'icon' => 'ti-ticket'],
                ['id' => 'assigned', 'label' => __('Assigned'), 'color' => '#f1cd29', 'icon' => 'ti-users'],
                ['id' => 'waiting',  'label' => __('Pending'),  'color' => '#f1a129', 'icon' => 'ti-player-pause'],
                ['id' => 'solved',   'label' => __('Solved'),   'color' => '#266ae9', 'icon' => 'ti-checkbox'],
                ['id' => 'closed',   'label' => __('Closed'),   'color' => '#555555', 'icon' => 'ti-archive'],
            ] as $c
        ): ?>
            <div class="col">
                <div class="card text-center h-100" style="border-top: 3px solid <?= $c['color'] ?>">
                    <div class="card-body py-3">
                        <i class="ti <?= $c['icon'] ?> fs-1 mb-1" style="color:<?= $c['color'] ?>"></i>
                        <div class="display-6 fw-bold ts-count" data-status="<?= $c['id'] ?>">—</div>
                        <div class="text-muted small"><?= $c['label'] ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts row 1 -->
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <?= __('Tickets by priority', 'ticketsstatistics') ?>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chart-priority" style="max-height:280px">
                    </canvas>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><?= __('Tickets by category (top 10)', 'ticketsstatistics') ?></span>
                    <button data-bs-toggle="tooltip" title="<?= __('Reset the zoom', 'ticketsstatistics') ?>" class="btn btn-sm btn-outline-secondary ts-reset-chart" data-canvas="chart-category">
                        <?= __('Reset', 'ticketsstatistics') ?>
                    </button>
                </div>
                <div class="card-body">
                    <canvas id="chart-category" style="max-height:280px"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts row 2 -->
    <div class="row g-3 mb-3">
        <div class="col-md-9">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><?= __('Tickets by town', 'ticketsstatistics') ?></span>
                    <div class="w-md-50">
                        <div class="btn-group btn-group-sm" role="group" aria-label="" id="ts-category-status-group">
                            <div class="btn"><?= __('Status', 'ticketsstatistics') ?></div>
                            <div class="btn">
                                <span class="badge bg-success me-1"></span>
                                <?= __('New', 'ticketsstatistics') ?>
                            </div>
                            <div class="btn">
                                <span class="badge bg-danger me-1"></span>
                                <?= __('Resolved', 'ticketsstatistics') ?>
                            </div>
                            <div class="btn">
                                <span class="badge bg-warning me-1"></span>
                                <?= __('In progress', 'ticketsstatistics') ?>
                            </div>
                        </div>
                        <button data-bs-toggle="tooltip" title="<?= __('Reset the zoom', 'ticketsstatistics') ?>" class="ms-md-2 btn btn-sm btn-outline-secondary ts-reset-chart" data-canvas="chart-city">
                            <?= __('Reset', 'ticketsstatistics') ?>
                        </button>
                    </div>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chart-city" style="height:250px">
                    </canvas>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <?= __('Total tickets per town', 'ticketsstatistics') ?>
                </div>
                <div class="card-body d-flex p-0">
                    <div
                        class="table-responsive-md w-100 overflow-y-auto" style="max-height:260px">
                        <table
                            class="table table-sm table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th class="text-center" scope="col"><?= __('Name', 'ticketsstatistics') ?></th>
                                    <th class="text-center" scope="col"><?= __('Total tickets', 'ticketsstatistics') ?></th>
                                </tr>
                            </thead>
                            <tbody id="ts-towns-table" class="overflow-y-auto">
                                <tr class="">
                                    <td><i class="ti ti-loader"></i></td>
                                    <td><i class="ti ti-loader"></i></td>
                                </tr>
                                <tr class="">
                                    <td><i class="ti ti-loader"></i></td>
                                    <td><i class="ti ti-loader"></i></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Charts row 3 -->
    <div class="card shadow-sm h-100 mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><?= __('Tickets by town (splitted)', 'ticketsstatistics') ?></span>
            <div class="w-md-50">
                <div class="btn-group btn-group-sm" role="group" aria-label="" id="ts-category-status-group">
                    <div class="btn"><?= __('Status', 'ticketsstatistics') ?></div>
                    <div class="btn">
                        <span class="badge bg-success me-1"></span>
                        <?= __('New', 'ticketsstatistics') ?>
                    </div>
                    <div class="btn">
                        <span class="badge bg-danger me-1"></span>
                        <?= __('Resolved', 'ticketsstatistics') ?>
                    </div>
                    <div class="btn">
                        <span class="badge bg-warning me-1"></span>
                        <?= __('In progress', 'ticketsstatistics') ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body d-flex align-items-center justify-content-center">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="border-end">
                        <canvas id="chart-city-new" style="height:280px">
                        </canvas>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border-end">
                        <canvas id="chart-city-resolved" style="height:280px">
                        </canvas>
                    </div>
                </div>
                <div class="col-md-4">
                    <canvas id="chart-city-progress" style="height:280px">
                    </canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts row 4 -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span>
                        <?= __('Tickets opened per day', 'ticketsstatistics') . ' (' . GlpiPlugin\Ticketsstatistics\TicketsStatistics::getPeriodLabel($period) . ')' ?>
                    </span>
                    <button data-bs-toggle="tooltip" title="<?= __('Reset the zoom', 'ticketsstatistics') ?>" class="btn btn-sm btn-outline-secondary ts-reset-chart" data-canvas="chart-perday">
                        <?= __('Reset', 'ticketsstatistics') ?>
                    </button>
                </div>
                <div class="card-body">
                    <canvas id="chart-perday" style="height:260px"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ts-tickets-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="py-md-3">
                    <h5 class="modal-title mb-0" id="ts-tickets-modal-title"><?= __('Tickets', 'ticketsstatistics') ?></h5>
                    <div class="text-muted small" id="ts-tickets-modal-count"></div>
                </div>
                <button class="btn btn-secondary btn-sm ms-auto" disabled id="ts-tickets-download-btn" data-bs-toggle="tooltip" title="<?= __('Download as CSV', 'ticketsstatistics') ?>">
                    <i class="ti ti-file-spreadsheet me-1"></i>
                    <?= __('Download', 'ticketsstatistics') ?>
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <div id="ts-tickets-modal-alert" class="alert alert-info d-none mb-3"></div>
                <div id="ts-tickets-modal-body"></div>
            </div>
        </div>
    </div>
</div>

<?php
\Html::footer();
