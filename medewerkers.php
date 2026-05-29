<?php
session_start();
require_once 'db.php';
require_once 'auth/auth_check.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

if ($_SESSION['rol'] === 'medewerker') {
    header('Location: uren.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $hash = password_hash($_POST['wachtwoord'], PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO medewerkers (naam, achternaam, email, functie, wachtwoord, rol, actief)
            VALUES (?, ?, ?, ?, ?, ?, 1)")->execute([
            $_POST['naam'],
            $_POST['achternaam'],
            $_POST['email'],
            $_POST['functie'],
            $hash,
            $_POST['rol']
        ]);
    }

    if ($action === 'edit') {
        // Wachtwoord alleen updaten als er iets is ingevuld
        if (!empty($_POST['wachtwoord'])) {
            $hash = password_hash($_POST['wachtwoord'], PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE medewerkers SET naam=?, achternaam=?, email=?, functie=?, rol=?, wachtwoord=? WHERE medewerker_id=?")
                ->execute([
                    $_POST['naam'],
                    $_POST['achternaam'],
                    $_POST['email'],
                    $_POST['functie'],
                    $_POST['rol'],
                    $hash,
                    $_POST['medewerker_id']
                ]);
        } else {
            $pdo->prepare("UPDATE medewerkers SET naam=?, achternaam=?, email=?, functie=?, rol=? WHERE medewerker_id=?")
                ->execute([
                    $_POST['naam'],
                    $_POST['achternaam'],
                    $_POST['email'],
                    $_POST['functie'],
                    $_POST['rol'],
                    $_POST['medewerker_id']
                ]);
        }
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM medewerkers WHERE medewerker_id=?")
            ->execute([$_POST['medewerker_id']]);
    }

    header('Location: medewerkers.php');
    exit;
}

$search = $_GET['search'] ?? '';

if ($search) {
    $like = "%$search%";
    $stmt = $pdo->prepare("SELECT * FROM medewerkers WHERE naam LIKE ? OR achternaam LIKE ? OR email LIKE ? OR functie LIKE ?");
    $stmt->execute([$like, $like, $like, $like]);
} else {
    $stmt = $pdo->query("SELECT * FROM medewerkers");
}

