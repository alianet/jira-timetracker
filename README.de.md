# Time Tracker

[English](README.md) | [Polski](README.pl.md) | **Deutsch** | [Čeština](README.cs.md) | [Slovenčina](README.sk.md) | [Français](README.fr.md)

## Starten

```bash
docker compose up --build -d
```

Die Anwendung ist unter <http://localhost:81> erreichbar.

## Atlassian-Konfiguration

Die Anwendung unterstützt zwei Modi:

- `individual` verwendet ein persönliches API-Token
- `company` verwendet OAuth 2.0 (3LO) und erfordert eine registrierte Atlassian-App

### Modus `individual`

Aus Sicherheitsgründen akzeptiert dieser Modus Anfragen nur über `localhost` oder
eine Loopback-IP-Adresse. Anfragen über einen Rechnernamen, eine LAN-Adresse oder
eine öffentliche Adresse erhalten die HTTP-Antwort 403.

In diesem Modus verbindet sich die Anwendung direkt über ein persönliches API-Token
mit Jira. Erzeugen Sie das Token in den Sicherheitseinstellungen Ihres Atlassian-Kontos
und ergänzen Sie anschließend `.env`:

```dotenv
JIRA_URL=https://ihre-firma.atlassian.net
ATLASSIAN_ACCOUNT_TYPE=individual
ATLASSIAN_EMAIL=ihre.email@firma.de
ATLASSIAN_API_TOKEN=...
```

`ATLASSIAN_EMAIL` muss die E-Mail-Adresse des Atlassian-Kontos sein und
`ATLASSIAN_API_TOKEN` das für dasselbe Konto erzeugte Token. In diesem Modus werden
`ATLASSIAN_CLIENT_ID`, `ATLASSIAN_CLIENT_SECRET` und `ATLASSIAN_REDIRECT_URI` nicht konfiguriert.

### Modus `company`

Dieser Modus erfordert eine OAuth-2.0-App (3LO) in der Atlassian Developer Console.
Setzen Sie den Callback auf `http://localhost:81/oauth/callback`, fügen Sie die Scopes
`read:jira-work`, `write:jira-work`, `read:jira-user` und `offline_access` hinzu und
ergänzen Sie `.env`:

```dotenv
JIRA_URL=https://ihre-firma.atlassian.net
ATLASSIAN_ACCOUNT_TYPE=company
ATLASSIAN_CLIENT_ID=...
ATLASSIAN_CLIENT_SECRET=...
ATLASSIAN_REDIRECT_URI=http://localhost:81/oauth/callback
SESSION_ENCRYPTION_KEY=...
```

Erzeugen Sie `SESSION_ENCRYPTION_KEY` einmalig und behalten Sie ihn zwischen Deployments bei:

```bash
docker compose run --rm php php -r 'echo sodium_bin2base64(random_bytes(32), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), PHP_EOL;'
```

Der Schlüssel verschlüsselt und authentifiziert OAuth-Access- und Refresh-Tokens,
bevor PHP die Sitzung speichert. Eine Änderung macht bestehende OAuth-Sitzungen ungültig.

Nach dem Start leitet die Anwendung zur Atlassian-Anmeldung weiter und lädt Daten im
Namen des angemeldeten Benutzers. `ATLASSIAN_ACCOUNT_TYPE=company` aktiviert den
Firmenablauf, `ATLASSIAN_ACCOUNT_TYPE=individual` den direkten persönlichen Token-Ablauf.

### Persönliches Atlassian-Token

Im Modus `individual` tragen Sie das API-Token in `ATLASSIAN_API_TOKEN` ein.
Im Modus `company` wird dieses Token nicht verwendet.

## Composer

Composer wird im PHP-Container ausgeführt, zum Beispiel:

```bash
docker compose exec php composer --version
docker compose exec php composer install
```

Die Anwendung ist ein DDD-orientierter modularer Monolith mit `Identity`, `Reporting`
und `TimeTracking` als Geschäftsmodulen. `Shared` ist ein kleiner gemeinsam genutzter
Domain-Kernel, technische Belange des Anwendungshosts liegen in `Kernel`. Controller
liegen in der jeweiligen `Presentation`-Schicht, Ports in `Application` oder `Domain`
und Jira-/Atlassian-Adapter in `Infrastructure`. Die Abhängigkeitskarte und automatischen
Grenzregeln stehen in [ARCHITECTURE.md](ARCHITECTURE.md). Der Berichtszeitraum kann im
Formular auf der Seite gewählt werden.

`Bootstrap` ist der einzige Composition Root in der Produktion. Abhängigkeiten zeigen
von `Presentation` über `Application` zu `Domain`; Infrastrukturadapter implementieren
Ports der Geschäftslogik. Der Domain-Code hängt nicht von HTTP, Twig, Jira oder CSV ab.

## Tests und Qualitätsprüfungen

Führen Sie die vollständige Prüfung im PHP-Container aus:

```bash
docker compose exec php composer php:all
```

Für kürzere Rückmeldungszyklen können einzelne Prüfungen gestartet werden:

```bash
docker compose exec php composer php:unit
docker compose exec php composer php:architecture
docker compose exec php composer php:static
docker compose exec php composer php:lint
docker compose exec php composer php:cs
```

Tests dürfen keine echten Jira-Zugangsdaten oder Netzwerkaufrufe verwenden.
Infrastrukturtests verwenden Fake-Transporte, Anwendungstests Port-Stubs.

