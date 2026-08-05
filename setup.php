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
 * @copyright Copyright (C) 2026 by Brayan Tiwa.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/ticketsstatistics
 * -------------------------------------------------------------------------
 */

/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define('PLUGIN_TICKETSSTATISTICS_VERSION', '0.6.1');

// Minimal GLPI version, inclusive
/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define("PLUGIN_TICKETSSTATISTICS_MIN_GLPI_VERSION", "10.0.16");

// Maximum GLPI version, exclusive
/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define("PLUGIN_TICKETSSTATISTICS_MAX_GLPI_VERSION", "11.0.7");

/**
 * Init hooks of the plugin.
 * REQUIRED
 */
function plugin_init_ticketsstatistics(): void
{
    global $PLUGIN_HOOKS;

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $isGLPI11 = version_compare(GLPI_VERSION, '11.0.0', '>=');
    $pluginAssetsRoot = $isGLPI11 ? '' : 'public/';
    if (!$isGLPI11) {
        // For GLPI 10, we need to explicitly declare CSRF compliance
        // @phpstan-ignore-next-line
        $PLUGIN_HOOKS[Glpi\Plugin\Hooks::CSRF_COMPLIANT]['ticketsstatistics'] = true;
    }

    $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::CHANGE_PROFILE]['ticketsstatistics'] = 'plugin_ticketsstatistics_change_profile';
    if ($isGLPI11) {
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::PRE_ITEM_LIST]['ticketsstatistics'] = 'plugin_ticketsstatistics_pre_item_list';
    }
    if (
        \Session::getCurrentInterface() === 'central'
        && strpos($uri, 'front/ticket.php') !== false
    ) {
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/ticketlist-cards-modal.js';
        if (!$isGLPI11) {
            // GLPI 10: PRE_ITEM_LIST does not exist; inject the counters widget via JS instead.
            $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = 'public/js/ticketlist-glpi10.js';
        }
    }

    if (\Session::haveRight("dashboard", READ)) {
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::REDEFINE_MENUS]['ticketsstatistics'] = 'plugin_ticketsstatistics_redefine_menus';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::DISPLAY_CENTRAL]['ticketsstatistics'] = 'plugin_ticketsstatistics_display_central';
        // $PLUGIN_HOOKS['menu_toadd']['ticketsstatistics'] = [
        //     'helpdesk' => ['GlpiPlugin\\Ticketsstatistics\\TicketsStatistics'],
        // ];
    }
    if (\Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['menu_toadd']['ticketsstatistics'] = [
            'config' => ['GlpiPlugin\\Ticketsstatistics\\TicketsStatisticsQuickActions'],
        ];
    }

    if (strpos($uri, "plugins/ticketsstatistics/front/dashboard.php") !== false) {
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/jspdf.umd.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/html2canvas-pro.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/chart.umd.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/hammerjs@2.0.8.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/chartjs-plugin-zoom.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/chartjs-plugin-datalabels.min.js';

        $periodJsPath = GLPI_ROOT . '/plugins/ticketsstatistics/public/js/period.js';
        $version = file_exists($periodJsPath) ? filemtime($periodJsPath) : md5(PLUGIN_TICKETSSTATISTICS_VERSION);
        $ext = $isGLPI11 ? '?version=' . $version : '';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/period.js' . $ext;
    }

    // Central dashboard stats widget JS
    if (
        strpos($uri, 'front/central') !== false
        || strpos($uri, 'Central') !== false
    ) {
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/chart.umd.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/chartjs-plugin-datalabels.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/central_stats.js';
    }

    if (strpos($uri, "plugins/ticketsstatistics/front/technicians.php") !== false) {
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/chart.umd.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/hammerjs@2.0.8.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/chartjs-plugin-zoom.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/chartjs-plugin-datalabels.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/technicians.js';
    }

    if (strpos($uri, "plugins/ticketsstatistics/front/assets.php") !== false) {
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/chart.umd.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/chartjs-plugin-datalabels.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/assets.js';
    }

    if (strpos($uri, "plugins/ticketsstatistics/front/computers.php") !== false) {
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/chart.umd.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/hammerjs@2.0.8.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/chartjs-plugin-zoom.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/chartjs-plugin-datalabels.min.js';
        $PLUGIN_HOOKS[\Glpi\Plugin\Hooks::ADD_JAVASCRIPT]['ticketsstatistics'][] = $pluginAssetsRoot . 'js/computers.js';
    }

    // Check for pending redirect after session is ready  
    if (isset($_SESSION['plugin_redirect'])) {
        $redirect = $_SESSION['plugin_redirect'];
        unset($_SESSION['plugin_redirect']);
        header("Location: " . $redirect);
        exit;
    }
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array{
 *      name: string,
 *      version: string,
 *      author: string,
 *      license: string,
 *      homepage: string,
 *      requirements: array{
 *          glpi: array{
 *              min: string,
 *              max: string,
 *          }
 *      }
 * }
 */
function plugin_version_ticketsstatistics(): array
{
    return [
        'name'           => 'TicketsStatistics',
        'version'        => PLUGIN_TICKETSSTATISTICS_VERSION,
        'author'         => 'Brayan Tiwa',
        'license'        => 'MIT',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_TICKETSSTATISTICS_MIN_GLPI_VERSION,
                'max' => PLUGIN_TICKETSSTATISTICS_MAX_GLPI_VERSION,
            ],
        ],
    ];
}

/**
 * Check pre-requisites before install
 * OPTIONAL
 */
function plugin_ticketsstatistics_check_prerequisites(): bool
{
    return true;
}

/**
 * Check configuration process
 * OPTIONAL
 *
 * @param bool $verbose Whether to display message on failure. Defaults to false.
 */
function plugin_ticketsstatistics_check_config(bool $verbose = false): bool
{
    // Your configuration check
    return true;

    // Example:
    // if ($verbose) {
    //    echo __('Installed / not configured', 'ticketsstatistics');
    // }
    // return false;
}
