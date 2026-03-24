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
    downloadPdfButton.addEventListener('click', async function () {
        const btn = this;
        const btnContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ti ti-loader ti-spin"></i> ' + __('Generating...', 'ticketsstatistics');

        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const pageW = pdf.internal.pageSize.getWidth();
        const pageH = pdf.internal.pageSize.getHeight();
        const margin = 10;
        const usableW = pageW - margin * 2;

        // Titre + date
        pdf.setFontSize(16);
        pdf.setFont('helvetica', 'bold');
        pdf.text(__('Tickets Statistics', 'ticketsstatistics'), margin, margin + 6);
        pdf.setFontSize(9);
        pdf.setFont('helvetica', 'normal');
        pdf.setTextColor(120);
        pdf.text(__('Generated on ', 'ticketsstatistics') + new Date().toLocaleString(), margin, margin + 12);
        pdf.setTextColor(0);

        let cursorY = margin + 18;

        const singleCanvases = [
            { id: 'chart-priority', label: 'Tickets by priority' },
            { id: 'chart-category', label: 'Tickets by category (top 10)' },
            { id: 'chart-perday', label: 'Tickets opened per day' },
        ];

        const cityCanvases = [
            { id: 'chart-city', label: 'All' },
            { id: 'chart-city-new', label: 'New' },
            { id: 'chart-city-resolved', label: 'Resolved' },
            { id: 'chart-city-progress', label: 'In progress' },
        ];

        // Charts individuels
        for (const item of singleCanvases) {
            const canvas = document.getElementById(item.id);
            if (!canvas) continue;

            const imgData = canvas.toDataURL('image/png', 1.0);
            const ratio = canvas.height / canvas.width;
            const imgH = usableW * ratio;

            if (cursorY + imgH + 8 > pageH - margin) {
                pdf.addPage();
                cursorY = margin;
            }

            pdf.setFontSize(10);
            pdf.setFont('helvetica', 'bold');
            pdf.text(item.label, margin, cursorY);
            cursorY += 5;

            pdf.addImage(imgData, 'PNG', margin, cursorY, usableW, imgH);
            cursorY += imgH + 8;
        }

        // Charts city — 4 sur une même ligne
        const cityImgs = [];
        const cityColW = usableW / 4;
        let maxCityH = 0;

        for (const item of cityCanvases) {
            const canvas = document.getElementById(item.id);
            if (!canvas) continue;
            const ratio = canvas.height / canvas.width;
            const imgH = cityColW * ratio;
            cityImgs.push({ imgData: canvas.toDataURL('image/png', 1.0), label: item.label, imgH });
            if (imgH > maxCityH) maxCityH = imgH;
        }

        if (cityImgs.length > 0) {
            if (cursorY + maxCityH + 10 > pageH - margin) {
                pdf.addPage();
                cursorY = margin;
            }

            // Titre de la section
            pdf.setFontSize(10);
            pdf.setFont('helvetica', 'bold');
            pdf.text(__('Tickets by town', 'ticketsstatistics'), margin, cursorY);
            cursorY += 5;

            // Labels
            pdf.setFontSize(8);
            pdf.setFont('helvetica', 'normal');
            cityImgs.forEach((c, i) => {
                pdf.text(c.label, margin + i * cityColW + cityColW / 2, cursorY, { align: 'center' });
            });
            cursorY += 4;

            // Images côte à côte
            cityImgs.forEach((c, i) => {
                pdf.addImage(c.imgData, 'PNG', margin + i * cityColW, cursorY, cityColW, c.imgH);
            });

            cursorY += maxCityH + 8;
        }

        // Capture les compteurs (big numbers) avec html2canvas
        const counters = document.getElementById('ts-counters');
        if (counters) {
            if (cursorY + 30 > pageH - margin) {
                pdf.addPage();
                cursorY = margin;
            }

            const counterCanvas = await html2canvas(counters, { scale: 2, backgroundColor: '#ffffff' });
            const counterImg = counterCanvas.toDataURL('image/png');
            const counterRatio = counterCanvas.height / counterCanvas.width;
            const counterH = usableW * counterRatio;

            pdf.setFontSize(10);
            pdf.setFont('helvetica', 'bold');
            pdf.text('Summary', margin, cursorY);
            cursorY += 5;

            pdf.addImage(counterImg, 'PNG', margin, cursorY, usableW, counterH);
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
                        backgroundColor: '#3bc519'
                    },
                    {
                        label: __('Resolved/Closed tickers', 'ticketsstatistics'),
                        data: data.category.values.resolved,
                        backgroundColor: '#C00000'
                    },
                    {
                        label: __('In progress', 'ticketsstatistics'),
                        data: data.category.values.in_progress,
                        backgroundColor: '#f1cd29'
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
            new Chart(document.getElementById('chart-city'), {
                type: 'bar',
                data: {
                    labels: data.cityData.labels,
                    datasets: [
                        {
                            label: __('Resolved / Closed'),
                            data: data.cityData.values.resolved,
                            backgroundColor: '#C00000',
                            hoverOffset: 16,
                        },
                        {
                            label: __('New'),
                            data: data.cityData.values.new,
                            backgroundColor: '#3bc519',
                            hoverOffset: 8,
                        },
                        {
                            label: __('In progress'),
                            data: data.cityData.values.in_progress,
                            backgroundColor: '#f1cd29',
                            hoverOffset: 4,
                        },
                    ]
                },
                options: {
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
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        datalabels: {
                            font: {
                                weight: 'bold',
                                size: 13,
                            },
                            anchor: 'center',
                            align: 'center',
                            color: '#fff',
                            formatter: (value) => value > 0 ? value : '',
                        }
                    },
                }
            });
            new Chart(document.getElementById('chart-city-resolved'), {
                type: 'pie',
                data: {
                    labels: data.cityData.labels,
                    datasets: [
                        {
                            label: __('Resolved / Closed'),
                            data: data.cityData.values.resolved,
                            // backgroundColor: 'hsl(0, 100%, 38%)',
                            backgroundColor: generateColorVariations(0, 100, 38, data.cityData.labels.length),
                            hoverOffset: 16,
                        },
                    ]
                },
                options: {
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        datalabels: {
                            font: {
                                weight: 'bold',
                                size: 13,
                            },
                            anchor: 'center',
                            align: 'center',
                            color: '#fff',
                            formatter: (value) => value > 0 ? value : '',
                        }
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
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        datalabels: {
                            font: {
                                weight: 'bold',
                                size: 13,
                            },
                            anchor: 'center',
                            align: 'center',
                            color: '#fff',
                            formatter: (value) => value > 0 ? value : '',
                        }
                    },
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
        });
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
        const lightness = Math.min(100, Math.max(0, l + (i - Math.floor(count / 2)) * 10));
        const saturation = Math.min(100, Math.max(0, s + (i - Math.floor(count / 2)) * 5));
        variations.push(`hsl(${h}, ${saturation}%, ${lightness}%)`);
    }

    return variations;
}
