let ticketsStatisticsModal;
window.tsModalTickets = [];

// Show/hide custom period fields
document.addEventListener('DOMContentLoaded', function () {
    var periodSelect = document.getElementById('ts-period');
    var customFields = document.getElementById('ts-custom-period-fields');
    var applyBtnCol = document.getElementById('ts-apply-btn-col');
    ticketsStatisticsModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('ts-tickets-modal'));
    periodSelect.addEventListener('change', function () {
        if (this.value === 'custom') {
            customFields.style.display = 'block';
            applyBtnCol.style.display = '';
        } else {
            customFields.style.display = 'none';
            applyBtnCol.style.display = 'none';
            const filterForm = document.getElementById('ts-filter-form');
            if (filterForm) filterForm.submit();
        }
    });

    const downloadPdfButton = document.getElementById('ticketsstatisticsDownloadPdfBtn');
    downloadPdfButton.addEventListener('click', async function () {
        const btn = this;
        const btnContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader ti-spin"></i> ' + __('Generating...', 'ticketsstatistics');

        const { jsPDF } = window.jspdf;

        const content = document.getElementById('ts-content');

        // Crée un bandeau "Generated on..." visible uniquement dans le clone
        const banner = document.createElement('div');
        banner.id = 'ts-pdf-banner';
        banner.style.cssText = 'padding:8px 12px;background:#f8f9fa;border-bottom:1px solid #dee2e6;font-size:13px;color:#555;';
        banner.innerHTML = `<i>Generated on ${new Date().toLocaleString()}</i>`;

        const canvas = await html2canvas(content, {
            scale: 2,
            backgroundColor: '#ffffff',
            useCORS: true,
            logging: false,
            onclone: (clonedDoc) => {
                // Insère le bandeau en haut du contenu cloné
                const clonedContent = clonedDoc.getElementById('ts-content');
                clonedContent.insertBefore(banner, clonedContent.firstChild);

                // Cache le bouton dans le clone
                const clonedBtn = clonedDoc.getElementById('ticketsstatisticsDownloadPdfBtn');
                if (clonedBtn) clonedBtn.style.display = 'none';
            }
        });

        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageW = pdf.internal.pageSize.getWidth();
        const pageH = pdf.internal.pageSize.getHeight();
        const margin = 10;
        const usableW = pageW - margin * 2;

        // Découpe l'image en pages si le contenu est plus grand qu'une page A4
        const imgW = canvas.width;
        const imgH = canvas.height;
        const ratio = imgW / usableW;
        const sliceH = pageH - margin * 2; // hauteur d'une page en mm
        const sliceHpx = sliceH * ratio;     // même hauteur en pixels
        let offsetY = 0;

        while (offsetY < imgH) {
            if (offsetY > 0) pdf.addPage();

            // Crée un canvas temporaire pour la tranche
            const sliceCanvas = document.createElement('canvas');
            sliceCanvas.width = imgW;
            sliceCanvas.height = Math.min(sliceHpx, imgH - offsetY);

            const ctx = sliceCanvas.getContext('2d');
            ctx.drawImage(canvas, 0, -offsetY);

            pdf.addImage(
                sliceCanvas.toDataURL('image/png'),
                'PNG',
                margin,
                margin,
                usableW,
                sliceCanvas.height / ratio
            );

            offsetY += sliceHpx;
        }

        pdf.save('tickets_statistics.pdf');

        btn.disabled = false;
        btn.innerHTML = btnContent;
    });

    Array.from(document.getElementsByClassName('ts-reset-chart')).forEach(el => el.addEventListener('click', function () {
        const canvas = document.getElementById(this.dataset.canvas);
        const chart = Chart.getChart(canvas);
        chart.resetZoom();
    }));

    try {
        loadCharts();
    } catch (error) {
        console.error('Failed to load charts.'.error.message);
    }
});

function refreshPageWithCategory(category) {
    const url = new URL(window.location.href);
    url.searchParams.set('ttr_category', category);
    window.location.href = url.toString();
}

