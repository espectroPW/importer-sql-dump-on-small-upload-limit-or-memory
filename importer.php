<?php
// Pokazuj błędy
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Zwiększ limit pamięci i czasu wykonania
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

// Dane logowania do bazy (można zmienić tutaj lub w formularzu)
$host = '';
$user = '';
$pass = '';
$db   = '';

// Funkcja do przetwarzania pliku SQL (zamiana fraz)
function processSQLFile($source_file, $find_replace_pairs) {
    if (empty($find_replace_pairs)) {
        return $source_file; // Jeśli nie ma żadnych zamian, zwróć oryginalny plik
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $processed_file = 'processed_' . basename($source_file, '.sql') . '_' . $timestamp . '.sql';
    
    echo "<p>Przetwarzanie pliku SQL - wykonywanie zamian...</p>";
    
    // Otwórz plik źródłowy do odczytu
    $source_handle = fopen($source_file, 'r');
    if (!$source_handle) {
        echo "<p style='color: red;'><strong>Błąd podczas otwierania pliku SQL do odczytu!</strong></p>";
        return false;
    }
    
    // Otwórz plik docelowy do zapisu
    $target_handle = fopen($processed_file, 'w');
    if (!$target_handle) {
        echo "<p style='color: red;'><strong>Błąd podczas tworzenia przetworzonego pliku!</strong></p>";
        fclose($source_handle);
        return false;
    }
    
    $replacements_made = 0;
    $lines_processed = 0;
    $replacement_counts = array();
    
    // Inicjalizuj liczniki dla każdej pary zamian
    foreach ($find_replace_pairs as $index => $pair) {
        $replacement_counts[$index] = 0;
    }
    
    // Przetwarzaj plik linia po linii
    while (($line = fgets($source_handle)) !== false) {
        $original_line = $line;
        
        // Wykonaj zamiany na aktualnej linii
        foreach ($find_replace_pairs as $index => $pair) {
            $find = $pair['find'];
            $replace = $pair['replace'];
            
            if (!empty($find)) {
                $count = 0;
                $line = str_replace($find, $replace, $line, $count);
                if ($count > 0) {
                    $replacement_counts[$index] += $count;
                    $replacements_made += $count;
                }
            }
        }
        
        // Zapisz przetworzoną linię
        fwrite($target_handle, $line);
        
        $lines_processed++;
        
        // Pokaż postęp co 10000 linii
        if ($lines_processed % 10000 == 0) {
            echo "<p>Przetworzono $lines_processed linii...</p>";
            @ob_flush(); @flush();
        }
    }
    
    fclose($source_handle);
    fclose($target_handle);
    
    // Pokaż wyniki zamian
    if ($replacements_made > 0) {
        echo "<p><strong>Wykonano zamiany:</strong></p>";
        foreach ($find_replace_pairs as $index => $pair) {
            $count = $replacement_counts[$index];
            if ($count > 0) {
                echo "<p>• <strong>$count</strong> wystąpień: <code>" . htmlspecialchars($pair['find']) . "</code> → <code>" . htmlspecialchars($pair['replace']) . "</code></p>";
            }
        }
        echo "<p><strong>Przetworzono plik:</strong> $processed_file (łącznie $replacements_made zamian w $lines_processed liniach)</p>";
        return $processed_file;
    } else {
        // Jeśli nie było żadnych zamian, usuń przetworzony plik i użyj oryginalnego
        unlink($processed_file);
        echo "<p><strong>Brak zamian do wykonania</strong> - używam oryginalnego pliku (przetworzono $lines_processed linii)</p>";
        return $source_file;
    }
}

// Funkcja do tworzenia dumpu
function createDump($conn, $db) {
    $timestamp = date('Y-m-d_H-i-s');
    $dump_file = 'dump_' . $db . '_' . $timestamp . '.sql';
    
    echo "<p>Tworzenie dumpu bazy danych...</p>";
    
    // Otwórz plik do zapisu
    $handle = fopen($dump_file, 'w');
    if (!$handle) {
        echo "<p><strong>Błąd podczas otwierania pliku do zapisu!</strong></p>";
        return false;
    }
    
    // Nagłówek dumpu
    fwrite($handle, "-- Dump bazy danych $db\n");
    fwrite($handle, "-- Utworzony: " . date('Y-m-d H:i:s') . "\n\n");
    
    // Pobierz listę tabel
    $tables = array();
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    foreach ($tables as $table) {
        // Struktura tabeli
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch_array();
        fwrite($handle, "\n-- Struktura tabeli `$table`\n");
        fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
        fwrite($handle, $row[1] . ";\n\n");
        
        // Pobierz liczbę rekordów
        $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
        $count_row = $count_result->fetch_assoc();
        $total_rows = $count_row['count'];
        
        if ($total_rows > 0) {
            fwrite($handle, "-- Dane z tabeli `$table`\n");
            
            // Przetwarzaj dane porcjami po 1000 rekordów
            $limit = 1000;
            for ($offset = 0; $offset < $total_rows; $offset += $limit) {
                $result = $conn->query("SELECT * FROM `$table` LIMIT $limit OFFSET $offset");
                
                while ($row = $result->fetch_assoc()) {
                    fwrite($handle, "INSERT INTO `$table` VALUES (");
                    $values = array();
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $conn->real_escape_string($value) . "'";
                        }
                    }
                    fwrite($handle, implode(', ', $values) . ");\n");
                }
                
                // Pokaż postęp
                echo "<p>Przetworzono " . min($offset + $limit, $total_rows) . " / $total_rows rekordów z tabeli `$table`</p>";
                @ob_flush(); @flush();
            }
            fwrite($handle, "\n");
        }
    }
    
    fclose($handle);
    echo "<p><strong>Dump utworzony:</strong> $dump_file</p>";
    return true;
}

