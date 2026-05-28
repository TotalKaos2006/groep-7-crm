<?php
$db_host = 'localhost';
<<<<<<< Updated upstream
$db_name = 'c4klokker';
=======
$db_name = 'klokker';
>>>>>>> Stashed changes
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    die('<div style="color:red">
            <b>Databasefout:</b> ' . htmlspecialchars($e->getMessage()) . '
         </div>');
}