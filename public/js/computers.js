document.addEventListener('DOMContentLoaded', function () {
    const dataEl = document.getElementById('ts-computers-chart-data');
    if (!dataEl) {
        return;
    }

    let modal = null;
    let lastFilters = null;
    const modalNode = document.getElementById('ts-computers-modal');
    if (modalNode && typeof bootstrap !== 'undefined') {
        modal = bootstrap.Modal.getOrCreateInstance(modalNode);
    }

    const parseJson = function (value) {
        if (!value) {
            return null;
        }

        try {
            return JSON.parse(value);
        } catch (e) {
            return null;
        }
    };

    Array.from(document.getElementsByClassName('ts-reset-chart')).forEach(el => el.addEventListener('click', function () {
        const canvas = document.getElementById(this.dataset.canvas);
        const chart = Chart.getChart(canvas);
        chart.resetZoom();
    }));

    const versionChartData = parseJson(dataEl.dataset.versionChart) || { labels: [], values: [] };
    const townVersionChartData = parseJson(dataEl.dataset.townVersionChart) || { labels: [], versions: [], values: {} };
    const kbChartData = parseJson(dataEl.dataset.kbChart) || { labels: [], values: [] };
    const getTownFilterValue = function () {
        const input = document.querySelector('#ts-computers-town select');
        return input ? (parseInt(input.value || '0', 10) || 0) : 0;
    };

    const getEntityFilterValue = function () {
        const input = document.querySelector('#ts-computers-entity select');
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

    const renderLoader = function () {
        return '<p class="text-center my-4">'
            + '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>'
            + escapeHtml(dataEl.dataset.loadingComputersLabel || __('Loading computers...', 'ticketsstatistics'))
            + '</p>';
    };

    const formatCount = function (count) {
        return count === 1 ? '1 ' + __('computer', 'ticketsstatistics') : count + ' ' + __('computers', 'ticketsstatistics');
    };

    const renderComputersTable = function (computers) {
        const rows = computers.map(function (computer) {
            const kbCodes = Array.isArray(computer.kb_codes) ? computer.kb_codes.join(', ') : '';
            return '<tr>'
                + '<td>' + escapeHtml(computer.id) + '</td>'
                + '<td><a href="' + escapeHtml(computer.url || '#') + '" target="_blank" class="fw-semibold">' + escapeHtml(computer.name || '') + '</a></td>'
                + '<td>' + escapeHtml(computer.user_name || '') + '</td>'
                + '<td>' + escapeHtml(computer.version_os || '') + '</td>'
                + '<td>' + escapeHtml(kbCodes) + '</td>'
                + '<td>' + escapeHtml(computer.serial || '') + '</td>'
                + '<td>' + escapeHtml(computer.inventory_number || '') + '</td>'
                + '<td>' + escapeHtml(computer.town || '') + '</td>'
                + '<td>' + escapeHtml(computer.last_update || '') + '</td>'
                + '</tr>';
        }).join('');

        return '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">'
            + '<thead><tr>'
            + '<th>' + escapeHtml(__('ID', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Name', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('User', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('OS version', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('KB patches', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Serial number', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Inventory number', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Town', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Last update', 'ticketsstatistics')) + '</th>'
            + '</tr></thead><tbody>' + rows + '</tbody></table></div>';
    };

    const renderKbRowsTable = function (rows) {
        const tableRows = rows.map(function (row) {
            return '<tr>'
                + '<td>' + escapeHtml(row.kb_code || '') + '</td>'
                + '<td>' + escapeHtml(row.installations || 0) + '</td>'
                + '</tr>';
        }).join('');

        return '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">'
            + '<thead><tr>'
            + '<th>' + escapeHtml(__('KB patches', 'ticketsstatistics')) + '</th>'
            + '<th>' + escapeHtml(__('Installations', 'ticketsstatistics')) + '</th>'
            + '</tr></thead><tbody>' + tableRows + '</tbody></table></div>';
    };

    const openComputersModal = function (scopeFilters) {
        if (!modal) {
            return;
        }

        lastFilters = buildScopeParams(scopeFilters);

        const titleNode = document.getElementById('ts-computers-modal-title');
        const countNode = document.getElementById('ts-computers-modal-count');
        const alertNode = document.getElementById('ts-computers-modal-alert');
        const bodyNode = document.getElementById('ts-computers-modal-body');
        const downloadBtn = document.getElementById('ts-computers-modal-download-btn');
        const fullBtn = document.getElementById('ts-computers-modal-full-btn');

        if (titleNode) {
            titleNode.textContent = dataEl.dataset.loadingComputersLabel || __('Loading computers...', 'ticketsstatistics');
        }
        if (countNode) {
            countNode.textContent = '';
        }
        if (alertNode) {
            alertNode.classList.add('d-none');
            alertNode.textContent = '';
        }
        if (bodyNode) {
            bodyNode.innerHTML = renderLoader();
        }
        if (downloadBtn) {
            downloadBtn.disabled = false;
            downloadBtn.onclick = function () {
                const q = toQuery(lastFilters || {});
                window.open((dataEl.dataset.computersExportUrl || '') + '?' + q, '_blank');
            };
        }
        if (fullBtn) {
            if (scopeFilters.scope === 'kb') {
                fullBtn.disabled = true;
                fullBtn.onclick = null;
            } else {
                fullBtn.disabled = false;
                fullBtn.onclick = function () {
                    const q = toQuery(lastFilters || {});
                    window.open((dataEl.dataset.computersFullListUrl || '') + '?' + q, '_blank');
                };
            }
        }

        modal.show();

        const query = toQuery(lastFilters);
        fetch((dataEl.dataset.computersAjaxUrl || '') + '?' + query)
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (titleNode) {
                    titleNode.textContent = payload.title || __('Computers', 'ticketsstatistics');
                }
                if (countNode) {
                    countNode.textContent = formatCount(payload.count || 0);
                }
                if (alertNode) {
                    if (payload.truncated) {
                        const label = dataEl.dataset.showingFirstComputersLabel || __('Showing the first %d computers only.', 'ticketsstatistics');
                        alertNode.textContent = label.replace('%d', String(payload.limit || 100));
                        alertNode.classList.remove('d-none');
                    } else {
                        alertNode.classList.add('d-none');
                    }
                }
                if (bodyNode) {
                    bodyNode.innerHTML = Array.isArray(payload.computers) && payload.computers.length > 0
                        ? renderComputersTable(payload.computers)
                        : '<div class="alert alert-secondary mb-0">' + escapeHtml(dataEl.dataset.noComputersLabel || __('No computers found for this selection.', 'ticketsstatistics')) + '</div>';
                }
            })
            .catch(function () {
                if (titleNode) {
                    titleNode.textContent = __('Computers', 'ticketsstatistics');
                }
                if (countNode) {
                    countNode.textContent = '';
                }
                if (alertNode) {
                    alertNode.classList.add('d-none');
                }
                if (bodyNode) {
                    bodyNode.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(dataEl.dataset.unableLoadComputersLabel || __('Unable to load computers.', 'ticketsstatistics')) + '</div>';
                }
            });
    };

    const openKbSummaryModal = function () {
        if (!modal) {
            return;
        }

        const titleNode = document.getElementById('ts-computers-modal-title');
        const countNode = document.getElementById('ts-computers-modal-count');
        const alertNode = document.getElementById('ts-computers-modal-alert');
        const bodyNode = document.getElementById('ts-computers-modal-body');
        const downloadBtn = document.getElementById('ts-computers-modal-download-btn');
        const fullBtn = document.getElementById('ts-computers-modal-full-btn');

        if (titleNode) {
            titleNode.textContent = dataEl.dataset.loadingComputersLabel || __('Loading computers...', 'ticketsstatistics');
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

        const query = toQuery({
            town_id: String(getTownFilterValue()),
            entity_id: String(getEntityFilterValue()),
        });
        fetch((dataEl.dataset.computersKbSummaryUrl || '') + '?' + query)
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                const rows = Array.isArray(payload.kbs) ? payload.kbs : [];
                const kbCount = payload.count || rows.length;

                if (titleNode) {
                    titleNode.textContent = payload.title || __('Total KB patches deployed', 'ticketsstatistics');
                }
                if (countNode) {
                    countNode.textContent = kbCount === 1 ? '1 KB' : kbCount + ' KB';
                }
                if (bodyNode) {
                    bodyNode.innerHTML = rows.length > 0
                        ? renderKbRowsTable(rows)
                        : '<div class="alert alert-secondary mb-0">' + escapeHtml(dataEl.dataset.noComputersLabel || __('No computers found for this selection.', 'ticketsstatistics')) + '</div>';
                }
            })
            .catch(function () {
                if (titleNode) {
                    titleNode.textContent = __('Total KB patches deployed', 'ticketsstatistics');
                }
                if (countNode) {
                    countNode.textContent = '';
                }
                if (bodyNode) {
                    bodyNode.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(dataEl.dataset.unableLoadComputersLabel || __('Unable to load computers.', 'ticketsstatistics')) + '</div>';
                }
            });
    };

    document.querySelectorAll('.ts-computers-card[data-counter-key]').forEach(function (card) {
        card.addEventListener('click', function () {
            const counterKey = card.dataset.counterKey || '';
            if (counterKey === 'kb_total') {
                openKbSummaryModal();
                return;
            }

            openComputersModal({ scope: 'counter', counter_key: counterKey });
        });
    });

    const escapeHtml = function (value) {
        return String(value == null ? '' : value);
    };

    const versionColorMap = {};
    const colorForVersion = function (version, indexFallback) {
        if (versionColorMap[version]) {
            return versionColorMap[version];
        }

        const index = typeof indexFallback === 'number' ? indexFallback : Object.keys(versionColorMap).length;

        // Première couleur : vert fixe #15c254
        let color;
        if (index === 0) {
            color = '#15c254';
        } else {
            color = 'hsl(' + (((index - 1) * 57) % 360) + ', 68%, 52%)';
        }

        versionColorMap[version] = color;
        return color;
    };

    if (window.ChartDataLabels) {
        Chart.register(ChartDataLabels);
    }

    const versionCanvas = document.getElementById('ts-computers-version-chart');
    if (versionCanvas && Array.isArray(versionChartData.labels) && versionChartData.labels.length > 0) {
        const colors = versionChartData.labels.map(function (version, index) {
            return colorForVersion(version, index);
        });

        new Chart(versionCanvas, {
            type: 'doughnut',
            data: {
                labels: versionChartData.labels,
                datasets: [{
                    data: versionChartData.values,
                    backgroundColor: colors,
                    hoverOffset: 10,
                }],
            },
            options: {
                onClick: function (_, elements) {
                    if (!elements || !elements.length) {
                        return;
                    }
                    const index = elements[0].index;
                    openComputersModal({
                        scope: 'version',
                        version: versionChartData.labels[index] || '',
                    });
                },
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    datalabels: {
                        color: '#fff',
                        formatter: function (value) {
                            return value > 0 ? value : '';
                        },
                    },
                },
            },
        });
    }

    const townCanvas = document.getElementById('ts-computers-town-version-chart');
    if (townCanvas
        && Array.isArray(townVersionChartData.labels)
        && townVersionChartData.labels.length > 0
        && Array.isArray(townVersionChartData.versions)
        && townVersionChartData.versions.length > 0
    ) {
        const datasets = townVersionChartData.versions.map(function (version, index) {
            const color = colorForVersion(version, index);
            const values = townVersionChartData.values && townVersionChartData.values[version]
                ? townVersionChartData.values[version]
                : [];

            return {
                label: version,
                data: values,
                backgroundColor: color,
                borderColor: color,
                borderWidth: 1,
            };
        });

        new Chart(townCanvas, {
            type: 'bar',
            data: {
                labels: townVersionChartData.labels,
                datasets: datasets,
            },
            options: {
                onClick: function (_, elements) {
                    if (!elements || !elements.length) {
                        return;
                    }
                    const point = elements[0];
                    const townLabel = townVersionChartData.labels[point.index] || '';
                    const versionLabel = townVersionChartData.versions[point.datasetIndex] || '';
                    openComputersModal({
                        scope: 'town_version',
                        town: townLabel,
                        version: versionLabel,
                    });
                },
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    zoom: {
                        pan: {
                            enabled: true,
                            mode: 'xy',
                        },
                        zoom: {
                            wheel: {
                                enabled: true,
                            },
                            pinch: {
                                enabled: true
                            },
                            mode: 'xy',
                        },
                        limits: {
                            y: { min: 0, max: 700 },
                        },
                    },
                    datalabels: {
                        color: '#fff',
                        formatter: function (value) {
                            return value > 0 ? value : '';
                        },
                    },
                },
                scales: {
                    x: {
                        stacked: true,
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        min: 0,
                        ticks: {
                            precision: 0,
                        },
                    },
                },
            },
        });
    }

    const kbCanvas = document.getElementById('ts-computers-kb-chart');
    if (kbCanvas && Array.isArray(kbChartData.labels) && kbChartData.labels.length > 0) {
        new Chart(kbCanvas, {
            type: 'bar',
            data: {
                labels: kbChartData.labels,
                datasets: [
                    {
                        label: __('Installations', 'ticketsstatistics'),
                        data: kbChartData.values,
                        backgroundColor: '#7c3aed',
                        borderColor: '#6d28d9',
                        borderWidth: 1,
                        datalabels: {
                            anchor: 'end',
                            align: 'end', // au-dessus de la barre (barre positive)
                        },
                    },
                    {
                        label: __('Missing', 'ticketsstatistics'),
                        data: kbChartData.missing,
                        backgroundColor: '#ef4444',
                        borderColor: '#b91c1c',
                        borderWidth: 1,
                        datalabels: {
                            anchor: 'start',
                            align: 'start', // en dessous de la barre (barre négative)
                        },
                    }
                ],
            },
            options: {
                onClick: function (_, elements) {
                    if (!elements || !elements.length) {
                        return;
                    }
                    const index = elements[0].index;
                    const dataset = elements[0].datasetIndex == 0 ? 'installed' : 'missing';
                    openComputersModal({
                        scope: 'kb',
                        kb_dataset: dataset,
                        kb_code: kbChartData.labels[index] || '',
                    });
                },
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    zoom: {
                        pan: {
                            enabled: true,
                            mode: 'xy',
                        },
                        zoom: {
                            wheel: {
                                enabled: true,
                            },
                            pinch: {
                                enabled: true
                            },
                            mode: 'xy',
                        },
                        limits: {
                            y: { min: -700, max: 700 },
                        },
                    },
                    datalabels: {
                        color: '#374151',
                        formatter: function (value) {
                            const abs = Math.abs(value);
                            return abs > 0 ? abs : '';
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const abs = Math.abs(context.parsed.y);
                                return context.dataset.label + ': ' + abs;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        stacked: true,
                        ticks: {
                            maxRotation: 45,
                            minRotation: 25,
                        },
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            callback: function (value) {
                                return Math.round(Math.abs(value));
                            },
                        },
                    },
                },
            },
        });
    }
});
