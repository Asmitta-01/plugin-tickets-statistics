/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI — ticket-list counter cards modal
 * -------------------------------------------------------------------------
 * Shared module used by both GLPI 10 (ticketlist-glpi10.js) and GLPI 11
 * (inline script injected by plugin_ticketsstatistics_pre_item_list).
 *
 * Exposes:  window.tsTicketlistCardsModal.init(options)
 *
 * options.cardSelector  – CSS selector for the clickable card elements.
 *                         Defaults to '[data-ts-tl-counter-key]'.
 * options.getFilters    – function() → { period, date_from, date_to }
 *                         Defaults to thismonth with no custom dates.
 * -------------------------------------------------------------------------
 */
(function () {
    'use strict';

    var MODAL_ID = 'ts-tl-tickets-modal';

    // Counter key → status_group value accepted by ajax/tickets.php.
    // An empty string means "no status filter" (= all tickets).
    var STATUS_GROUP_MAP = {
        incoming: 'incoming',
        assigned: 'assigned',
        waiting: 'waiting',
        solved_closed: 'solved_closed',
        total: ''
    };

    // -----------------------------------------------------------------
    // Modal DOM (created once, re-used for every card click)
    // -----------------------------------------------------------------
    function getOrCreateModal() {
        var existing = document.getElementById(MODAL_ID);
        if (existing) {
            return existing;
        }

        var el = document.createElement('div');
        el.className = 'modal fade';
        el.id = MODAL_ID;
        el.tabIndex = -1;
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML =
            '<div class="modal-dialog modal-xl modal-dialog-scrollable">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<div>' +
            '<h5 class="modal-title mb-0" id="' + MODAL_ID + '-title"></h5>' +
            '<div class="text-muted small" id="' + MODAL_ID + '-count"></div>' +
            '</div>' +
            '<div class="d-flex align-items-center gap-2 ms-auto me-2">' +
            '<button class="btn btn-sm btn-outline-primary" id="' + MODAL_ID + '-full-btn">' +
            '<i class="ti ti-link me-1"></i>' + escapeHtml(__('Open full list', 'ticketsstatistics')) +
            '</button>' +
            '<button class="btn btn-sm btn-outline-secondary" id="' + MODAL_ID + '-dl-btn" disabled>' +
            '<i class="ti ti-download me-1"></i>CSV' +
            '</button>' +
            '</div>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div id="' + MODAL_ID + '-alert" class="alert alert-info d-none mb-3"></div>' +
            '<div id="' + MODAL_ID + '-body"></div>' +
            '</div>' +
            '</div>' +
            '</div>';

        document.body.appendChild(el);
        return el;
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------
    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderLoader() {
        return '<p class="text-center my-4">' +
            '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' +
            escapeHtml(__('Loading...', 'ticketsstatistics')) +
            '</p>';
    }

    function renderTable(tickets) {
        var rows = tickets.map(function (t) {
            return '<tr>'
                + '<td>' + escapeHtml(t.id) + '</td>'
                + '<td><a href="' + escapeHtml(t.url) + '" target="_blank" class="fw-semibold">' + escapeHtml(t.name) + '</a></td>'
                + '<td>' + escapeHtml(t.status) + '</td>'
                + '<td>' + escapeHtml(t.last_update) + '</td>'
                + '<td>' + escapeHtml(t.creation) + '</td>'
                + '<td>' + escapeHtml(t.closed) + '</td>'
                + '<td>' + escapeHtml(t.category) + '</td>'
                + '<td>' + escapeHtml(t.town) + '</td>'
                + '</tr>';
        }).join('');

        return '<div class="table-responsive">'
            + '<table class="table table-sm table-hover align-middle mb-0">'
            + '<thead><tr>'
            + '<th>' + escapeHtml(__('ID', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Title', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Status', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Last update', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Creation', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Close date', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Category', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Town', 'ticketsstatistics')) + '</th>'
            + '</tr></thead>'
            + '<tbody>' + rows + '</tbody>'
            + '</table></div>';
    }

    function exportCsv(tickets) {
        var headers = [
            __('ID', 'ticketsstatistics'),
            __('Title', 'ticketsstatistics'),
            __('Status', 'ticketsstatistics'),
            __('Last update', 'ticketsstatistics'),
            __('Creation', 'ticketsstatistics'),
            __('Close date', 'ticketsstatistics'),
            __('Category', 'ticketsstatistics'),
            __('Town', 'ticketsstatistics'),
        ];

        var esc = function (v) {
            return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
        };

        var rows = tickets.map(function (t) {
            return [t.id, t.name, t.status, t.last_update, t.creation, t.closed, t.category, t.town]
                .map(esc).join(';');
        });

        var csv = [headers.map(esc).join(';')].concat(rows).join('\r\n');

        var latin1 = new Uint8Array(csv.split('').map(function (c) {
            var code = c.charCodeAt(0);
            return code > 255 ? 63 : code;
        }));

        var blob = new Blob([latin1], { type: 'text/csv;charset=iso-8859-1;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'Tickets.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // -----------------------------------------------------------------
    // Core: open the modal and fetch the ticket list
    // -----------------------------------------------------------------
    function openModal(counterKey, label, getFilters) {
        var modalEl = getOrCreateModal();
        var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);

        var titleEl = document.getElementById(MODAL_ID + '-title');
        var countEl = document.getElementById(MODAL_ID + '-count');
        var alertEl = document.getElementById(MODAL_ID + '-alert');
        var bodyEl = document.getElementById(MODAL_ID + '-body');
        var dlBtn = document.getElementById(MODAL_ID + '-dl-btn');
        var fullBtn = document.getElementById(MODAL_ID + '-full-btn');

        // Reset state
        titleEl.textContent = __('Loading tickets...', 'ticketsstatistics');
        countEl.textContent = '';
        alertEl.textContent = '';
        alertEl.classList.add('d-none');
        bodyEl.innerHTML = renderLoader();
        dlBtn.disabled = true;
        dlBtn.onclick = null;
        if (fullBtn) {
            fullBtn.onclick = function () {
                openFullList(counterKey, label, getFilters);
            };
        }

        bsModal.show();

        // Build request params
        var filters = typeof getFilters === 'function' ? (getFilters() || {}) : {};
        var period = filters.period || 'thismonth';
        var statusGroup = Object.prototype.hasOwnProperty.call(STATUS_GROUP_MAP, counterKey)
            ? STATUS_GROUP_MAP[counterKey]
            : counterKey;

        var params = new URLSearchParams();
        params.set('type', 'counter');
        params.set('label', label || '');
        params.set('period', period);
        if (period === 'custom') {
            if (filters.date_from) { params.set('date_from', filters.date_from); }
            if (filters.date_to) { params.set('date_to', filters.date_to); }
        }
        if (statusGroup) {
            params.set('status_group', statusGroup);
        }

        var root = CFG_GLPI.root_doc;
        fetch(root + '/plugins/ticketsstatistics/ajax/tickets.php?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                titleEl.textContent = payload.title;
                countEl.textContent = payload.count === 1
                    ? __('1 ticket', 'ticketsstatistics')
                    : payload.count + ' ' + __('tickets', 'ticketsstatistics');

                if (payload.truncated) {
                    alertEl.textContent = __('Showing the first 100 tickets only.', 'ticketsstatistics');
                    alertEl.classList.remove('d-none');
                }

                var stored = payload.tickets || [];
                dlBtn.disabled = stored.length === 0;
                dlBtn.onclick = function () {
                    if (stored.length) { exportCsv(stored); }
                };

                bodyEl.innerHTML = stored.length
                    ? renderTable(stored)
                    : '<div class="alert alert-secondary mb-0">'
                    + escapeHtml(__('No tickets found for this selection.', 'ticketsstatistics'))
                    + '</div>';
            })
            .catch(function () {
                titleEl.textContent = __('Tickets', 'ticketsstatistics');
                countEl.textContent = '';
                alertEl.classList.add('d-none');
                bodyEl.innerHTML = '<div class="alert alert-danger mb-0">'
                    + escapeHtml(__('Unable to load tickets.', 'ticketsstatistics'))
                    + '</div>';
            });
    }

    //-----------------------------------------------------------------
    // Ouverture d'une nouvelle page pour voir la liste complète des tickets (sans limite de 100)
    //-----------------------------------------------------------------
    function openFullList(counterKey, label, getFilters) {
        var filters = typeof getFilters === 'function' ? (getFilters() || {}) : {};
        var period = filters.period || 'thismonth';

        var req = new URLSearchParams();
        req.set('counter_key', counterKey || '');
        req.set('period', period);
        if (period === 'custom') {
            if (filters.date_from) {
                req.set('date_from', filters.date_from);
            }
            if (filters.date_to) {
                req.set('date_to', filters.date_to);
            }
        }

        var root = CFG_GLPI.root_doc;
        fetch(root + '/plugins/ticketsstatistics/ajax/tickets_full_list_url.php?' + req.toString())
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (!payload || !payload.url) {
                    return;
                }

                var fullUrl = payload.url;
                if (fullUrl.charAt(0) === '/') {
                    fullUrl = window.location.origin + fullUrl;
                }

                window.open(new URL(fullUrl), '_self');
            })
            .catch(function (e) {
                console.error('Error fetching full list URL:', e);
            });
    }

    // -----------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------

    /**
     * Bind click/keyboard handlers to all matching counter cards.
     * Safe to call multiple times — already-bound cards are skipped.
     *
     * @param {object} options
     * @param {string}   [options.cardSelector]  CSS selector for card elements.
     * @param {function} [options.getFilters]    Returns { period, date_from, date_to }.
     */
    function init(options) {
        var opts = options || {};
        var selector = opts.cardSelector || '[data-ts-tl-counter-key]';
        var getFilters = typeof opts.getFilters === 'function'
            ? opts.getFilters
            : function () { return { period: 'thismonth', date_from: '', date_to: '' }; };

        document.querySelectorAll(selector).forEach(function (card) {
            if (card.getAttribute('data-ts-tl-modal-bound') === '1') {
                return;
            }
            card.setAttribute('data-ts-tl-modal-bound', '1');
            card.style.cursor = 'pointer';
            card.setAttribute('role', 'button');
            card.setAttribute('tabindex', '0');

            var handler = function () {
                var key = card.getAttribute('data-ts-tl-counter-key') || '';
                var label = card.getAttribute('data-ts-tl-counter-label') || __('Tickets', 'ticketsstatistics');
                // openModal(key, label, getFilters);
                openFullList(key, label, getFilters);
            };

            card.addEventListener('click', handler);
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    handler();
                }
            });
        });
    }

    window.tsTicketlistCardsModal = { init: init };
})();
