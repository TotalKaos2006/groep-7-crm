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
        $pdo->prepare("INSERT INTO medewerkers (naam, achternaam, email, functie)
        VALUES (?, ?, ?, ?)")->execute([
                    $_POST['naam'],
                    $_POST['achternaam'],
                    $_POST['email'],
                    $_POST['functie']
                ]);
    }

    if ($action === 'edit') {
        $pdo->prepare("UPDATE medewerkers SET naam=?, achternaam=?, email=?, functie=? WHERE medewerker_id=?")
            ->execute([
                $_POST['naam'],
                $_POST['achternaam'],
                $_POST['email'],
                $_POST['functie'],
                $_POST['medewerker_id']
            ]);
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM medewerkers WHERE medewerker_id=?")
            ->execute([$_POST['medewerker_id']]);
    }
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
        <div class="nav-buttons">
            <a href="index.php">Home</a>
            <a href="projecten.php">Projecten</a>
            <a href="uren.php">Uren</a>
            <a class="nav-buttons active" href="#">Medewerkers</a>
            <a href="klanten.php">Klanten</a>
        </div>
        <div class="user-info">
            <strong><?= htmlspecialchars($_SESSION['naam']) ?></strong>
            (<?= htmlspecialchars($_SESSION['rol']) ?>)
            &nbsp;|&nbsp;
            <a href="auth/logout.php">Uitloggen</a>
        </div>
    </header>

    <div class="container">
        <div class="navbar">
            <div class="navbar-left"></div>
            <div class="navbar-center">
                <form method="get">
                    <input type="text" name="search" placeholder="Zoeken..." value="<?= $search ?>">
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
                <th>Acties</th>
            </tr>

            <?php foreach ($data as $r): ?>
                <tr>
                    <td><?= $r['medewerker_id'] ?></td>
                    <td><?= $r['naam'] ?></td>
                    <td><?= $r['achternaam'] ?></td>
                    <td><?= $r['email'] ?></td>
                    <td><?= $r['functie'] ?></td>
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

    <div id="addModal" class="modal">
        <form method="post">
            <input type="hidden" name="action" value="add">
            <input name="naam" placeholder="Naam"><br>
            <input name="achternaam" placeholder="Achternaam"><br>
            <input name="email" placeholder="Email"><br>
            <select name="functie">
                <option value="">-- Kies een functie --</option>
                <option value="IT">IT</option>
                <option value="SD">SD</option>
            </select>
            <br>
            <button>Opslaan</button>
            <button type="button" onclick="closeModal('addModal')">Sluiten</button>
        </form>
    </div>

    <div id="editModal" class="modal">
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="medewerker_id" id="id">
            <input name="naam" id="naam" placeholder="Naam"><br>
            <input name="achternaam" id="achternaam" placeholder="Achternaam"><br>
            <input name="email" id="email" placeholder="Email"><br>
            <select name="functie" id="functie">
                <option value="">-- Kies een functie --</option>
                <option value="IT">IT</option>
                <option value="SD">SD</option>
            </select>
            <br>
            <button>Opslaan</button>
            <button type="button" onclick="closeModal('editModal')">Sluiten</button>
        </form>
    </div>

    <footer class="index-footer"> <p> © 2026 - <a class="nav-buttons" href="../Miscellaneous/Privacyverklaring-Klokker.pdf" target="_blank">AVG document - </a> </p> </footer>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        function edit(d) {
            document.getElementById('id').value = d.medewerker_id;
            document.getElementById('naam').value = d.naam;
            document.getElementById('achternaam').value = d.achternaam;
            document.getElementById('email').value = d.email;
            document.getElementById('functie').value = d.functie;
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
                head: [['ID', 'Naam', 'Achternaam', 'Email', 'Functie']],
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