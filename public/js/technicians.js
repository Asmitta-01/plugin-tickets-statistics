
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('ts-tech-filter-form');
    const $period = document.getElementById('ts-tech-period');
    const $customFields = document.getElementById('ts-tech-custom-period-fields');
    const $applyBtn = document.getElementById('ts-tech-apply-btn-col');

    // Toggle custom date fields
    $period.addEventListener('change', function () {
        const isCustom = this.value === 'custom';
        $customFields.style.display = isCustom ? 'block' : 'none';
        $applyBtn.style.display = isCustom ? 'block' : 'none';

        if (!isCustom) {
            loadTechnicianData();
        }
    });

    // Category change triggers reload
    document.getElementById('ts-tech-category').addEventListener('change', loadTechnicianData);

    const viewOnlyTechniciansCheckbox = document.getElementById('ts-only-technicians-switch');
    if (viewOnlyTechniciansCheckbox) {
        viewOnlyTechniciansCheckbox.addEventListener('change', function () {
            loadTechnicianData();
        });
    }

    // Form submission
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        loadTechnicianData();
    });

    // Reset zoom buttons
    document.querySelectorAll('.ts-reset-chart').forEach(btn => {
        btn.addEventListener('click', function () {
            const canvasId = this.dataset.canvas;
            if (window.techniciansCharts && window.techniciansCharts[canvasId]) {
                window.techniciansCharts[canvasId].resetZoom();
            }
        });
    });

    // Load initial data
    loadTechnicianData();
});

function loadTechnicianData() {
    const params = new URLSearchParams();
    params.append('period', document.getElementById('ts-tech-period').value);
    params.append('category', document.getElementById('ts-tech-category').querySelector('select').value || 0);
    params.append('only_current', document.getElementById('ts-only-technicians-switch').checked ? 'true' : 'false');

    if (document.getElementById('ts-tech-period').value === 'custom') {
        const dateFrom = document.getElementById('ts-tech-date-from').value;
        const dateTo = document.getElementById('ts-tech-date-to').value;
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);
    }

    fetch(CFG_GLPI.root_doc + '/plugins/ticketsstatistics/ajax/technicians.php?' + params.toString())
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            updateTable(data.technicians);
            updateCharts(data.charts, data.technicians);
        })
        .catch(error => {
            console.error('Error:', error);
            GLPINotification.error(__('Failed to load technicians data.', 'ticketsstatistics'));
        });
}

function updateTable(technicians) {
    const tbody = document.getElementById('ts-tech-tbody');
    tbody.innerHTML = '';

    technicians.forEach(tech => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><a class="fw-bold" href="/front/user.form.php?id=${tech.user_id}" target="_blank">${escapeHtml(tech.name)}</a></td>
            <td class="text-end">${tech.total}</td>
            <td class="text-end">${tech.resolved}</td>
            <td class="text-end">${tech.in_progress}</td>
            <td class="text-end">${tech.waiting}</td>
            <td class="text-end">${tech.avg_resolution_time}</td>
            <td class="text-end">${tech.resolution_rate}</td>
            <td class="text-end">${tech.avg_assign_time}</td>
        `;
        tbody.appendChild(row);
    });

    // Enable sorting with DataTables if available
    if ($.fn.dataTable) {
        const table = $('#ts-tech-table');
        if ($.fn.DataTable.isDataTable(table)) {
            table.DataTable().destroy();
        }
        table.DataTable({
            language: {
                processing: __('Processing...', 'ticketsstatistics'),
                search: __('Search', 'ticketsstatistics'),
                lengthMenu: __('Show _MENU_ entries', 'ticketsstatistics'),
            },
            order: [
                [1, 'desc']
            ],
        });
    }
}

function updateCharts(charts, technicians) {
    if (!window.techniciansCharts) {
        window.techniciansCharts = {};
    }

    // Status by technician - stacked bar
    updateChart('chart-tech-status', {
        type: 'bar',
        data: {
            labels: charts.status_by_tech.labels,
            datasets: charts.status_by_tech.datasets,
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                },
                zoom: {
                    pan: {
                        enabled: true,
                        mode: 'x',
                    },
                    zoom: {
                        wheel: {
                            enabled: true
                        },
                        pinch: {
                            enabled: true
                        },
                        mode: 'x'
                    },
                    limits: {
                        x: { min: 0, max: 2000 },
                    },
                },
                datalabels: {
                    display: false
                },
            },
            scales: {
                x: {
                    stacked: true,
                    beginAtZero: true,
                },
                y: {
                    stacked: true
                },
            },
        },
    });

    // Average resolution time - bar
    updateChart('chart-tech-resolution-time', {
        type: 'bar',
        data: {
            labels: charts.avg_resolution_time.labels,
            datasets: [{
                label: __('Hours', 'ticketsstatistics'),
                data: charts.avg_resolution_time.data,
                backgroundColor: '#3498db',
                borderColor: '#2980b9',
                borderWidth: 1,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                },
                zoom: {
                    pan: {
                        enabled: true,
                        mode: 'x',
                    },
                    zoom: {
                        wheel: {
                            enabled: true
                        },
                        pinch: {
                            enabled: true
                        },
                        mode: 'x'
                    }
                },
                datalabels: {
                    anchor: 'end',
                    align: 'end'
                },
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            },
        },
    });

    // Resolution rate - bar
    updateChart('chart-tech-resolution-rate', {
        type: 'bar',
        data: {
            labels: charts.resolution_rate.labels,
            datasets: [{
                label: __('Rate(%)', 'ticketsstatistics'),
                data: charts.resolution_rate.data,
                backgroundColor: '#49bf4d',
                borderColor: '#39a935',
                borderWidth: 1,
            }],
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                },
                zoom: {
                    pan: {
                        enabled: true,
                        mode: 'x',
                    },
                    zoom: {
                        wheel: {
                            enabled: true
                        },
                        pinch: {
                            enabled: true
                        },
                        mode: 'x'
                    }
                },
                datalabels: {
                    anchor: 'end',
                    align: 'end'
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                }
            },
        },
    });
}

function updateChart(canvasId, config) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    if (window.techniciansCharts[canvasId]) {
        window.techniciansCharts[canvasId].destroy();
    }

    const ctx = canvas.getContext('2d');
    window.techniciansCharts[canvasId] = new Chart(ctx, config);
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