$data = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Medewerkers | Klokker</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <header>

        <img src="Klokker.jpg" alt="Logo" class="login-logo">

        <div class="nav-buttons">
            <a href="index.php">Home</a>
            <a href="projecten.php">Projecten</a>
            <a href="uren.php">Uren</a>
            <a class="nav-buttons active" href="#">Medewerkers</a>
            <a href="klanten.php">Klanten</a>
        </div>
        <div class="user-info">
            <?= htmlspecialchars($_SESSION['naam']) ?>
            &nbsp;/&nbsp;
            <a href="auth/logout.php">Uitloggen</a>
        </div>
    </header>

    <div class="container">
        <div class="navbar">
            <div class="navbar-left"></div>
            <div class="navbar-center">
                <form method="get">
                    <input type="text" name="search" placeholder="Zoeken..." value="<?= htmlspecialchars($search) ?>">
                </form>
            </div>
            <div class="navbar-actions">
                <button class="toevoegen" onclick="openModal('addModal')">+</button>
                <button class="toevoegen" onclick="exportPDF()">🖨️</button>
            </div>
        </div>

        <table id="mainTable">
            <tr>
                <th>ID</th>
                <th>Naam</th>
                <th>Achternaam</th>
                <th>Email</th>
                <th>Functie</th>
                <th>Rol</th>
                <th>Acties</th>
            </tr>

            <?php foreach ($data as $r): ?>
                <tr>
                    <td><?= $r['medewerker_id'] ?></td>
                    <td><?= htmlspecialchars($r['naam']) ?></td>
                    <td><?= htmlspecialchars($r['achternaam']) ?></td>
                    <td><?= htmlspecialchars($r['email']) ?></td>
                    <td><?= htmlspecialchars($r['functie']) ?></td>
                    <td><?= htmlspecialchars($r['rol']) ?></td>
                    <td>
                        <button class="edit" onclick='edit(<?= json_encode($r) ?>)'>Edit</button>
                        <form method="post" style="display:inline" onsubmit="return confirm('Verwijderen?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="medewerker_id" value="<?= $r['medewerker_id'] ?>">
                            <button class="delete">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Modal: Toevoegen -->
    <div id="addModal" class="modal">
        <form method="post">
            <input type="hidden" name="action" value="add">
            <input name="naam" placeholder="Naam" required><br>
            <input name="achternaam" placeholder="Achternaam" required><br>
            <input name="email" type="email" placeholder="Email" required><br>
            <select name="functie">
                <option value="">-- Kies een functie --</option>
                <option value="IT">IT</option>
                <option value="SD">SD</option>
            </select><br>
            <select name="rol" required>
                <option value="">-- Kies een rol --</option>
                <option value="medewerker">Medewerker</option>
                <option value="verkoop">Verkoop</option>
                <option value="afdelingshoofd">Afdelingshoofd</option>
            </select><br>
            <input name="wachtwoord" type="password" placeholder="Wachtwoord" required><br>
            <button>Opslaan</button>
            <button type="button" onclick="closeModal('addModal')">Sluiten</button>
        </form>
    </div>

    <!-- Modal: Bewerken -->
    <div id="editModal" class="modal">
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="medewerker_id" id="edit_id">
            <input name="naam" id="edit_naam" placeholder="Naam" required><br>
            <input name="achternaam" id="edit_achternaam" placeholder="Achternaam" required><br>
            <input name="email" id="edit_email" type="email" placeholder="Email" required><br>
            <select name="functie" id="edit_functie">
                <option value="">-- Kies een functie --</option>
                <option value="IT">IT</option>
                <option value="SD">SD</option>
            </select><br>
            <select name="rol" id="edit_rol" required>
                <option value="">-- Kies een rol --</option>
                <option value="medewerker">Medewerker</option>
                <option value="verkoop">Verkoop</option>
                <option value="afdelingshoofd">Afdelingshoofd</option>
            </select><br>
            <input name="wachtwoord" id="edit_wachtwoord" type="password" placeholder="Nieuw wachtwoord"><br>
            <button>Opslaan</button>
            <button type="button" onclick="closeModal('editModal')">Sluiten</button>
        </form>
    </div>

    <footer class="index-footer">
        <p>© 2026 - <a class="nav-buttons" href="avg_document_groep7.pdf" target="_blank">AVG document</a></p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        function edit(d) {
            document.getElementById('edit_id').value = d.medewerker_id;
            document.getElementById('edit_naam').value = d.naam;
            document.getElementById('edit_achternaam').value = d.achternaam;
            document.getElementById('edit_email').value = d.email;
            document.getElementById('edit_functie').value = d.functie;
            document.getElementById('edit_rol').value = d.rol;
            document.getElementById('edit_wachtwoord').value = ''; // altijd leeg laten
            openModal('editModal');
        }

        function exportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.setFontSize(14);
            doc.text('Medewerkers – Klokker', 14, 15);
            doc.setFontSize(10);
            doc.text('Datum: ' + new Date().toLocaleDateString('nl-NL'), 14, 22);

            const rows = [];
            document.querySelectorAll('#mainTable tr').forEach(tr => {
                const c = tr.querySelectorAll('td');
                if (c.length >= 6)
                    rows.push([
                        c[0].textContent.trim(),
                        c[1].textContent.trim(),
                        c[2].textContent.trim(),
                        c[3].textContent.trim(),
                        c[4].textContent.trim(),
                        c[5].textContent.trim()
                    ]);
            });

            doc.autoTable({
                head: [['ID', 'Naam', 'Achternaam', 'Email', 'Functie', 'Rol']],
                body: rows,
                startY: 27,
                styles: { fontSize: 10 },
                headStyles: { fillColor: [26, 26, 46] }
            });
            doc.save('medewerkers.pdf');
        }
    </script>

</body>

</html>