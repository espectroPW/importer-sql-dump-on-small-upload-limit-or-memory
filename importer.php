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
            
            // Otwórz plik SQL do odczytu
            $handle = fopen($sql_file, "r");
            if (!$handle) {
                echo "<p style='color: red;'><strong>Nie udało się otworzyć pliku SQL.</strong></p>";
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
</body>
</html>
