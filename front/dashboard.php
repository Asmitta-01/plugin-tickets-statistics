<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

Session::checkCentralAccess();

Html::header(__('Tickets Statistics', 'ticketsstatistics'), '', 'helpdesk', 'ticketsstatistics');
?>

<div class="container-fluid mt-3">
    <div class="row g-3 mb-3">
        <div class="col-12">
            <h2 class="page-title">
                <i class="ti ti-chart-bar me-2"></i>
                <?= __('Tickets Statistics', 'ticketsstatistics') ?>
            </h2>
        </div>
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
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header"><?= __('Tickets by priority', 'ticketsstatistics') ?></div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chart-priority" style="max-height:280px"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header"><?= __('Tickets by category (top 10)', 'ticketsstatistics') ?></div>
                <div class="card-body">
                    <canvas id="chart-category" style="max-height:280px"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts row 2 -->
    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header"><?= __('Tickets opened per day (last 30 days)', 'ticketsstatistics') ?></div>
                <div class="card-body">
                    <canvas id="chart-perday" style="max-height:260px"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
    (function() {
        const root = CFG_GLPI.root_doc;
        const url = root + '/plugins/ticketsstatistics/ajax/data.php';

        fetch(url)
            .then(r => r.json())
            .then(data => {
                // Big number counters
                document.querySelectorAll('.ts-count').forEach(el => {
                    const status = el.dataset.status;
                    el.textContent = data.counters[status] ?? 0;
                });

                // Priority donut
                new Chart(document.getElementById('chart-priority'), {
                    type: 'doughnut',
                    data: {
                        labels: data.priority.labels,
                        datasets: [{
                            data: data.priority.values,
                            backgroundColor: [
                                '#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#3498db', '#9b59b6'
                            ]
                        }]
                    },
                    options: {
                        plugins: {
                            legend: {
                                position: 'right'
                            }
                        },
                        maintainAspectRatio: false
                    }
                });

                // Category bar
                new Chart(document.getElementById('chart-category'), {
                    type: 'bar',
                    data: {
                        labels: data.category.labels,
                        datasets: [{
                            label: '<?= __('Tickets') ?>',
                            data: data.category.values,
                            backgroundColor: '#3b82f6'
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        maintainAspectRatio: false
                    }
                });

                // Per-day line
                new Chart(document.getElementById('chart-perday'), {
                    type: 'line',
                    data: {
                        labels: data.perday.labels,
                        datasets: [{
                            label: '<?= __('Tickets opened') ?>',
                            data: data.perday.values,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16,185,129,.15)',
                            fill: true,
                            tension: 0.3
                        }]
                    },
                    options: {
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        maintainAspectRatio: false
                    }
                });
            });
    }());
</script>

<?php
Html::footer();
