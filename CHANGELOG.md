# Changelog

This file documents the most important changes introduced in each version of
the application. The project follows [Semantic Versioning](https://semver.org/).

## [1.1.0] - 2026-09-10

### Jira integration

- Jira rate-limit responses now return HTTP 429, preserve the `Retry-After`
  header when available, and show a localized message with the suggested wait
  time across authentication, reporting, and worklog operations.
- Actionable application errors, such as an expired Jira session, are now shown
  in HTML and API responses instead of being replaced with a generic error.

### Configuration and operations

- Updated `alianet/env-sync` to 1.1.0 and configured validation that requires
  `JIRA_URL` to differ from the template value.
- Composer install and update hooks now detect missing, additional, duplicate,
  and required-but-unchanged environment variables.

### Dependencies and CI

- Updated Symfony components from 7.4 to 8.1, Monolog from 3.11 to 3.12, and the
  minimum supported Twig version from 3.0 to 3.15.
- Updated the GitHub Actions checkout action from version 4 to version 7.

### Documentation

- Standardized the changelog language to English.

## [1.0.0] - 2026-09-05

The first stable release of Jira Time Tracker.

### Time reporting

- Monthly Jira worklog report displayed as an issue-by-day matrix.
- Personal time report, user search, and report preview for a selected user.
- Month and year selection, navigation between adjacent months, and quick return
  to the current period.
- Entries grouped by issue and day, with daily totals, issue totals, and total
  time expressed in hours and working days.
- Natural sorting of issue keys and direct links to Jira issues.
- Visual indicators for the current day, weekends, holidays, and working days
  with missing required hours.
- Report row filtering by selecting a day in the table header.
- Configurable reporting-day length, time zone, and national or regional holiday
  calendar.

### Worklog management

- Jira issue search by key or summary without reloading the page.
- Adding worklogs from a search result or a report cell.
- Editing the time and comment of the user's own existing worklogs.
- Deleting the user's own worklogs after confirming the operation.
- Worklog history for the user's own issues, also available in read-only mode
  when viewing another user's report.
- Support for Jira time notation (`w`, `d`, `h`, `m`) and decimal hours normalized
  by the form.
- A set of predefined tags for quickly filling in worklog comments.

### CSV export

- Optional export of the monthly report to a CSV file.
- Configurable column selection and order, filename pattern, and BOM marker.
- Optional configurable summary row and spacing before the summary.
- Columns covering issue details, date, time, comment, issue URL, and worklog ID.
- Ability to disable export entirely, including hiding the button and blocking
  the endpoint.

### Atlassian authentication

- Individual mode using an email address and a personal Atlassian API token,
  restricted to connections through `localhost` or a loopback address.
- Company mode using OAuth 2.0 (3LO), including token refresh and logout.
- OAuth tokens stored in the PHP session are encrypted and authenticated with
  a configurable key.
- A single authenticated HTTP client shared by Jira adapters within each request.

### User interface

- Responsive Twig-based HTML interface with `default` and `compact` variants.
- Interface available in Polish, English, German, Czech, Slovak, and French.
- Language selection stored in a cookie and automatically matched against the
  `Accept-Language` header.
- Messages for successful operations, form errors, integration errors, and empty
  search results.

### Configuration and operations

- Complete application environment launched with Docker Compose.
- Configuration through `.env` and `.env.local` files, with local overrides
  supported without rebuilding the image.
- Configurable log level and separate logs for the application and critical PHP
  errors.
- Safe generic HTML and JSON responses that do not expose exception details.

### Security and quality

- CSRF protection for write operations and logout.
- Validation of user identifiers before they are used in JQL queries.
- Modular architecture with separate reporting and time-tracking contexts and
  isolated Atlassian/Jira integrations.
- Automated unit and architecture tests, static analysis, code style checks, and
  syntax validation run with the shared `composer php:all` command.

[1.1.0]: https://github.com/alianet/jira-timetracker/releases/tag/v1.1.0
[1.0.0]: https://github.com/alianet/jira-timetracker/releases/tag/v1.0.0
