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
    $ajaxUrl = $CFG_GLPI['root_doc'] . '/plugins/ticketsstatistics/ajax/data.php?period=last30';

    $counters = [
        ['id' => 'incoming',      'label' => __('New'),                                    'icon' => 'ti-ticket'],
        ['id' => 'assigned',      'label' => __('Assigned'),                               'icon' => 'ti-users'],
        ['id' => 'waiting',       'label' => __('Pending'),                                'icon' => 'ti-player-pause'],
        ['id' => 'solved_closed', 'label' => __('Resolved / Closed', 'ticketsstatistics'), 'icon' => 'ti-checkbox'],
        ['id' => 'total',         'label' => __('Total tickets', 'ticketsstatistics'),      'icon' => 'ti-archive'],
    ];

    echo '<div class="row g-3 mb-4 px-2" id="ts-counters-ticketlist">';
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
    echo '</div>';

    $encodedUrl = json_encode($ajaxUrl);
    echo <<<JS
    <script>
    (function () {
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
        fetch({$encodedUrl})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                document.querySelectorAll('#ts-counters-ticketlist .ts-ticketlist-count').forEach(function (el) {
                    var status = el.dataset.status;
                    if (data.counters && data.counters[status] !== undefined) {
                        el.textContent = data.counters[status];
                    }
                });
            })
            .catch(function () {});
    })();
    </script>
    JS;
}
