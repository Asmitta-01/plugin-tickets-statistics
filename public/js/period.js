let ticketsStatisticsModal;
window.tsModalTickets = [];

// Show/hide custom period fields
document.addEventListener('DOMContentLoaded', function () {
    const periodSelect = document.getElementById('ts-period');
    const categorySelect = document.querySelector('#ts-category>select');
    ticketsStatisticsModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('ts-tickets-modal'));
    periodSelect.addEventListener('change', submitFilterForm);
    categorySelect.addEventListener('change', submitFilterForm);

    const viewSolvedCheckbox = document.getElementById('ts-view-solved');
    if (viewSolvedCheckbox) {
        viewSolvedCheckbox.addEventListener('change', function () {
            toggleCountersView(this.checked);
        });
    }

    const openStatusesGlobal = document.getElementById('ts-open-statuses-global');
    if (openStatusesGlobal) {
        openStatusesGlobal.addEventListener('change', function () {
            const filterForm = document.getElementById('ts-filter-form');
            if (filterForm) {
                filterForm.submit();
            }
        });
    }

    const downloadPdfButton = document.getElementById('ticketsstatisticsDownloadPdfBtn');
    downloadPdfButton.addEventListener('click', exportPageToPDF);

    const downloadLowPdfButton = document.getElementById('ticketsstatisticsDownloadLowPdfBtn');
    downloadLowPdfButton.addEventListener('click', (ev) => exportPageToPDF(ev, true));

    const downloadMarkdownButton = document.getElementById('ticketsstatisticsDownloadMarkdownBtn');
    if (downloadMarkdownButton) {
        downloadMarkdownButton.addEventListener('click', exportDashboardToMarkdown);
    }

    Array.from(document.getElementsByClassName('ts-reset-chart')).forEach(el => el.addEventListener('click', function () {
        const canvas = document.getElementById(this.dataset.canvas);
        const chart = Chart.getChart(canvas);
        chart.resetZoom();
    }));

    initCounterCardsModalLinks();

    try {
        loadCharts();
    } catch (error) {
        console.error('Failed to load charts.'.error.message);
    }
});

/**
 * 
 * @param {Event} event 
 * @param {boolean} lowQuality Default `false`
 */
async function exportPageToPDF(event, lowQuality = false) {
    const btn = event.currentTarget;
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
        scale: lowQuality ? 1 : 1.3,
        backgroundColor: '#ffffff',
        useCORS: true,
        logging: false,
        onclone: (clonedDoc) => {
            // Insère le bandeau en haut du contenu cloné
            const clonedContent = clonedDoc.getElementById('ts-content');
            clonedContent.insertBefore(banner, clonedContent.firstChild);

            // Cache le bouton dans le clone
            const clonedBtn = clonedDoc.getElementById('ts-download-btn-group');
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

        const imageFormat = lowQuality ? 'jpeg' : 'png';
        pdf.addImage(
            sliceCanvas.toDataURL('image/' + imageFormat),
            imageFormat.toUpperCase(),
            margin,
            margin,
            usableW,
            sliceCanvas.height / ratio
        );

        offsetY += sliceHpx;
    }

    const selectedPeriod = document.querySelector('#ts-period option:checked');
    let selectedPeriodText = selectedPeriod?.textContent || '';
    if (selectedPeriod.value === 'custom') {
        const from = document.getElementById('ts-date-from').value;
        const to = document.getElementById('ts-date-to').value;
        if (from && to) {
            const fromDate = new Date(from);
            const toDate = new Date(to);
            if (!isNaN(fromDate) && !isNaN(toDate)) {
                const options = { year: 'numeric', month: 'short', day: 'numeric' };
                const fromStr = fromDate.toLocaleDateString(undefined, options);
                const toStr = toDate.toLocaleDateString(undefined, options);
                selectedPeriodText = `${fromStr} - ${toStr}`;
            }
        }
    }
    const title = __('Tickets Statistics', 'ticketsstatistics') + (selectedPeriodText ? ` - ${selectedPeriodText}` : '');
    const author = 'Brayan Tiwa';
    pdf.setProperties({ title, author });
    pdf.save(title + '.pdf');

    btn.disabled = false;
    btn.innerHTML = btnContent;
}

/**
 * Exports current dashboard statistics to a structured Markdown document.
 */
