<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

if ($_SESSION['rol'] === 'medewerker') {
    header('Location: uren.php');
    exit;
}


$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pdo->prepare(
            "INSERT INTO klanten (naam, achternaam, email, telefoonnummer)
             VALUES (?, ?, ?, ?)"
        )->execute([
                    trim($_POST['naam']),
                    trim($_POST['achternaam']),
                    trim($_POST['email']),
                    trim($_POST['telefoonnummer']),
                ]);
    }

    if ($action === 'edit') {
        $pdo->prepare(
            "UPDATE klanten
             SET naam=?, achternaam=?, email=?, telefoonnummer=?
             WHERE klant_id=?"
        )->execute([
                    trim($_POST['naam']),
                    trim($_POST['achternaam']),
                    trim($_POST['email']),
                    trim($_POST['telefoonnummer']),
                    (int) $_POST['klant_id'],
                ]);
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM klanten WHERE klant_id=?")
            ->execute([(int) $_POST['klant_id']]);
    }
}

$search = $_GET['search'] ?? '';

if ($search) {
    $like = "%$search%";
    $stmt = $pdo->prepare(
        "SELECT * FROM klanten
         WHERE naam LIKE ? OR achternaam LIKE ? OR email LIKE ? OR telefoonnummer LIKE ?"
    );
    $stmt->execute([$like, $like, $like, $like]);
} else {
    $stmt = $pdo->query("SELECT * FROM klanten ORDER BY klant_id DESC");
}

$data = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Klanten | Klokker</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <header>

        <img src="Klokker.jpg" alt="Logo" class="login-logo">

        <div class="nav-buttons">
            <a href="index.php">Home</a>
            <a href="projecten.php">Projecten</a>
            <a href="uren.php">Uren</a>
            <a href="medewerkers.php">Medewerkers</a>
            <a class="nav-buttons active" href="#">Klanten</a>
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
                <th>Telefoonnummer</th>
                <th>Acties</th>
            </tr>

            <?php foreach ($data as $r): ?>
                <tr>
                    <td><?= $r['klant_id'] ?></td>
                    <td><?= htmlspecialchars($r['naam']) ?></td>
                    <td><?= htmlspecialchars($r['achternaam']) ?></td>
                    <td><?= htmlspecialchars($r['email']) ?></td>
                    <td><?= htmlspecialchars($r['telefoonnummer']) ?></td>
                    <td>
                        <button class="edit" onclick='edit(<?= json_encode($r) ?>)'>Edit</button>
                        <form method="post" style="display:inline" onsubmit="return confirm('Verwijderen?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="klant_id" value="<?= $r['klant_id'] ?>">
                            <button class="delete">Delete</button>
                        </form>
                        <button class="uren"
                            onclick="location.href='projecten.php?klant_id=<?= $r['klant_id'] ?>'">Projecten</button>
                    </td>
                </tr>
            <?php endforeach; ?>

        </table>
    </div>

    <div id="addModal" class="modal">
        <form method="post">
            <input type="hidden" name="action" value="add">

            <input name="naam" placeholder="Naam" required><br>
            <input name="achternaam" placeholder="Achternaam" required><br>
            <input name="email" placeholder="Email" type="email" required><br>
            <input name="telefoonnummer" placeholder="Telefoonnummer" type="tel"><br>

            <button>Opslaan</button>
            <button type="button" onclick="closeModal('addModal')">Sluiten</button>
        </form>
    </div>

    <div id="editModal" class="modal">
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="klant_id" id="edit_id">

            <input name="naam" id="edit_naam" placeholder="Naam" required><br>
            <input name="achternaam" id="edit_achternaam" placeholder="Achternaam" required><br>
            <input name="email" id="edit_email" placeholder="Email" type="email" required><br>
            <input name="telefoonnummer" id="edit_telefoon" placeholder="Telefoonnummer" type="tel"><br>

            <button>Opslaan</button>
            <button type="button" onclick="closeModal('editModal')">Sluiten</button>
        </form>
    </div>

    <footer class="index-footer">
        <p> © 2026 - <a class="nav-buttons" href="avg_document_groep7.pdf" target="_blank">AVG document</a> </p>
    </footer>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        function edit(d) {
            document.getElementById('edit_id').value = d.klant_id;
            document.getElementById('edit_naam').value = d.naam;
            document.getElementById('edit_achternaam').value = d.achternaam;
            document.getElementById('edit_email').value = d.email;
            document.getElementById('edit_telefoon').value = d.telefoonnummer;
            openModal('editModal');
        }

        function exportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.setFontSize(14);
            doc.text('Klanten – Klokker', 14, 15);
            doc.setFontSize(10);
            doc.text('Datum: ' + new Date().toLocaleDateString('nl-NL'), 14, 22);

            const rows = [];
            document.querySelectorAll('#mainTable tr').forEach(tr => {
                const c = tr.querySelectorAll('td');
                if (c.length >= 5)
                    rows.push([
                        c[0].textContent.trim(),
                        c[1].textContent.trim(),
                        c[2].textContent.trim(),
                        c[3].textContent.trim(),
                        c[4].textContent.trim()
                    ]);
            });

            doc.autoTable({
                head: [['ID', 'Naam', 'Achternaam', 'Email', 'Telefoonnummer']],
                body: rows,
                startY: 27,
                styles: { fontSize: 10 },
                headStyles: { fillColor: [26, 26, 46] }
            });
            doc.save('klanten.pdf');
        }
    </script>

</body>

</html>