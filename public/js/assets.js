let tsSoftwareCoverageChart = null;
let tsAssetsChartDataNode = null;
let tsAssetsModal = null;
let tsAssetsModalComputers = [];

document.addEventListener('DOMContentLoaded', function () {
    const chartDataNode = document.getElementById('ts-assets-chart-data');
    if (!chartDataNode || typeof Chart === 'undefined') {
        return;
    }
    tsAssetsChartDataNode = chartDataNode;

    if (typeof bootstrap !== 'undefined') {
        const modalNode = document.getElementById('ts-assets-modal');
        if (modalNode) {
            tsAssetsModal = new bootstrap.Modal(modalNode);
        }
    }

    if (typeof ChartDataLabels !== 'undefined' && !window.tsAssetsChartLabelsRegistered) {
        Chart.register(ChartDataLabels);
        window.tsAssetsChartLabelsRegistered = true;
    }

    [
        {
            id: 'ts-assets-town-chart',
            data: parseChartData(chartDataNode.dataset.townChart),
        },
        {
            id: 'ts-assets-manufacturer-chart',
            data: parseChartData(chartDataNode.dataset.manufacturerChart),
        },
    ].forEach(function (chart) {
        renderStackedChart(chart.id, chart.data);
    });

    const topSoftwaresRaw = chartDataNode.dataset.topSoftwaresChart;
    if (topSoftwaresRaw) {
        renderTopSoftwareChart('ts-assets-top-software-chart', topSoftwaresRaw);
    }

    const coverageRaw = chartDataNode.dataset.softwareCoverageChart;
    if (coverageRaw) {
        tsSoftwareCoverageChart = renderCoverageDonut('ts-assets-software-coverage-chart', coverageRaw);
    }

    initSoftwareCoverageAjax(chartDataNode);

    const toggleInput = document.getElementById('ts-assets-cards-toggle');
    if (toggleInput) {
        const savedState = localStorage.getItem('ts_assets_show_all_cards');
        if (savedState === 'true') {
            toggleInput.checked = true;
            document.querySelectorAll('.ts-assets-secondary-card').forEach(function (el) {
                el.classList.remove('d-none');
            });
        }

        toggleInput.addEventListener('change', function () {
            const isChecked = this.checked;
            localStorage.setItem('ts_assets_show_all_cards', isChecked ? 'true' : 'false');
            document.querySelectorAll('.ts-assets-secondary-card').forEach(function (el) {
                if (isChecked) {
                    el.classList.remove('d-none');
                } else {
                    el.classList.add('d-none');
                }
            });
        });
    }

    document.querySelectorAll('.ts-assets-card[data-counter-key]').forEach(function (card) {
        card.addEventListener('click', function () {
            const counterKey = card.dataset.counterKey || 'total';
            openAssetsModal(counterKey, chartDataNode);
        });
    });
});

let tsAssetsDrilldownModal = null;

