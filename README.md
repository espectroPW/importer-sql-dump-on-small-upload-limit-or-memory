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

4. **Kliknij "Rozpocznij import"**

## Proces importu

1. **Walidacja** - Sprawdzenie danych połączenia i istnienia pliku
2. **Połączenie** - Nawiązanie połączenia z bazą danych
3. **Backup** - Tworzenie dumpu aktualnej bazy danych
4. **Import** - Wykonywanie zapytań SQL z wybranego pliku
5. **Raport** - Wyświetlenie statystyk i ewentualnych błędów

## Nazewnictwo plików backup

Pliki backup są tworzone w formacie:

Przykład: `dump_mojabaza_2024-01-15_14-30-25.sql`

## Optymalizacja

Skrypt jest zoptymalizowany do pracy z dużymi bazami danych:

- **Limit pamięci**: 512MB
- **Czas wykonania**: 300 sekund (5 minut)
- **Przetwarzanie porcjami**: 1000 rekordów na raz
- **Zapis strumieniowy**: Dane zapisywane bezpośrednio do pliku

## Bezpieczeństwo

- ⚠️ **Zawsze rób backup** przed użyciem skryptu
- ⚠️ **Nie pozostawiaj skryptu** na serwerze produkcyjnym po użyciu
- ⚠️ **Chroń dane logowania** - usuń je z kodu po zakończeniu
- ⚠️ **Ogranicz dostęp** do katalogu ze skryptem