## Use Case oder Adapter hinzufügen

Definieren Sie für einen Use Case dessen Eingabe-Command beziehungsweise Query und
einen Handler in der `Application`-Schicht des zuständigen Moduls. Geschäftsvalidierung
gehört in Domain-Value-Objects. Verwenden Sie eine Schnittstelle aus `Application` oder
`Domain/Port` und testen Sie den Handler mit einem Stub dieses Ports. Der HTTP-Controller
soll nur die Anfrage abbilden, den Handler aufrufen und die Antwort erzeugen.

Ein Adapter implementiert diesen Port im Verzeichnis `Infrastructure` des Moduls.
Vendor-Payloads und Feldnamen bleiben dort. Ergänzen Sie Mapping- oder Vertragstests
und verdrahten Sie den konkreten Adapter ausschließlich in `src/Bootstrap.php`.
Aktualisieren Sie `tests/Architecture/LayerDependenciesTest.php`, wenn ein neues
verpflichtendes Port-/Adapter-Paar geprüft werden soll, und führen Sie abschließend
`composer php:all` aus.

## Oberflächensprachen

Die Oberfläche enthält polnische, englische, deutsche, tschechische, slowakische und französische Übersetzungen:

```dotenv
APP_LOCALES=pl,en,de,cs,sk,fr
APP_DEFAULT_LOCALE=pl
```

Die Sprache kann in der Seitennavigation gewechselt werden. Die Anwendung speichert
die Auswahl in einem Cookie und verwendet andernfalls die `Accept-Language`-Einstellung des Browsers.

## Oberflächenvariante

Die Variable `TEMPLATE` in `.env` wählt das Seitenlayout:

```dotenv
TEMPLATE=default
```

`default` behält die Standardansicht bei. `compact` reduziert Abstände und begrenzt
das Scrollen auf die Berichtstabelle, damit die Oberfläche auf kleinere Bildschirme passt.

## CSV-Export konfigurieren

Der monatliche Export wird über Variablen in `.env` angepasst:

```dotenv
REPORT_EXPORT_ENABLED=true
REPORT_EXPORT_COLUMNS=issue,project,summary,date,minutes,comment
REPORT_EXPORT_FILENAME={username}_{year}_{month}.csv
REPORT_EXPORT_BOM=true
REPORT_EXPORT_SUMMARY_ROW='["SUMME","","Arbeitszeit gesamt:","","{total_minutes}","{total_hours}h {total_remaining_minutes}m"]'
REPORT_EXPORT_SUMMARY_SPACER=true
DAILY_HOURS_LIMIT=7.5
APP_TIMEZONE=Europe/Warsaw
LOG_LEVEL=error
```

`REPORT_EXPORT_ENABLED=false` blendet den Download-Button aus und sperrt den
Export-Endpunkt. Fehlt die Variable oder steht sie auf `false`, bleibt der Export
deaktiviert und die übrigen Variablen sind nicht erforderlich. Erst
`REPORT_EXPORT_ENABLED=true` aktiviert die Validierung von `REPORT_EXPORT_COLUMNS`,
`REPORT_EXPORT_FILENAME`, `REPORT_EXPORT_BOM`, `REPORT_EXPORT_SUMMARY_ROW` und
`REPORT_EXPORT_SUMMARY_SPACER`.

Die Anwendung liest die Konfiguration direkt aus `.env`. Eine optionale `.env.local`
wird anschließend geladen und überschreibt gleichnamige Werte. Beide Dateien werden
zusammen mit dem Code in den Container eingebunden. Änderungen erfordern daher weder
einen neuen Container-Build noch systemweite Umgebungsvariablen.

Unbehandelte Ausnahmen einschließlich Fehlern beim Anwendungsstart werden mit Stacktrace
in `var/log/app.log` geschrieben. Native schwerwiegende PHP-Fehler werden zusätzlich in
`var/log/php-error.log` gespeichert. Fehlerdetails erscheinen nie in HTTP-Antworten;
Benutzer erhalten eine allgemeine HTML-Seite oder einen JSON-Fehler. `LOG_LEVEL` legt
die minimale Protokollstufe von `debug` bis `emergency` fest; Standard ist `error`.

Verfügbare Spalten sind `issue`, `project`, `summary`, `date`, `minutes`, `hours`,
`time_spent`, `comment`, `url` und `worklog_id`. Die Dateinamenmaske unterstützt
`{username}`, `{year}` und `{month}`. In der Summenzeile stehen `{total_minutes}`,
`{total_hours}`, `{total_remaining_minutes}` und `{total_decimal_hours}` zur Verfügung.
Die Summenzeile ist ein JSON-Array; ein leeres Array `[]` deaktiviert sie vollständig.

Das Suchfeld über der Tabelle findet Vorgänge nach Schlüssel oder Titel ohne Neuladen
der Seite. Ein Klick auf ein Ergebnis oder eine Tageszelle öffnet das Formular, speichert
den Zeiteintrag direkt in Jira und aktualisiert den Bericht. Enthält eine Zelle bereits
Zeit, kann ein bestimmter Zeiteintrag gewählt und dessen Zeit oder Kommentar bearbeitet werden.

## Lizenz

Dieses Projekt ist unter der [MIT-Lizenz](LICENSE) verfügbar.

## Beenden

```bash
docker compose down
```