function openAssetsModal(counterKey, chartDataNode) {
    if (!chartDataNode) {
        return;
    }

    const modalUrl = chartDataNode.dataset.assetsModalUrl;
    const exportUrl = chartDataNode.dataset.assetsExportUrl;
    const fullListUrl = chartDataNode.dataset.assetsFullListUrl;
    const townId = chartDataNode.dataset.townId || 0;
    const manufacturerId = chartDataNode.dataset.manufacturerId || 0;

    const modalNode = document.getElementById('ts-assets-modal');
    if (!modalNode) {
        return;
    }

    if (typeof bootstrap !== 'undefined' && !tsAssetsDrilldownModal) {
        tsAssetsDrilldownModal = new bootstrap.Modal(modalNode);
    }

    const titleNode = document.getElementById('ts-assets-modal-title');
    const countNode = document.getElementById('ts-assets-modal-count');
    const bodyNode = document.getElementById('ts-assets-modal-body');
    const alertNode = document.getElementById('ts-assets-modal-alert');
    const downloadBtn = document.getElementById('ts-assets-modal-download-btn');
    const fullBtn = document.getElementById('ts-assets-modal-full-btn');

    if (titleNode) titleNode.textContent = 'Chargement...';
    if (countNode) countNode.textContent = '';
    if (bodyNode) bodyNode.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
    if (alertNode) alertNode.classList.add('d-none');
    if (fullBtn) fullBtn.classList.remove('d-none');

    const params = new URLSearchParams({
        counter_key: counterKey,
        town_id: townId,
        manufacturer_id: manufacturerId
    });

    if (downloadBtn) {
        downloadBtn.onclick = null;
        downloadBtn.classList.remove('disabled');
        downloadBtn.href = `${exportUrl}?${params.toString()}`;
    }
    if (fullBtn) fullBtn.href = `${fullListUrl}?${params.toString()}`;

    if (tsAssetsDrilldownModal) {
        tsAssetsDrilldownModal.show();
    }

    fetch(`${modalUrl}?${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (titleNode) titleNode.textContent = data.title || 'Actifs';
            if (countNode) countNode.textContent = `${data.count || 0} élément(s)`;

            if (data.truncated && alertNode) {
                alertNode.textContent = `Affichage limité aux ${data.limit} premiers éléments. Utilisez l'export CSV ou la liste complète pour voir tout.`;
                alertNode.classList.remove('d-none');
            }

            if (!data.assets || data.assets.length === 0) {
                bodyNode.innerHTML = '<div class="text-muted text-center py-4">Aucun actif trouvé pour cette sélection.</div>';
                return;
            }

            let html = '<div class="table-responsive"><table class="table table-hover table-striped align-middle mb-0">';
            html += '<thead class="table-light"><tr>';
            html += '<th>Nom</th>';
            html += '<th>Type</th>';
            html += '<th>Fabricant</th>';
            html += '<th>Modèle</th>';
            html += '<th>N° Série</th>';
            html += '<th>Commune</th>';
            html += '<th>Entité</th>';
            html += '<th class="text-end">Action</th>';
            html += '</tr></thead><tbody>';

            data.assets.forEach(asset => {
                html += '<tr>';
                html += `<td class="fw-semibold">${escapeHtml(asset.name || '-')}</td>`;
                html += `<td><span class="badge bg-secondary-lt">${escapeHtml(asset.type_name || asset.itemtype)}</span></td>`;
                html += `<td>${escapeHtml(asset.manufacturer || '-')}</td>`;
                html += `<td>${escapeHtml(asset.model || '-')}</td>`;
                html += `<td><code>${escapeHtml(asset.serial || '-')}</code></td>`;
                html += `<td>${escapeHtml(asset.town || '-')}</td>`;
                html += `<td>${escapeHtml(asset.entity || '-')}</td>`;
                html += `<td class="text-end"><a href="${asset.url}" class="btn btn-sm btn-outline-primary" target="_blank"><i class="ti ti-external-link me-1"></i>Ouvrir</a></td>`;
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            bodyNode.innerHTML = html;
        })
        .catch(err => {
            console.error('Error fetching assets modal:', err);
            bodyNode.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement des actifs.</div>';
        });
}

function escapeHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function parseChartData(rawValue) {
    if (!rawValue) {
        return { labels: [], datasets: [] };
    }

    try {
        return JSON.parse(rawValue);
    } catch (error) {
        console.error('Failed to parse assets chart data', error);
        return { labels: [], datasets: [] };
    }
}

function renderStackedChart(canvasId, data) {
    if (!data.labels || data.labels.length === 0) {
        return;
    }

    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        return;
    }

    canvas.height = Math.max(260, data.labels.length * 34);

    new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: data.datasets,
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                datalabels: {
                    display: false,
                },
            },
            scales: {
                x: {
                    stacked: true,
                    beginAtZero: true,
                },
                y: {
                    stacked: true,
                },
            },
        },
    });
}

function renderTopSoftwareChart(canvasId, rawValue) {
    let data;
    try {
        data = JSON.parse(rawValue);
    } catch (e) {
        return;
    }

    if (!data.labels || data.labels.length === 0) {
        return;
    }

    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        return;
    }

    canvas.height = Math.max(260, data.labels.length * 30);

    new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: '',
                    data: data.values,
                    backgroundColor: '#C00000',
                },
            ],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                datalabels: {
                    display: true,
                    anchor: 'end',
                    align: 'end',
                    color: '#374151',
                    font: { size: 11 },
                    formatter: function (value) {
                        return value;
                    },
                },
            },
            scales: {
                x: { beginAtZero: true },
            },
        },
    });
}