// Sprawdź czy formularz został wysłany
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'] ?? $host;
    $user = $_POST['user'] ?? $user;
    $pass = $_POST['pass'] ?? $pass;
    $db = $_POST['db'] ?? $db;
    $sql_file = $_POST['sql_file'] ?? '';
    
    // Pobierz pary zamian
    $find_replace_pairs = array();
    if (isset($_POST['find']) && isset($_POST['replace'])) {
        for ($i = 0; $i < count($_POST['find']); $i++) {
            $find = trim($_POST['find'][$i] ?? '');
            $replace = trim($_POST['replace'][$i] ?? '');
            if (!empty($find)) {
                $find_replace_pairs[] = array('find' => $find, 'replace' => $replace);
            }
        }
    }
    
    if (empty($user) || empty($db) || empty($sql_file)) {
        echo "<p style='color: red;'><strong>Wszystkie pola są wymagane!</strong></p>";
    } else {
        // Połącz z bazą
        $conn = new mysqli($host, $user, $pass, $db);
        if ($conn->connect_error) {
            echo "<p style='color: red;'><strong>Błąd połączenia:</strong> " . $conn->connect_error . "</p>";
        } else {
            echo "<h2>Rozpoczynam import...</h2>";
            
            // Utwórz dump przed importem
            if (!createDump($conn, $db)) {
                echo "<p style='color: red;'><strong>Przerwano import - nie udało się utworzyć dumpu!</strong></p>";
                $conn->close();
                exit;
            }
            
            // Sprawdź czy plik istnieje
            if (!file_exists($sql_file)) {
                echo "<p style='color: red;'><strong>Plik $sql_file nie istnieje!</strong></p>";
                $conn->close();
                exit;
            }
            
            // Przetwórz plik SQL (wykonaj zamiany)
            $processed_file = processSQLFile($sql_file, $find_replace_pairs);
            if ($processed_file === false) {
                echo "<p style='color: red;'><strong>Przerwano import - błąd podczas przetwarzania pliku!</strong></p>";
                $conn->close();
                exit;
            }
            
            // Otwórz przetworzony plik SQL do odczytu
            $handle = fopen($processed_file, "r");
            if (!$handle) {
                echo "<p style='color: red;'><strong>Nie udało się otworzyć przetworzonego pliku SQL.</strong></p>";
                $conn->close();
                exit;
            }
            
            $query = '';
            $counter = 0;
            $errors = 0;
            
            echo "<p>Importowanie danych...</p>";
            
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                
                // Pomijaj komentarze i puste linie
                if ($line === '' || strpos($line, '--') === 0 || strpos($line, '/*') === 0) {
                    continue;
                }
                
                $query .= $line . "\n";
                
                // Jeśli zapytanie kończy się średnikiem, wykonaj
                if (substr(rtrim($line), -1) === ';') {
                    if (!$conn->query($query)) {
                        echo "<p style='color: red;'><strong>Błąd zapytania:</strong> " . $conn->error . "<br><code>" . htmlspecialchars(substr($query, 0, 200)) . "...</code></p>";
                        $errors++;
                    } else {
                        $counter++;
                        if ($counter % 50 == 0) {
                            echo "<p>Wykonano $counter zapytań...</p>";
                            @ob_flush(); @flush();
                        }
                    }
                    $query = '';
                }
            }
            
            fclose($handle);
            
            // Usuń przetworzony plik tymczasowy jeśli jest inny niż oryginalny
            if ($processed_file !== $sql_file && file_exists($processed_file)) {
                unlink($processed_file);
                echo "<p><em>Usunięto plik tymczasowy: $processed_file</em></p>";
            }
            
            $conn->close();
            
            echo "<p><strong>Import zakończony.</strong></p>";
            echo "<p>Wykonano: $counter zapytań</p>";
            if ($errors > 0) {
                echo "<p style='color: red;'>Błędy: $errors</p>";
            }
        }
    }
}

