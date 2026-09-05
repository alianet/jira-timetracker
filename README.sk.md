# Time Tracker

[English](README.md) | [Polski](README.pl.md) | [Deutsch](README.de.md) | [Čeština](README.cs.md) | **Slovenčina** | [Français](README.fr.md)

## Spustenie

```bash
docker compose up --build -d
```

Aplikácia bude dostupná na adrese <http://localhost:81>.

## Nastavenie Atlassian

Aplikácia podporuje dva režimy:

- `individual` používa osobný API token
- `company` používa OAuth 2.0 (3LO) a vyžaduje registrovanú aplikáciu Atlassian

### Režim `individual`

Z bezpečnostných dôvodov prijíma tento režim požiadavky iba cez `localhost` alebo
IP adresu spätnej slučky. Požiadavky cez názov počítača, LAN adresu alebo verejnú
adresu dostanú odpoveď HTTP 403.

V tomto režime sa aplikácia pripája k Jire priamo pomocou osobného API tokenu.
Vygenerujte token v nastaveniach zabezpečenia účtu Atlassian a potom vyplňte `.env`:

```dotenv
JIRA_URL=https://vasa-firma.atlassian.net
ATLASSIAN_ACCOUNT_TYPE=individual
ATLASSIAN_EMAIL=vas.email@firma.sk
ATLASSIAN_API_TOKEN=...
```

`ATLASSIAN_EMAIL` musí byť e-mailová adresa účtu Atlassian a `ATLASSIAN_API_TOKEN`
token vygenerovaný pre rovnaký účet. V tomto režime nenastavujete `ATLASSIAN_CLIENT_ID`,
`ATLASSIAN_CLIENT_SECRET` ani `ATLASSIAN_REDIRECT_URI`.

### Režim `company`

Tento režim vyžaduje vytvorenie aplikácie OAuth 2.0 (3LO) v Atlassian Developer Console.
Nastavte callback na `http://localhost:81/oauth/callback`, pridajte oprávnenia
`read:jira-work`, `write:jira-work`, `read:jira-user` a `offline_access` a vyplňte `.env`:

```dotenv
JIRA_URL=https://vasa-firma.atlassian.net
ATLASSIAN_ACCOUNT_TYPE=company
ATLASSIAN_CLIENT_ID=...
ATLASSIAN_CLIENT_SECRET=...
ATLASSIAN_REDIRECT_URI=http://localhost:81/oauth/callback
SESSION_ENCRYPTION_KEY=...
```

Vygenerujte `SESSION_ENCRYPTION_KEY` raz a zachovajte ho medzi nasadeniami:

```bash
docker compose run --rm php php -r 'echo sodium_bin2base64(random_bytes(32), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), PHP_EOL;'
```

Kľúč šifruje a overuje prístupové a obnovovacie tokeny OAuth pred uložením relácie PHP.
Jeho zmena zneplatní existujúce relácie OAuth.

Po spustení aplikácia presmeruje používateľa na prihlásenie Atlassian a načítava údaje
v jeho mene. `ATLASSIAN_ACCOUNT_TYPE=company` zapína firemný tok, zatiaľ čo
`ATLASSIAN_ACCOUNT_TYPE=individual` používa priamy tok s osobným tokenom.

### Osobný token Atlassian

V režime `individual` vložte API token do `ATLASSIAN_API_TOKEN`.
V režime `company` sa tento token nepoužíva.

## Composer

Composer beží v PHP kontajneri, napríklad:

```bash
docker compose exec php composer --version
docker compose exec php composer install
```

Aplikácia je modulárny monolit orientovaný na DDD s obchodnými modulmi `Identity`,
`Reporting` a `TimeTracking`. `Shared` je malé zdieľané doménové jadro a technické
záležitosti hostiteľa aplikácie sú v `Kernel`. Kontroléry sú vo vrstve `Presentation`
každého modulu, porty v `Application` alebo `Domain` a adaptéry Jira/Atlassian v
`Infrastructure`. Mapa závislostí a automatické pravidlá hraníc sú v
[ARCHITECTURE.md](ARCHITECTURE.md). Obdobie prehľadu možno zvoliť vo formulári.

`Bootstrap` je jediný produkčný composition root. Závislosti smerujú z `Presentation`
cez `Application` do `Domain`; infraštruktúrne adaptéry implementujú porty vlastnené
obchodnou logikou. Doménový kód nezávisí od HTTP, Twig, Jira ani CSV.

## Testy a kontroly kvality

Kompletnú povinnú kontrolu spustite v PHP kontajneri:

```bash
docker compose exec php composer php:all
```

Pre rýchlejšiu spätnú väzbu spustite jednotlivé sady:

```bash
docker compose exec php composer php:unit
docker compose exec php composer php:architecture
docker compose exec php composer php:static
docker compose exec php composer php:lint
docker compose exec php composer php:cs
```

Testy nesmú používať skutočné prihlasovacie údaje Jira ani sieťové volania.
Infraštruktúrne testy používajú falošné transporty a aplikačné testy stuby portov.

