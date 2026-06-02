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
        lbl.className = 'form-label mb-0 fw-semibold';
        lbl.textContent = 'Period';
        toolbar.appendChild(lbl);

        var sel = document.createElement('select');
        sel.className = 'form-select form-select-sm w-auto';
        sel.id = 'ts-ticketlist-period';
        periods.forEach(function (p) {
            var opt = document.createElement('option');
            opt.value = p.value;
            opt.setAttribute('data-ts-label', p.value);
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

        // Resolved-period view toggle
        var switchWrap = document.createElement('div');
        switchWrap.className = 'form-check form-switch mb-0 ms-3';
        switchWrap.innerHTML =
            '<input class="form-check-input" type="checkbox" role="switch" id="ts-ticketlist-view-solved">' +
            '<label class="form-check-label fw-semibold" for="ts-ticketlist-view-solved">' + __('Resolved period view', 'ticketsstatistics') + '</label>';
        toolbar.appendChild(switchWrap);

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
                '<div class="card text-center h-100"'
                + ' data-ts-tl-counter-key="' + c.id + '"'
                + ' data-ts-tl-counter-label="' + c.label() + '"'
                + ' style="border-top:3px solid ' + color + ';cursor:pointer"'
                + ' onmouseenter="this.style.boxShadow=\'0 1px 4px ' + color + '\';"'
                + ' onmouseleave="this.style.boxShadow=\'0 6px 16px rgba(15, 23, 42, 0.05)\';">' +
                '<div class="card-body py-3">' +
                '<i class="ti ' + c.icon + ' fs-1 mb-1" style="color:' + color + '"></i>' +
                '<div class="display-6 fw-bold ts-ticketlist-count" data-status="' + c.id + '">\u2014</div>' +
                '<div class="text-muted" data-ts-label="' + c.id + '">' + c.label() + '</div>' +
                '</div>' +
                '</div>';
            row.appendChild(col);
        });

        wrapper.appendChild(row);

        // Solved-date view cards row (hidden by default)
        var solvedColors = {
            resolved_in_period: '#C00000',
            opened_in_period: '#49bf4d',
            avg_ttr: '#3498db'
        };
        var solvedCards = [
            { key: 'resolved_in_period', icon: 'ti-checkbox', labelId: 'resolved_in_period' },
            { key: 'opened_in_period', icon: 'ti-ticket', labelId: 'opened_in_period' },
            { key: 'avg_ttr', icon: 'ti-clock', labelId: 'avg_ttr' }
        ];
        var solvedRow = document.createElement('div');
        solvedRow.className = 'row g-3';
        solvedRow.id = 'ts-counters-ticketlist-solved';
        solvedRow.style.display = 'none';
        solvedCards.forEach(function (sc) {
            var color = solvedColors[sc.key] || '#000';
            var col = document.createElement('div');
            col.className = 'col';
            col.innerHTML =
                '<div class="card text-center h-100" style="border-top:3px solid ' + color + '">' +
                '<div class="card-body py-3">' +
                '<i class="ti ' + sc.icon + ' fs-1 mb-1" style="color:' + color + '"></i>' +
                '<div class="display-6 fw-bold ts-ticketlist-solved-count" data-solved="' + sc.key + '">—</div>' +
                '<div class="text-muted" data-ts-solved-label="' + sc.labelId + '"></div>' +
                '</div></div>';
            solvedRow.appendChild(col);
        });
        wrapper.appendChild(solvedRow);

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
                if (data.solvedView) {
                    document.querySelectorAll('#ts-counters-ticketlist-solved .ts-ticketlist-solved-count').forEach(function (el) {
                        var key = el.getAttribute('data-solved');
                        var val = data.solvedView[key] !== undefined ? data.solvedView[key] : 0;
                        el.textContent = key === 'avg_ttr' ? val + 'h' : val;
                    });
                }
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

        var viewSolvedSwitch = document.getElementById('ts-ticketlist-view-solved');
        if (viewSolvedSwitch) {
            viewSolvedSwitch.addEventListener('change', function () {
                var defRow = document.getElementById('ts-counters-ticketlist');
                var solvedRow = document.getElementById('ts-counters-ticketlist-solved');
                if (defRow) defRow.style.display = this.checked ? 'none' : '';
                if (solvedRow) solvedRow.style.display = this.checked ? '' : 'none';
            });
        }

        // Initial load with default period
        loadCounters('last30');

        // Bind counter cards to the tickets modal
        if (window.tsTicketlistCardsModal && typeof window.tsTicketlistCardsModal.init === 'function') {
            window.tsTicketlistCardsModal.init({
                cardSelector: '#ts-counters-ticketlist [data-ts-tl-counter-key]',
                getFilters: function () {
                    var p = document.getElementById('ts-ticketlist-period');
                    var df = document.getElementById('ts-ticketlist-date-from');
                    var dt = document.getElementById('ts-ticketlist-date-to');
                    return {
                        period: p ? p.value : 'last30',
                        date_from: df ? df.value : '',
                        date_to: dt ? dt.value : ''
                    };
                }
            });
        }
    }

    // Patch already-rendered label text once the locale domain arrives.
    function applyTranslations() {
        counters.forEach(function (c) {
            var el = document.querySelector('[data-ts-label="' + c.id + '"]');
            if (el) { el.textContent = c.label(); }
        });
        periods.forEach(function (p) {
            var opt = document.querySelector('[data-ts-label="' + p.value + '"]');
            if (opt) { opt.textContent = p.label(); }
        });
        var applyBtn = document.getElementById('ts-ticketlist-apply');
        if (applyBtn) { applyBtn.textContent = __('Apply', 'ticketsstatistics'); }

        var solvedLabelMap = {
            resolved_in_period: __('Resolved / Closed in period', 'ticketsstatistics'),
            opened_in_period: __('Opened in period', 'ticketsstatistics'),
            avg_ttr: __('Average TTR', 'ticketsstatistics'),
        };
        Object.keys(solvedLabelMap).forEach(function (key) {
            var el = document.querySelector('[data-ts-solved-label="' + key + '"]');
            if (el) { el.textContent = solvedLabelMap[key]; }
        });
        var switchLabel = document.querySelector('label[for="ts-ticketlist-view-solved"]');
        if (switchLabel) { switchLabel.textContent = __('Resolved period view', 'ticketsstatistics'); }
    }

    // Detect when the ticketsstatistics domain is loaded by trying to translate
    // a known string and checking it changed from the raw msgid.
    // This avoids depending on Jed internals and works for all GLPI versions.
    function isDomainLoaded() {
        try {
            // Use a string whose en_US translation differs from the msgid in
            // other locales. If the result differs from the msgid the domain
            // is loaded. For en_US both are identical, so we cap at 10 tries
            // (1 s) and accept the English fallback — it is already correct.
            return window.i18n &&
                typeof window.i18n.dcnpgettext === 'function' &&
                window.i18n.dcnpgettext('ticketsstatistics', undefined, 'Last 7 days', undefined, undefined) !== 'Last 7 days';
        } catch (e) {
            return false;
        }
    }

    // Poll for the ticketsstatistics domain (loaded async by GLPI's locale AJAX).
    // The widget is already visible; this only updates text labels once ready.
    function waitForDomain(tries) {
        if (isDomainLoaded()) {
            applyTranslations();
        } else if (tries > 0) {
            setTimeout(function () { waitForDomain(tries - 1); }, 100);
        } else {
            // Timed out — run anyway in case the domain loaded but the locale is
            // en_US (msgid === msgstr so isDomainLoaded() always returns false).
            applyTranslations();
        }
    }

    // Run init immediately so the widget replaces the GLPI mini-dashboard at once.
    // Then start polling for translations (dom is ready at <body> end).
    init();
    $(function () { waitForDomain(50); });
})();
