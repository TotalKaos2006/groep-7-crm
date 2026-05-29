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

        <img src="Klokker.jpg" alt="Logo" class="login-logo">

        <div class="nav-buttons">
            <a class="nav-buttons active" href="index.php">Home</a>
            <?php if ($_SESSION['rol'] !== 'medewerker'): ?>
                <a href="projecten.php">Projecten</a>
            <?php endif; ?>
            <a href="uren.php">Uren</a>
            <?php if ($_SESSION['rol'] !== 'medewerker'): ?>
                <a href="medewerkers.php">Medewerkers</a>
                <a href="klanten.php">Klanten</a>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <?= htmlspecialchars($_SESSION['naam']) ?>
            &nbsp;/&nbsp;
            <a href="auth/logout.php">Uitloggen</a>
        </div>
    </header>

        <div class="avg-buttons">
        <a class="avg-buttons" href="beheerprocedure_groep7.pdf" target="_blank">Beheerprocedure groep 7</a> 
        <a class="avg-buttons" href="avg_document_groep7.pdf" target="_blank">AVG document groep 7</a> 
        <a class="avg-buttons" href="" target="_blank">Handleiding groep 7</a> 
        </div>

    <footer class="index-footer"> <p> © 2026 </p> </footer>

</body>

</html>