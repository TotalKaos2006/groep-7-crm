<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}
?>
<!DOCTYPE html>

<head>
    <link rel="stylesheet" href="style.css">
    <title>UrenRegistratieSysteem</title>
</head>

<body>

    <header>
    <div class="nav-buttons">
        <a href="index.php">Home</a>

        <?php if ($_SESSION['rol'] !== 'medewerker'): ?>
            <a href="projecten.php">Projecten</a>
            <a href="medewerkers.php">Medewerkers</a>
            <a href="klanten.php">Klanten</a>
        <?php endif; ?>

        <a href="uren.php">Uren</a>
    </div>
    <div class="user-info">
        <strong><?= htmlspecialchars($_SESSION['naam']) ?></strong>
        (<?= htmlspecialchars($_SESSION['rol']) ?>)
        | <a href="logout.php">Uitloggen</a>
    </div>
</header>

    <footer class="index-footer"> <p> © 2026 - <a class="nav-buttons" href="../Miscellaneous/Privacyverklaring-Klokker.pdf" target="_blank">AVG document</a> </p> </footer>

</body>

</html>