# Architecture

The application is a modular monolith with two business modules: `Reporting`
and `TimeTracking`. `Identity` is a technical security and provider-integration
module. Domain-level cross-cutting code lives in `Shared`; technical
application-host concerns live in `Kernel`. `Bootstrap` is the main production
composition root.

```text
src/
├── Identity/
│   ├── Application/
│   ├── Infrastructure/{Atlassian,Session}/
│   └── Presentation/Http/
├── Reporting/
│   ├── Domain/
│   ├── Application/Port/
│   ├── Infrastructure/{Calendar,Csv,Jira}/
│   └── Presentation/{Http,ViewModel}/
├── TimeTracking/
│   ├── Domain/{Exception,Model}/
│   ├── Application/{Command,Handler,Port,Query}/
│   ├── Infrastructure/Jira/
│   └── Presentation/Http/
├── Shared/{Domain,Infrastructure}/
├── Kernel/{Config,Exception,Infrastructure,Presentation,Support}/
└── Bootstrap.php
```

The dependency direction inside each module is:

```text
Presentation ──> Application ──> Domain
      │                ▲             ▲
      └────────────────┘             │
Infrastructure ───── implements ports┘

Bootstrap ──> all concrete adapters (composition only)
```

Runtime configuration belongs to `Kernel/Config`, because it is parsed by the
technical host and composed into multiple modules. This includes
`WorkdayConfig` and `ReportExportConfig`; configuration failures live in
`Kernel/Exception`. Reporting infrastructure consumes the resulting export
settings but does not own their parsing.

Adapters backed by the native PHP session live in
`Identity/Infrastructure/Session`. `SessionAccessTokenStore` implements the
application authentication store, while `NativeSessionSecurity` implements
the narrow `SessionSecurity` port kept beside its sole consumer in
`Identity/Presentation/Http`. Presentation therefore contains no port
implementations; infrastructure depends inward on the port owner.

Business modules expose explicit interfaces in `Application` or `Domain/Port`.
Deptrac defines the complete layer and module dependency map in `deptrac.php`.
The architecture tests additionally reject framework/adapter imports in Domain,
concrete adapter imports in Application, adapter construction in Presentation,
business-module imports in Kernel, missing port implementations, unexpected
top-level catalogues, and module dependency cycles.

`Identity` is deliberately not classified as a bounded context. It owns
authentication flows, credentials, connection metadata, session adapters, and
the external user-directory integration, but no business invariants or domain
model. Its `AccountId` is consequently an application query-result type rather
than a one-class Domain layer. Identity remains a separate technical feature
module so authentication concerns do not become generic Kernel host code.

HTTP response types belong exclusively to `Kernel/Presentation/Http`.
`Shared` is reserved for genuinely shared domain concepts and infrastructure;
it has no Presentation layer.

### Shared HTTP transport

`Shared/Infrastructure/Http` owns the reusable, stateless HTTP/JSON mechanism:
building requests, applying the base URL, reading status and content, decoding
JSON, and reporting transport failures without user-facing wording. It is
shared infrastructure rather than a separate technical module because it has
no lifecycle or application boundary of its own; it is a small adapter utility
consumed by module infrastructure and wired by `Bootstrap` at the composition
boundary.

Provider semantics remain local. Jira endpoints, response field names,
Atlassian Document Format, pagination, and translation of Jira error payloads
to application messages belong to the `Identity`, `Reporting`, and
`TimeTracking` infrastructure adapters. `Identity` and `TimeTracking` retain
module-local `AtlassianTransport` and `JiraTransport` interfaces as test seams;
Reporting injects the shared `JsonHttpTransport` interface directly. Adapter
tests use fakes for these interfaces and never perform network calls.

Reporting treats a selected Jira account as its own `WorklogAuthorId`. The HTTP
controllers create this value object before invoking an application handler;
the application port therefore never carries an unvalidated user-provided
string. Its allow-list accepts the opaque identifier characters currently used
by Atlassian (`A-Z`, `a-z`, digits, colon, underscore, and hyphen) and rejects
quotes, backslashes, whitespace, JQL operators, and identifiers longer than 128
bytes.

