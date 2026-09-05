# Time Tracker

**English** | [Polski](README.pl.md) | [Deutsch](README.de.md) | [Čeština](README.cs.md) | [Slovenčina](README.sk.md) | [Français](README.fr.md)

## Running

```bash
docker compose up --build -d
```

The app will be available at <http://localhost:81>.

## Atlassian setup

The app supports two modes:
- `individual` uses a personal API token
- `company` uses OAuth 2.0 (3LO) and requires a registered Atlassian app

### `individual` mode

For security, this mode accepts requests only through `localhost` or an IP
loopback address. Requests made using a machine name, LAN address, or public
address receive an HTTP 403 response.

In this mode the app connects to Jira directly with a personal API token.
Generate the token in your Atlassian account security settings, then fill in `.env`:

```dotenv
JIRA_URL=https://your-company.atlassian.net
ATLASSIAN_ACCOUNT_TYPE=individual
ATLASSIAN_EMAIL=your.email@company.com
ATLASSIAN_API_TOKEN=...
```

`ATLASSIAN_EMAIL` must be the Atlassian account email, and `ATLASSIAN_API_TOKEN`
must be the token generated for the same account. In this mode you do not configure
`ATLASSIAN_CLIENT_ID`, `ATLASSIAN_CLIENT_SECRET`, or `ATLASSIAN_REDIRECT_URI`.

### `company` mode

This mode requires creating an OAuth 2.0 (3LO) app in the Atlassian developer
console. Set the callback to `http://localhost:81/oauth/callback`, add the scopes
`read:jira-work`, `write:jira-work`, `read:jira-user`, and `offline_access`, then
fill in `.env`:

```dotenv
JIRA_URL=https://your-company.atlassian.net
ATLASSIAN_ACCOUNT_TYPE=company
ATLASSIAN_CLIENT_ID=...
ATLASSIAN_CLIENT_SECRET=...
ATLASSIAN_REDIRECT_URI=http://localhost:81/oauth/callback
SESSION_ENCRYPTION_KEY=...
```

Generate `SESSION_ENCRYPTION_KEY` once and keep it stable between deployments:

```bash
docker compose run --rm php php -r 'echo sodium_bin2base64(random_bytes(32), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), PHP_EOL;'
```

The key encrypts and authenticates OAuth access and refresh tokens before PHP writes
the session to disk. Changing it invalidates existing OAuth sessions.

After startup, the app redirects to Atlassian login and fetches data on behalf of
the signed-in user. `ATLASSIAN_ACCOUNT_TYPE=company` switches the UI to the company
flow, while `ATLASSIAN_ACCOUNT_TYPE=individual` uses the direct personal token flow.

### Personal Atlassian token

If you use `individual` mode, put the API token into `ATLASSIAN_API_TOKEN`.
If you use `company` mode, that token is not used.

## Composer

Composer runs inside the PHP container, for example:

```bash
docker compose exec php composer --version
docker compose exec php composer install
```

The app is a DDD-oriented modular monolith with `Identity`, `Reporting`, and
`TimeTracking` as business modules. `Shared` is a small domain shared kernel,
while technical application-host concerns live in `Kernel`. Controllers live in each module's `Presentation`,
ports in `Application` or `Domain`, and Jira/Atlassian adapters in
`Infrastructure`. See [ARCHITECTURE.md](ARCHITECTURE.md) for the dependency map
and automated boundary rules. You can choose the report period from the form on
the page.

`Bootstrap` is the only production composition root. Dependencies point from
`Presentation` to `Application` to `Domain`; infrastructure adapters implement
business-owned ports. Domain code does not depend on HTTP, Twig, Jira, or CSV.

## Tests and quality checks

Run the complete required check in the PHP container:

```bash
docker compose exec php composer php:all
```

For a shorter feedback loop, run an individual suite:

```bash
docker compose exec php composer php:unit
docker compose exec php composer php:architecture
docker compose exec php composer php:static
docker compose exec php composer php:lint
docker compose exec php composer php:cs
```

