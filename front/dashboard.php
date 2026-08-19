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

\Html::header(__('Tickets Statistics', 'ticketsstatistics'), '', 'helpdesk');
$includeMissc = \Plugin::isPluginActive('cfaomobility');
?>

<div class="container-fluid mt-3" id="ts-content">
    <div class="d-flex justify-content-between align-items-center g-3 mb-3">
        <h2 class="page-title">
            <i class="ti ti-chart-bar me-2"></i>
            <?= __('Tickets Statistics', 'ticketsstatistics') ?>
        </h2>

        <a href="/plugins/ticketsstatistics/front/technicians.php" data-bs-toggle="tooltip" title="<?= __('View technicians statistics', 'ticketsstatistics') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="ti ti-users-group me-1"></i> <?= __('Technicians Stats', 'ticketsstatistics') ?>
        </a>
    </div>

    <!-- Filter row -->
    <?php
    $period = $_GET['period'] ?? 'thismonth';
    $openStatusesGlobal = !isset($_GET['open_statuses_global']) || (int) $_GET['open_statuses_global'] === 1;
    ?>
    <div class="d-flex align-items-center justify-content-between mb-3 alert alert-secondary">
        <form class="row align-items-end" method="get" id="ts-filter-form">
            <div class="col-auto row gx-2 align-items-center">
                <label for="ts-period" class="form-label mb-1 fw-semibold"><?= __('Period', 'ticketsstatistics') ?></label>
                <select class="form-select form-select-sm" id="ts-period" name="period">
                    <?php foreach (GlpiPlugin\Ticketsstatistics\PeriodFilter::getAvailablePeriods() as $value => $label): ?>
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
            <div class="col-auto align-items-center">
                <label for="ts-category" class="form-label mb-1 fw-semibold"><?= __('Category', 'ticketsstatistics') ?></label>
                <div id="ts-category">
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
            <div class="col-auto align-self-center" <?= $period === 'custom' ? '' : ' style="display:none;"' ?> id="ts-apply-btn-col">
                <button type="submit" class="btn btn-primary btn-sm"><?= __('Apply', 'ticketsstatistics') ?></button>
            </div>
            <div class="col-auto d-flex align-items-center mb-md-2 gap-2 ms-3">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="ts-view-solved">
                    <label class="form-check-label fw-semibold" for="ts-view-solved"><?= __('Resolved period view', 'ticketsstatistics') ?></label>
                </div>
                <div class="form-check form-switch mb-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= __('If enabled, the open statuses will not consider the selected period', 'ticketsstatistics') ?>">
                    <input type="hidden" name="open_statuses_global" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="ts-open-statuses-global" name="open_statuses_global" value="1" <?= $openStatusesGlobal ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="ts-open-statuses-global"><?= __('Global open statuses', 'ticketsstatistics') ?></label>
                </div>
            </div>
        </form>

        <div class="btn-group" id="ts-download-btn-group">
            <button id='ticketsstatisticsDownloadPdfBtn' class='btn btn-primary'>
                <i class='ti ti-download'></i> <?= __('Download PDF', 'ticketsstatistics') ?>
            </button>
            <button
                type="button"
                class="btn btn-primary dropdown-toggle dropdown-toggle-split"
                data-bs-toggle="dropdown"
                aria-haspopup="true"
                aria-expanded="false">
            </button>
            <div class="dropdown-menu">
                <a class="dropdown-item" id="ticketsstatisticsDownloadLowPdfBtn" href="#">
                    <i class='ti ti-file-download'></i>
                    <?= __('Download PDF in low quality', 'ticketsstatistics') ?>
                </a>
                <a class="dropdown-item" id="ticketsstatisticsDownloadMarkdownBtn" href="#">
                    <i class='ti ti-file-text'></i>
                    <?= __('Download stats in Markdown', 'ticketsstatistics') ?>
                </a>
            </div>
        </div>

    </div>

    <!-- Big numbers row — creation-date view -->
    <div class="row g-3 mb-4" id="ts-counters-default">
        <?php $bigCounters = [
            ['id' => 'incoming', 'label' => __('New'), 'tooltip' => __('Tickets still in New status', 'ticketsstatistics'), 'icon' => 'ti-ticket'],
            ['id' => 'assigned', 'label' => __('Assigned'), 'tooltip' => __('Assigned tickets', 'ticketsstatistics'), 'icon' => 'ti-users'],
            ['id' => 'waiting', 'label' => __('Pending'),  'tooltip' => __('Pending tickets', 'ticketsstatistics'), 'icon' => 'ti-player-pause'],
            ['id' => 'solved_closed',   'label' => __('Resolved / Closed', 'ticketsstatistics'),   'tooltip' => __('Resolved or closed tickets', 'ticketsstatistics'), 'icon' => 'ti-checkbox'],
            ['id' => 'total', 'label' => __('Total tickets', 'ticketsstatistics'),   'tooltip' => __('Total tickets received in the period', 'ticketsstatistics'), 'icon' => 'ti-archive'],
        ];
        if ($includeMissc) {
            // Insert the MISSC counter before the 'Resolved / Closed' counter
            array_splice($bigCounters, 3, 0, [['id' => 'missc',  'label' => __('MISSC', 'cfaomobility'),  'tooltip' => __('Tickets sent to MISSC', 'ticketsstatistics'), 'icon' => 'ti-notebook']]);
        }
        ?>

        <?php foreach ($bigCounters as $c): ?>
            <div class="col">
                <?php $color = GlpiPlugin\Ticketsstatistics\TicketsStatistics::getStatusColor($c['id']); ?>
                <div
                    class="card text-center h-100 ts-counter-card"
                    style="border-top: 3px solid <?= $color ?>; cursor: pointer;"
                    onmouseenter="this.style.boxShadow='0 1px 4px <?= $color ?>';"
                    onmouseleave="this.style.boxShadow='0 6px 16px rgba(15, 23, 42, 0.05)';"
                    role="button"
                    tabindex="0"
                    data-counter-key="<?= htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8') ?>"
                    data-counter-label="<?= htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="card-body py-3">
                        <i class="ti <?= $c['icon'] ?> fs-1 mb-1" style="color:<?= $color ?>"></i>
                        <div class="display-6 fw-bold ts-count" data-status="<?= $c['id'] ?>">—</div>
                        <div class="text-muted small w-auto" title="<?= $c['tooltip'] ?>" data-bs-toggle="tooltip">
                            <?php if ($c['id'] == 'assigned'): ?>
                                <i class="itilstatus far fa-circle assigned me-1"></i>
                            <?php elseif ($c['id'] == 'missc'): ?>
                                <i class="far fa-circle me-1" style="color:<?= $color ?>;"></i>
                            <?php endif; ?>
                            <?= $c['label'] ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Big numbers row — resolved-date view -->
    <div class="row g-3 mb-4" id="ts-counters-solved" style="display:none">
        <?php foreach (
            [
                ['key' => 'resolved_in_period', 'label' => __('Resolved / Closed in period', 'ticketsstatistics'), 'icon' => 'ti-checkbox',  'color' => '#C00000'],
                ['key' => 'opened_in_period',   'label' => __('Opened in period', 'ticketsstatistics'),            'icon' => 'ti-ticket',    'color' => '#49bf4d'],
                ['key' => 'avg_ttr',            'label' => __('Average TTR', 'ticketsstatistics'),                 'icon' => 'ti-clock',     'color' => '#3498db'],
            ] as $c
        ): ?>
            <div class="col">
                <div class="card text-center h-100" style="border-top: 3px solid <?= $c['color'] ?>">
                    <div class="card-body py-3">
                        <i class="ti <?= $c['icon'] ?> fs-1 mb-1" style="color:<?= $c['color'] ?>"></i>
                        <div class="display-6 fw-bold ts-solved-count" data-solved="<?= $c['key'] ?>">—</div>
                        <div class="text-muted"><?= $c['label'] ?></div>
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
                    <?= $includeMissc ? __('Tickets sent to MISSC', 'ticketsstatistics') . ' (' . __('Status', 'ticketsstatistics') . ')' : __('Tickets by priority', 'ticketsstatistics') ?>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="<?= $includeMissc ? 'chart-missc' : 'chart-priority' ?>" style="height:280px; max-height:280px">
                    </canvas>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <?php $categoryLabel = ($_GET['category'] ?? 0) > 0 ? \ITILCategory::getFriendlyNameById((int) $_GET['category']) : 'Top 10'; ?>
                    <span><?= sprintf(__('Tickets by category (%s)', 'ticketsstatistics'), $categoryLabel) ?></span>
                    <div>
                        <?php \GlpiPlugin\Ticketsstatistics\TicketsStatistics::showStatusGroupButtons('ts-category-status-group'); ?>
                        <button data-bs-toggle="tooltip" title="<?= __('Reset the zoom', 'ticketsstatistics') ?>" class="btn btn-sm btn-outline-secondary ms-2 ts-reset-chart" data-canvas="chart-category">
                            <?= __('Reset', 'ticketsstatistics') ?>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="chart-category" style="max-height:280px"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts row 2 -->
    <div class="row g-3 mb-3">
        <div class="col-md-8">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><?= __('Tickets by town', 'ticketsstatistics') ?></span>
                    <div class="w-md-50">
                        <?php \GlpiPlugin\Ticketsstatistics\TicketsStatistics::showStatusGroupButtons('ts-town-status-group'); ?>
                        <button data-bs-toggle="tooltip" title="<?= __('Reset the zoom', 'ticketsstatistics') ?>" class="ms-md-2 btn btn-sm btn-outline-secondary ts-reset-chart" data-canvas="chart-city">
                            <?= __('Reset', 'ticketsstatistics') ?>
                        </button>
                    </div>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chart-city" style="max-height:250px">
                    </canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <?= __('Total tickets per town', 'ticketsstatistics') ?>
                </div>
                <div class="card-body d-flex p-0">
                    <div
                        class="table-responsive-md w-100 overflow-auto" style="max-height:260px">
                        <table
                            class="table table-sm table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-md-3 align-content-center" width="50%" scope="col"><?= __('Name', 'ticketsstatistics') ?></th>
                                    <th class="text-center align-content-center" width="30%" scope="col"><?= __('Total tickets', 'ticketsstatistics') ?></th>
                                    <th class="text-center align-content-center" width="20%" scope="col"><?= __('Actions', 'ticketsstatistics') ?></th>
                                </tr>
                            </thead>
                            <tbody id="ts-towns-table">
                                <tr>
                                    <td class="ps-md-3"><i class="ti ti-loader"></i></td>
                                    <td class="text-center"><i class="ti ti-loader"></i></td>
                                    <td class="text-center"><i class="ti ti-loader"></i></td>
                                </tr>
                                <tr>
                                    <td class="ps-md-3"><i class="ti ti-loader"></i></td>
                                    <td class="text-center"><i class="ti ti-loader"></i></td>
                                    <td class="text-center"><i class="ti ti-loader"></i></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Charts row 3: Tickets resolution intervals + Open tickets age -->
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <?= __('Resolved tickets by TTR intervals', 'ticketsstatistics') ?>
                        <span data-bs-toggle="tooltip" title="<?= __('Resolved tickets by TTR intervals among tickets created in the selected period', 'ticketsstatistics') ?>" class="ms-1"><i class="ti ti-info-circle align-bottom"></i></span>
                    </div>
                </div>
                <div class="card-body d-flex align-items-center">
                    <canvas id="chart-ttr-intervals" style="height: 280px; max-height:280px"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <?= __('Open tickets by age', 'ticketsstatistics') ?>
                </div>
                <div class="card-body d-flex align-items-center">
                    <canvas id="chart-open-age" style="height: 280px; max-height:280px"></canvas>
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
                        <?= __('Tickets opened per day', 'ticketsstatistics') . ' / ' . __('Tickets closed per day', 'ticketsstatistics') . ' (' . GlpiPlugin\Ticketsstatistics\PeriodFilter::getPeriodLabel($period) . ')' ?>
                    </span>
                    <button data-bs-toggle="tooltip" title="<?= __('Reset the zoom', 'ticketsstatistics') ?>" class="btn btn-sm btn-outline-secondary ts-reset-chart" data-canvas="chart-perday">
                        <?= __('Reset', 'ticketsstatistics') ?>
                    </button>
                </div>
                <div class="card-body">
                    <canvas id="chart-perday" style="height:280px; max-height:280px"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts row 5: TTR -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <?= __('Average TTR', 'ticketsstatistics') . ' (' . GlpiPlugin\Ticketsstatistics\PeriodFilter::getPeriodLabel($period) . ')' ?>
                        <span data-bs-toggle="tooltip" title="<?= __('Time to resolve tickets created in the selected period, grouped by resolution date', 'ticketsstatistics') ?>" class="ms-1"><i class="ti ti-info-circle align-bottom"></i></span>
                    </div>
                    <button data-bs-toggle="tooltip" title="<?= __('Reset the zoom', 'ticketsstatistics') ?>" class="btn btn-sm btn-outline-secondary ts-reset-chart" data-canvas="chart-resolution">
                        <?= __('Reset', 'ticketsstatistics') ?>
                    </button>
                </div>
                <div class="card-body">
                    <canvas id="chart-resolution" style="max-height:260px"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts row 6: Tickets by town (splitted) -->
    <div class="card shadow-sm h-100 mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><?= __('Tickets by town (splitted)', 'ticketsstatistics') ?></span>
            <div class="w-md-50">
                <?php \GlpiPlugin\Ticketsstatistics\TicketsStatistics::showStatusGroupButtons('ts-towns-status-group'); ?>
            </div>
        </div>
        <div class="card-body d-flex align-items-center justify-content-center">
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="border-end">
                        <canvas id="chart-city-new" style="height:280px; max-height:280px">
                        </canvas>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border-end">
                        <canvas id="chart-city-resolved" style="height:280px; max-height:280px">
                        </canvas>
                    </div>
                </div>
                <div class="col-md-4">
                    <canvas id="chart-city-progress" style="height:280px; max-height:280px">
                    </canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts row 7 : Monthly volume -->
    <div class="row g-3 mb-3">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span><?= __("Monthly Volume of Tickets", 'ticketsstatistics') ?> </span>
                </div>
                <div class="card-body">
                    <canvas id="chart-monthly-volume" style="height: 380px; max-height:380px"></canvas>
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
                <div class="d-flex align-items-center gap-2 ms-auto me-2">
                    <button class="btn btn-sm btn-outline-primary" id="ts-tickets-modal-full-btn">
                        <i class="ti ti-external-link me-1"></i>
                        <?= __('Open full list', 'ticketsstatistics') ?>
                    </button>
                    <button class="btn btn-secondary btn-sm ms-auto" disabled id="ts-tickets-download-btn" data-bs-toggle="tooltip" title="<?= __('Download as CSV', 'ticketsstatistics') ?>">
                        <i class="ti ti-file-spreadsheet me-1"></i>
                        <?= __('Download', 'ticketsstatistics') ?>
                    </button>
                </div>
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