// Pobierz listę plików SQL w katalogu
$sql_files = array();
$files = scandir('.');
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
        $sql_files[] = $file;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Importer bazy danych</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"], select { 
            width: 300px; 
            padding: 8px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
        }
        button { 
            background: #007cba; 
            color: white; 
            padding: 10px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
        }
        button:hover { background: #005a87; }
        .btn-secondary {
            background: #6c757d;
            margin-left: 10px;
        }
        .btn-secondary:hover { background: #545b62; }
        .warning { 
            background: #fff3cd; 
            border: 1px solid #ffeaa7; 
            padding: 15px; 
            border-radius: 4px; 
            margin-bottom: 20px; 
        }
        .info { 
            background: #d1ecf1; 
            border: 1px solid #bee5eb; 
            padding: 15px; 
            border-radius: 4px; 
            margin-bottom: 20px; 
        }
        .replace-section {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .replace-pair {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .replace-pair input {
            width: 200px;
        }
        .replace-pair button {
            padding: 5px 10px;
            font-size: 12px;
        }
        .examples {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h1>Importer bazy danych</h1>
    
    <div class="warning">
        <strong>⚠️ UWAGA:</strong> Ten skrypt utworzy dump aktualnej bazy danych przed importem, 
        ale upewnij się, że masz kopię zapasową przed uruchomieniem importu!
    </div>
    
    <div class="info">
        <strong>ℹ️ Informacja:</strong> Możesz zmienić dane połączenia w kodzie skryptu lub wypełnić formularz poniżej.
    </div>
    
    <form method="POST">
        <div class="form-group">
            <label for="host">Host bazy danych:</label>
            <input type="text" id="host" name="host" value="<?php echo htmlspecialchars($host); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="user">Użytkownik:</label>
            <input type="text" id="user" name="user" value="<?php echo htmlspecialchars($user); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="pass">Hasło:</label>
            <input type="password" id="pass" name="pass" value="<?php echo htmlspecialchars($pass); ?>">
        </div>
        
        <div class="form-group">
            <label for="db">Nazwa bazy danych:</label>
            <input type="text" id="db" name="db" value="<?php echo htmlspecialchars($db); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="sql_file">Plik SQL do importu:</label>
            <?php if (!empty($sql_files)): ?>
                <select id="sql_file" name="sql_file" required>
                    <option value="">-- Wybierz plik --</option>
                    <?php foreach ($sql_files as $file): ?>
                        <option value="<?php echo htmlspecialchars($file); ?>"><?php echo htmlspecialchars($file); ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="text" id="sql_file" name="sql_file" placeholder="Ścieżka do pliku .sql" required>
            <?php endif; ?>
        </div>
        
        <div class="replace-section">
            <h3>🔄 Zamiana fraz w pliku SQL (opcjonalne)</h3>
            <p>Możesz zastąpić określone frazy przed importem. Przydatne przy migracji z dev na produkcję.</p>
            
            <div id="replace-pairs">
                <div class="replace-pair">
                    <input type="text" name="find[]" placeholder="Znajdź (np. dev.testowa.pl)">
                    <span>→</span>
                    <input type="text" name="replace[]" placeholder="Zamień na (np. testowa.pl)">
                    <button type="button" onclick="removeReplacePair(this)" class="btn-secondary">Usuń</button>
                </div>
            </div>
            
            <button type="button" onclick="addReplacePair()" class="btn-secondary">Dodaj kolejną zamianę</button>
            
            <div class="examples">
                <strong>Przykłady zamian:</strong><br>
                • <code>dev.testowa.pl</code> → <code>testowa.pl</code><br>
                • <code>http://localhost:8080</code> → <code>https://testowa.pl</code><br>
                • <code>/dev/uploads/</code> → <code>/uploads/</code><br>
                • <code>dev_prefix_</code> → <code></code> (usunięcie prefiksu)
            </div>
        </div>
        
        <button type="submit">Rozpocznij import</button>
    </form>
    
    <?php if (!empty($sql_files)): ?>
        <h3>Dostępne pliki SQL w katalogu:</h3>
        <ul>
            <?php foreach ($sql_files as $file): ?>
                <li><?php echo htmlspecialchars($file); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <script>
        function addReplacePair() {
            const container = document.getElementById('replace-pairs');
            const div = document.createElement('div');
            div.className = 'replace-pair';
            div.innerHTML = `
                <input type="text" name="find[]" placeholder="Znajdź (np. dev.testowa.pl)">
                <span>→</span>
                <input type="text" name="replace[]" placeholder="Zamień na (np. testowa.pl)">
                <button type="button" onclick="removeReplacePair(this)" class="btn-secondary">Usuń</button>
            `;
            container.appendChild(div);
        }
        
        function removeReplacePair(button) {
            const container = document.getElementById('replace-pairs');
            if (container.children.length > 1) {
                button.parentElement.remove();
            }
        }
    </script>
</body>
</html>
