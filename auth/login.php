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

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: #f0f2f5;
        }

        .login-box {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .1);
            width: 320px;
        }

        h2 {
            margin-top: 0;
            color: #333;
        }

        label {
            display: block;
            margin-bottom: .3rem;
            font-size: .9rem;
            color: #555;
        }

        input {
            width: 100%;
            padding: .6rem;
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            padding: .7rem;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }

        button:hover {
            background: #1558b0;
        }

        .fout {
            color: red;
            font-size: .9rem;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <h2>Gilde DevOps CRM</h2>
        <?php if ($fout): ?>
            <p class="fout"><?= htmlspecialchars($fout) ?></p>
        <?php endif; ?>
        <form method="POST">
            <label for="email">E-mailadres</label>
            <input type="email" id="email" name="email" required autofocus placeholder="E-mail">

            <label for="wachtwoord">Wachtwoord</label>
            <input type="password" id="wachtwoord" name="wachtwoord" required>

            <button type="submit">Inloggen</button>
        </form>
    </div>
</body>

</html>