function renderCoverageDonut(canvasId, rawValue) {
    let data;
    try {
        data = JSON.parse(rawValue);
    } catch (e) {
        return null;
    }

    if (!data || data.total <= 0 || (data.with === 0 && data.without === 0)) {
        return null;
    }

    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        return null;
    }

    return new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: [
                canvas.dataset.labelWith || 'With software',
                canvas.dataset.labelWithout || 'Without software',
            ],
            datasets: [
                {
                    data: [data.with, data.without],
                    backgroundColor: ['#16a34a', '#ef4444'],
                    hoverOffset: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            onClick: function (event, elements, chart) {
                if (!elements || elements.length === 0) {
                    return;
                }

                const index = elements[0].index;
                const coverage = index === 0 ? 'with' : 'without';
                openSoftwareCoverageComputersModal(coverage);
            },
            plugins: {
                legend: { position: 'bottom' },
                datalabels: {
                    display: true,
                    color: '#fff',
                    font: { size: 13, weight: 'bold' },
                    formatter: function (value, ctx) {
                        const total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                        if (total === 0) return '';
                        return Math.round((value / total) * 100) + '%';
                    },
                },
            },
        },
    });
}

function initSoftwareCoverageAjax(chartDataNode) {
    const form = document.getElementById('ts-software-coverage-form');
    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        fetchSoftwareCoverage(form, chartDataNode);
    });

    const softwareInput = form.querySelector('select[name="software[]"], select[name="software"]');
    if (softwareInput) {
        softwareInput.addEventListener('change', function () {
            fetchSoftwareCoverage(form, chartDataNode);
        });
    }

    const matchAllInput = form.querySelector('[name="match_all"][type="checkbox"]');
    if (matchAllInput) {
        matchAllInput.addEventListener('change', function () {
            fetchSoftwareCoverage(form, chartDataNode);
        });
    }
}

function fetchSoftwareCoverage(form, chartDataNode) {
    const selectedSoftwareIds = getSelectedSoftwareIds(form);
    const ajaxUrl = form.dataset.ajaxUrl;

    if (!ajaxUrl) {
        return;
    }

    if (selectedSoftwareIds.length === 0) {
        applyCoverageState(
            {
                state: 'no_selection',
                coverage: { with: 0, without: 0, total: 0, name: '' },
            },
            chartDataNode
        );
        return;
    }

    const messageNode = document.getElementById('ts-assets-software-coverage-message');
    if (messageNode) {
        messageNode.textContent = chartDataNode.dataset.processingLabel || 'Processing...';
        messageNode.classList.remove('d-none');
    }

    const params = new URLSearchParams(new FormData(form));
    params.set('match_all', getMatchAllSelection(form) ? '1' : '0');
    fetch(ajaxUrl + '?' + params.toString(), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function (payload) {
            applyCoverageState(payload, chartDataNode);
        })
        .catch(function () {
            applyCoverageState(
                {
                    state: 'no_data',
                    coverage: { with: 0, without: 0, total: 0, name: '' },
                },
                chartDataNode
            );
        });
}

function applyCoverageState(payload, chartDataNode) {
    const summaryNode = document.getElementById('ts-assets-software-coverage-summary');
    const messageNode = document.getElementById('ts-assets-software-coverage-message');
    const chartColNode = document.getElementById('ts-assets-software-coverage-chart-col');
    const titleNode = document.getElementById('ts-assets-software-coverage-title');
    const withNode = document.getElementById('ts-assets-software-with');
    const withoutNode = document.getElementById('ts-assets-software-without');

    const noSelectionLabel = chartDataNode.dataset.noSoftwareSelectedLabel || 'No software selected';
    const noDataLabel = chartDataNode.dataset.noDataLabel || 'No data available';
    const coverageTitle = chartDataNode.dataset.softwareCoverageTitle || 'Software coverage';
    const state = (payload && payload.state) || 'no_data';
    const coverage = (payload && payload.coverage) || { with: 0, without: 0, total: 0, name: '' };

    if (state === 'has_data') {
        if (summaryNode) {
            summaryNode.classList.remove('d-none');
        }
        if (messageNode) {
            messageNode.classList.add('d-none');
        }
        if (chartColNode) {
            chartColNode.classList.remove('d-none');
        }
        if (withNode) {
            withNode.textContent = String(coverage.with || 0);
        }
        if (withoutNode) {
            withoutNode.textContent = String(coverage.without || 0);
        }
        if (titleNode) {
            titleNode.textContent = coverageTitle + ' - ' + (coverage.name || '');
        }

        if (tsSoftwareCoverageChart) {
            tsSoftwareCoverageChart.destroy();
        }
        tsSoftwareCoverageChart = renderCoverageDonut(
            'ts-assets-software-coverage-chart',
            JSON.stringify(coverage)
        );
        return;
    }

    if (summaryNode) {
        summaryNode.classList.add('d-none');
    }
    if (chartColNode) {
        chartColNode.classList.add('d-none');
    }
    if (messageNode) {
        messageNode.textContent = state === 'no_selection' ? noSelectionLabel : noDataLabel;
        messageNode.classList.remove('d-none');
    }
    if (titleNode) {
        titleNode.textContent = coverageTitle;
    }
    if (tsSoftwareCoverageChart) {
        tsSoftwareCoverageChart.destroy();
        tsSoftwareCoverageChart = null;
    }
}

