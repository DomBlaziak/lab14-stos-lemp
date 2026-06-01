**Laboratorium 14 & 14D: Architektura wielokontenerowa LEMP i Docker Secrets**

Autor: **Dominik Błaziak**

**ZADANIE OBOWIĄZKOWE (Laboratorium 14)**

**Architektura i segmentacja sieciowa stacku LEMP**

Aplikacja składa się z czterech odrębnych kontenerów (mikrousług):

-> Nginx (kontener o nazwie nginx): Wydajny serwer WWW, który działa jako Reverse Proxy.

-> PHP-FPM (kontener o nazwie php): Interpreter procesów odpowiedzialny za dynamiczne wykonywanie skryptów PHP.

-> MySQL (kontener o nazwie mysql): Relacyjny silnik bazy danych do składowania danych aplikacji.

-> phpMyAdmin (kontener o nazwie phpmyadmin): Graficzny interfejs webowy służący do administracji bazą danych.  

**Uzasadnienie topologii sieciowej oraz przynależności serwera phpMyAdmin**

Zgodnie z wymaganiami projektowymi, sieć została podzielona na dwie odizolowane strefy za pomocą domyślnego sterownika bridge:  

**Sieć backend:** Służy do bezpiecznej komunikacji wewnętrznej. Przypisane są do niej kontenery php oraz db. Ich porty wewnętrzne (odpowiednio 9000 oraz 3306) zostały całkowicie odizolowane od systemu operacyjnego hosta za pomocą dyrektywy expose. Uniemożliwia to bezpośredni atak na bazę lub interpreter z zewnątrz.  

**Sieć frontend:** Służy do przyjmowania ruchu użytkowników z poziomu przeglądarki. Serwer WWW Nginx mapuje ruch z bezpiecznego portu hosta 4001 na port 80 wewnątrz kontenera, dzięki czemu aplikacja jest dostępna dla świata.  

Kontener phpmyadmin został celowo dołączony do obu sieci jednocześnie (frontend oraz backend). Wynika to z faktu, że aplikacja ta musi pełnić rolę **pomostu architektonicznego** w systemie:  

- Poprzez **sieć frontend** umożliwia administratorowi zmapowanie dedykowanego portu 6001 na port 80 kontenera, dzięki czemu uzyskujemy dostęp do interfejsu graficznego panelu w przeglądarce.  

- Poprzez jednoczesną przynależność do **sieci backend**, phpMyAdmin zyskuje wewnętrzny, bezpieczny dostęp do kontenera bazy danych (mysql) na porcie 3306. Pozwala to na pomyślną autoryzację, zarządzanie tabelami i przesyłanie kwerend SQL, bez wystawiania samej bazy danych bezpośrednio na świat.  

**Wykaz użytych poleceń wraz z wynikami działania**

**1. Uruchomienie usług w trybie odizolowanym (detach)**

Uruchomienie całego stacku kontenerów LEMP wraz z phpMyAdmin za pomocą jednego polecenia w katalogu projektu:  

```bash
     docker compose up -d
```








**2. Weryfikacja statusu uruchomionych kontenerów**

Kontrola stanu procesów, nazewnictwa oraz zmapowanych portów w celu upewnienia się, że system działa stabilnie:

```bash
     docker compose ps
```









**3. Dowód poprawnego działania stacku LEMP i bazy danych**

**Działanie strony startowej:** Po wpisaniu w przeglądarce adresu `http://localhost:4001` serwer Nginx pomyślnie przetworzył zapytanie, odnalazł domyślną stronę i przekazał ją do wykonania kontenerowi PHP-FPM, który poprawnie wyrenderował dynamiczny plik index.php.  

**Inicjalizacja testowej bazy danych:** Po zalogowaniu się do panelu graficznego phpMyAdmin pod adresem `http://localhost:6001` pomyślnie utworzono nową testową bazę danych o nazwie testowa_baza_dominik, co udowadnia poprawność komunikacji oraz prawidłowe działanie trwałego wolumenu danych.

---

**ZADANIE NIEOBOWIĄZKOWE (Laboratorium 14D)**

**Bezpieczeństwo danych wrażliwych za pomocą mechanizmu Docker Secrets**

