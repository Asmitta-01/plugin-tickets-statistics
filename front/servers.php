<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

use GlpiPlugin\Ticketsstatistics\ServersStatistics;

\Session::checkCentralAccess();

if (!\Session::haveRight('dashboard', READ)) {
    \Html::displayRightError();
}

$townId = (int) ($_GET['town'] ?? 0);
$entityId = (int) ($_GET['entity'] ?? \Session::getActiveEntity());

$counters = ServersStatistics::getServersCounters($townId, $entityId);
$servers = ServersStatistics::getAllServers($townId, $entityId);
$natureBreakdown = ServersStatistics::getServersNatureBreakdown($townId, $entityId);
$modelBreakdown = ServersStatistics::getServersModelBreakdown($townId, $entityId);

global $CFG_GLPI;
$serversAjaxUrl = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/ticketsstatistics/ajax/servers.php';
$serversExportUrl = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/ticketsstatistics/ajax/servers_export.php';
$serversFullListUrl = ($CFG_GLPI['root_doc'] ?? '') . '/plugins/ticketsstatistics/ajax/servers_full_list.php';

\Html::header(__('Servers Statistics', 'ticketsstatistics'), '', 'assets');
?>

<div class="container-fluid my-3" id="ts-servers-content">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="page-title mb-0">
            <i class="ti ti-server me-2"></i>
            <?= __('Servers Statistics', 'ticketsstatistics') ?>
        </h2>

        <a href="/plugins/ticketsstatistics/front/computers.php" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="<?= __('Back to computers dashboard', 'ticketsstatistics') ?>">
            <i class="ti ti-arrow-left me-1"></i> <?= __('Back to computers dashboard', 'ticketsstatistics') ?>
        </a>
    </div>

    <div class="alert alert-secondary mb-3">
        <form class="row g-2 align-items-end" method="get" id="ts-servers-filter-form">
            <div class="col-md-4 d-flex gap-3 flex-wrap">
                <div>
                    <label for="ts-servers-entity" class="form-label mb-1 fw-semibold"><?= \Entity::getTypeName(1) ?></label>
                    <div id="ts-servers-entity">
                        <?php \Entity::dropdown([
                            'name'                => 'entity',
                            'value'               => $entityId,
                            'display_emptychoice' => false,
                            'addicon'             => false,
                            'comments'            => false,
                            'class'               => 'form-select form-select-sm',
                        ]); ?>
                    </div>
                </div>

                <div>
                    <label for="ts-servers-town" class="form-label mb-1 fw-semibold"><?= __('Town', 'ticketsstatistics') ?></label>
                    <div id="ts-servers-town">
                        <?php \Location::dropdown([
                            'name'                => 'town',
                            'display_emptychoice' => true,
                            'emptylabel'          => __('All towns', 'ticketsstatistics'),
                            'value'               => $_GET['town'] ?? 0,
                            'addicon'             => false,
                            'comments'            => false,
                            'class'               => 'form-select form-select-sm',
                        ]); ?>
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <?= __('Filter', 'ticketsstatistics') ?>
                </button>
            </div>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm h-100 text-center ts-servers-card" data-counter-key="total" style="border-top:3px solid #0ea5e9; cursor: pointer;">
                <div class="card-body py-3">
                    <i class="ti ti-server fs-1" style="color:#0ea5e9"></i>
                    <div class="display-6 fw-bold"><?= (int) $counters['total'] ?></div>
                    <div class="text-muted"><?= __('Total servers', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm h-100 text-center ts-servers-card" data-counter-key="physical" style="border-top:3px solid #22c55e; cursor: pointer;">
                <div class="card-body py-3">
                    <i class="ti ti-server-2 fs-1" style="color:#22c55e"></i>
                    <div class="display-6 fw-bold"><?= (int) $counters['physical'] ?></div>
                    <div class="text-muted"><?= __('Physical servers', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm h-100 text-center ts-servers-card" data-counter-key="virtual" style="border-top:3px solid #f59e0b; cursor: pointer;">
                <div class="card-body py-3">
                    <i class="ti ti-box fs-1" style="color:#f59e0b"></i>
                    <div class="display-6 fw-bold"><?= (int) $counters['virtual'] ?></div>
                    <div class="text-muted"><?= __('Virtual servers', 'ticketsstatistics') ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm h-100 text-center ts-servers-card" data-counter-key="hypervisors" style="border-top:3px solid #7c3aed; cursor: pointer;">
                <div class="card-body py-3">
                    <i class="ti ti-cpu fs-1" style="color:#7c3aed"></i>
                    <div class="display-6 fw-bold"><?= (int) $counters['hypervisors'] ?></div>
                    <div class="text-muted" data-bs-toggle="tooltip" title="<?= __('Physical servers hosting virtual machines', 'ticketsstatistics') ?>">
                        <?= __('Virtualization hosts', 'ticketsstatistics') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="ti ti-chart-pie me-1"></i>
                        <?= __('Servers by nature', 'ticketsstatistics') ?>
                    </h3>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 320px;">
                        <canvas id="ts-servers-nature-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="ti ti-chart-bar me-1"></i>
                        <?= __('Servers by hardware / model', 'ticketsstatistics') ?>
                    </h3>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 320px;">
                        <canvas id="ts-servers-model-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="card-title mb-0">
                <i class="ti ti-list me-1"></i>
                <?= __('Servers inventory', 'ticketsstatistics') ?> (<?= count($servers) ?>)
            </h3>
            <div class="d-flex gap-2 align-items-center">
                <input type="text" id="ts-servers-search-input" class="form-control form-control-sm" placeholder="<?= __('Search...', 'ticketsstatistics') ?>" style="max-width: 220px;">
                <a href="<?= $serversExportUrl ?>?town_id=<?= $townId ?>&entity_id=<?= $entityId ?>&counter_key=total" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-file-spreadsheet me-1"></i> <?= __('Export CSV', 'ticketsstatistics') ?>
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0" id="ts-servers-main-table">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th><?= __('ID', 'ticketsstatistics') ?></th>
                            <th><?= __('Name', 'ticketsstatistics') ?></th>
                            <th><?= __('Nature', 'ticketsstatistics') ?></th>
                            <th><?= __('Hardware / Model', 'ticketsstatistics') ?></th>
                            <th><?= __('Operating system', 'ticketsstatistics') ?></th>
                            <th class="text-center"><?= __('Hosted VMs', 'ticketsstatistics') ?></th>
                            <th><?= __('Serial number', 'ticketsstatistics') ?></th>
                            <th><?= __('Town', 'ticketsstatistics') ?></th>
                            <th><?= __('Entity', 'ticketsstatistics') ?></th>
                            <th><?= __('Last update', 'ticketsstatistics') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($servers) === 0): ?>
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    <?= __('No servers found for this selection.', 'ticketsstatistics') ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($servers as $s): ?>
                                <?php
                                $badgeClass = !empty($s['is_hypervisor'])
                                    ? 'bg-purple text-white'
                                    : (!empty($s['is_virtual']) ? 'bg-warning text-white' : 'bg-success text-white');
                                ?>
                                <tr>
                                    <td><?= (int) $s['id'] ?></td>
                                    <td>
                                        <a href="<?= htmlspecialchars($s['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="fw-semibold">
                                            <?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= htmlspecialchars($s['server_type_label'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($s['manufacturer'], ENT_QUOTES, 'UTF-8') ?>
                                        <?= $s['model'] !== '-' ? htmlspecialchars($s['model'], ENT_QUOTES, 'UTF-8') : '' ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($s['os_name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?= $s['os_version'] !== '-' ? '(' . htmlspecialchars($s['os_version'], ENT_QUOTES, 'UTF-8') . ')' : '' ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int) ($s['hosted_vms_count'] ?? 0) > 0): ?>
                                            <span class="badge bg-indigo-lt fw-bold"><?= (int) $s['hosted_vms_count'] ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($s['serial'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($s['town'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($s['entity'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($s['last_update'], ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ts-servers-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="ts-servers-modal-title"><?= __('Servers', 'ticketsstatistics') ?></h5>
                    <span class="badge bg-blue text-white" id="ts-servers-modal-count"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('Close', 'ticketsstatistics') ?>"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info d-none" id="ts-servers-modal-alert"></div>
                <div id="ts-servers-modal-body"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="ts-servers-modal-download-btn">
                    <i class="ti ti-file-spreadsheet me-1"></i> <?= __('Export CSV', 'ticketsstatistics') ?>
                </button>
                <button type="button" class="btn btn-outline-primary" id="ts-servers-modal-full-btn">
                    <i class="ti ti-external-link me-1"></i> <?= __('View in GLPI', 'ticketsstatistics') ?>
                </button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal"><?= __('Close', 'ticketsstatistics') ?></button>
            </div>
        </div>
    </div>
</div>

<div id="ts-servers-chart-data"
    data-nature-chart="<?= htmlspecialchars(json_encode($natureBreakdown, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
    data-model-chart="<?= htmlspecialchars(json_encode($modelBreakdown, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>"
    data-servers-ajax-url="<?= htmlspecialchars($serversAjaxUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-servers-export-url="<?= htmlspecialchars($serversExportUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-servers-full-list-url="<?= htmlspecialchars($serversFullListUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-loading-servers-label="<?= htmlspecialchars(__('Loading servers...', 'ticketsstatistics'), ENT_QUOTES, 'UTF-8') ?>"
    data-no-servers-label="<?= htmlspecialchars(__('No servers found for this selection.', 'ticketsstatistics'), ENT_QUOTES, 'UTF-8') ?>"
    data-unable-load-servers-label="<?= htmlspecialchars(__('Unable to load servers.', 'ticketsstatistics'), ENT_QUOTES, 'UTF-8') ?>"
    data-truncated-label="<?= htmlspecialchars(__('Display limited to %d servers. Use full list or CSV for all entries.', 'ticketsstatistics'), ENT_QUOTES, 'UTF-8') ?>">
</div>

<?php \Html::footer(); ?>