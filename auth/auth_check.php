<?php

if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit;
}

// Haal medewerker op uit database
$stmt = $pdo->prepare('SELECT * FROM medewerkers WHERE medewerker_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$medewerker = $stmt->fetch();

// Gebruiker bestaat niet meer → uitloggen
if (!$medewerker) {
    session_destroy();
    header('Location: auth/login.php?reden=verwijderd');
    exit;
}

// Rol is veranderd → sessie bijwerken én uitloggen
if ($medewerker['rol'] !== $_SESSION['rol']) {
    session_destroy();
    header('Location: auth/login.php?reden=rol_gewijzigd');
    exit;
}

// Sessie up-to-date houden
$_SESSION['rol'] = $medewerker['rol'];
$_SESSION['naam'] = $medewerker['naam'] . ' ' . $medewerker['achternaam'];