Tradycyjne podejście polegające na przekazywaniu haseł w sekcji environment pliku Compose lub poprzez plik .env stwarza poważne ryzyko bezpieczeństwa. Hasła są wtedy zapisane jawnym tekstem i stają się widoczne dla każdego użytkownika, który posiada uprawnienia do wywołania polecenia docker inspect na działającym kontenerze.  

W celu całkowitej eliminacji tego zagrożenia, wdrożono natywny, bezpieczny mechanizm Docker Secrets, którego implementacja przebiegała w sposób dwuetapowy:  

**Definicja najwyższego poziomu (Top-level secrets element):** Na samym dole pliku docker-compose.yaml zadeklarowano globalną sekcję secrets. Powiązano w niej logiczne, systemowe nazwy haseł (db_root_password oraz db_password) z fizycznymi plikami tekstowymi .txt, które znajdują się w odizolowanym katalogu na dysku hosta.  

**Aktualizacja i powiązanie wewnątrz definicji usług:** Wewnątrz konfiguracji kontenerów mysql, php oraz phpmyadmin dodano atrybut secrets, przyznając im uprawnienia dostępu do zadeklarowanych zasobów wrażliwych. Usługi wykorzystują zaawansowane zmienne środowiskowe z przyrostkiem _FILE (takie jak MYSQL_ROOT_PASSWORD_FILE, MYSQL_PASSWORD_FILE oraz PMA_PASSWORD_FILE).  


Dzięki takiej architekturze, Docker odczytuje hasła i automatycznie montuje je w systemie plików kontenera jako tymczasowy punkt typu bind mount o uprawnieniach wyłącznie do odczytu (Read-Only). Pliki te są dostępne wewnątrz odizolowanego środowiska w predefiniowanej ścieżce systemowej /run/secrets/. Hasła te nigdy nie są wstrzykiwane bezpośrednio do zmiennych środowiskowych procesu roboczego kontenera, co uniemożliwia ich przypadkowy wyciek w logach aplikacji.


**Wykaz poleceń i dowód techniczny działania mechanizmu Secrets**

Zgodnie z wymaganiami laboratoryjnymi, w sprawozdaniu należy dowieść, że pliki zawierające dane wrażliwe zostały poprawnie powiązane z serwisem jako bezpieczne punkty montowania w trybie Read-Only.  

W tym celu, aby uniknąć konieczności instalowania zewnętrznych narzędzi takich jak jq, wykorzystano zaawansowane, wbudowane flagi formatowania go-template dostarczane bezpośrednio przez polecenie docker container inspect:

```bash
     docker container inspect --format='{{json .Mounts}}' mysql
```

Wynik działania tego polecenia zwracany przez terminal systemu:













Analizując strukturę punktów montowania wygenerowaną przez demona Dockera, widzimy wyraźnie dwa niezależne punkty wejścia typu bind dedykowane dla ochrony danych wrażliwych:  

-> Pierwszy z nich pobiera źródło z pliku tekstowego /Users/Dominik/lab14/.secrets/db_root_password.txt i montuje go wewnątrz kontenera bazy danych w ścieżce docelowej /run/secrets/db_root_password.  

-> Drugi punkt poprawnie montuje hasło użytkownika w ścieżce /run/secrets/db_password.  

-> Kluczowy parametr "RW": false dla obu tych wpisów jednoznacznie dowodzi, że sekrety zostały zmapowane w trybie tylko do odczytu.


**Ostateczny dowód integracji aplikacji z architekturą chmurową**

Wywołanie w przeglądarce strony pod adresem `http://localhost:4001` uruchamia skrypt index.php, który w locie sięga do bezpiecznej ścieżki /run/secrets/db_password, oczyszcza ciąg tekstowy funkcją trim() i bezbłędnie inicjuje obiekt PDO do bazy danych.

Wyświetlenie na ekranie komunikatu o treści:
`"Sukces: PHP pomyślnie połączyło się z bazą MySQL przy użyciu Docker Secrets!"`
stanowi ostateczny, aplikacyjny dowód na poprawność wdrożenia założeń bezpieczeństwa systemów rozproszonych i poprawne działanie całego środowiska.







