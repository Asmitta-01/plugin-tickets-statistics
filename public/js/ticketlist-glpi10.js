/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI — ticket list widget (GLPI 10 fallback)
 * -------------------------------------------------------------------------
 * Injected via the add_javascript hook because PRE_ITEM_LIST does not exist
 * in GLPI 10. Mirrors the logic of plugin_ticketsstatistics_pre_item_list()
 * in hook.php.
 * -------------------------------------------------------------------------
 */

(function () {
    // Only run on the ticket list page
    if (!window.location.pathname.endsWith('/front/ticket.php')) {
        return;
    }

    var baseUrl = CFG_GLPI.root_doc + '/plugins/ticketsstatistics/ajax/data.php';

    // Status colors — must match TicketsStatistics::getStatusColors()
    var colors = {
        incoming: '#49bf4d',
        assigned: '#49bf4d',
        waiting: '#ffa500',
        solved_closed: '#C00000',
        total: '#555555'
    };

    // Counter cards config — labels are functions so __() is called lazily,
    // after the ticketsstatistics locale domain has been loaded by GLPI.
    var counters = [
        { id: 'incoming', label: function () { return __('New', 'ticketsstatistics'); }, icon: 'ti-ticket' },
        { id: 'assigned', label: function () { return __('Assigned', 'ticketsstatistics'); }, icon: 'ti-users' },
        { id: 'waiting', label: function () { return __('Pending', 'ticketsstatistics'); }, icon: 'ti-player-pause' },
        { id: 'solved_closed', label: function () { return __('Resolved / Closed', 'ticketsstatistics'); }, icon: 'ti-checkbox' },
        { id: 'total', label: function () { return __('Total tickets', 'ticketsstatistics'); }, icon: 'ti-archive' }
    ];

    // Period selector options
    var periods = [
        { value: 'last7', label: function () { return __('Last 7 days', 'ticketsstatistics'); } },
        { value: 'last30', label: function () { return __('Last 30 days', 'ticketsstatistics'); }, selected: true },
        { value: 'last90', label: function () { return __('Last 90 days', 'ticketsstatistics'); } },
        { value: 'thisyear', label: function () { return __('This year', 'ticketsstatistics'); } },
        { value: 'lastyear', label: function () { return __('Last year', 'ticketsstatistics'); } },
        { value: 'custom', label: function () { return __('Custom period', 'ticketsstatistics'); } }
    ];

    // -----------------------------------------------------------------
    // Build widget DOM
    // -----------------------------------------------------------------
    function buildWidget() {
        var wrapper = document.createElement('div');
        wrapper.id = 'ts-ticketlist-wrapper';
        wrapper.className = 'mb-4 px-2';

        // --- Toolbar ---
        var toolbar = document.createElement('div');
        toolbar.className = 'd-flex align-items-center gap-2 mb-2';

        var lbl = document.createElement('label');
        lbl.htmlFor = 'ts-ticketlist-period';
        lbl.className = 'form-label mb-0 fw-semibold small';
        lbl.textContent = 'Period';
        toolbar.appendChild(lbl);

        var sel = document.createElement('select');
        sel.className = 'form-select form-select-sm w-auto';
        sel.id = 'ts-ticketlist-period';
        periods.forEach(function (p) {
            var opt = document.createElement('option');
            opt.value = p.value;
            opt.textContent = p.label();
            if (p.selected) { opt.selected = true; }
            sel.appendChild(opt);
        });
        toolbar.appendChild(sel);

        // Custom date range (hidden by default)
        var customDiv = document.createElement('div');
        customDiv.id = 'ts-ticketlist-custom';
        customDiv.className = 'd-none d-flex align-items-center gap-1';
        customDiv.innerHTML =
            '<input type="date" class="form-control form-control-sm" id="ts-ticketlist-date-from">' +
            '<span class="small">\u2013</span>' +
            '<input type="date" class="form-control form-control-sm" id="ts-ticketlist-date-to">' +
            '<button class="btn btn-primary btn-sm" id="ts-ticketlist-apply">' + __('Apply', 'ticketsstatistics') + '</button>';
        toolbar.appendChild(customDiv);

        wrapper.appendChild(toolbar);

        // --- Cards row ---
        var row = document.createElement('div');
        row.className = 'row g-3';
        row.id = 'ts-counters-ticketlist';
        row.style.position = 'relative';

        // Spinner overlay
        var spinner = document.createElement('div');
        spinner.id = 'ts-ticketlist-spinner';
        spinner.className = 'position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center';
        spinner.setAttribute('style', 'background:rgba(255,255,255,.6);z-index:10;');
        spinner.innerHTML = '<div class="spinner-border text-secondary" role="status"><span class="visually-hidden">Loading\u2026</span></div>';
        row.appendChild(spinner);

        counters.forEach(function (c) {
            var color = colors[c.id] || '#000000';
            var col = document.createElement('div');
            col.className = 'col';
            col.innerHTML =
                '<div class="card text-center h-100" style="border-top:3px solid ' + color + '">' +
                '<div class="card-body py-3">' +
                '<i class="ti ' + c.icon + ' fs-1 mb-1" style="color:' + color + '"></i>' +
                '<div class="display-6 fw-bold ts-ticketlist-count" data-status="' + c.id + '">\u2014</div>' +
                '<div class="text-muted">' + c.label() + '</div>' +
                '</div>' +
                '</div>';
            row.appendChild(col);
        });

        wrapper.appendChild(row);
        return wrapper;
    }

    // -----------------------------------------------------------------
    // Remove GLPI's built-in mini dashboard (we replace it)
    // -----------------------------------------------------------------
    function removeGlpiMiniDashboard() {
        var el = document.querySelector('.dashboard-card:has(div >.dashboard.mini)');
        if (el) { el.remove(); return true; }
        return false;
    }

    // -----------------------------------------------------------------
    // Fetch counters from AJAX endpoint and populate the cards
    // -----------------------------------------------------------------
    function loadCounters(period, dateFrom, dateTo) {
        var spinner = document.getElementById('ts-ticketlist-spinner');
        if (spinner) {
            spinner.classList.remove('d-none');
            spinner.classList.add('d-flex');
        }

        var url = baseUrl + '?period=' + encodeURIComponent(period);
        if (period === 'custom') {
            if (dateFrom) { url += '&date_from=' + encodeURIComponent(dateFrom); }
            if (dateTo) { url += '&date_to=' + encodeURIComponent(dateTo); }
        }

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                document.querySelectorAll('#ts-counters-ticketlist .ts-ticketlist-count').forEach(function (el) {
                    var status = el.getAttribute('data-status');
                    if (data.counters && data.counters[status] !== undefined) {
                        el.textContent = data.counters[status];
                    }
                });
            })
            .catch(function () { })
            .finally(function () {
                if (spinner) {
                    spinner.classList.add('d-none');
                    spinner.classList.remove('d-flex');
                }
            });
    }

    // -----------------------------------------------------------------
    // Initialise
    // -----------------------------------------------------------------
    function init() {
        // Handle the mini dashboard removal (may not yet be in the DOM)
        if (!removeGlpiMiniDashboard()) {
            var obs = new MutationObserver(function () {
                if (removeGlpiMiniDashboard()) { obs.disconnect(); }
            });
            obs.observe(document.documentElement, { childList: true, subtree: true });
        }

        // Locate the search container and prepend the widget
        var container = document.querySelector('.col.search-container');
        if (!container) { return; }

        var widget = buildWidget();
        container.insertBefore(widget, container.firstChild);

        // Event listeners
        var periodSel = document.getElementById('ts-ticketlist-period');
        var customDiv = document.getElementById('ts-ticketlist-custom');
        var dateFrom = document.getElementById('ts-ticketlist-date-from');
        var dateTo = document.getElementById('ts-ticketlist-date-to');
        var applyBtn = document.getElementById('ts-ticketlist-apply');

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

        // Initial load with default period
        loadCounters('last30');
    }

    // Delay init until the ticketsstatistics locale domain has been loaded by
    // GLPI's async AJAX call (front/locale.php). Polling is needed because GLPI
    // provides no event for this. We give up after ~5 s and run anyway so the
    // widget still appears (with untranslated fallback strings).
    function waitForDomain(tries) {
        if (
            window.i18n &&
            window.i18n.options &&
            window.i18n.options.locale_data &&
            window.i18n.options.locale_data['ticketsstatistics']
        ) {
            init();
        } else if (tries > 0) {
            setTimeout(function () { waitForDomain(tries - 1); }, 100);
        } else {
            init(); // give up — run with untranslated fallback
        }
    }

    // $(function(){}) ensures DOMContentLoaded has fired (same timing as
    // GLPI's locale AJAX trigger) before we start polling.
    $(function () { waitForDomain(50); });
})();
