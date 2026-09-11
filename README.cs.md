# Time Tracker

[English](README.md) | [Polski](README.pl.md) | [Deutsch](README.de.md) | **Čeština** | [Slovenčina](README.sk.md) | [Français](README.fr.md)

## Spuštění

```bash
docker compose up --build -d
```

Aplikace bude dostupná na adrese <http://localhost:81>.

## Nastavení Atlassian

Aplikace podporuje dva režimy:

- `individual` používá osobní API token
- `company` používá OAuth 2.0 (3LO) a vyžaduje registrovanou aplikaci Atlassian

### Režim `individual`

Z bezpečnostních důvodů přijímá tento režim požadavky pouze přes `localhost` nebo
IP adresu zpětné smyčky. Požadavky přes název počítače, LAN adresu nebo veřejnou
adresu obdrží odpověď HTTP 403.

V tomto režimu se aplikace připojuje k Jiře přímo pomocí osobního API tokenu.
Vygenerujte token v nastavení zabezpečení účtu Atlassian a poté vyplňte `.env`:

```dotenv
JIRA_URL=https://vase-firma.atlassian.net
ATLASSIAN_ACCOUNT_TYPE=individual
ATLASSIAN_EMAIL=vas.email@firma.cz
ATLASSIAN_API_TOKEN=...
```

`ATLASSIAN_EMAIL` musí být e-mailová adresa účtu Atlassian a `ATLASSIAN_API_TOKEN`
token vygenerovaný pro stejný účet. V tomto režimu nenastavujete `ATLASSIAN_CLIENT_ID`,
`ATLASSIAN_CLIENT_SECRET` ani `ATLASSIAN_REDIRECT_URI`.

### Režim `company`

Tento režim vyžaduje vytvoření aplikace OAuth 2.0 (3LO) v Atlassian Developer Console.
Nastavte callback na `http://localhost:81/oauth/callback`, přidejte oprávnění
`read:jira-work`, `write:jira-work`, `read:jira-user` a `offline_access` a vyplňte `.env`:

```dotenv
JIRA_URL=https://vase-firma.atlassian.net
ATLASSIAN_ACCOUNT_TYPE=company
ATLASSIAN_CLIENT_ID=...
ATLASSIAN_CLIENT_SECRET=...
ATLASSIAN_REDIRECT_URI=http://localhost:81/oauth/callback
SESSION_ENCRYPTION_KEY=...
```

Vygenerujte `SESSION_ENCRYPTION_KEY` jednou a zachovejte jej mezi nasazeními:

```bash
docker compose run --rm php php -r 'echo sodium_bin2base64(random_bytes(32), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), PHP_EOL;'
```

Klíč šifruje a ověřuje přístupové a obnovovací tokeny OAuth před uložením relace PHP.
Jeho změna zneplatní existující relace OAuth.

Po spuštění aplikace přesměruje uživatele k přihlášení Atlassian a načítá data jeho
jménem. `ATLASSIAN_ACCOUNT_TYPE=company` zapíná firemní tok, zatímco
`ATLASSIAN_ACCOUNT_TYPE=individual` používá přímý tok s osobním tokenem.

### Osobní token Atlassian

V režimu `individual` vložte API token do `ATLASSIAN_API_TOKEN`.
V režimu `company` se tento token nepoužívá.

## Composer

Composer běží uvnitř PHP kontejneru, například:

```bash
docker compose exec php composer --version
docker compose exec php composer install
```

## Cache kontejneru

Při `LOG_LEVEL=debug` používá každý požadavek kontrolu zdrojů Symfony `ConfigCache`.
Změny souboru `config/services.php`, PHP souborů v `src/` nebo třídy
`ContainerFactory` automaticky zneplatní a znovu sestaví zkompilovanou cache kontejneru.

V produkci požadavky pouze načítají předem sestavenou cache a nikdy neprocházejí
zdrojové soubory. Composer ji automaticky zahřeje po `install` a `update` pomocí
skriptů `post-install-cmd` a `post-update-cmd`. Pokud nasazení znovu neinstaluje
závislosti nebo spouští Composer s `--no-scripts`, zahřejte cache explicitně po
nasazení nového kódu, než na novou verzi přesměrujete provoz:

```bash
docker compose exec --user www-data php composer cache:container:warmup
```

Zahřátí atomicky nahradí cache kontejneru, takže běžné nasazení nevyžaduje předchozí
vyčištění. Pokud je při zastavené aplikaci nutné čisté sestavení, odstraňte pouze
vygenerované soubory a zahřátí spusťte znovu:

```bash
rm -f var/cache/container/AppContainer.php var/cache/container/AppContainer.php.meta var/cache/container/AppContainer.php.meta.json
docker compose exec --user www-data php composer cache:container:warmup
```

Neodstraňujte celý adresář `var/cache`.

Aplikace je modulární monolit orientovaný na DDD s obchodními moduly `Identity`,
`Reporting` a `TimeTracking`. `Shared` je malé sdílené doménové jádro a technické
záležitosti hostitele aplikace jsou v `Kernel`. Kontrolery jsou ve vrstvě
`Presentation` každého modulu, porty v `Application` nebo `Domain` a adaptéry
Jira/Atlassian v `Infrastructure`. Mapa závislostí a automatická pravidla hranic
jsou v [ARCHITECTURE.md](ARCHITECTURE.md). Období přehledu lze zvolit ve formuláři.

