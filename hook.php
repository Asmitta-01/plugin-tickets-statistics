<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2026 by the TicketsStatistics plugin team.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/ticketsstatistics
 * -------------------------------------------------------------------------
 */

/**
 * Plugin install process
 */
function plugin_ticketsstatistics_install(): bool
{
    return true;
}

/**
 * Plugin uninstall process
 */
function plugin_ticketsstatistics_uninstall(): bool
{
    return true;
}

function plugin_ticketsstatistics_redefine_menus($menu)
{
    // Hide specific helpdesk menu items  
    // if (isset($menu['create_ticket'])) {
    //     unset($menu['create_ticket']);
    // }

    // Redirect default dashboard to our custom one
    $menu['helpdesk']['default_dashboard'] = '/plugins/ticketsstatistics/front/dashboard.php';

    return $menu;
}

function plugin_ticketsstatistics_change_profile()
{
    // Only redirect if no explicit redirect parameter exists
    if (($user_id = \Session::getLoginUserID()) !== false) {
        $has_dashboard_right = \Profile::haveUserRight($user_id, 'dashboard', READ, 0);
        if ($has_dashboard_right) {
            $_SESSION['plugin_redirect'] = '/plugins/ticketsstatistics/front/dashboard.php';
        }
    }
}

function plugin_ticketsstatistics_pre_item_list(array $params): void
{
    if (($params['itemtype'] ?? '') !== 'Ticket') {
        return;
    }
    if (\Session::getCurrentInterface() !== 'central') {
        return;
    }

    global $CFG_GLPI;
    $baseAjaxUrl = htmlspecialchars($CFG_GLPI['root_doc'] . '/plugins/ticketsstatistics/ajax/data.php', ENT_QUOTES, 'UTF-8');

    $counters = [
        ['id' => 'incoming',      'label' => __('New'),                                    'icon' => 'ti-ticket'],
        ['id' => 'assigned',      'label' => __('Assigned'),                               'icon' => 'ti-users'],
        ['id' => 'waiting',       'label' => __('Pending'),                                'icon' => 'ti-player-pause'],
        ['id' => 'solved_closed', 'label' => __('Resolved / Closed', 'ticketsstatistics'), 'icon' => 'ti-checkbox'],
        ['id' => 'total',         'label' => __('Total tickets', 'ticketsstatistics'),      'icon' => 'ti-archive'],
    ];

    $periods = \GlpiPlugin\Ticketsstatistics\PeriodFilter::getAvailablePeriods();

    // Period selector + wrapper
    echo '<div id="ts-ticketlist-wrapper" class="mb-4 px-2">';

    // Toolbar: period selector
    echo '<div class="d-flex align-items-center gap-2 mb-2">';
    echo '<label for="ts-ticketlist-period" class="form-label mb-0 fw-semibold small">' . __('Period', 'ticketsstatistics') . '</label>';
    echo '<select class="form-select form-select-sm w-auto" id="ts-ticketlist-period">';
    foreach ($periods as $value => $label) {
        $sel = ($value === 'last30') ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select>';
    // Custom date fields (hidden by default)
    echo '<div id="ts-ticketlist-custom" class="d-none d-flex align-items-center gap-1">';
    echo '<input type="date" class="form-control form-control-sm" id="ts-ticketlist-date-from">';
    echo '<span class="small">–</span>';
    echo '<input type="date" class="form-control form-control-sm" id="ts-ticketlist-date-to">';
    echo '<button class="btn btn-primary btn-sm" id="ts-ticketlist-apply">' . __('Apply', 'ticketsstatistics') . '</button>';
    echo '</div>';
    echo '</div>';

    // Cards row (position: relative so spinner can overlay)
    echo '<div class="row g-3" id="ts-counters-ticketlist" style="position:relative;">';
    // Spinner overlay (hidden by default)
    echo '<div id="ts-ticketlist-spinner" class="position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center" style="background:rgba(255,255,255,.6);z-index:10;">';
    echo '<div class="spinner-border text-secondary" role="status"><span class="visually-hidden">Loading...</span></div>';
    echo '</div>';

    foreach ($counters as $c) {
        $color    = htmlspecialchars(\GlpiPlugin\Ticketsstatistics\TicketsStatistics::getStatusColor($c['id']), ENT_QUOTES, 'UTF-8');
        $icon     = htmlspecialchars($c['icon'], ENT_QUOTES, 'UTF-8');
        $statusId = htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8');
        $label    = htmlspecialchars($c['label'], ENT_QUOTES, 'UTF-8');
        echo '<div class="col">';
        echo '<div class="card text-center h-100" style="border-top: 3px solid ' . $color . '">';
        echo '<div class="card-body py-3">';
        echo '<i class="ti ' . $icon . ' fs-1 mb-1" style="color:' . $color . '"></i>';
        echo '<div class="display-6 fw-bold ts-ticketlist-count" data-status="' . $statusId . '">—</div>';
        echo '<div class="text-muted small">' . $label . '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>'; // .row
    echo '</div>'; // #ts-ticketlist-wrapper

    $encodedBaseUrl = json_encode($CFG_GLPI['root_doc'] . '/plugins/ticketsstatistics/ajax/data.php');
    echo <<<JS
    <script>
    (function () {
        var baseUrl = {$encodedBaseUrl};

        function removeGlpiMiniDashboard() {
            var el = document.querySelector('.dashboard.mini');
            if (el) { el.remove(); return true; }
            return false;
        }
        if (!removeGlpiMiniDashboard()) {
            var obs = new MutationObserver(function () {
                if (removeGlpiMiniDashboard()) obs.disconnect();
            });
            obs.observe(document.documentElement, { childList: true, subtree: true });
        }

        function loadCounters(period, dateFrom, dateTo) {
            var spinner = document.getElementById('ts-ticketlist-spinner');
            spinner.classList.remove('d-none');
            spinner.classList.add('d-flex');

            var url = baseUrl + '?period=' + encodeURIComponent(period);
            if (period === 'custom') {
                if (dateFrom) url += '&date_from=' + encodeURIComponent(dateFrom);
                if (dateTo)   url += '&date_to='   + encodeURIComponent(dateTo);
            }

            fetch(url)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    document.querySelectorAll('#ts-counters-ticketlist .ts-ticketlist-count').forEach(function (el) {
                        var status = el.dataset.status;
                        if (data.counters && data.counters[status] !== undefined) {
                            el.textContent = data.counters[status];
                        }
                    });
                })
                .catch(function () {})
                .finally(function () {
                    spinner.classList.add('d-none');
                    spinner.classList.remove('d-flex');
                });
        }

        var periodSel  = document.getElementById('ts-ticketlist-period');
        var customDiv  = document.getElementById('ts-ticketlist-custom');
        var dateFrom   = document.getElementById('ts-ticketlist-date-from');
        var dateTo     = document.getElementById('ts-ticketlist-date-to');
        var applyBtn   = document.getElementById('ts-ticketlist-apply');

        periodSel.addEventListener('change', function () {
            if (this.value === 'custom') {
                customDiv.classList.remove('d-none');
                customDiv.classList.add('d-flex');
            } else {
                customDiv.classList.add('d-none');
                customDiv.classList.remove('d-flex');
                loadCounters(this.value);
            }
        });

        applyBtn.addEventListener('click', function () {
            loadCounters('custom', dateFrom.value, dateTo.value);
        });

        // Initial load
        loadCounters('last30');
    })();
    </script>
    JS;
}