function loadCharts() {
    const root = CFG_GLPI.root_doc;
    const params = new URLSearchParams(document.location.search)
    const url = root + '/plugins/ticketsstatistics/ajax/data.php' + '?' + params.toString();

    const colorSuccess = '#49bf4d';
    const colorDanger = '#C00000';
    const colorWarning = '#ffa500';

    fetch(url)
        .then(r => r.json())
        .then(data => {
            // Big number counters
            document.querySelectorAll('.ts-count').forEach(el => {
                const status = el.dataset.status;
                el.textContent = data.counters[status] ?? 0;
            });

            Chart.register(ChartDataLabels);

            // Priority donut
            new Chart(document.getElementById('chart-priority'), {
                type: 'doughnut',
                data: {
                    labels: data.priority.labels,
                    datasets: [{
                        data: data.priority.values,
                        backgroundColor: [
                            '#e74c3c', '#e67e22', '#f1c40f', '#2ecc71', '#3498db', '#9b59b6'
                        ]
                    }]
                },
                options: {
                    onClick: function (_, elements) {
                        if (!elements.length) {
                            return;
                        }

                        openTicketsModal({
                            type: 'priority',
                            label: data.priority.labels[elements[0].index]
                        });
                    },
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        datalabels: {
                            color: '#fff',
                        }
                    },
                    maintainAspectRatio: false
                }
            });

            // Category bar
            new Chart(document.getElementById('chart-category'), {
                type: 'bar',
                data: {
                    labels: data.category.labels,
                    datasets: [{
                        label: __('New Tickets', 'ticketsstatistics'),
                        data: data.category.values.new,
                        backgroundColor: colorSuccess
                    },
                    {
                        label: __('Resolved/Closed tickers', 'ticketsstatistics'),
                        data: data.category.values.resolved,
                        backgroundColor: colorDanger
                    },
                    {
                        label: __('In progress', 'ticketsstatistics'),
                        data: data.category.values.in_progress,
                        backgroundColor: colorWarning
                    }]
                },
                options: {
                    onClick: function (_, elements) {
                        if (!elements.length) {
                            return;
                        }

                        openTicketsModal({
                            type: 'category',
                            label: data.category.labels[elements[0].index],
                            status_group: ['new', 'resolved', 'in_progress'][elements[0].datasetIndex]
                        });
                    },
                    plugins: {
                        legend: {
                            display: false
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
                                y: { min: 0, max: 2000 },
                            },
                        },
                        datalabels: {
                            font: {
                                // weight: 'bold',
                                size: 13,
                            },
                            anchor: 'end',
                            align: 'end',
                            color: '#333',
                            formatter: (value) => value > 0 ? value : '',
                        }
                    },
                    scales: {
                        y: {
                            min: 0,
                        },
                    },
                    maintainAspectRatio: false
                }
            });

            // City charts
            const townsData = data.cityData.labels.map((name, i) => ({
                name,
                count: (data.cityData.values.new[i] || 0) +
                    (data.cityData.values.resolved[i] || 0) +
                    (data.cityData.values.in_progress[i] || 0)
            }));
            fillTownsTable(townsData)
            new Chart(document.getElementById('chart-city'), {
                type: 'bar',
                data: {
                    labels: data.cityData.labels,
                    datasets: [
                        {
                            label: __('Resolved / Closed', 'ticketsstatistics'),
                            data: data.cityData.values.resolved,
                            backgroundColor: colorDanger,
                            hoverOffset: 16,
                        },
                        {
                            label: __('New'),
                            data: data.cityData.values.new,
                            backgroundColor: colorSuccess,
                            hoverOffset: 8,
                        },
                        {
                            label: __('In progress'),
                            data: data.cityData.values.in_progress,
                            backgroundColor: colorWarning,
                            hoverOffset: 4,
                        },
                    ]
                },
                options: {
                    onClick: function (_, elements) {
                        if (!elements.length) {
                            return;
                        }

                        openTicketsModal({
                            type: 'city',
                            label: data.cityData.labels[elements[0].index],
                            status_group: ['resolved', 'new', 'in_progress'][elements[0].datasetIndex]
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
                                y: { min: 0, max: Math.max(...data.cityData.values.resolved) * 2 },
                            },
                        },
                        datalabels: {
                            font: {
                                size: 13,
                            },
                            anchor: 'center',
                            align: 'center',
                            color: '#fff',
                            formatter: (value, ctx) => {
                                // Affiche la valeur brute
                                return value > 0 ? value : '';
                            },
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            min: 0,
                        }
                    },
                }
            });
            const citiesDataLabels = {
                anchor: 'end',
                align: 'end',
                formatter: (value) => value > 0 ? value : '',
            };
            new Chart(document.getElementById('chart-city-new'), {
                type: 'pie',
                data: {
                    labels: data.cityData.labels,
                    datasets: [
                        {
                            label: __('New'),
                            data: data.cityData.values.new,
                            backgroundColor: generateColorVariations(108, 78, 44, data.cityData.labels.length),
                            hoverOffset: 16,
                        },
                    ]
                },
                options: {
                    onClick: function (_, elements) {
                        if (!elements.length) {
                            return;
                        }

                        openTicketsModal({
                            type: 'city',
                            label: data.cityData.labels[elements[0].index],
                            status_group: 'new'
                        });
                    },
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        datalabels: citiesDataLabels
                    },
                }
            });
            new Chart(document.getElementById('chart-city-resolved'), {
                type: 'pie',
                data: {
                    labels: data.cityData.labels,
                    datasets: [
                        {
                            label: __('Resolved / Closed', 'ticketsstatistics'),
                            data: data.cityData.values.resolved,
                            // backgroundColor: 'hsl(0, 100%, 38%)',
                            backgroundColor: generateColorVariations(0, 100, 38, data.cityData.labels.length),
                            hoverOffset: 16,
                        },
                    ]
                },
                options: {
                    onClick: function (_, elements) {
                        if (!elements.length) {
                            return;
                        }

                        openTicketsModal({
                            type: 'city',
                            label: data.cityData.labels[elements[0].index],
                            status_group: 'resolved'
                        });
                    },
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        datalabels: citiesDataLabels
                    },
                }
            });
            new Chart(document.getElementById('chart-city-progress'), {
                type: 'pie',
                data: {
                    labels: data.cityData.labels,
                    datasets: [
                        {
                            label: __('In progress'),
                            data: data.cityData.values.in_progress,
                            backgroundColor: generateColorVariations(49, 88, 55, data.cityData.labels.length),
                            hoverOffset: 16,
                        },
                    ]
                },
                options: {
                    onClick: function (_, elements) {
                        if (!elements.length) {
                            return;
                        }

                        openTicketsModal({
                            type: 'city',
                            label: data.cityData.labels[elements[0].index],
                            status_group: 'in_progress'
                        });
                    },
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        datalabels: citiesDataLabels
                    },
                }
            });

            // Per-day line
            new Chart(document.getElementById('chart-perday'), {
                type: 'line',
                data: {
                    labels: data.perday.labels,
                    datasets: [{
                        label: __('Tickets opened', 'ticketsstatistics'),
                        data: data.perday.opened,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,.15)',
                        fill: true,
                        tension: data.perday.opened.length > 60 ? 0.1 : 0.3
                    },
                    {
                        label: __('Tickets closed', 'ticketsstatistics'),
                        data: data.perday.closed,
                        borderColor: '#fb6356',
                        backgroundColor: 'rgba(251, 99, 86, .15)',
                        fill: true,
                        tension: data.perday.closed.length > 60 ? 0.1 : 0.3
                    }]
                },
                options: {
                    onClick: function (_, elements, chart) {
                        if (!elements.length) {
                            return;
                        }

                        const { datasetIndex } = elements[0];
                        openTicketsModal({
                            type: datasetIndex == 0 ? 'perday-opened' : 'perday-closed',
                            label: data.perday.labels[elements[0].index]
                        });
                    },
                    plugins: {
                        legend: {
                            display: 'top'
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
                                y: { min: 0, max: 100 },
                            },
                        },
                        datalabels: { display: false }
                    },
                    scales: {
                        y: {
                            min: 0,
                        },
                    },
                    maintainAspectRatio: false
                }
            });

            // TTR lines
            new Chart('chart-resolution', {
                type: 'line',
                data: {
                    labels: data.resolution.labels,
                    datasets: [
                        {
                            label: __('Avg resolution time(hours)', 'ticketsstatistics'),
                            data: data.resolution.values,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13,110,253,0.08)',
                            tension: 0.3,
                            fill: true,
                            pointRadius: 3,
                        },
                        {
                            label: __('Global average: %avgh', 'ticketsstatistics').replace('%avg', data.resolution.average[0]),
                            data: data.resolution.average,
                            borderColor: '#dc3545',
                            borderDash: [8, 4],
                            borderWidth: 2.5,
                            pointRadius: 1,
                            fill: false,
                            tension: 0,
                        },
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: ctx =>
                                    ctx.datasetIndex === 0
                                        ? `${ctx.dataset.label}: ${ctx.parsed.y}h`
                                        : ctx.dataset.label
                            }
                        },
                        datalabels: { display: false },
                        zoom: {
                            pan: {
                                enabled: true,
                                mode: 'xy',
                            },
                            zoom: {
                                wheel: { enabled: true },
                                pinch: { enabled: true },
                                mode: 'xy',
                            },
                            limits: { y: { min: 0 } },
                        },
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: val => `${Math.round(val * 100) / 100}h`
                            }
                        }
                    }
                }
            });
        });
}

