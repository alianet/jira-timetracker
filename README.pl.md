# Time Tracker

[English](README.md) | **Polski** | [Deutsch](README.de.md) | [Čeština](README.cs.md) | [Slovenčina](README.sk.md) | [Français](README.fr.md)

## Uruchomienie

```bash
docker compose up --build -d
```

Strona będzie dostępna pod adresem <http://localhost:81>.

## Konfiguracja Atlassian

Aplikacja obsługuje dwa tryby:
- `individual` używa osobistego tokenu API
- `company` używa OAuth 2.0 (3LO) i wymaga zarejestrowanej aplikacji Atlassian

### Tryb `individual`

Ze względów bezpieczeństwa ten tryb przyjmuje żądania wyłącznie przez
`localhost` lub adres pętli zwrotnej. Żądania kierowane przez nazwę komputera,
adres sieci LAN lub adres publiczny otrzymują odpowiedź HTTP 403.

W tym trybie aplikacja łączy się z Jirą bezpośrednio za pomocą osobistego tokenu API.
W Atlassian wygeneruj token w ustawieniach konta, w sekcji bezpieczeństwa i
tokenów API, a następnie uzupełnij `.env`:

```dotenv
JIRA_URL=https://twoja-firma.atlassian.net
ATLASSIAN_ACCOUNT_TYPE=individual
ATLASSIAN_EMAIL=twoj.email@firma.pl
ATLASSIAN_API_TOKEN=...
```

`ATLASSIAN_EMAIL` musi być adresem e-mail konta Atlassian, a `ATLASSIAN_API_TOKEN`
to token wygenerowany dla tego samego konta. W tym trybie nie konfigurujesz
`ATLASSIAN_CLIENT_ID`, `ATLASSIAN_CLIENT_SECRET` ani `ATLASSIAN_REDIRECT_URI`.

### Tryb `company`

W tym trybie wymagane jest utworzenie aplikacji OAuth 2.0 (3LO) w konsoli
deweloperskiej Atlassian. Ustaw callback na `http://localhost:81/oauth/callback`,
dodaj zakresy `read:jira-work`, `write:jira-work`, `read:jira-user` oraz
`offline_access`, a następnie uzupełnij `.env`:

```dotenv
JIRA_URL=https://twoja-firma.atlassian.net
ATLASSIAN_ACCOUNT_TYPE=company
ATLASSIAN_CLIENT_ID=...
ATLASSIAN_CLIENT_SECRET=...
ATLASSIAN_REDIRECT_URI=http://localhost:81/oauth/callback
SESSION_ENCRYPTION_KEY=...
```

Wygeneruj `SESSION_ENCRYPTION_KEY` jeden raz i zachowaj go między wdrożeniami:

```bash
docker compose run --rm php php -r 'echo sodium_bin2base64(random_bytes(32), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), PHP_EOL;'
```

Klucz szyfruje i uwierzytelnia tokeny dostępu i odświeżania OAuth przed zapisaniem
sesji PHP na dysku. Jego zmiana unieważnia istniejące sesje OAuth.

Po uruchomieniu aplikacja przekieruje do logowania Atlassian i pobierze dane
w imieniu zalogowanego użytkownika. `ATLASSIAN_ACCOUNT_TYPE=company` przełącza
UI na tryb firmowy, a `ATLASSIAN_ACCOUNT_TYPE=individual` na bezpośrednie użycie
osobistego tokenu.

### Osobisty token Atlassian

Jeśli używasz trybu `individual`, token API wprowadzasz do
`ATLASSIAN_API_TOKEN`. Jeśli używasz trybu `company`, ten token nie jest używany.

## Composer

Composer działa w kontenerze PHP, na przykład:

```bash
docker compose exec php composer --version
docker compose exec php composer install
```

Kod aplikacji jest modularnym monolitem DDD podzielonym na moduły biznesowe
`Identity`, `Reporting` i `TimeTracking`. `Shared` zawiera mały współdzielony
kernel domenowy, a techniczna powłoka aplikacji znajduje się w `Kernel`.
Kontrolery znajdują się w warstwie
`Presentation` danego modułu, porty w `Application` lub `Domain`, a adaptery
Jira/Atlassian w `Infrastructure`. Mapa zależności i automatyczne reguły granic
znajdują się w [ARCHITECTURE.md](ARCHITECTURE.md). Okres raportu można wybrać w
formularzu na stronie.

`Bootstrap` jest jedynym produkcyjnym composition root. Zależności biegną od
`Presentation` przez `Application` do `Domain`, a adaptery infrastruktury
implementują porty należące do warstw biznesowych. Domena nie zależy od HTTP,
Twig, Jira ani CSV.

## Testy i kontrola jakości

Pełny wymagany zestaw kontroli uruchom w kontenerze PHP:

```bash
docker compose exec php composer php:all
```

Podczas pracy można uruchamiać pojedyncze zestawy:

```bash
docker compose exec php composer php:unit
docker compose exec php composer php:architecture
docker compose exec php composer php:static
docker compose exec php composer php:lint
docker compose exec php composer php:cs
```

Testy nie mogą używać prawdziwych danych logowania Jira ani wykonywać wywołań
sieciowych. Testy infrastruktury korzystają z atrap transportu, a testy warstwy
aplikacji z atrap portów.