function exportDashboardToMarkdown(event) {
    if (event) {
        event.preventDefault();
    }

    if (!window.lastDashboardData) {
        const root = CFG_GLPI.root_doc;
        const params = new URLSearchParams(document.location.search);
        const url = root + '/plugins/ticketsstatistics/ajax/data.php' + '?' + params.toString();

        fetch(url)
            .then(r => r.json())
            .then(data => {
                window.lastDashboardData = data;
                triggerMarkdownDownload(data);
            })
            .catch(err => {
                console.error('Failed to load data for Markdown export', err);
            });
        return;
    }

    triggerMarkdownDownload(window.lastDashboardData);
}

function triggerMarkdownDownload(data) {
    const md = generateDashboardMarkdown(data);
    const blob = new Blob([md], { type: 'text/markdown;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;

    const selectedPeriod = document.querySelector('#ts-period option:checked');
    let periodText = selectedPeriod ? selectedPeriod.textContent.trim().replace(/[^a-zA-Z0-9_-]/g, '_') : 'period';
    const dateStr = new Date().toISOString().slice(0, 10);
    a.download = `Tickets_Statistics_${periodText}_${dateStr}.md`;

    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

/**
 * Generates clear, readable and structured Markdown representation of dashboard stats for AI/human analysis.
 */
function generateDashboardMarkdown(data) {
    const selectedPeriod = document.querySelector('#ts-period option:checked');
    let periodLabel = selectedPeriod ? selectedPeriod.textContent.trim() : 'N/A';
    const periodValue = document.getElementById('ts-period')?.value || '';
    if (periodValue === 'custom') {
        const from = document.getElementById('ts-date-from')?.value;
        const to = document.getElementById('ts-date-to')?.value;
        if (from && to) {
            periodLabel = `${from} to ${to}`;
        }
    }

    const selectedCategory = document.querySelector('#ts-category select option:checked');
    const categoryLabel = selectedCategory ? selectedCategory.textContent.trim() : __('All categories', 'ticketsstatistics');

    const openStatusesGlobal = document.getElementById('ts-open-statuses-global')?.checked;

    const calcPct = (val, tot) => tot > 0 ? ((val / tot) * 100).toFixed(1) + '%' : '0.0%';

    const lines = [];
    lines.push('# GLPI - Tickets Statistics Report');
    lines.push('');
    lines.push(`- **Generated on:** ${new Date().toLocaleString()}`);
    lines.push(`- **Selected Period:** ${periodLabel}`);
    lines.push(`- **Selected ITIL Category:** ${categoryLabel}`);
    lines.push(`- **Global Open Statuses Mode:** ${openStatusesGlobal ? 'Enabled' : 'Disabled'}`);
    lines.push('');
    lines.push('---');
    lines.push('');

    // 1. Executive Summary
    lines.push('## 1. Executive Summary');
    lines.push('');
    lines.push('### Tickets Created in Period (Creation-Date View)');
    lines.push('');
    lines.push('| Status | Tickets Count | Share (%) |');
    lines.push('|---|---:|---:|');

    const totalTickets = data.counters?.total || 0;
    const statusItems = [
        { label: 'New (Incoming)', val: data.counters?.incoming || 0 },
        { label: 'Assigned', val: data.counters?.assigned || 0 },
        { label: 'Pending (Waiting)', val: data.counters?.waiting || 0 },
        { label: 'Resolved / Closed', val: (data.counters?.solved || 0) + (data.counters?.closed || 0) },
    ];
    if (data.counters?.missc !== undefined) {
        statusItems.push({ label: 'MISSC', val: data.counters.missc });
    }

    statusItems.forEach(item => {
        lines.push(`| ${item.label} | ${item.val} | ${calcPct(item.val, totalTickets)} |`);
    });
    lines.push(`| **Total Created** | **${totalTickets}** | **100.0%** |`);
    lines.push('');

    if (data.solvedView) {
        lines.push('### Resolution Activity in Period (Resolved-Date View)');
        lines.push('');
        lines.push(`- **Resolved / Closed in Period:** ${data.solvedView.resolved_in_period || 0}`);
        lines.push(`- **Opened in Period:** ${data.solvedView.opened_in_period || 0}`);
        lines.push(`- **Average Resolution Time (TTR):** ${data.solvedView.avg_ttr || 0} h`);
        lines.push('');
    }

    lines.push('---');
    lines.push('');

    // 2. Tickets by Priority
    if (data.priority && data.priority.labels && data.priority.labels.length) {
        lines.push('## 2. Tickets by Priority');
        lines.push('');
        lines.push('| Priority Level | Count | Share (%) |');
        lines.push('|---|---:|---:|');
        const prioTotal = data.priority.values.reduce((a, b) => a + b, 0);
        data.priority.labels.forEach((label, i) => {
            const val = data.priority.values[i] || 0;
            lines.push(`| ${label} | ${val} | ${calcPct(val, prioTotal)} |`);
        });
        lines.push(`| **Total** | **${prioTotal}** | **100.0%** |`);
        lines.push('');
        lines.push('---');
        lines.push('');
    }

    // 3. MISSC Support Matching (if present)
    if (data.misscs && data.misscs.labels && data.misscs.labels.length) {
        lines.push('## 3. MISSC Support Matching');
        lines.push('');
        lines.push('| MISSC Status | Count | Share (%) |');
        lines.push('|---|---:|---:|');
        const misscTotal = data.misscs.values.reduce((a, b) => a + b, 0);
        data.misscs.labels.forEach((label, i) => {
            const val = data.misscs.values[i] || 0;
            let displayLabel = label;
            if (label === 'new') displayLabel = 'New';
            else if (label === 'in_progress') displayLabel = 'In progress';
            else if (label === 'resolved') displayLabel = 'Resolved / Closed';
            lines.push(`| ${displayLabel} | ${val} | ${calcPct(val, misscTotal)} |`);
        });
        lines.push(`| **Total MISSC** | **${misscTotal}** | **100.0%** |`);
        lines.push('');
        lines.push('---');
        lines.push('');
    }

    // 4. Tickets by ITIL Category
    if (data.category && data.category.labels && data.category.labels.length) {
        lines.push('## 4. Tickets by ITIL Category');
        lines.push('');
        lines.push('| Category | Count | Share (%) |');
        lines.push('|---|---:|---:|');
        const catTotal = Array.from(data.category.values).reduce((a, b) => a + b, 0);
        data.category.labels.forEach((label, i) => {
            const val = data.category.values[i] || 0;
            lines.push(`| ${label || 'None'} | ${val} | ${calcPct(val, catTotal)} |`);
        });
        lines.push(`| **Total** | **${catTotal}** | **100.0%** |`);
        lines.push('');
        lines.push('---');
        lines.push('');
    }

    // 5. Tickets by Location (Top 10 Cities)
    if (data.cityData && data.cityData.labels && data.cityData.labels.length) {
        lines.push('## 5. Tickets by Location (Top 10 Towns)');
        lines.push('');
        lines.push('| Town / City | New | In Progress | Resolved / Closed | Total |');
        lines.push('|---|---:|---:|---:|---:|');
        data.cityData.labels.forEach((city, i) => {
            const newCount = data.cityData.values?.new?.[i] || 0;
            const inProgCount = data.cityData.values?.in_progress?.[i] || 0;
            const resCount = data.cityData.values?.resolved?.[i] || 0;
            const cityTotal = newCount + inProgCount + resCount;
            lines.push(`| ${city || 'Unknown'} | ${newCount} | ${inProgCount} | ${resCount} | ${cityTotal} |`);
        });
        lines.push('');
        lines.push('---');
        lines.push('');
    }

    // 6. Resolved Tickets by TTR Intervals
    if (data.ttrDistribution && data.ttrDistribution.labels && data.ttrDistribution.labels.length) {
        const ttrTotal = data.ttrDistribution.values.reduce((a, b) => a + b, 0);
        const prevTotal = data.ttrDistribution.previousTotal || 0;
        let variationStr = 'N/A';
        if (prevTotal > 0) {
            const varNum = ((ttrTotal - prevTotal) / prevTotal) * 100;
            variationStr = `${varNum >= 0 ? '+' : ''}${varNum.toFixed(1)}%`;
        }

        lines.push('## 6. Resolved Tickets by Resolution Time (TTR) Intervals');
        lines.push('');
        lines.push(`- **Total Resolved (created in period):** ${ttrTotal}`);
        lines.push(`- **Previous Period Total:** ${prevTotal}`);
        lines.push(`- **Period-over-Period Variation:** ${variationStr}`);
        lines.push('');
        lines.push('| Resolution Time (TTR) Interval | Count | Share (%) |');
        lines.push('|---|---:|---:|');
        data.ttrDistribution.labels.forEach((label, i) => {
            const val = data.ttrDistribution.values[i] || 0;
            lines.push(`| ${label} | ${val} | ${calcPct(val, ttrTotal)} |`);
        });
        lines.push(`| **Total** | **${ttrTotal}** | **100.0%** |`);
        lines.push('');
        lines.push('---');
        lines.push('');
    }

    // 7. Open Tickets by Age Bracket
    if (data.openAgeDistribution && data.openAgeDistribution.labels && data.openAgeDistribution.labels.length) {
        const openTotal = data.openAgeDistribution.values.reduce((a, b) => a + b, 0);
        lines.push('## 7. Open Tickets by Age Bracket');
        lines.push('');
        lines.push(`- **Total Open Tickets:** ${openTotal}`);
        lines.push('');
        lines.push('| Age Bracket | Count | Share (%) |');
        lines.push('|---|---:|---:|');
        data.openAgeDistribution.labels.forEach((label, i) => {
            const val = data.openAgeDistribution.values[i] || 0;
            lines.push(`| ${label} | ${val} | ${calcPct(val, openTotal)} |`);
        });
        lines.push(`| **Total Open** | **${openTotal}** | **100.0%** |`);
        lines.push('');
        lines.push('---');
        lines.push('');
    }

    // 8. Monthly Volume of Tickets
    if (data.perMonth && data.perMonth.labels && data.perMonth.labels.length) {
        const yearTotal = data.perMonth.values.reduce((a, b) => a + b, 0);
        lines.push('## 8. Monthly Volume of Tickets (Year Overview)');
        lines.push('');
        lines.push('| Month | Month Key | Tickets Opened | Share (%) |');
        lines.push('|---|---|---:|---:|');
        data.perMonth.labels.forEach((label, i) => {
            const key = data.perMonth.keys?.[i] || '';
            const val = data.perMonth.values[i] || 0;
            lines.push(`| ${label} | ${key} | ${val} | ${calcPct(val, yearTotal)} |`);
        });
        lines.push(`| **Total Year** | | **${yearTotal}** | **100.0%** |`);
        lines.push('');
        lines.push('---');
        lines.push('');
    }

    // 9. Daily Activity & Resolution Trends
    if (data.perday && data.perday.labels && data.perday.labels.length) {
        lines.push('## 9. Daily Ticket Activity');
        lines.push('');
        lines.push('| Date | Tickets Opened | Tickets Closed | Avg TTR (h) |');
        lines.push('|---|---:|---:|---:|');
        data.perday.labels.forEach((date, i) => {
            const opened = data.perday.opened?.[i] ?? 0;
            const closed = data.perday.closed?.[i] ?? 0;
            const resIdx = data.resolution?.labels?.indexOf(date);
            const avgTtr = resIdx !== undefined && resIdx >= 0 && data.resolution?.values?.[resIdx] !== undefined
                ? data.resolution.values[resIdx] + ' h'
                : '-';
            lines.push(`| ${date} | ${opened} | ${closed} | ${avgTtr} |`);
        });
        lines.push('');
    }

    return lines.join('\n');
}

function initCounterCardsModalLinks() {
    const statusGroupByCounter = {
        incoming: 'incoming',
        assigned: 'assigned',
        waiting: 'waiting',
        solved_closed: 'solved_closed',
        missc: 'missc',
        total: '',
    };

    const cards = document.querySelectorAll('.ts-counter-card[data-counter-key]');
    cards.forEach(function (card) {
        const openFromCard = function (e) {
            e.preventDefault();
            const counterKey = card.dataset.counterKey || '';
            const counterLabel = card.dataset.counterLabel || __('Tickets', 'ticketsstatistics');
            const statusGroup = statusGroupByCounter[counterKey] || '';

            const filters = {
                type: 'counter',
                label: counterLabel,
                counter_key: counterKey,
            };

            if (statusGroup) {
                filters.status_group = statusGroup;
            }

            openTicketsModal(filters, true);
        };

        card.addEventListener('click', openFromCard);
        card.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            openFromCard();
        });
    });
}

function toggleCountersView(isSolved) {
    const defaultRow = document.getElementById('ts-counters-default');
    const solvedRow = document.getElementById('ts-counters-solved');
    if (defaultRow) defaultRow.style.display = isSolved ? 'none' : '';
    if (solvedRow) solvedRow.style.display = isSolved ? '' : 'none';
}

function submitFilterForm() {
    const customFields = document.getElementById('ts-custom-period-fields');
    const applyBtnCol = document.getElementById('ts-apply-btn-col');
    if (document.getElementById('ts-period').value === 'custom') {
        customFields.style.display = 'block';
        applyBtnCol.style.display = '';
    } else {
        customFields.style.display = 'none';
        applyBtnCol.style.display = 'none';
        const filterForm = document.getElementById('ts-filter-form');
        if (filterForm) filterForm.submit();
    }
}
function ensureHoverTooltip() {
    let el = document.getElementById('chart-hover-tooltip');
    if (!el) {
        el = document.createElement('div');
        el.id = 'chart-hover-tooltip';
        el.style.cssText = `
            position: fixed;
            display: none;
            pointer-events: none;
            background: rgba(17, 24, 39, 0.92);
            color: #fff;
            font-size: 12px;
            font-family: sans-serif;
            padding: 6px 10px;
            border-radius: 6px;
            z-index: 9999;
            white-space: nowrap;
        `;
        document.body.appendChild(el);
    }
    return el;
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
            window.lastDashboardData = data;
            // Big number counters — creation-date view
            document.querySelectorAll('.ts-count').forEach(el => {
                const status = el.dataset.status;
                el.textContent = data.counters[status] ?? 0;
            });

            // Big number counters — resolved-date view
            if (data.solvedView) {
                document.querySelectorAll('.ts-solved-count').forEach(el => {
                    const key = el.dataset.solved;
                    const val = data.solvedView[key] ?? 0;
                    el.textContent = key === 'avg_ttr' ? val + 'h' : val;
                });
            }

            Chart.register(ChartDataLabels);

            // Missc donut
            if (data.misscs) {
                new Chart(document.getElementById('chart-missc'), {
                    type: 'doughnut',
                    data: {
                        labels: data.misscs.labels.map(label => {
                            switch (label) {
                                case 'new': return __('New', 'ticketsstatistics');
                                case 'in_progress': return __('In progress', 'ticketsstatistics');
                                case 'resolved': return __('Resolved / Closed', 'ticketsstatistics');
                                default: return label;
                            }
                        }),
                        datasets: [{
                            data: data.misscs.values,
                            backgroundColor: ['#49bf4d', '#ffa500', '#c00000'],
                            hoverOffset: 12
                        }]
                    },
                    options: {
                        onClick: function (_, elements) {
                            if (!elements.length) {
                                return;
                            }

                            openTicketsModal({
                                type: 'missc',
                                status_group: data.misscs.labels[elements[0].index]
                            });
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                },
                                onClick: function (e, legendItem, legend) {
                                    const index = legendItem.index;
                                    openTicketsModal({
                                        type: 'missc',
                                        status_group: data.misscs.labels[index]
                                    });
                                }
                            },
                            datalabels: {
                                color: '#fff',
                                formatter: function (value, context) {
                                    return value > 0 ? value : '';
                                }
                            }
                        },
                        maintainAspectRatio: false
                    }
                });
            }

            const ttrIntervalsData = data.ttrDistribution;
            const total = ttrIntervalsData.values.reduce((a, b) => a + b, 0);
            const previousTotal = ttrIntervalsData.previousTotal || 0;
            let variation = 0;
            if (previousTotal > 0) {
                variation = ((total - previousTotal) / previousTotal) * 100;
            } else if (total > 0) {
                variation = 100;
            }

            const centerIntervalsVariationPlugin = {
                id: 'chart-ttr-intervals-variation',
                afterDraw(chart) {
                    const { ctx, chartArea: { left, top, right, bottom } } = chart;
                    const centerX = (left + right) / 2;
                    const centerY = (top + bottom) / 2;
                    const isPositive = variation >= 0;

                    ctx.save();
                    ctx.textAlign = 'center';

                    const mainFontSize = 32; // 2rem ≈ 32px  
                    const subFontSize = 14;
                    const gap = 4;
                    const totalHeight = mainFontSize + gap + subFontSize;
                    const startY = centerY - totalHeight / 2;

                    // Texte principal  
                    ctx.font = 'bold 2rem sans-serif';
                    ctx.fillStyle = '#000';
                    ctx.textBaseline = 'top';
                    ctx.fillText(total, centerX, startY);

                    ctx.font = '14px sans-serif';
                    ctx.fillStyle = isPositive ? '#2ecc71' : '#e74c3c';
                    ctx.textBaseline = 'top';
                    ctx.fillText(`${isPositive ? '▲' : '▼'} ${Math.abs(variation).toFixed(1)}%`, centerX, startY + mainFontSize + gap);

                    ctx.restore();
                    chart.$centerTextBox = { x: centerX - 40, y: centerY - 20, width: 80, height: 40 };
                }
            };

            // TTR intervals donut
            const chartTTRIntervals = new Chart(document.getElementById('chart-ttr-intervals'), {
                type: 'doughnut',
                data: {
                    labels: ttrIntervalsData.labels,
                    datasets: [{
                        data: ttrIntervalsData.values,
                        backgroundColor: ttrIntervalsData.colors,
                        hoverOffset: 12
                    }]
                },
                options: {
                    onClick: function (_, elements) {
                        if (!elements.length) {
                            return;
                        }

                        openTicketsModal({
                            type: 'ttr_bucket',
                            label: ttrIntervalsData.labels[elements[0].index]
                        });
                    },
                    layout: {
                        padding: { top: 20, bottom: 20 }
                    },
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'circle'
                            },
                            title: {
                                display: true,
                                text: __('Resolution Time', 'ticketsstatistics'),
                                font: { weight: 'bold' }
                            }
                        },
                        datalabels: {
                            color: '#333',
                            formatter: (value) => {
                                const pct = total > 0 ? Math.round((value / total) * 100) : 0;
                                return value > 0 ? value + ' (' + pct + '%)' : '';
                            },
                            clamp: true,
                            anchor: 'end',
                            align: 'end',
                        },
                    },
                    maintainAspectRatio: false,
                },
                plugins: [centerIntervalsVariationPlugin]
            });
            attachCenterTooltip(chartTTRIntervals, __('Variation of tickets between the selected period and the previous one', 'ticketsstatistics'));

            // Open tickets by age (New/Assigned/Waiting)
            if (data.openAgeDistribution) {
                const openAge = data.openAgeDistribution;
                const totalOpen = openAge.values.reduce((a, b) => a + b, 0);

                const centerOpenAgeTotalPlugin = {
                    id: 'chart-open-age-total',
                    afterDraw(chart) {
                        const { ctx, chartArea: { left, top, right, bottom } } = chart;
                        const centerX = (left + right) / 2;
                        const centerY = (top + bottom) / 2;

                        ctx.save();
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';

                        ctx.font = 'bold 2rem sans-serif';
                        ctx.fillStyle = '#374151';
                        ctx.fillText(totalOpen, centerX, centerY);

                        ctx.restore();

                        const textMetrics = ctx.measureText(totalOpen);
                        chart.$centerTextBox = { x: centerX - textMetrics.width / 2, y: centerY - 16, width: textMetrics.width, height: 32 };
                    }
                };

                const openAgeChart = new Chart(document.getElementById('chart-open-age'), {
                    type: 'doughnut',
                    data: {
                        labels: openAge.labels,
                        datasets: [{
                            data: openAge.values,
                            backgroundColor: openAge.colors,
                            hoverOffset: 12,
                        }],
                    },
                    options: {
                        onClick: function (_, elements) {
                            if (!elements || !elements.length) {
                                return;
                            }

                            const point = elements[0];
                            const bucketLabel = openAge.labels[point.index] || '';

                            openTicketsModal({
                                type: 'open_age',
                                label: bucketLabel,
                            });
                        },
                        maintainAspectRatio: false,
                        layout: {
                            padding: { top: 20, bottom: 20 }
                        },
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                },
                                title: {
                                    display: true,
                                    text: __('Opened duration', 'ticketsstatistics').replace("&#39;", '’'),
                                    font: { weight: 'bold' },
                                }
                            },
                            datalabels: {
                                color: '#333',
                                anchor: 'end',
                                align: 'end',
                                formatter: function (value) {
                                    const pct = totalOpen > 0 ? Math.round((value / totalOpen) * 100) : 0;
                                    return value + ' (' + pct + '%)';
                                },
                            },
                        },
                    },
                    plugins: [centerOpenAgeTotalPlugin]
                });
                attachCenterTooltip(openAgeChart, __('Total open tickets', 'ticketsstatistics'));
            }

            // Priority donut
            if (document.getElementById('chart-priority') !== null) {
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
            }

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
                            category_id: Number.isInteger(data.category.ids?.[elements[0].index])
                                ? data.category.ids[elements[0].index]
                                : null,
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

            // Monthly volume bar (Per Month)
            new Chart(document.getElementById('chart-monthly-volume'), {
                type: 'bar',
                data: {
                    labels: data.perMonth.labels,
                    datasets: [{
                        label: __('Tickets', 'ticketsstatistics'),
                        data: data.perMonth.values,
                        backgroundColor: '#0d6efd',
                    }],
                },
                options: {
                    onClick: function (_, elements) {
                        if (!elements.length) {
                            return;
                        }

                        openTicketsModal({
                            type: 'per_month',
                            label: data.perMonth.keys[elements[0].index]
                        });
                    },
                    layout: {
                        padding: { top: 20 }
                    },
                    plugins: {
                        legend: { display: false },
                        datalabels: { anchor: 'end', align: 'end' },
                    },
                    scales: {
                        x: { ticks: { maxRotation: 45, minRotation: 45 } },
                        y: { beginAtZero: true },
                    },
                },
            });
        });
}

