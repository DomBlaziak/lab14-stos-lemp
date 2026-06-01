<?php
echo "<h1>Stack LEMP działa poprawnie!</h1>";
echo "<p>Uruchomiono pomyślnie na serwerze: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";

// PHP odczytuje hasło bezpośrednio z zamontowanego sekretu
$secret_path = '/run/secrets/db_password';

if (file_exists($secret_path)) {
    $db_password = trim(file_get_contents($secret_path));
    
    $host = 'db'; // Nazwa kontenera bazy danych jako host
    $user = 'dominik_user';
    $dbname = 'testowa_baza_dominik';

    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $db_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "<h3 style='color:green;'>Sukces: PHP pomyślnie połączyło się z bazą MySQL przy użyciu Docker Secrets!</h3>";
    } catch (PDOException $e) {
        echo "<h3 style='color:red;'>Błąd połączenia z bazą: " . $e->getMessage() . "</h3>";
    }
} else {
    echo "<h3 style='color:red;'>Błąd: Plik sekretu nie istnieje w ścieżce /run/secrets/db_password</h3>";
}

phpinfo();
?>