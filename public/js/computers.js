document.addEventListener('DOMContentLoaded', function () {
    const dataEl = document.getElementById('ts-computers-chart-data');
    if (!dataEl) {
        return;
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

    const versionChartData = parseJson(dataEl.dataset.versionChart) || { labels: [], values: [] };
    const townVersionChartData = parseJson(dataEl.dataset.townVersionChart) || { labels: [], versions: [], values: {} };
    const kbChartData = parseJson(dataEl.dataset.kbChart) || { labels: [], values: [] };

    const versionColorMap = {};
    const colorForVersion = function (version, indexFallback) {
        if (versionColorMap[version]) {
            return versionColorMap[version];
        }

        const index = typeof indexFallback === 'number' ? indexFallback : Object.keys(versionColorMap).length;
        const color = 'hsl(' + ((index * 57) % 360) + ', 68%, 52%)';
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
                scales: {
                    x: {
                        stacked: true,
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
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
                datasets: [{
                    label: __('Installations', 'ticketsstatistics'),
                    data: kbChartData.values,
                    backgroundColor: '#7c3aed',
                    borderColor: '#6d28d9',
                    borderWidth: 1,
                }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    datalabels: {
                        anchor: 'end',
                        align: 'end',
                        color: '#374151',
                        formatter: function (value) {
                            return value > 0 ? value : '';
                        },
                    },
                },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 25,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                        },
                    },
                },
            },
        });
    }
});