function openSoftwareCoverageComputersModal(coverage) {
    if (!tsAssetsComputersModal || !tsAssetsChartDataNode) {
        return;
    }

    const form = document.getElementById('ts-software-coverage-form');
    const selectedSoftwareIds = form ? getSelectedSoftwareIds(form) : [];
    const ajaxUrl = tsAssetsChartDataNode.dataset.softwareComputersUrl || '';

    if (!ajaxUrl || selectedSoftwareIds.length === 0) {
        return;
    }

    const params = new URLSearchParams(new FormData(form));
    params.set('match_all', getMatchAllSelection(form) ? '1' : '0');
    params.set('coverage', coverage);

    const titleNode = document.getElementById('ts-assets-modal-title');
    const countNode = document.getElementById('ts-assets-modal-count');
    const alertNode = document.getElementById('ts-assets-modal-alert');
    const bodyNode = document.getElementById('ts-assets-modal-body');
    const downloadBtn = document.getElementById('ts-assets-modal-download-btn');
    const fullBtn = document.getElementById('ts-assets-modal-full-btn');

    if (fullBtn) {
        fullBtn.classList.add('d-none');
    }

    if (titleNode) {
        titleNode.textContent = tsAssetsChartDataNode.dataset.loadingComputersLabel || 'Loading computers...';
    }
    if (countNode) {
        countNode.textContent = '';
    }
    if (alertNode) {
        alertNode.textContent = '';
        alertNode.classList.add('d-none');
    }
    if (downloadBtn) {
        downloadBtn.removeAttribute('href');
        downloadBtn.classList.add('disabled');
    }
    if (bodyNode) {
        bodyNode.innerHTML = renderComputersLoaderTable();
    }

    if (tsAssetsModal) {
        tsAssetsModal.show();
    }

    fetch(ajaxUrl + '?' + params.toString(), {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function (payload) {
            tsAssetsModalComputers = payload.computers || [];

            if (titleNode) {
                titleNode.textContent = payload.title || (tsAssetsChartDataNode.dataset.softwareCoverageTitle || 'Software coverage');
            }
            if (countNode) {
                countNode.textContent = formatComputersCount(payload.count || 0);
            }
            if (downloadBtn) {
                downloadBtn.disabled = !tsAssetsModalComputers.length;
                downloadBtn.onclick = downloadCoverageComputersCsv;
            }

            if (alertNode) {
                if (payload.truncated) {
                    const label = tsAssetsChartDataNode.dataset.showingFirstComputersLabel || 'Showing the first %d computers only.';
                    alertNode.textContent = label.replace('%d', String(payload.limit || tsAssetsModalComputers.length));
                    alertNode.classList.remove('d-none');
                } else {
                    alertNode.classList.add('d-none');
                }
            }

            if (bodyNode) {
                bodyNode.innerHTML = tsAssetsModalComputers.length
                    ? renderComputersTable(tsAssetsModalComputers)
                    : '<div class="alert alert-secondary mb-0">'
                    + (tsAssetsChartDataNode.dataset.noComputersLabel || 'No computers found for this selection.')
                    + '</div>';
            }
        })
        .catch(function () {
            if (titleNode) {
                titleNode.textContent = tsAssetsChartDataNode.dataset.softwareCoverageTitle || 'Software coverage';
            }
            if (countNode) {
                countNode.textContent = '';
            }
            if (alertNode) {
                alertNode.classList.add('d-none');
            }
            if (bodyNode) {
                bodyNode.innerHTML = '<div class="alert alert-danger mb-0">'
                    + (tsAssetsChartDataNode.dataset.unableLoadComputersLabel || 'Unable to load computers.')
                    + '</div>';
            }
        });
}

function renderComputersLoaderTable() {
    const headers = getComputersTableHeaders();

    return `
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>${headers.map(function (header) { return '<th>' + escapeHtml(header) + '</th>'; }).join('')}</tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="placeholder col-6"></span></td>
                        <td><span class="placeholder col-10"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                    </tr>
                    <tr>
                        <td><span class="placeholder col-6"></span></td>
                        <td><span class="placeholder col-10"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                        <td><span class="placeholder col-8"></span></td>
                    </tr>
                </tbody>
            </table>
        </div>`;
}

function renderComputersTable(computers) {
    const headers = getComputersTableHeaders();

    const rows = computers.map(function (computer) {
        return '<tr>'
            + '<td>' + escapeHtml(computer.id) + '</td>'
            + '<td><a href="' + escapeHtml(computer.url) + '" class="fw-semibold" target="_blank">' + escapeHtml(computer.name) + '</a></td>'
            + '<td>' + escapeHtml(computer.serial) + '</td>'
            + '<td>' + escapeHtml(computer.inventory_number) + '</td>'
            + '<td>' + escapeHtml(computer.user) + '</td>'
            + '<td>' + renderSoftwareItems(computer.software_items) + '</td>'
            + '<td>' + escapeHtml(computer.manufacturer) + '</td>'
            + '<td>' + escapeHtml(computer.town) + '</td>'
            + '<td>' + escapeHtml(computer.state) + '</td>'
            + '<td>' + escapeHtml(computer.last_update) + '</td>'
            + '</tr>';
    }).join('');

    return '<div class="table-responsive">'
        + '<table class="table table-sm table-hover align-middle mb-0">'
        + '<thead><tr>'
        + headers.map(function (header) { return '<th>' + escapeHtml(header) + '</th>'; }).join('')
        + '</tr></thead>'
        + '<tbody>' + rows + '</tbody></table></div>';
}

function downloadCoverageComputersCsv() {
    if (!tsAssetsModalComputers.length) {
        return;
    }

    const headers = getComputersTableHeaders();
    const escapeValue = function (value) {
        return '"' + String(value ?? '').replace(/"/g, '""') + '"';
    };

    const rows = tsAssetsModalComputers.map(function (computer) {
        const softwareNames = (computer.software_items || []).map(function (item) {
            return item.name;
        }).join(' | ');

        return [
            computer.id,
            computer.name,
            computer.serial,
            computer.inventory_number,
            computer.user,
            softwareNames,
            computer.manufacturer,
            computer.town,
            computer.state,
            computer.last_update,
        ].map(escapeValue).join(';');
    });

    const csv = [headers.map(escapeValue).join(';')].concat(rows).join('\r\n');
    const latin1 = new Uint8Array(csv.split('').map(function (char) {
        const code = char.charCodeAt(0);
        return code > 255 ? '?'.charCodeAt(0) : code;
    }));

    const blob = new Blob([latin1], { type: 'text/csv;charset=iso-8859-1;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'software_coverage_computers.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function formatComputersCount(count) {
    return String(count) + ' ' + __('Computers', 'ticketsstatistics');
}

function getSelectedSoftwareIds(form) {
    const softwareIds = [];
    const select = form.querySelector('select[name="software[]"], select[name="software"]');
    if (!select) {
        return softwareIds;
    }

    const options = select && select.options;
    var opt;
    for (var i = 0, iLen = options.length; i < iLen; i++) {
        opt = options[i];

        if (opt.selected) {
            const id = parseInt(String(opt.value || opt.text), 10);
            if (!Number.isNaN(id) && id > 0 && !softwareIds.includes(id)) {
                softwareIds.push(id);
            }
        }
    }
    return softwareIds;
}

function getMatchAllSelection(form) {
    const checkbox = form.querySelector('[name="match_all"][type="checkbox"]');
    return !checkbox || checkbox.checked;
}

function renderSoftwareItems(softwareItems) {
    if (!softwareItems || softwareItems.length === 0) {
        return '-';
    }

    return softwareItems.map(function (item) {
        return '<a href="' + escapeHtml(item.url) + '" class="fw-semibold" target="_blank">'
            + escapeHtml(item.name)
            + '</a>';
    }).join('<br>');
}

function getComputersTableHeaders() {
    return [
        __('ID', 'ticketsstatistics'),
        __('Name', 'ticketsstatistics'),
        __('Serial', 'ticketsstatistics'),
        __('Inventory number', 'ticketsstatistics'),
        __('User', 'ticketsstatistics'),
        __('Software', 'ticketsstatistics'),
        __('Manufacturer', 'ticketsstatistics'),
        __('Town', 'ticketsstatistics'),
        __('Status', 'ticketsstatistics'),
        __('Last update', 'ticketsstatistics'),
    ];
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}