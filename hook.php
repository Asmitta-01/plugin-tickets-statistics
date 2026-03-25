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
