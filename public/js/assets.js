let tsSoftwareCoverageChart = null;

document.addEventListener('DOMContentLoaded', function () {
    const chartDataNode = document.getElementById('ts-assets-chart-data');
    if (!chartDataNode || typeof Chart === 'undefined') {
        return;
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
});

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

    const softwareInput = form.querySelector('[name="software"]');
    if (softwareInput) {
        softwareInput.addEventListener('change', function () {
            fetchSoftwareCoverage(form, chartDataNode);
        });
    }
}

function fetchSoftwareCoverage(form, chartDataNode) {
    const softwareInput = form.querySelector('[name="software"]');
    const softwareId = parseInt((softwareInput && softwareInput.value) || '0', 10) || 0;
    const ajaxUrl = form.dataset.ajaxUrl;

    if (!ajaxUrl) {
        return;
    }

    if (softwareId <= 0) {
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