The allow-list is sufficient to prevent the selected HTTP `accountId` from
breaking out of its quoted JQL literal. `JiraReportClient` nevertheless retains
JQL escaping because the current user's account identifier comes from Jira's
own response rather than the HTTP boundary. That provider response is the
remaining trust boundary: a compromised or incompatible upstream could return
unexpected JQL characters, and the hand-written escaper depends on Jira's JQL
escaping rules remaining compatible. No other raw user value is interpolated
into this JQL: its dates are derived from the validated `ReportPeriod`, while
field names and operators are constants. Replacing string-built JQL with an
official parameterized/query-builder facility, if Jira exposes one, is a
separate infrastructure-hardening task.

## Reporting read model

`Reporting` is the read side of the application's CQRS-style split. Its
`MonthlyReport` is a derived read model assembled from worklog entries for
rendering and export; it is not an aggregate and does not protect transactional
invariants. Reporting deliberately owns its own `WorklogDate`, `IssueKey`, and
`WorklogDuration` value objects instead of sharing the similarly shaped
TimeTracking types, so both bounded contexts can evolve independently.

Monthly aggregation is expressed through typed row accumulators and a row
collection. The public read model retains exact integer second totals, natural
issue-key ordering, daily worklog grouping, and the calculated decimal day
total expected by its consumers.

Reporting has one HTTP controller per endpoint. `ReportPageController` owns
query parsing, session-derived page context, report view construction, template
rendering, and the HTML response. `ReportCsvController` owns export enablement,
the disabled-export error, query parsing, and the complete CSV response. Their
request-scoped factories resolve application handlers and view dependencies,
not additional controllers. HTTP period input is named `ReportQuery`, while
`ReportPeriod` refers exclusively to the domain value object.

### Workday and time-zone policies

`WorkdayConfig` converts `DAILY_HOURS_LIMIT` once into
`reportingDaySeconds` and resolves `APP_TIMEZONE` (default:
`Europe/Warsaw`). Bootstrap injects that reporting-day value into report
generation and a `ReportTimeZone` into all domain objects performing calendar
operations. Reporting Domain contains no environment-specific time-zone
literal.

The configurable reporting day is deliberately separate from Jira's time
notation. In Jira input and payloads, `1d` is always 8 hours and `1w` is always
5 Jira days, regardless of `DAILY_HOURS_LIMIT`. `JiraTimeFormat` names these as
protocol constants and is their single source of truth. It supplies injected
`WorkTimeUnits` to `TimeAmount` and formats durations in the Jira adapter; the
domain value object itself contains no hard-coded day or week length.

## TimeTracking write context

`TimeTracking` is a thin write context, not an aggregate-based domain model.
It validates command values and sends add, update, and delete operations through
the application-level `WorklogGateway`; it does not load persistent state and
does not claim to protect cross-operation invariants. The Jira implementation
remains an infrastructure adapter named `JiraWorklogRepository`, where the
provider-specific name is accurate.

HTTP controllers pass primitive boundary values in add, update, and delete
commands. Application handlers map each complete command to the context's value
objects before invoking the gateway and translate `InvalidWorklog` into the
application error type without changing its user-visible message. The
controller receives a `SavedWorklog` result for redirect construction and does
not depend on `TimeTracking/Domain/Model`. There is no marker command interface:
commands are explicit use-case inputs and have no polymorphic dispatcher.

`NewWorklog` and `Worklog` remain separate domain payload types passed to the
gateway intentionally. Creation has no external worklog identifier, while
update requires one. Merging them behind an optional identifier would make
invalid operation states representable without providing aggregate behavior or
additional business rules.

## Access authorization

Access authorization belongs to `Identity/Presentation/Http`. Its
`Authorization` service resolves the request-scoped connection and owns both
unauthenticated outcomes: rendering `auth/login.html.twig` with status 401 for
interactive authentication, and raising `ApplicationRuntimeException` with a
configuration-related message for individual mode. Kernel's `Dispatcher`
receives only a response-producing closure from the composition root, so the
technical host has no dependency on any business module.

## Request-scoped connections

`Identity` owns authentication only. It exposes a provider-neutral `Connection` with
the authentication mode, authentication state, credentials, cloud identifier,
site URL, and API base URL. It neither imports types from `Reporting` or
`TimeTracking` nor constructs their adapters. OAuth token refresh remains an
Identity infrastructure concern and happens while resolving the connection.

`Bootstrap` resolves that connection once per request and is the only place
that combines it with the concrete Jira adapters used by `Reporting` and
`TimeTracking`. A single base Symfony HTTP client is created for the request;
an authenticated scoped client derived from it is shared by all adapters built
for the resolved credentials. This preserves request-scoped session semantics
without turning `Identity` into a composition root.

Run all checks with:

```bash
composer php:all
```