function fillTownsTable(data) {
    const root = CFG_GLPI.root_doc;

    const tbody = document.getElementById('ts-towns-table');
    const rows = data.map(town => {
        const params = (new URLSearchParams(document.location.search));
        params.append('city', town.name)
        const url = root + '/plugins/ticketsstatistics/front/ticketcityexport.php?' + params.toString();
        return `
        <tr>
            <td class="ps-3">${escapeHtml(town.name)}</td>
            <td class="text-center">${town.count}</td>
            <td class="text-center">
                <a class="text-decoration-none" href="${url}" title="${__('Download tickets in CSV', 'ticketsstatistics')}" target="_blank">
                    <i class="ti ti-file-spreadsheet me-1"></i>
                </a>
            </td>
        </tr>
    `;
    }).join('');
    tbody.innerHTML = rows;
}

function openTicketsModal(filters) {
    const root = CFG_GLPI.root_doc;
    const params = new URLSearchParams(document.location.search);
    const title = document.getElementById('ts-tickets-modal-title');
    const count = document.getElementById('ts-tickets-modal-count');
    const alertBox = document.getElementById('ts-tickets-modal-alert');
    const body = document.getElementById('ts-tickets-modal-body');

    params.set('type', filters.type);
    params.set('label', filters.label || '');

    if (filters.status_group) {
        params.set('status_group', filters.status_group);
    }

    title.textContent = __('Loading tickets...', 'ticketsstatistics');
    count.textContent = '';
    alertBox.textContent = '';
    alertBox.classList.add('d-none');
    body.innerHTML = renderLoaderCards();
    ticketsStatisticsModal.show();

    fetch(root + '/plugins/ticketsstatistics/ajax/tickets.php?' + params.toString())
        .then(response => response.json())
        .then(payload => {
            title.textContent = payload.title;
            count.textContent = formatTicketsCount(payload.count);

            if (payload.truncated) {
                alertBox.textContent = __('Showing the first 100 tickets only.', 'ticketsstatistics');
                alertBox.classList.remove('d-none');
            }

            window.tsModalTickets = payload.tickets;
            const downloadBtn = document.getElementById('ts-tickets-download-btn');
            downloadBtn.disabled = !payload.tickets.length;
            downloadBtn.onclick = function () {
                if (!window.tsModalTickets.length) return;
                const headers = ['ID', __('Title'), __('Status'), __('Last update'), __('Creation', 'ticketsstatistics'), __('Close date', 'ticketsstatistics'), __('Category'), __('Town', 'ticketsstatistics')];
                const escape = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';

                const rows = window.tsModalTickets.map(t =>
                    [t.id, t.name, t.status, t.last_update, t.creation, t.closed, t.category, t.town]
                        .map(escape)
                        .join(';')
                );

                const csv = [headers.map(escape).join(';'), ...rows].join('\r\n');

                // Encode en Latin-1
                const latin1 = new Uint8Array(
                    csv.split('').map(c => {
                        const code = c.charCodeAt(0);
                        return code > 255 ? '?'.charCodeAt(0) : code;
                    })
                );

                const blob = new Blob([latin1], { type: 'text/csv;charset=iso-8859-1;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');

                a.href = url;
                a.download = 'Tickets.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            };

            body.innerHTML = payload.tickets.length
                ? renderTicketsTable(payload.tickets)
                : `<div class="col-12"><div class="alert alert-secondary mb-0">${__('No tickets found for this selection.', 'ticketsstatistics')}</div></div>`;
        })
        .catch(() => {
            title.textContent = __('Tickets', 'ticketsstatistics');
            count.textContent = '';
            alertBox.classList.add('d-none');
            body.innerHTML = `<div class="col-12"><div class="alert alert-danger mb-0">${__('Unable to load tickets.', 'ticketsstatistics')}</div></div>`;
        });
}

function formatTicketsCount(count) {
    if (count === 1) {
        return __('1 ticket', 'ticketsstatistics');
    }

    return count + ' ' + __('tickets', 'ticketsstatistics');
}

function renderLoaderCards() {
    return `
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>${__('ID', 'ticketsstatistics')}</th>
                        <th>${__('Title', 'ticketsstatistics')}</th>
                        <th>${__('Status', 'ticketsstatistics')}</th>
                        <th>${__('Last update', 'ticketsstatistics')}</th>
                        <th>${__('Creation', 'ticketsstatistics')}</th>
                        <th>${__('Category', 'ticketsstatistics')}</th>
                        <th>${__('Town', 'ticketsstatistics')}</th>
                    </tr>
                </thead>
                <tbody>
                    ${new Array(3).fill(`
                        <tr>
                            <td><span class="placeholder col-8"></span></td>
                            <td><span class="placeholder col-10"></span></td>
                            <td><span class="placeholder col-8"></span></td>
                            <td><span class="placeholder col-9"></span></td>
                            <td><span class="placeholder col-9"></span></td>
                            <td><span class="placeholder col-8"></span></td>
                            <td><span class="placeholder col-8"></span></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
}

function renderTicketsTable(tickets) {
    const rows = tickets.map(ticket => `
        <tr>
            <td>${ticket.id}</td>
            <td>
                <a href="${ticket.url}" class="fw-semibold" target="_blank">
                    ${escapeHtml(ticket.name)}
                </a>
            </td>
            <td>${escapeHtml(ticket.status)}</td>
            <td>${escapeHtml(ticket.last_update)}</td>
            <td>${escapeHtml(ticket.creation)}</td>
            <td>${escapeHtml(ticket.closed)}</td>
            <td>${escapeHtml(ticket.category)}</td>
            <td>${escapeHtml(ticket.town)}</td>
        </tr>
    `).join('');

    return `
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>${__('ID', 'ticketsstatistics')}</th>
                        <th>${__('Title', 'ticketsstatistics')}</th>
                        <th>${__('Status', 'ticketsstatistics')}</th>
                        <th>${__('Last update', 'ticketsstatistics')}</th>
                        <th>${__('Creation', 'ticketsstatistics')}</th>
                        <th>${__('Close date', 'ticketsstatistics')}</th>
                        <th>${__('Category', 'ticketsstatistics')}</th>
                        <th>${__('Town', 'ticketsstatistics')}</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
    `;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

/**
 * Generate variations of a base color in HSL format
 * @param {number} h - Hue (0-360)
 * @param {number} s - Saturation (0-100)
 * @param {number} l - Lightness (0-100)
 * @param {number} count - Number of variations
 * @returns {string[]} Array of HSL color strings
 */
function generateColorVariations(h, s, l, count) {
    const variations = [];

    for (let i = 0; i < count; i++) {
        // Adjust lightness and saturation for variation
        const lightness = Math.min(100, Math.max(25, l + (i - Math.floor(count / 2)) * 10));
        const saturation = Math.min(100, Math.max(0, s + (i - Math.floor(count / 2)) * 5));
        variations.push(`hsl(${h}, ${saturation}%, ${lightness}%)`);
    }

    return variations;
}
