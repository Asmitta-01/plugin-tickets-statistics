# TicketsStatistics GLPI plugin

TicketsStatistics adds a dedicated reporting dashboard to GLPI for helpdesk ticket activity.

The plugin provides:

- ticket counters by status
- charts by priority and category
- charts and tables by town
- ticket creation trends per day
- period filters including custom date ranges
- PDF export of the dashboard

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
find . -type f \( -name '*.php' -o -name '*.js' \) > files.list
xgettext --language=PHP --language=JavaScript --keyword=__ --from-code=UTF-8 --output=locales/ticketsstatistics.pot --join --add-comments --files-from=files.list
rm files.list
```

Then open the generated `locales/ticketsstatistics.pot` file with a translation tool (e.g. [Poedit](https://poedit.net/)) and create the `.po` files for each language. Finally, compile the `.po` files to `.mo` files.

## Contributing

- Open a ticket for each bug/feature so it can be discussed
- Follow [development guidelines](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html)
- Refer to [GitFlow](http://git-flow.readthedocs.io/) process for branching
- Work on a new branch on your own fork
- Open a PR that will be reviewed by a developer
