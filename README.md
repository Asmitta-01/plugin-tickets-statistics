# TicketsStatistics GLPI plugin

![Version](https://img.shields.io/badge/Version-0.10.0-2563eb)
![PHP](https://img.shields.io/badge/PHP-%3E%3D8.3-777bb4)
![GLPI](https://img.shields.io/badge/GLPI-10.0.16%20to%2011.0.6-0ea5e9)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/Asmitta-01/plugin-tickets-statistics)

TicketsStatistics adds a dedicated reporting dashboard to GLPI for helpdesk ticket activity, plus town-based ticket, asset, computer/patch, and server analytics.

**Current release:** 0.10.0

## Table of contents

- [Features](#features)
- [Data displayed](#data-displayed-what-each-value-means)
- [Compatibility](#compatibility)
- [Usage](#usage)
- [Translations](#translations)
- [Contributing](#contributing)

## Features

### Tickets dashboard

- Ticket counters by status
- Charts by priority, category, TTR intervals, open ticket age, and monthly volume
- Town-based ticket analytics
- Ticket creation trends per day
- Period filters, including custom date ranges
- PDF export of the dashboard
- **Markdown export** (`#ticketsstatisticsDownloadMarkdownBtn`) for automated AI analysis and reporting

### Resolved period view

A toggle switch on the counters row (main dashboard, ticket list, and central widget) that switches the big-number cards from the default creation-date view to a solved-date view: tickets resolved/closed in the period, tickets opened in the period, and the average TTR for tickets resolved in that period.

### Open statuses global mode

New / Assigned / Pending cards can be computed globally (outside the period filter) while other cards remain period-based. Behavior stays consistent across cards, modal lists, and the full-list opening.

### Technicians

Dedicated technicians statistics page with assignment and performance metrics.

### Assets

- Dedicated assets statistics page, filterable by town (location) and manufacturer
- Overview cards (with toggle switch): Total assets, Laptops, Desktops, Servers, Monitors, Printers, Switches, Firewalls
- Stacked charts by town and by manufacturer, context-aware with selected filters
- Software analytics on computers: top installed software and coverage (with/without a selected software)

### Computers & patch statistics

Dedicated page, filterable by town and entity:

- Cards: Windows 11 computers, latest Windows version (auto-detected), computers to update (capturing older Windows 11 and Windows 10 machines), obsolete computers (CPU generation < 8), total KB patches deployed
- Charts: Windows by OS version, Windows by site and OS version (stacked), latest KB patches and installations
- Additional charts: computers by town split by type (laptop, desktop, server, VMware, other), and Windows compliance by entity and OS version (stacked)
- Adaptive color palette: green for latest version, amber for previous version, and darkening shades of red for older Windows releases
- Enhanced CPU generation detection: accurately handles Intel 10th Gen Ice Lake G-series (e.g. `i7-1065G7`, `i5-1035G1`), Intel Core Ultra, N-series, and Xeon processors
- Direct navigation button to the **Servers dashboard**
- Interactions: clicking cards/charts opens detail modals, with CSV export and GLPI full-list opening where applicable

### Printers statistics

Dedicated page (`front/printers.php`), accessible from the assets dashboard:

- Overview cards: **Total Printers**, **Total Printed Pages**, **Cartridges in Stock (New)**, and **Cartridges in Use**
- **Bar Chart (by model)**: Distribution of printers by hardware model (Top 8)
- **Bar Chart (by town)**: Distribution of printers by geographical location
- **Bar Chart (by pages)**: Top printers ranked by total printed pages
- **Line Chart (evolution)**: Evolution of global page counters over the last 12 months
- **Doughnut Chart (Ink levels)**: Overview of ink and toner levels (Critical, Low, Good, Full) collected from SNMP
- **Interactive drilldown**: Clicking on chart segments (models, towns, or top printers) opens a detailed modal with the filtered printer list and direct links to GLPI.

### Servers statistics

Dedicated page (`front/servers.php`), accessible from the computers and assets dashboards:

- Overview cards: **Total servers**, **Physical servers**, **Virtual servers**, and **Virtualization hosts** (hypervisors hosting VMs)
- **Pie Chart (by nature)**: Visual breakdown of servers by nature (Physical, Virtual, Virtualization host) with consistent badge colors
- **Bar Chart (by hardware / model)**: Horizontal distribution of servers across hardware models and virtual container environments
- **Interactive drilldown**: Clicking any counter card, pie slice, or bar opens a detailed modal with server list, CSV export, and GLPI search redirect
- **Servers inventory table**: Responsive table with instant client-side text filtering and full CSV export

### Central dashboard widget

Stats widget embedded in the GLPI central dashboard (Tab 0): ticket status doughnut, top requesters bar chart, tickets by town bar chart, and assets by type doughnut — all filterable by period.

### GLPI integration

- Full list opens `computer.php` with GLPI `criteria[...]` URL parameters, so the native GLPI search engine handles filtering directly
- Shared computers helpers live in `src/ComputersStatistics.php`
- Dedicated server service lives in `src/ServersStatistics.php`

## Data displayed (what each value means)

This section explains the meaning of the values shown in the dashboard.

- Period and category filters apply to all dashboard values.
- In the default view, the period filter is based on ticket creation date (`tickets.date`).
- In resolved period view, the period filter is based on resolved/closed date (`COALESCE(solvedate, closedate)`).

### Counters (default view)

| Value | Meaning |
| --- | --- |
| New, Assigned, Pending, Resolved/Closed | Number of tickets currently in that status within the selected scope |
| Total tickets | Sum of the status counters in the selected scope |
| MISSC (when `cfaomobility` is active) | Number of tickets with a non-empty MISSC number |

- **Global open statuses switch** (enabled by default): New, Assigned, and Pending counters are computed globally (without period filtering), while still respecting entity/category restrictions.
- When this switch is disabled, New, Assigned, and Pending counters use the selected period like the other counters.
- Card click behavior stays consistent with the switch: modal results and "Open full list" use the same global-vs-period logic (including the GLPI 10 ticket-list widget).

### Opened vs Closed per day

- **Opened per day**: count of tickets by creation day (`DATE(tickets.date)`).
- **Closed per day**: count of tickets by resolution/closure day (`DATE(COALESCE(solvedate, closedate))`).
- Days shown are the union of both day sets — a day can have opened = 0 and closed > 0 (or the reverse).
- In custom period mode, closed-per-day values are also bounded by the selected custom range on resolved/closed date.

### TTR (Time To Resolution)

- **Duration source**: `solve_delay_stat`, or `close_delay_stat` when the solve delay is missing/zero.
- **Unit displayed**: hours.
- **Daily TTR value**: average of all valid ticket durations for that resolved/closed day.
- **Global average**: average of all valid ticket durations in the selected scope, repeated as a baseline across all days.
- In custom period mode, TTR values are also bounded by the selected custom range on resolved/closed date.

### Resolved period view counters

| Value | Meaning |
| --- | --- |
| Resolved / Closed in period | Tickets in status Resolved or Closed whose resolved/closed date falls in the selected period |
| Opened in period | Tickets whose creation date falls in the selected period |
| Avg TTR | Average resolution time (hours) for tickets resolved/closed in the selected period |

### Resolved tickets by TTR intervals

- **Source**: Resolved and closed tickets among those created in the selected period (`date`), having a resolution time (`solve_delay_stat` or `close_delay_stat`).
- **Buckets**: Tickets are grouped into predefined time intervals: `< 2h`, `2h-4h`, `4h-8h`, `8h-16h`, and `>= 16h`.
- **Center value**: The total number of resolved/closed tickets among those created in the selected period, and the percentage variation compared to resolved/closed tickets among those created in the previous period.

### Open tickets by age

- **Source**: All tickets in an open status (`New`, `Assigned`, `Pending`). This chart is not affected by the period filter.
- **Buckets**: Tickets are grouped by their age (time since creation): `< 24h`, `1-3 days`, `3-7 days`, and `> 7 days`.
- **Center value**: The total number of open tickets.

### Monthly Volume of Tickets

- **Source**: Tickets opened during the selected period, grouped by month.

## Compatibility

- GLPI 10.0.16 and newer in the 10.x series
- GLPI 11.0.6 or older in the 11.x series
- PHP 8.3, 8.4, and 8.5

### Notes & version specificities

- The plugin uses `\Glpi\DBAL\QueryExpression` for building database queries when available (GLPI 11.0.0+), and falls back to `\QueryExpression` otherwise.
- Plugin JavaScript assets are loaded with the `public/` prefix on GLPI 10.x, and without it on GLPI 11.x.

## Usage

After installation and activation, users with access to the helpdesk dashboard can open the Tickets Statistics page from the GLPI menu.

## Translations

Generate the PHP translation strings:

```bash
find . -type f \( -name '*.php' \) > files-php.list
xgettext --language=PHP --keyword=__ --from-code=UTF-8 --output=locales/ticketsstatistics.pot --join --add-comments --files-from=files-php.list
rm files-php.list
```

Generate the JavaScript translation strings:

```bash
find . -type f \( -name '*.js' \) > files-js.list
xgettext --language=JavaScript --keyword=__ --from-code=UTF-8 --output=locales/ticketsstatistics.pot --join --add-comments --files-from=files-js.list
rm files-js.list
```

Then open the generated `locales/ticketsstatistics.pot` file with a translation tool (e.g. [Poedit](https://poedit.net/)) and create the `.po` files for each language. Finally, compile the `.po` files to `.mo` files.

## Contributing

- Open a ticket for each bug/feature so it can be discussed
- Follow the [development guidelines](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html)
- Refer to the [GitFlow](http://git-flow.readthedocs.io/) process for branching
- Work on a new branch on your own fork
- Open a PR that will be reviewed by a developer