## Dodawanie przypadku użycia lub adaptera

Nowy przypadek użycia dodaj jako komendę albo zapytanie i handler w warstwie
`Application` właściwego modułu. Walidację biznesową umieść w obiektach wartości
domeny, a zależność zewnętrzną opisz interfejsem należącym do `Application` lub
`Domain/Port`. Handler przetestuj z atrapą portu. Kontroler HTTP powinien wyłącznie
zmapować żądanie, wywołać handler i utworzyć odpowiedź.

Nowy adapter umieść w katalogu `Infrastructure` modułu i zaimplementuj nim port;
formaty dostawcy oraz nazwy jego pól nie powinny opuszczać adaptera. Dodaj testy
mapowania lub kontraktu, a konkretną implementację podłącz wyłącznie w
`src/Bootstrap.php`. Gdy nowa para port/adapter ma być obowiązkowa, uzupełnij
`tests/Architecture/LayerDependenciesTest.php`, a na końcu uruchom
`composer php:all`.

## Języki interfejsu

Interfejs zawiera tłumaczenia polskie, angielskie, niemieckie, czeskie, słowackie i francuskie:

```dotenv
APP_LOCALES=pl,en,de,cs,sk,fr
APP_DEFAULT_LOCALE=pl
```

Język można zmienić w nawigacji strony. Aplikacja zapamiętuje wybór w ciasteczku,
a przy jego braku korzysta z preferencji `Accept-Language` przeglądarki.

## Wariant interfejsu

Zmienna `TEMPLATE` w `.env` wybiera układ strony:

```dotenv
TEMPLATE=default
```

Wartość `default` zachowuje standardowy widok. Ustawienie `compact` zmniejsza
odstępy i ogranicza przewijanie do obszaru tabeli raportu, dzięki czemu interfejs
lepiej mieści się w mniejszym oknie.

## Konfiguracja eksportu CSV

Eksport miesięczny można dostosować zmiennymi w `.env`:

```dotenv
REPORT_EXPORT_ENABLED=true
REPORT_EXPORT_COLUMNS=issue,project,summary,date,minutes,comment
REPORT_EXPORT_FILENAME={username}_{year}_{month}.csv
REPORT_EXPORT_BOM=true
REPORT_EXPORT_SUMMARY_ROW='["PODSUMOWANIE","","Suma czasu pracy:","","{total_minutes}","{total_hours} godz. {total_remaining_minutes} min."]'
REPORT_EXPORT_SUMMARY_SPACER=true
DAILY_HOURS_LIMIT=7.5
APP_TIMEZONE=Europe/Warsaw
LOG_LEVEL=error
```

`REPORT_EXPORT_ENABLED=false` ukrywa przycisk pobierania i blokuje endpoint eksportu.
Jeśli ta zmienna jest pominięta albo ustawiona na `false`, eksport pozostaje
nieaktywny i pozostałe zmienne nie są wymagane. Dopiero `REPORT_EXPORT_ENABLED=true`
włącza walidację `REPORT_EXPORT_COLUMNS`, `REPORT_EXPORT_FILENAME`,
`REPORT_EXPORT_BOM`, `REPORT_EXPORT_SUMMARY_ROW` i
`REPORT_EXPORT_SUMMARY_SPACER`.

Aplikacja czyta konfigurację bezpośrednio z pliku `.env`. Opcjonalny `.env.local`
jest wczytywany później i nadpisuje wartości o tych samych nazwach. Oba pliki są
zamontowane w kontenerze razem z kodem, dlatego ich zmiana nie wymaga odtwarzania
kontenera ani ustawiania systemowych zmiennych środowiskowych.

Nieobsłużone wyjątki, także błędy podczas startu aplikacji, są zapisywane wraz ze
stosem wywołań w `var/log/app.log`. Natywne błędy krytyczne PHP są dodatkowo
zapisywane w `var/log/php-error.log`. Szczegóły błędów nie trafiają do odpowiedzi
HTTP; użytkownik otrzymuje ogólną stronę HTML albo komunikat JSON.
`LOG_LEVEL` określa minimalny poziom logowania i przyjmuje wartości od `debug` do
`emergency`; domyślnie używany jest poziom `error`.

Dostępne kolumny to: `issue`, `project`, `summary`, `date`, `minutes`, `hours`,
`time_spent`, `comment`, `url` i `worklog_id`. Maska nazwy pliku obsługuje
`{username}`, `{year}` i `{month}`. W komórkach wiersza podsumowania można użyć
`{total_minutes}`, `{total_hours}`, `{total_remaining_minutes}` oraz
`{total_decimal_hours}`. Wiersz podsumowania jest tablicą JSON; pusta tablica `[]`
całkowicie go wyłącza.

Wyszukiwarka nad tabelą znajduje zadania po numerze lub tytule bez przeładowania
strony. Kliknięcie wyniku wyszukiwania albo komórki dnia otwiera formularz, który
zapisuje worklog bezpośrednio w Jirze i odświeża raport. Jeśli komórka zawiera już czas, formularz
pozwala wybrać konkretny worklog i poprawić jego czas lub komentarz.

## Licencja

Projekt jest udostępniany na [licencji MIT](LICENSE).

## Zatrzymanie

```bash
docker compose down
```
