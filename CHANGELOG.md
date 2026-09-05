# Changelog

W tym pliku dokumentowane są najważniejsze zmiany w kolejnych wersjach
aplikacji. Projekt stosuje [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-09-05

Pierwsze stabilne wydanie aplikacji Jira Time Tracker.

### Raportowanie czasu

- Miesięczny raport worklogów z Jiry prezentowany w formie macierzy zadań i dni.
- Raport własnego czasu oraz wyszukiwanie użytkowników i podgląd raportu wybranej
  osoby.
- Wybór miesiąca i roku, nawigacja między sąsiednimi miesiącami oraz szybki
  powrót do bieżącego okresu.
- Grupowanie wpisów według zadania i dnia, sumy dzienne, sumy dla zadań oraz
  łączny czas wyrażony w godzinach i dniach roboczych.
- Naturalne sortowanie kluczy zadań i bezpośrednie odnośniki do zgłoszeń w Jirze.
- Oznaczenia bieżącego dnia, weekendów, świąt i dni roboczych z brakującym
  wymaganym czasem pracy.
- Filtrowanie wierszy raportu przez wybranie dnia w nagłówku tabeli.
- Konfigurowalna długość dnia raportowego, strefa czasowa oraz krajowy lub
  regionalny kalendarz świąt.

### Obsługa worklogów

- Wyszukiwanie zgłoszeń Jiry po kluczu lub tytule bez przeładowania strony.
- Dodawanie worklogów z poziomu wyniku wyszukiwania albo komórki raportu.
- Edycja czasu i komentarza istniejących własnych worklogów.
- Usuwanie własnych worklogów po potwierdzeniu operacji.
- Podgląd historii wpisów dla własnych zadań oraz odczyt historii worklogów
  podczas przeglądania raportu innego użytkownika.
- Obsługa notacji czasu Jiry (`w`, `d`, `h`, `m`) i godzin dziesiętnych
  normalizowanych przez formularz.
- Zestaw gotowych tagów ułatwiających uzupełnianie komentarzy worklogów.

### Eksport CSV

- Opcjonalny eksport miesięcznego raportu do pliku CSV.
- Konfigurowalny zestaw i kolejność kolumn, maska nazwy pliku oraz znacznik BOM.
- Opcjonalny, konfigurowalny wiersz podsumowania i odstęp przed podsumowaniem.
- Kolumny obejmujące dane zadania, datę, czas, komentarz, adres zgłoszenia oraz
  identyfikator workloga.
- Możliwość całkowitego wyłączenia eksportu wraz z ukryciem przycisku i blokadą
  endpointu.

### Uwierzytelnianie Atlassian

- Tryb indywidualny wykorzystujący adres e-mail i osobisty token API Atlassian.
- Ograniczenie trybu indywidualnego do połączeń przez `localhost` lub adres
  pętli zwrotnej.
- Tryb firmowy wykorzystujący OAuth 2.0 (3LO), w tym odświeżanie tokenów i
  wylogowanie.
- Szyfrowanie i uwierzytelnianie tokenów OAuth zapisywanych w sesji PHP za
  pomocą konfigurowalnego klucza.
- Współdzielenie jednego uwierzytelnionego klienta HTTP przez adaptery Jiry w
  obrębie żądania.

### Interfejs użytkownika

- Responsywny interfejs HTML oparty na Twig z wariantami `default` i `compact`.
- Interfejs w języku polskim, angielskim, niemieckim, czeskim, słowackim i
  francuskim.
- Wybór języka zapamiętywany w ciasteczku oraz automatyczne dopasowanie do
  nagłówka `Accept-Language`.
- Komunikaty powodzenia, błędów formularzy, błędów integracji i pustych wyników
  wyszukiwania.

### Konfiguracja i eksploatacja

- Uruchamianie kompletnego środowiska aplikacji przez Docker Compose.
- Konfiguracja z plików `.env` i `.env.local`, z możliwością lokalnego
  nadpisywania ustawień bez przebudowy obrazu.
- Konfigurowalny poziom logowania oraz osobne logi aplikacji i krytycznych
  błędów PHP.
- Bezpieczne ogólne odpowiedzi HTML i JSON bez ujawniania szczegółów wyjątków.

### Bezpieczeństwo i jakość

- Ochrona operacji zapisu i wylogowania tokenem CSRF.
- Walidacja identyfikatorów użytkowników przed użyciem w zapytaniach JQL.
- Modułowa architektura z rozdzielonymi kontekstami raportowania i zapisu czasu
  oraz izolacją integracji Atlassian/Jira.
- Automatyczne testy jednostkowe i architektoniczne, analiza statyczna, kontrola
  stylu oraz sprawdzanie składni uruchamiane wspólnym poleceniem
  `composer php:all`.