Tests must not use real Jira credentials or network calls. Infrastructure tests
use fake transports, while application tests use port stubs.

## Adding a use case or adapter

To add a use case, define its input command/query and a handler in the owning
module's `Application` layer. Put business validation in Domain value objects,
depend on an interface owned by `Application` or `Domain/Port`, and test the
handler with a stub of that port. The HTTP controller should only map the request,
call the handler, and create the response.

To add an adapter, implement that port under the module's `Infrastructure`
directory and keep vendor payloads and field names there. Add adapter mapping or
contract tests, then wire the concrete adapter only in `src/Bootstrap.php`. Update
`tests/Architecture/LayerDependenciesTest.php` when a new mandatory port/adapter
pair should be enforced, and finish with `composer php:all`.

## Interface languages

The interface includes Polish, English, German, Czech, Slovak, and French translations:

```dotenv
APP_LOCALES=pl,en,de,cs,sk,fr
APP_DEFAULT_LOCALE=pl
```

Users can switch language from the page navigation. The app remembers the selection
in a cookie and otherwise uses the browser's `Accept-Language` preference.

## Interface variant

The `TEMPLATE` variable in `.env` selects the page layout:

```dotenv
TEMPLATE=default
```

The `default` value keeps the standard view. Set it to `compact` to reduce spacing
and keep scrolling inside the report table so the interface fits smaller windows.

## CSV export setup

Monthly export can be adjusted with `.env` variables:

```dotenv
REPORT_EXPORT_ENABLED=true
REPORT_EXPORT_COLUMNS=issue,project,summary,date,minutes,comment
REPORT_EXPORT_FILENAME={username}_{year}_{month}.csv
REPORT_EXPORT_BOM=true
REPORT_EXPORT_SUMMARY_ROW='["SUMMARY","","Work time total:","","{total_minutes}","{total_hours}h {total_remaining_minutes}m"]'
REPORT_EXPORT_SUMMARY_SPACER=true
DAILY_HOURS_LIMIT=7.5
APP_TIMEZONE=Europe/Warsaw
LOG_LEVEL=error
```

`REPORT_EXPORT_ENABLED=false` hides the download button and blocks the export
endpoint. If this variable is missing or set to `false`, export stays disabled and
the remaining variables are not required. Only `REPORT_EXPORT_ENABLED=true` enables
validation for `REPORT_EXPORT_COLUMNS`, `REPORT_EXPORT_FILENAME`,
`REPORT_EXPORT_BOM`, `REPORT_EXPORT_SUMMARY_ROW`, and
`REPORT_EXPORT_SUMMARY_SPACER`.

The app reads configuration directly from `.env`. An optional `.env.local` file is
loaded afterwards and overrides matching keys. Both files are mounted into the
container together with the code, so changing them does not require rebuilding the
container or setting system-wide environment variables.

Unhandled exceptions, including errors raised while bootstrapping the application,
are written with their stack traces to `var/log/app.log`. Native PHP fatal errors
are additionally written to `var/log/php-error.log`. Error details are never shown
in HTTP responses; users receive a generic HTML page or JSON error instead.
`LOG_LEVEL` sets the minimum logging level and accepts values from `debug` through
`emergency`; the default is `error`.

Available columns are `issue`, `project`, `summary`, `date`, `minutes`, `hours`,
`time_spent`, `comment`, `url`, and `worklog_id`. The filename mask supports
`{username}`, `{year}`, and `{month}`. Summary row cells can use
`{total_minutes}`, `{total_hours}`, `{total_remaining_minutes}`, and
`{total_decimal_hours}`. The summary row is a JSON array; an empty array `[]`
disables it completely.

The search field above the table looks up issues by key or title without reloading
the page. Clicking a search result or a day cell opens the form, which writes the
worklog directly to Jira and refreshes the report. If a cell already contains time,
the form lets you pick a specific worklog and edit its time or comment.

## License

This project is available under the [MIT License](LICENSE).

## Stopping

```bash
docker compose down
```