`Bootstrap` je jediný produkční composition root. Závislosti směřují z `Presentation`
přes `Application` do `Domain`; infrastrukturní adaptéry implementují porty vlastněné
obchodní logikou. Doménový kód nezávisí na HTTP, Twig, Jira ani CSV.

## Testy a kontroly kvality

Kompletní povinnou kontrolu spusťte v PHP kontejneru:

```bash
docker compose exec php composer php:all
```

Pro rychlejší zpětnou vazbu spusťte jednotlivé sady:

```bash
docker compose exec php composer php:unit
docker compose exec php composer php:architecture
docker compose exec php composer php:static
docker compose exec php composer php:lint
docker compose exec php composer php:cs
```

Testy nesmí používat skutečné přihlašovací údaje Jira ani síťová volání.
Infrastrukturní testy používají falešné transporty a aplikační testy stuby portů.

## Přidání use case nebo adaptéru

Pro nový use case definujte vstupní command/query a handler ve vrstvě `Application`
příslušného modulu. Obchodní validaci umístěte do doménových value objectů, používejte
rozhraní vlastněné `Application` nebo `Domain/Port` a otestujte handler pomocí stubu
tohoto portu. HTTP kontroler má pouze mapovat požadavek, zavolat handler a vytvořit odpověď.

Adaptér implementujte pod adresářem `Infrastructure` daného modulu a ponechte tam
payloady a názvy polí dodavatele. Přidejte testy mapování nebo kontraktu a konkrétní
adaptér zapojte pouze v `src/Bootstrap.php`. Pokud má být vynucena nová povinná dvojice
port/adaptér, aktualizujte `tests/Architecture/LayerDependenciesTest.php` a dokončete
práci příkazem `composer php:all`.

## Jazyky rozhraní

Rozhraní obsahuje polské, anglické, německé, české, slovenské a francouzské překlady:

```dotenv
APP_LOCALES=pl,en,de,cs,sk,fr
APP_DEFAULT_LOCALE=pl
```

Jazyk lze změnit v navigaci stránky. Aplikace uloží volbu do cookie a jinak použije
preferenci `Accept-Language` prohlížeče.

## Varianta rozhraní

Proměnná `TEMPLATE` v `.env` vybírá rozložení stránky:

```dotenv
TEMPLATE=default
```

Hodnota `default` zachová standardní zobrazení. Nastavení `compact` zmenší rozestupy
a ponechá posouvání uvnitř tabulky přehledu, takže se rozhraní vejde na menší obrazovky.

## Nastavení exportu CSV

Měsíční export lze upravit proměnnými v `.env`:

```dotenv
REPORT_EXPORT_ENABLED=true
REPORT_EXPORT_COLUMNS=issue,project,summary,date,minutes,comment
REPORT_EXPORT_FILENAME={username}_{year}_{month}.csv
REPORT_EXPORT_BOM=true
REPORT_EXPORT_SUMMARY_ROW='["SOUHRN","","Celkový pracovní čas:","","{total_minutes}","{total_hours}h {total_remaining_minutes}m"]'
REPORT_EXPORT_SUMMARY_SPACER=true
DAILY_HOURS_LIMIT=7.5
APP_TIMEZONE=Europe/Warsaw
LOG_LEVEL=error
```

`REPORT_EXPORT_ENABLED=false` skryje tlačítko stažení a zablokuje exportní endpoint.
Pokud proměnná chybí nebo je nastavena na `false`, export zůstane vypnutý a ostatní
proměnné nejsou povinné. Teprve `REPORT_EXPORT_ENABLED=true` zapne validaci
`REPORT_EXPORT_COLUMNS`, `REPORT_EXPORT_FILENAME`, `REPORT_EXPORT_BOM`,
`REPORT_EXPORT_SUMMARY_ROW` a `REPORT_EXPORT_SUMMARY_SPACER`.

Aplikace načítá konfiguraci přímo z `.env`. Volitelný soubor `.env.local` se načte
poté a přepíše stejnojmenné hodnoty. Oba soubory jsou spolu s kódem připojeny do
kontejneru, takže změny nevyžadují nový build ani systémové proměnné prostředí.

Nezachycené výjimky včetně chyb při startu aplikace se zapisují se stack trace do
`var/log/app.log`. Nativní fatální chyby PHP se navíc zapisují do
`var/log/php-error.log`. Podrobnosti chyb se nikdy nezobrazují v HTTP odpovědích;
uživatel dostane obecnou HTML stránku nebo chybu JSON. `LOG_LEVEL` určuje minimální
úroveň logování od `debug` po `emergency`; výchozí je `error`.

Dostupné sloupce jsou `issue`, `project`, `summary`, `date`, `minutes`, `hours`,
`time_spent`, `comment`, `url` a `worklog_id`. Maska názvu souboru podporuje
`{username}`, `{year}` a `{month}`. Buňky souhrnného řádku mohou používat
`{total_minutes}`, `{total_hours}`, `{total_remaining_minutes}` a
`{total_decimal_hours}`. Souhrnný řádek je pole JSON; prázdné pole `[]` jej vypne.

Vyhledávací pole nad tabulkou hledá úkoly podle klíče nebo názvu bez obnovení stránky.
Kliknutí na výsledek nebo buňku dne otevře formulář, který zapíše pracovní záznam
přímo do Jiry a aktualizuje přehled. Pokud buňka již obsahuje čas, lze vybrat konkrétní
pracovní záznam a upravit jeho čas nebo komentář.

## Licence

Projekt je dostupný pod [licencí MIT](LICENSE).

## Zastavení

```bash
docker compose down
```