/**
 * Attaches a tooltip to the center of a chart when hovering over the center text box.
 * @param {Chart} chart 
 * @param {string} tooltipContent
 */
function attachCenterTooltip(chart, tooltipContent) {
    const tooltipEl = ensureHoverTooltip();

    chart.canvas.addEventListener('mousemove', function (evt) {
        const box = chart.$centerTextBox;
        if (!box) {
            tooltipEl.style.display = 'none';
            return;
        }

        const rect = chart.canvas.getBoundingClientRect();
        const x = evt.clientX - rect.left;
        const y = evt.clientY - rect.top;

        const inside = x >= box.x && x <= box.x + box.width &&
            y >= box.y && y <= box.y + box.height;

        if (inside) {
            tooltipEl.textContent = tooltipContent;
            tooltipEl.style.left = (evt.clientX + 12) + 'px';
            tooltipEl.style.top = (evt.clientY + 12) + 'px';
            tooltipEl.style.display = 'block';
            chart.canvas.style.cursor = 'help';
        } else {
            tooltipEl.style.display = 'none';
            chart.canvas.style.cursor = 'default';
        }
    });

    chart.canvas.addEventListener('mouseleave', function () {
        tooltipEl.style.display = 'none';
        chart.canvas.style.cursor = 'default';
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

function openTicketsModal(filters, openFullListPage = false) {
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

    if (filters.category_id !== undefined && filters.category_id !== null) {
        params.set('category_id', String(filters.category_id));
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
            title.innerHTML = payload.title;
            count.textContent = formatTicketsCount(payload.count);

            if (payload.truncated) {
                alertBox.textContent = __('Showing the first 100 tickets only.', 'ticketsstatistics');
                alertBox.classList.remove('d-none');
            }

            const fullListBtn = document.getElementById('ts-tickets-modal-full-btn');
            fullListBtn.disabled = !payload.tickets.length;

            fullListBtn.onclick = function () {
                const req = new URLSearchParams();
                req.set('period', params.get('period') || 'thismonth');
                req.set('type', filters.type || '');
                req.set('label', filters.label || '');
                if (filters.status_group) {
                    req.set('status_group', filters.status_group);
                }
                const openStatusesGlobal = params.get('open_statuses_global');
                if (openStatusesGlobal !== null) {
                    req.set('open_statuses_global', openStatusesGlobal);
                }

                const dateFrom = params.get('date_from');
                const dateTo = params.get('date_to');
                if (dateFrom) {
                    req.set('date_from', dateFrom);
                }
                if (dateTo) {
                    req.set('date_to', dateTo);
                }

                if (filters.counter_key) {
                    req.set('counter_key', filters.counter_key);
                } else if (filters.type === 'counter') {
                    req.set('counter_key', filters.status_group || 'total');
                } else if (filters.status_group) {
                    req.set('counter_key', filters.status_group);
                }

                fetch(root + '/plugins/ticketsstatistics/ajax/tickets_full_list_url.php?' + req.toString())
                    .then(r => r.json())
                    .then(data => {
                        if (!data?.url) {
                            return;
                        }

                        const targetUrl = data.url.startsWith('/')
                            ? window.location.origin + data.url
                            : data.url;

                        window.open(targetUrl, '_self', 'noopener');
                    })
                    .catch((e) => {
                        console.error('Error fetching full list URL:', e);
                    });
            };
            if (openFullListPage && payload.tickets.length) {
                fullListBtn.click();
                return;
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
    return String(value ?? '');
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
