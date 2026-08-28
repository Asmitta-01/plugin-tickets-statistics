document.addEventListener('DOMContentLoaded', function () {
    const dataNode = document.getElementById('ts-printers-chart-data');
    if (!dataNode) return;

    const townId = dataNode.getAttribute('data-town-id') || '0';
    const manufacturerId = dataNode.getAttribute('data-manufacturer-id') || '0';
    const ajaxUrl = dataNode.getAttribute('data-ajax-url');

    const modelsData = JSON.parse(dataNode.getAttribute('data-model-chart') || '[]');
    const townsData = JSON.parse(dataNode.getAttribute('data-town-chart') || '[]');
    const topPagesData = JSON.parse(dataNode.getAttribute('data-top-pages-chart') || '[]');
    const evolutionData = JSON.parse(dataNode.getAttribute('data-evolution-chart') || '[]');
    const inkData = JSON.parse(dataNode.getAttribute('data-ink-chart') || '{}');

    // Period filter logic
    const periodSelect = document.getElementById('ts-period');
    const customFields = document.getElementById('ts-custom-period-fields');
    if (periodSelect && customFields) {
        periodSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customFields.style.display = 'block';
            } else {
                customFields.style.display = 'none';
                this.closest('form').submit();
            }
        });
    }

    // Modal elements
    const printersModal = new bootstrap.Modal(document.getElementById('ts-printers-modal'));
    const modalTitle = document.getElementById('ts-printers-modal-title');
    const modalCount = document.getElementById('ts-printers-modal-count');
    const modalBody = document.getElementById('ts-printers-modal-body');

    function openPrintersModal(title, counterKey, label = '', displayLabel = null) {
        modalTitle.textContent = title + (label ? ' : ' + (displayLabel || label) : '');
        modalCount.textContent = '...';
        modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>';
        printersModal.show();

        const params = new URLSearchParams({
            town_id: townId,
            manufacturer_id: manufacturerId,
            counter_key: counterKey,
            label: label
        });

        fetch(ajaxUrl + '?' + params.toString(), {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(response => response.json())
            .then(data => {
                modalCount.textContent = data.count || '0';
                modalBody.innerHTML = data.html || '';
            })
            .catch(err => {
                console.error(err);
                modalBody.innerHTML = '<div class="alert alert-danger">Error loading data.</div>';
            });
    }

    // Chart.js default defaults
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
    Chart.defaults.color = '#6c757d';

    // Plugins
    Chart.register(ChartDataLabels);

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            datalabels: {
                color: '#fff',
                font: { weight: 'bold' },
                formatter: (value) => value > 0 ? value : ''
            }
        },
        onClick: (e, activeEls, chart) => {
            if (!activeEls || activeEls.length === 0) return;
            const idx = activeEls[0].index;
            const label = chart.data.labels[idx];

            let key = '';
            if (chart.canvas.id === 'ts-printers-model-chart') key = 'model';
            else if (chart.canvas.id === 'ts-printers-town-chart') key = 'town';
            else if (chart.canvas.id === 'ts-printers-top-pages-chart') key = 'top_pages';
            // Ink and Evolution clicks might not map perfectly to a single filtered list without complex queries.

            if (key) {
                openPrintersModal(chart.options.plugins.title?.text || label, key, label);
            }
        }
    };

    // Printers by Model Chart
    const ctxModel = document.getElementById('ts-printers-model-chart');
    if (ctxModel && modelsData.length > 0) {
        new Chart(ctxModel, {
            type: 'bar',
            data: {
                labels: modelsData.map(d => d.model),
                datasets: [{
                    data: modelsData.map(d => d.count),
                    backgroundColor: '#4299e1',
                    hoverBackgroundColor: '#2b6cb0'
                }]
            },
            options: {
                ...commonOptions,
                indexAxis: 'y',
                plugins: {
                    ...commonOptions.plugins,
                    title: { text: 'Printers by Model', display: false },
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold' },
                        anchor: 'center',
                        align: 'center',
                        formatter: (value) => value > 0 ? value : ''
                    }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } },
                    y: {
                        ticks: {
                            callback: function (val, index) {
                                let label = this.getLabelForValue(val);
                                return label.length > 25 ? label.substr(0, 25) + '...' : label;
                            }
                        }
                    }
                }
            }
        });
    }

    // Printers by Town Chart
    const ctxTown = document.getElementById('ts-printers-town-chart');
    if (ctxTown && townsData.length > 0) {
        new Chart(ctxTown, {
            type: 'bar',
            data: {
                labels: townsData.map(d => d.town),
                datasets: [{
                    data: townsData.map(d => d.count),
                    backgroundColor: '#49bf4d',
                    hoverBackgroundColor: '#3e8e41'
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    title: { text: 'Printers by Town', display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true, ticks: {
                            precision: 0,
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            callback: function (val) {
                                const xLabel = this.getLabelForValue(val);
                                return xLabel.length > 10 ? xLabel.split(' ') : xLabel; // découpe en plusieurs lignes
                            }
                        }
                    }
                }
            }
        });
    }

    // Top Printers by Pages
    const ctxTopPages = document.getElementById('ts-printers-top-pages-chart');
    if (ctxTopPages && topPagesData.length > 0) {
        new Chart(ctxTopPages, {
            type: 'bar',
            data: {
                labels: topPagesData.map(d => d.name),
                datasets: [{
                    data: topPagesData.map(d => d.pages),
                    backgroundColor: '#f59e0b',
                    borderRadius: 4
                }]
            },
            options: {
                ...commonOptions,
                indexAxis: 'y',
                plugins: {
                    ...commonOptions.plugins,
                    title: { text: 'Printer Details', display: false },
                    datalabels: {
                        color: '#fff',
                        font: { weight: 'bold' },
                        anchor: 'end',
                        align: 'left',
                        formatter: (value) => value > 0 ? new Intl.NumberFormat().format(value) : ''
                    }
                },
                scales: {
                    x: { beginAtZero: true },
                    y: {
                        ticks: {
                            callback: function (val, index) {
                                let label = this.getLabelForValue(val);
                                return label.length > 25 ? label.substr(0, 25) + '...' : label;
                            }
                        }
                    }
                }
            }
        });
    }

    // Pages Evolution Chart (Line)
    const ctxEvolution = document.getElementById('ts-printers-evolution-chart');
    if (ctxEvolution && evolutionData.length > 0) {
        new Chart(ctxEvolution, {
            type: 'line',
            data: {
                labels: evolutionData.map(d => d.month),
                datasets: [{
                    label: 'Pages',
                    data: evolutionData.map(d => d.total),
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: '#8b5cf6'
                }]
            },
            options: {
                ...commonOptions,
                plugins: {
                    ...commonOptions.plugins,
                    datalabels: { display: false } // Hide datalabels for line chart
                },
                scales: {
                    y: { beginAtZero: true },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });
    }

    // Ink Levels Chart (Doughnut)
    const ctxInk = document.getElementById('ts-printers-ink-chart');
    if (ctxInk) {
        const labels = [
            dataNode.getAttribute('data-lang-critical'),
            dataNode.getAttribute('data-lang-low'),
            dataNode.getAttribute('data-lang-good'),
            dataNode.getAttribute('data-lang-full')
        ];

        const data = [
            inkData.critical || 0,
            inkData.low || 0,
            inkData.good || 0,
            inkData.full || 0
        ];

        const totalInk = data.reduce((a, b) => a + b, 0);

        if (totalInk > 0) {
            new Chart(ctxInk, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: ['#e53e3e', '#dd6b20', '#38b2ac', '#48bb78'],
                        borderWidth: 1
                    }]
                },
                options: {
                    ...commonOptions,
                    onClick: function (event, elements) {
                        if (elements && elements.length > 0) {
                            const index = elements[0].index;
                            const keys = ['critical', 'low', 'good', 'full'];
                            const clickedKey = keys[index];
                            const clickedLabel = labels[index];
                            const title = document.querySelector('[data-counter-key="ink"] .card-header').textContent.split(' (')[0];
                            openPrintersModal(title, 'ink', clickedKey, clickedLabel);
                        }
                    },
                    plugins: {
                        legend: { display: true, position: 'bottom' },
                        datalabels: {
                            color: '#fff',
                            font: { weight: 'bold' },
                            formatter: (value, context) => {
                                if (value === 0) return '';
                                return value;
                            }
                        }
                    }
                }
            });
        }
    }
});
