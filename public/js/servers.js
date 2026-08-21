document.addEventListener('DOMContentLoaded', function () {
    const dataEl = document.getElementById('ts-servers-chart-data');
    if (!dataEl) {
        return;
    }

    let modal = null;
    const modalNode = document.getElementById('ts-servers-modal');
    if (modalNode && typeof bootstrap !== 'undefined') {
        modal = bootstrap.Modal.getOrCreateInstance(modalNode);
    }

    const getTownFilterValue = function () {
        const input = document.querySelector('#ts-servers-town select');
        return input ? (parseInt(input.value || '0', 10) || 0) : 0;
    };

    const getEntityFilterValue = function () {
        const input = document.querySelector('#ts-servers-entity select');
        return input ? (parseInt(input.value || '0', 10) || 0) : 0;
    };

    const buildScopeParams = function (extra) {
        const params = Object.assign({}, extra || {});
        params.town_id = String(getTownFilterValue());
        params.entity_id = String(getEntityFilterValue());
        return params;
    };

    const toQuery = function (obj) {
        const params = new URLSearchParams();
        Object.keys(obj).forEach(function (key) {
            const value = obj[key];
            if (value !== undefined && value !== null && value !== '') {
                params.set(key, String(value));
            }
        });
        return params.toString();
    };

    const escapeHtml = function (value) {
        return String(value == null ? '' : value);
    };

    const renderLoader = function () {
        return '<p class="text-center my-4">'
            + '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>'
            + escapeHtml(dataEl.dataset.loadingServersLabel || __('Loading servers...', 'ticketsstatistics'))
            + '</p>';
    };

    const formatCount = function (count) {
        return count === 1 ? '1 ' + __('server', 'ticketsstatistics') : count + ' ' + __('servers', 'ticketsstatistics');
    };

    const renderServersTable = function (servers) {
        const rows = servers.map(function (server) {
            const badgeClass = server.is_hypervisor
                ? 'bg-purple text-white'
                : (server.is_virtual ? 'bg-warning text-dark' : 'bg-success text-white');

            const hostedVmsBadge = server.hosted_vms_count > 0
                ? '<span class="badge bg-indigo-lt fw-bold">' + server.hosted_vms_count + '</span>'
                : '-';

            return '<tr>'
                + '<td>' + escapeHtml(server.id) + '</td>'
                + '<td><a href="' + escapeHtml(server.url || '#') + '" target="_blank" class="fw-semibold">' + escapeHtml(server.name || '') + '</a></td>'
                + '<td><span class="badge ' + badgeClass + '">' + escapeHtml(server.server_type_label || '') + '</span></td>'
                + '<td>' + escapeHtml(server.manufacturer || '-') + ' ' + escapeHtml(server.model !== '-' ? server.model : '') + '</td>'
                + '<td>' + escapeHtml(server.os_name || '-') + (server.os_version !== '-' ? ' (' + escapeHtml(server.os_version) + ')' : '') + '</td>'
                + '<td class="text-center">' + hostedVmsBadge + '</td>'
                + '<td>' + escapeHtml(server.serial || '-') + '</td>'
                + '<td>' + escapeHtml(server.town || '-') + '</td>'
                + '<td>' + escapeHtml(server.entity || '-') + '</td>'
                + '<td>' + escapeHtml(server.last_update || '-') + '</td>'
                + '</tr>';
        }).join('');

        return '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">'
            + '<thead><tr>'
            + '<th>' + escapeHtml(__('ID', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Name', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Nature', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Hardware / Model', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Operating system', 'ticketsstatistics')) + '</th>'
            + '<th class="text-center">' + escapeHtml(__('Hosted VMs', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Serial number', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Town', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Entity', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Last update', 'ticketsstatistics')) + '</th>'
            + '</tr></thead>'
            + '<tbody>' + rows + '</tbody>'
            + '</table></div>';
    };

    const openServersModal = function (scopeParams) {
        if (!modal) {
            return;
        }

        const titleNode = document.getElementById('ts-servers-modal-title');
        const countNode = document.getElementById('ts-servers-modal-count');
        const alertNode = document.getElementById('ts-servers-modal-alert');
        const bodyNode = document.getElementById('ts-servers-modal-body');
        const downloadBtn = document.getElementById('ts-servers-modal-download-btn');
        const fullBtn = document.getElementById('ts-servers-modal-full-btn');

        if (titleNode) {
            titleNode.textContent = dataEl.dataset.loadingServersLabel || __('Loading servers...', 'ticketsstatistics');
        }
        if (countNode) {
            countNode.textContent = '';
        }
        if (alertNode) {
            alertNode.classList.add('d-none');
            alertNode.textContent = '';
        }
        if (downloadBtn) {
            downloadBtn.disabled = true;
            downloadBtn.onclick = null;
        }
        if (fullBtn) {
            fullBtn.disabled = true;
            fullBtn.onclick = null;
        }
        if (bodyNode) {
            bodyNode.innerHTML = renderLoader();
        }

        modal.show();

        const params = buildScopeParams(scopeParams);
        const query = toQuery(params);

        fetch((dataEl.dataset.serversAjaxUrl || '') + '?' + query)
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                const servers = Array.isArray(payload.servers) ? payload.servers : [];
                const count = payload.count || servers.length;

                if (titleNode) {
                    titleNode.textContent = payload.title || __('Servers', 'ticketsstatistics');
                }
                if (countNode) {
                    countNode.textContent = formatCount(count);
                }
                if (alertNode) {
                    if (payload.truncated && payload.limit) {
                        alertNode.textContent = (dataEl.dataset.truncatedLabel || 'Display limited to %d servers. Use full list or CSV for all entries.')
                            .replace('%d', String(payload.limit));
                        alertNode.classList.remove('d-none');
                    } else {
                        alertNode.classList.add('d-none');
                    }
                }
                if (downloadBtn) {
                    downloadBtn.disabled = false;
                    downloadBtn.onclick = function () {
                        window.location.href = (dataEl.dataset.serversExportUrl || '') + '?' + query;
                    };
                }
                if (fullBtn) {
                    fullBtn.disabled = false;
                    fullBtn.onclick = function () {
                        window.open((dataEl.dataset.serversFullListUrl || '') + '?' + query, '_blank');
                    };
                }
                if (bodyNode) {
                    bodyNode.innerHTML = servers.length > 0
                        ? renderServersTable(servers)
                        : '<div class="alert alert-secondary mb-0">' + escapeHtml(dataEl.dataset.noServersLabel || __('No servers found for this selection.', 'ticketsstatistics')) + '</div>';
                }
            })
            .catch(function () {
                if (titleNode) {
                    titleNode.textContent = __('Servers', 'ticketsstatistics');
                }
                if (countNode) {
                    countNode.textContent = '';
                }
                if (bodyNode) {
                    bodyNode.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(dataEl.dataset.unableLoadServersLabel || __('Unable to load servers.', 'ticketsstatistics')) + '</div>';
                }
            });
    };

    document.querySelectorAll('.ts-servers-card[data-counter-key]').forEach(function (card) {
        card.addEventListener('click', function () {
            const counterKey = card.dataset.counterKey || 'total';
            openServersModal({ counter_key: counterKey });
        });
    });

    // Table quick search filter on main page
    const searchInput = document.getElementById('ts-servers-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const val = this.value.toLowerCase().trim();
            const tableRows = document.querySelectorAll('#ts-servers-main-table tbody tr');
            tableRows.forEach(function (tr) {
                const text = tr.textContent.toLowerCase();
                tr.style.display = text.includes(val) ? '' : 'none';
            });
        });
    }
});