## Pridanie use case alebo adaptéra

Pre nový use case definujte vstupný command/query a handler vo vrstve `Application`
príslušného modulu. Obchodnú validáciu umiestnite do doménových value objektov,
používajte rozhranie vlastnené `Application` alebo `Domain/Port` a otestujte handler
pomocou stubu tohto portu. HTTP kontrolér má iba mapovať požiadavku, zavolať handler
a vytvoriť odpoveď.

Adaptér implementujte v adresári `Infrastructure` daného modulu a ponechajte tam
payloady a názvy polí dodávateľa. Pridajte testy mapovania alebo kontraktu a konkrétny
adaptér zapojte iba v `src/Bootstrap.php`. Ak sa má vynucovať nová povinná dvojica
port/adaptér, aktualizujte `tests/Architecture/LayerDependenciesTest.php` a dokončite
prácu príkazom `composer php:all`.

## Jazyky rozhrania

Rozhranie obsahuje poľské, anglické, nemecké, české, slovenské a francúzske preklady:

```dotenv
APP_LOCALES=pl,en,de,cs,sk,fr
APP_DEFAULT_LOCALE=pl
```

Jazyk možno zmeniť v navigácii stránky. Aplikácia uloží voľbu do cookie a inak použije
preferenciu `Accept-Language` prehliadača.

## Variant rozhrania

Premenná `TEMPLATE` v `.env` vyberá rozloženie stránky:

```dotenv
TEMPLATE=default
```

Hodnota `default` zachová štandardné zobrazenie. Nastavenie `compact` zmenší rozostupy
a ponechá posúvanie vnútri tabuľky prehľadu, takže sa rozhranie zmestí na menšie obrazovky.

## Nastavenie exportu CSV

Mesačný export možno upraviť premennými v `.env`:

```dotenv
REPORT_EXPORT_ENABLED=true
REPORT_EXPORT_COLUMNS=issue,project,summary,date,minutes,comment
REPORT_EXPORT_FILENAME={username}_{year}_{month}.csv
REPORT_EXPORT_BOM=true
REPORT_EXPORT_SUMMARY_ROW='["SÚHRN","","Celkový pracovný čas:","","{total_minutes}","{total_hours}h {total_remaining_minutes}m"]'
REPORT_EXPORT_SUMMARY_SPACER=true
DAILY_HOURS_LIMIT=7.5
APP_TIMEZONE=Europe/Warsaw
LOG_LEVEL=error
```

`REPORT_EXPORT_ENABLED=false` skryje tlačidlo stiahnutia a zablokuje exportný endpoint.
Ak premenná chýba alebo je nastavená na `false`, export zostane vypnutý a ostatné
premenné nie sú povinné. Až `REPORT_EXPORT_ENABLED=true` zapne validáciu
`REPORT_EXPORT_COLUMNS`, `REPORT_EXPORT_FILENAME`, `REPORT_EXPORT_BOM`,
`REPORT_EXPORT_SUMMARY_ROW` a `REPORT_EXPORT_SUMMARY_SPACER`.

Aplikácia načítava konfiguráciu priamo z `.env`. Voliteľný súbor `.env.local` sa načíta
potom a prepíše hodnoty s rovnakými názvami. Oba súbory sú spolu s kódom pripojené do
kontajnera, takže zmeny nevyžadujú nový build ani systémové premenné prostredia.

Nezachytené výnimky vrátane chýb pri štarte aplikácie sa zapisujú so stack trace do
`var/log/app.log`. Natívne fatálne chyby PHP sa navyše zapisujú do
`var/log/php-error.log`. Podrobnosti chýb sa nikdy nezobrazujú v HTTP odpovediach;
používateľ dostane všeobecnú HTML stránku alebo chybu JSON. `LOG_LEVEL` určuje minimálnu
úroveň logovania od `debug` po `emergency`; predvolená je `error`.

Dostupné stĺpce sú `issue`, `project`, `summary`, `date`, `minutes`, `hours`,
`time_spent`, `comment`, `url` a `worklog_id`. Maska názvu súboru podporuje
`{username}`, `{year}` a `{month}`. Bunky súhrnného riadka môžu používať
`{total_minutes}`, `{total_hours}`, `{total_remaining_minutes}` a
`{total_decimal_hours}`. Súhrnný riadok je pole JSON; prázdne pole `[]` ho vypne.

Vyhľadávacie pole nad tabuľkou hľadá úlohy podľa kľúča alebo názvu bez obnovenia stránky.
Kliknutie na výsledok alebo bunku dňa otvorí formulár, ktorý zapíše pracovný záznam
priamo do Jiry a aktualizuje prehľad. Ak bunka už obsahuje čas, možno vybrať konkrétny
pracovný záznam a upraviť jeho čas alebo komentár.

## Licencia

Projekt je dostupný pod [licenciou MIT](LICENSE).

## Zastavenie

```bash
docker compose down
```
