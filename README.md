# TicketsStatistics GLPI plugin

Add your plugin description here.

## Translations

Run this command to generate the translation files:

```bash
find . -type f \( -name '*.php' -o -name '*.js' \) > files.list
xgettext --language=PHP --language=JavaScript --keyword=__ --from-code=UTF-8 --output=locales/ticketsstatistics.pot --no-location --add-comments --files-from=files.list
rm files.list
```

Then open the generated `locales/ticketsstatistics.pot` file with a translation tool (e.g. [Poedit](https://poedit.net/)) and create the `.po` files for each language. Finally, compile the `.po` files to `.mo` files.

## Contributing

* Open a ticket for each bug/feature so it can be discussed
* Follow [development guidelines](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html)
* Refer to [GitFlow](http://git-flow.readthedocs.io/) process for branching
* Work on a new branch on your own fork
* Open a PR that will be reviewed by a developer
