// Show/hide custom period fields
document.addEventListener('DOMContentLoaded', function () {
    var periodSelect = document.getElementById('ts-period');
    var customFields = document.getElementById('ts-custom-period-fields');
    var applyBtnCol = document.getElementById('ts-apply-btn-col');
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
    downloadPdfButton.addEventListener('click', function () {
        console.log('Downloading Tickets charts...');
        // chart.options.animation = false; chart.update();
        const categoryCanvas = document.getElementById('chart-category');
        const priorityCanvas = document.getElementById('chart-priority');
        const perDayCanvas = document.getElementById('chart-perday');

        const categoryImgData = categoryCanvas.toDataURL('image/png');
        const priorityImgData = priorityCanvas.toDataURL('image/png');
        const perDayImgData = perDayCanvas.toDataURL('image/png');

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF();
        pdf.text('Tickets Statistics', 10, 10);
        pdf.addImage(categoryImgData, 'PNG', 10, 20, 110, 50);
        pdf.addImage(priorityImgData, 'PNG', 10, 80, 120, 60);
        pdf.addImage(perDayImgData, 'PNG', 10, 150, 185, 50);
        pdf.save('tickets_stats.pdf');
        console.log('Download complete');
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

function loadCharts() {
    const root = CFG_GLPI.root_doc;
    const params = new URLSearchParams(document.location.search)
    const url = root + '/plugins/ticketsstatistics/ajax/data.php' + '?' + params.toString();

    fetch(url)
        .then(r => r.json())
        .then(data => {
            // Big number counters
            document.querySelectorAll('.ts-count').forEach(el => {
                const status = el.dataset.status;
                el.textContent = data.counters[status] ?? 0;
            });

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
                    plugins: {
                        legend: {
                            position: 'right'
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
                        label: __('Tickets'),
                        data: data.category.values,
                        backgroundColor: '#3b82f6'
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    maintainAspectRatio: false
                }
            });

            // Per-day line
            new Chart(document.getElementById('chart-perday'), {
                type: 'line',
                data: {
                    labels: data.perday.labels,
                    datasets: [{
                        label: __('Tickets opened'),
                        data: data.perday.values,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,.15)',
                        fill: true,
                        tension: data.perday.values.length > 60 ? 0.1 : 0.3
                    }]
                },
                options: {
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
                                y: { min: 0, max: Math.max(...data.perday.values) * 1.15 },
                            },
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
        });
}