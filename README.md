# Database Importer - Importer Bazy Danych

Skrypt PHP do importowania plików SQL do bazy danych MySQL/MariaDB z automatycznym tworzeniem backupu przed importem.

## Funkcjonalności

- ✅ **Automatyczny backup** - Tworzy dump aktualnej bazy danych przed importem
- ✅ **Podwójne źródło konfiguracji** - Dane można podać w kodzie lub formularzu
- ✅ **Automatyczne wykrywanie plików** - Listuje dostępne pliki .sql w katalogu
- ✅ **Optymalizacja pamięci** - Przetwarzanie danych porcjami (1000 rekordów)
- ✅ **Monitoring postępu** - Pokazuje postęp importu i tworzenia backupu
- ✅ **Obsługa błędów** - Szczegółowe raportowanie błędów i statystyk
- ✅ **Bezpieczne przerywanie** - Import zatrzymuje się jeśli backup się nie powiedzie
- ✅ **Zamiana fraz** - Wyszukiwanie i podmiany tekstów w pliku SQL przed importem
- ✅ **Przetwarzanie strumieniowe** - Zamiany wykonywane linia po linii bez ładowania całego pliku

## Wymagania

- PHP 7.0 lub nowszy
- Rozszerzenie MySQLi
- Dostęp do bazy danych MySQL/MariaDB
- Uprawnienia do zapisu w katalogu skryptu

## Instalacja

1. Skopiuj plik `importer.php` do katalogu na serwerze
2. Upewnij się, że katalog ma uprawnienia do zapisu (dla plików backup)
3. Umieść pliki .sql do importu w tym samym katalogu

## Konfiguracja

### Opcja 1: Konfiguracja w kodzie

Edytuj plik `importer.php` i zmień dane połączenia:

```php
$host = 'localhost';        // Host bazy danych
$user = 'twoj_uzytkownik';  // Nazwa użytkownika
$pass = 'twoje_haslo';      // Hasło
$db   = 'twoja_baza';       // Nazwa bazy danych
```

### Opcja 2: Konfiguracja przez formularz

Otwórz skrypt w przeglądarce i wypełnij formularz. Dane z formularza nadpiszą wartości z kodu.

## Użycie

1. **Otwórz skrypt w przeglądarce**
   ```
   http://twoja-domena.pl/sciezka/importer.php
   ```

2. **Wypełnij dane połączenia** (jeśli nie są ustawione w kodzie)

3. **Wybierz plik SQL** z listy rozwijanej lub wpisz ścieżkę

4. **[OPCJONALNIE] Skonfiguruj zamiany fraz** - Zobacz sekcję "Zamiana fraz" poniżej

5. **Kliknij "Rozpocznij import"**

## Zamiana fraz w pliku SQL

Nowa funkcjonalność pozwala na automatyczne wyszukiwanie i podmianę fraz w pliku SQL przed importem. Szczególnie przydatne przy migracji z środowiska deweloperskiego na produkcję.

### Jak używać:

1. **W sekcji "Zamiana fraz w pliku SQL"** dodaj pary tekstów do zamiany
2. **Pole "Znajdź"** - tekst, który ma zostać zastąpiony
3. **Pole "Zamień na"** - tekst, którym ma zostać zastąpiony
4. **Kliknij "Dodaj kolejną zamianę"** aby dodać więcej par

### Przykłady zamian:

**Migracja domeny:**
```
Znajdź: dev.testowa.pl
Zamień na: testowa.pl
```

**Zmiana protokołu:**
```
Znajdź: http://localhost:8080
Zamień na: https://testowa.pl
```

**Ścieżki do plików:**
```
Znajdź: /dev/uploads/
Zamień na: /uploads/
```

**Usunięcie prefiksu:**
```
Znajdź: dev_prefix_
Zamień na: (pozostaw puste)
```

**Prefiksy tabel:**
```
Znajdź: dev_wp_
Zamień na: wp_
```

### Zalety funkcji zamian:

- **Pamięciowo efektywna** - przetwarzanie linia po linii
- **Bezpieczna** - tworzy plik tymczasowy, nie modyfikuje oryginału
- **Szczegółowe raporty** - pokazuje ile zamian zostało wykonanych
- **Automatyczne sprzątanie** - usuwa pliki tymczasowe po imporcie
- **Monitoring postępu** - pokazuje postęp dla dużych plików

## Proces importu

1. **Walidacja** - Sprawdzenie danych połączenia i istnienia pliku
2. **Połączenie** - Nawiązanie połączenia z bazą danych
3. **Backup** - Tworzenie dumpu aktualnej bazy danych
4. **[NOWE] Przetwarzanie** - Wykonywanie zamian fraz w pliku SQL (jeśli skonfigurowane)
5. **Import** - Wykonywanie zapytań SQL z przetworzonego pliku
6. **Sprzątanie** - Usunięcie plików tymczasowych
7. **Raport** - Wyświetlenie statystyk i ewentualnych błędów

## Nazewnictwo plików

**Pliki backup:**
```
dump_mojabaza_2024-01-15_14-30-25.sql
```

**Pliki tymczasowe (automatycznie usuwane):**
```
processed_dump_2024-01-15_14-30-25.sql
```

## Optymalizacja

Skrypt jest zoptymalizowany do pracy z dużymi bazami danych:

- **Limit pamięci**: 512MB
- **Czas wykonania**: 300 sekund (5 minut)
- **Przetwarzanie porcjami**: 1000 rekordów na raz
- **Zapis strumieniowy**: Dane zapisywane bezpośrednio do pliku
- **Zamiany strumieniowe**: Przetwarzanie zamian linia po linii
- **Postęp w czasie rzeczywistym**: Aktualizacje co 10,000 linii

## Bezpieczeństwo

- ⚠️ **Zawsze rób backup** przed użyciem skryptu
- ⚠️ **Nie pozostawiaj skryptu** na serwerze produkcyjnym po użyciu
- ⚠️ **Chroń dane logowania** - usuń je z kodu po zakończeniu
- ⚠️ **Ogranicz dostęp** do katalogu ze skryptem
- ⚠️ **Testuj zamiany** - sprawdź wyniki zamian na kopii przed produkcją

## Przykłady użycia

### Migracja WordPress z dev na produkcję:
```
dev.mojstrona.pl → mojstrona.pl
http://dev.mojstrona.pl → https://mojstrona.pl
/dev/wp-content/uploads/ → /wp-content/uploads/
dev_wp_ → wp_
```

### Migracja lokalnego środowiska:
```
localhost:8080 → mojstrona.pl
http://localhost → https://mojstrona.pl
C:\xampp\htdocs\projekt\ → /home/user/public_html/
```
