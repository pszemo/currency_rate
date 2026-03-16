# currency_rate

Moduł PrestaShop 9 wyświetlający aktualne i historyczne kursy walut NBP.

## Funkcjonalności

- Tabela z historycznymi kursami walut z ostatnich 30 dni (strona FO z paginacją i sortowaniem)
- Przelicznik cen produktów na inne waluty na karcie produktu
- Pobieranie kursów z publicznego API NBP
- Cachowanie danych (Memcached / inny backend PS)
- Panel diagnostyczny cache w BO
- Automatyczna aktualizacja przez cron
- Konfiguracja aktywnych walut w BO

## Wymagania

- PrestaShop 9.0+
- PHP 8.2+
- Composer (tylko do uruchomienia testów)
- Memcached (opcjonalnie)

Jeśli nie masz środowiska developerskiego, możesz skorzystać z gotowej konfiguracji Docker z Memcached:
[https://github.com/pszemo/docker-ps-memcache](https://github.com/pszemo/docker-ps-memcache)

## Instalacja

### Opcja A — przez panel BO

1. Pobierz archiwum ZIP z zakładki [Releases](https://github.com/pszemo/currency_rate/releases/download/1/currency_rate.zip)
2. W panelu administracyjnym przejdź do **Moduły → Zainstaluj z pliku**
3. Wgraj pobrany plik ZIP

### Opcja B — przez git clone

```bash
git clone https://github.com/pszemo/currency_rate \
  /ścieżka/do/prestashop/modules/currency_rate
```

Następnie zainstaluj moduł przez BO → **Moduły** → znajdź **Currency Rate** → **Zainstaluj**.


## Konfiguracja

### 1. Włącz cache (zalecane)

W panelu BO przejdź do **Zaawansowane → Wydajność**:

1. **Użyj pamięci podręcznej** → `TAK`
2. **System buforowania** → `CacheMemcached`
3. **Dodaj serwer** → adres: `memcached`, port: `11211`, waga: `1`

### 2. Pobierz kursy walut

W konfiguracji modułu kliknij **Pobierz teraz (30 dni)** — jednorazowe pobranie kursów NBP z ostatnich 30 dni.

### 3. Skonfiguruj cron (opcjonalnie)

URL crona z tokenem jest widoczny w panelu konfiguracji modułu. Dodaj do crontaba serwera:

```
0 6 * * * curl -s "https://twojadomena.pl/module/currency_rate/cron?token=TWÓJ_TOKEN" >> /var/log/currency_rate_cron.log 2>&1
```

Token jest generowany automatycznie podczas instalacji modułu i widoczny w panelu BO.

## Użyte technologie

- PHP 8.2+ / PrestaShop 9
- Symfony HttpClient (pobieranie danych z API NBP)
- PrestaShop Cache (Memcached)
- Smarty (szablony FO/BO)

## Możliwe problemy
### Po instalacji — zregeneruj autoloader

```bash
docker exec -it prestashop_apache composer install \
  -d /var/www/html/modules/currency_rate --no-dev
```

> Nazwa kontenera może się różnić — sprawdź przez `docker ps`.

## Możliwe optymalizacje i alternatywne podejścia
 
### Cache
Zamiast Memcached można użyć **Redis** — oferuje persistence danych między restartami i bardziej rozbudowane struktury danych. PrestaShop nie obsługuje Redisa natywnie, ale można go podpiąć przez własną implementację `Cache`.
 
### Pobieranie kursów
Aktualnie kursy są pobierane przez cron raz dziennie. Alternatywne podejścia:
- **Queue/worker** (np. Symfony Messenger) — bardziej niezawodne niż cron, z obsługą retry przy błędach API
- **Lazy loading** — pobieranie kursów dopiero przy pierwszym żądaniu danego dnia zamiast przez scheduled job
 
### Źródło danych
`NbpApiClient` implementuje konkretne API NBP. Bardziej elastyczne podejście to wprowadzenie interfejsu `ExchangeRateProviderInterface` — pozwoliłoby na łatwą podmianę źródła danych (np. ECB, Fixer.io) bez zmian w serwisie.
 
### Baza danych
Tabela `currency_rate` przy dużej liczbie walut i długim oknie historycznym może urosnąć. Możliwe optymalizacje:
- indeks złożony na `(date DESC, currency_code)` dla szybszych zapytań historycznych
- automatyczne usuwanie rekordów starszych niż X dni przez scheduled cleanup