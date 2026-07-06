# TicketsStatistics GLPI plugin

TicketsStatistics adds a dedicated reporting dashboard to GLPI for helpdesk ticket activity.

Current release: 0.4.3

The plugin provides:

- ticket counters by status
- charts by priority and category
- town-based ticket analytics
- ticket creation trends per day
- dedicated technicians statistics page with assignment and performance metrics
- period filters including custom date ranges
- PDF export of the dashboard
- dedicated assets statistics page with filters by town (location) and manufacturer
- assets overview cards (total, computers, network devices, monitors)
- stacked assets charts by town and by manufacturer (context-aware with selected filters)
- software analytics on computers: top installed softwares and coverage (with/without selected software)
- **stats widget embedded in the GLPI central dashboard** (Tab 0): ticket status doughnut, top requesters bar chart, tickets by town bar chart, and assets by type doughnut — all filterable by period
- **resolved period view**: a toggle switch on the counters row (available on the main dashboard, the ticket list, and the central widget) that switches the big-number cards from the default creation-date view to a solved-date view showing tickets resolved/closed in the period, tickets opened in the period, and the average TTR for tickets resolved in that period

## Data displayed (what each value means)

This section explains the meaning of the values shown in the dashboard.

- Period and category filters apply to all dashboard values.
- In the default view, the period filter is based on ticket creation date (`tickets.date`).
- In resolved period view, the period filter is based on resolved/closed date (`COALESCE(solvedate, closedate)`).

### Counters (default view)

- New, Assigned, Pending, Resolved/Closed: number of tickets currently in those statuses within the selected scope.
- Total tickets: sum of status counters in the selected scope.
- MISSC (when `cfaomobility` is active): number of tickets with a non-empty MISSC number.

### Opened vs Closed per day

- Opened per day: count of tickets by creation day (`DATE(tickets.date)`).
- Closed per day: count of tickets by resolution/closure day (`DATE(COALESCE(solvedate, closedate))`).
- Days shown are the union of both day sets. A day can have opened = 0 and closed > 0 (or the reverse).
- In custom period mode, closed-per-day values are also bounded by the selected custom range on resolved/closed date.

### TTR (Time To Resolution)

- Ticket duration source: `solve_delay_stat`, or `close_delay_stat` when solve delay is missing/zero.
- Unit displayed: hours.
- Daily TTR value: average of all valid ticket durations for that resolved/closed day.
- Global average: average of all valid ticket durations in the selected scope, repeated as a baseline across all days.
- In custom period mode, TTR values are bounded by the selected custom range on resolved/closed date.

### Resolved period view counters

- Resolved / Closed in period: tickets in status Resolved or Closed whose resolved/closed date is in the selected period.
- Opened in period: tickets whose creation date is in the selected period.
- Avg TTR: average resolution time (hours) for tickets resolved/closed in the selected period.

## Compatibility

This plugin targets:

- GLPI 10.0.16 and newer in the 10.x series
- GLPI 11.0.6 or older in the 11.x series

### Notes & versions specificities

- The plugin use `\Glpi\DBAL\QueryExpression` for building database queries if available (GLPI 11.0.0+), otherwise it falls back to `\QueryExpression`.
- The plugin assets(javascript) are loaded with the `public/` prefix for GLPI 10.x and without it for GLPI 11.x.

## Usage

After installation and activation, users with access to the helpdesk dashboard can open the Tickets Statistics page from the GLPI menu.

## Translations

Run this command to generate the translation files:

```bash
find . -type f \( -name '*.php' \) > files-php.list
xgettext --language=PHP --keyword=__ --from-code=UTF-8 --output=locales/ticketsstatistics.pot --join --add-comments --files-from=files-php.list
rm files-php.list
```

```bash
find . -type f \( -name '*.js' \) > files-js.list
xgettext --language=JavaScript --keyword=__ --from-code=UTF-8 --output=locales/ticketsstatistics.pot --join --add-comments --files-from=files-js.list
rm files-js.list
```

Then open the generated `locales/ticketsstatistics.pot` file with a translation tool (e.g. [Poedit](https://poedit.net/)) and create the `.po` files for each language. Finally, compile the `.po` files to `.mo` files.

## Contributing

- Open a ticket for each bug/feature so it can be discussed
- Follow [development guidelines](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html)
- Refer to [GitFlow](http://git-flow.readthedocs.io/) process for branching
- Work on a new branch on your own fork
- Open a PR that will be reviewed by a developer
