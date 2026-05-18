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

\Html::header(__('Technicians Statistics', 'ticketsstatistics'), '', 'helpdesk');
?>

<div class="container-fluid mt-3" id="ts-technicians-content">
    <div class="d-flex justify-content-between align-items-center g-3 mb-3">
        <h2 class="page-title">
            <i class="ti ti-users-group me-2"></i>
            <?= __('Technicians Statistics', 'ticketsstatistics') ?>
        </h2>

        <a href="/plugins/ticketsstatistics/front/dashboard.php" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i> <?= __('Back to dashboard', 'ticketsstatistics') ?>
        </a>
    </div>

    <!-- Filter row -->
    <?php
    $period = $_GET['period'] ?? 'last30';
    ?>
    <div class="d-flex align-items-center justify-content-between mb-3 alert alert-secondary">
        <form class="row align-items-end" method="get" id="ts-tech-filter-form">
            <div class="col-auto row gx-2 align-items-center">
                <label for="ts-tech-period" class="form-label mb-1 fw-semibold"><?= __('Period', 'ticketsstatistics') ?></label>
                <select class="form-select form-select-sm" id="ts-tech-period" name="period">
                    <?php foreach (GlpiPlugin\Ticketsstatistics\PeriodFilter::getAvailablePeriods() as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $period === $value ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5 align-items-center" id="ts-tech-custom-period-fields" style="display:<?= $period === 'custom' ? 'block' : 'none' ?>;">
                <label class="form-label mb-1 fw-semibold"><?= __('Custom range', 'ticketsstatistics') ?></label>
                <div class="input-group input-group-sm">
                    <input type="date" class="form-control" id="ts-tech-date-from" name="date_from" value="<?= isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : '' ?>">
                    <span class="input-group-text"><?= __('to', 'ticketsstatistics') ?></span>
                    <input type="date" class="form-control form" id="ts-tech-date-to" name="date_to" value="<?= isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : '' ?>">
                </div>
            </div>
            <div class="col-auto align-items-center">
                <label for="ts-tech-category" class="form-label mb-1 fw-semibold"><?= __('Category', 'ticketsstatistics') ?></label>
                <div id="ts-tech-category">
                    <?php \ITILCategory::dropdown([
                        'name' => 'category',
                        'display_emptychoice' => true,
                        'emptylabel' => __('All categories', 'ticketsstatistics'),
                        'value' => $_GET['category'] ?? 0,
                        'addicon' => false,
                        'comments' => false,
                        'class' => 'form-select form-select-sm',
                        'on_change' => "this.dispatchEvent(new Event('change'))"
                    ]); ?>
                </div>
            </div>
            <div class="col-auto align-self-center" <?= $period === 'custom' ? '' : ' style="display:none;"' ?> id="ts-tech-apply-btn-col">
                <button type="submit" class="btn btn-primary btn-sm"><?= __('Apply', 'ticketsstatistics') ?></button>
            </div>
        </form>
    </div>

    <!-- Charts row 1 -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <?= __('Tickets by status per technician', 'ticketsstatistics') ?>
                    <button data-bs-toggle="tooltip" title="<?= __('Reset the zoom', 'ticketsstatistics') ?>" class="btn btn-sm btn-outline-secondary ms-2 ts-reset-chart" data-canvas="chart-tech-status">
                        <?= __('Reset', 'ticketsstatistics') ?>
                    </button>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chart-tech-status" style="min-height:400px; max-height:400px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts row 2 -->
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <?= __('Average resolution time (hours)', 'ticketsstatistics') ?>
                    <button data-bs-toggle="tooltip" title="<?= __('Reset the zoom', 'ticketsstatistics') ?>" class="btn btn-sm btn-outline-secondary ms-2 ts-reset-chart" data-canvas="chart-tech-resolution-time">
                        <?= __('Reset', 'ticketsstatistics') ?>
                    </button>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chart-tech-resolution-time" style="min-height:350px; max-height:350px;"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <?= __('Resolution rate (%)', 'ticketsstatistics') ?>
                    <button data-bs-toggle="tooltip" title="<?= __('Reset the zoom', 'ticketsstatistics') ?>" class="btn btn-sm btn-outline-secondary ms-2 ts-reset-chart" data-canvas="chart-tech-resolution-rate">
                        <?= __('Reset', 'ticketsstatistics') ?>
                    </button>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chart-tech-resolution-rate" style="min-height:350px; max-height:350px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Data table -->
    <div class="row g-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <?= __('Technicians detailed statistics', 'ticketsstatistics') ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm" id="ts-tech-table">
                            <thead>
                                <tr>
                                    <th><?= __('Technician', 'ticketsstatistics') ?></th>
                                    <th class="text-end"><?= __('Total Tickets', 'ticketsstatistics') ?></th>
                                    <th class="text-end"><?= __('Resolved', 'ticketsstatistics') ?></th>
                                    <th class="text-end"><?= __('In Progress', 'ticketsstatistics') ?></th>
                                    <th class="text-end"><?= __('Waiting', 'ticketsstatistics') ?></th>
                                    <th class="text-end"><?= __('Avg Resolution Time (h)', 'ticketsstatistics') ?></th>
                                    <th class="text-end"><?= __('Resolution Rate (%)', 'ticketsstatistics') ?></th>
                                    <th class="text-end"><?= __('Avg Assign Time (h)', 'ticketsstatistics') ?></th>
                                </tr>
                            </thead>
                            <tbody id="ts-tech-tbody">
                                <!-- Populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
\Html::footer();
?>