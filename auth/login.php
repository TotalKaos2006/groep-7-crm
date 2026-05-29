<?php
session_start();
require_once '../db.php';

$fout = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $ww = $_POST['wachtwoord'] ?? '';

    // Query op jouw medewerkers tabel
    $stmt = $pdo->prepare("
        SELECT medewerker_id, naam, achternaam, email, wachtwoord, rol
        FROM medewerkers
        WHERE email = ? AND actief = 1
    ");
    $stmt->execute([$email]);
    $medewerker = $stmt->fetch();

    if ($medewerker && password_verify($ww, $medewerker['wachtwoord'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $medewerker['medewerker_id'];
        $_SESSION['naam'] = $medewerker['naam'] . ' ' . $medewerker['achternaam'];
        $_SESSION['rol'] = $medewerker['rol'];

        header('Location: ../index.php');
        exit;
    } else {
        $fout = 'E-mailadres of wachtwoord onjuist.';
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
    <link rel="stylesheet" href="stylelogin.css">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>

    </style>
</head>

<body>
    <div class="login-box">
        <form method="POST">

            <img src="Klokker.jpg" alt="Logo" class="login-logo">

            <label for="email">E-mailadres</label>
            <input type="email" id="email" name="email" required autofocus placeholder="E-mail">

            <label for="wachtwoord">Wachtwoord</label>
            <input type="password" id="wachtwoord" name="wachtwoord" required autofocus placeholder="Wachtwoord">
<?php if ($fout): ?>
            <p class="fout"><?= htmlspecialchars($fout) ?></p>
        <?php endif; ?>
            <button type="submit">Login</button>
        </form>
    </div>
</body>

</html>