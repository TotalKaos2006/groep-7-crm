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

// ── Alle klanten ophalen voor de dropdown ────────────────────────────────
$allKlanten = $pdo->query(
    "SELECT klant_id, naam, achternaam FROM klanten ORDER BY naam"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pdo->prepare(
            "INSERT INTO projecten (klant_id, projectnaam, status, omschrijving)
             VALUES (?, ?, ?, ?)"
        )->execute([
                    (int) $_POST['klant_id'],
                    trim($_POST['projectnaam']),
                    $_POST['status'],
                    trim($_POST['omschrijving'] ?? ''),
                ]);
    }

    if ($action === 'edit') {
        $pdo->prepare(
            "UPDATE projecten
             SET klant_id=?, projectnaam=?, status=?, omschrijving=?
             WHERE project_id=?"
        )->execute([
                    (int) $_POST['klant_id'],
                    trim($_POST['projectnaam']),
                    $_POST['status'],
                    trim($_POST['omschrijving'] ?? ''),
                    (int) $_POST['project_id'],
                ]);
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM projecten WHERE project_id=?")
            ->execute([(int) $_POST['project_id']]);
    }
}

$search = $_GET['search'] ?? '';
$filterKlantId = isset($_GET['klant_id']) ? (int) $_GET['klant_id'] : 0;

$conditions = [];
$params = [];

if ($filterKlantId) {
    $conditions[] = 'p.klant_id = ?';
    $params[] = $filterKlantId;
}

if ($search) {
    $like = "%$search%";
    $conditions[] = '(p.projectnaam LIKE ? OR k.naam LIKE ? OR k.achternaam LIKE ? OR p.status LIKE ? OR p.omschrijving LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$stmt = $pdo->prepare(
    "SELECT p.project_id, p.projectnaam, p.status, p.omschrijving,
            p.klant_id, CONCAT(k.naam, ' ', k.achternaam) AS klant_naam
     FROM projecten p
     JOIN klanten k ON p.klant_id = k.klant_id
     {$where}
     ORDER BY p.project_id"
);
$stmt->execute($params);

$data = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Projecten | Klokker</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <header>
        <div class="nav-buttons">
            <a href="index.php">Home</a>
            <a class="nav-buttons active" href="#">Projecten</a>
            <a href="uren.php">Uren</a>
            <a href="medewerkers.php">Medewerkers</a>
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
                <th>Projectnaam</th>
                <th>Klant</th>
                <th>Status</th>
                <th>Omschrijving</th>
                <th>Acties</th>
            </tr>

            <?php foreach ($data as $r): ?>
                <tr>
                    <td><?= $r['project_id'] ?></td>
                    <td><?= htmlspecialchars($r['projectnaam']) ?></td>
                    <td><?= htmlspecialchars($r['klant_naam']) ?></td>
                    <td><?= htmlspecialchars($r['status']) ?></td>
                    <td><?= htmlspecialchars($r['omschrijving']) ?></td>
                    <td>
                        <button class="edit" onclick='edit(<?= json_encode($r) ?>)'>Edit</button>
                        <form method="post" style="display:inline" onsubmit="return confirm('Verwijderen?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="project_id" value="<?= $r['project_id'] ?>">
                            <button class="delete">Delete</button>
                        </form>
                        <button class="uren"
                            onclick="location.href='uren.php?project_id=<?= $r['project_id'] ?>'">Uren</button>
                    </td>
                </tr>
            <?php endforeach; ?>

        </table>
    </div>

    <div id="addModal" class="modal">
        <form method="post">
            <input type="hidden" name="action" value="add">

            <input name="projectnaam" placeholder="Projectnaam" required><br>

            <select name="klant_id" required>
                <option value="">-- Selecteer klant --</option>
                <?php foreach ($allKlanten as $k): ?>
                    <option value="<?= $k['klant_id'] ?>">
                        <?= htmlspecialchars($k['naam'] . ' ' . $k['achternaam']) ?>
                    </option>
                <?php endforeach; ?>
            </select><br>

            <select name="status">
                <option value="actief">Actief</option>
                <option value="gepauzeerd">Gepauzeerd</option>
                <option value="afgerond">Afgerond</option>
            </select><br>

            <textarea name="omschrijving" placeholder="Omschrijving (optioneel)" rows="3"></textarea><br>

            <button>Opslaan</button>
            <button type="button" onclick="closeModal('addModal')">Sluiten</button>
        </form>
    </div>

    <div id="editModal" class="modal">
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="project_id" id="edit_id">

            <input name="projectnaam" id="edit_projectnaam" placeholder="Projectnaam" required><br>

            <select name="klant_id" id="edit_klant_id" required>
                <option value="">-- Selecteer klant --</option>
                <?php foreach ($allKlanten as $k): ?>
                    <option value="<?= $k['klant_id'] ?>">
                        <?= htmlspecialchars($k['naam'] . ' ' . $k['achternaam']) ?>
                    </option>
                <?php endforeach; ?>
            </select><br>

            <select name="status" id="edit_status">
                <option value="actief">Actief</option>
                <option value="gepauzeerd">Gepauzeerd</option>
                <option value="afgerond">Afgerond</option>
            </select><br>

            <textarea name="omschrijving" id="edit_omschrijving" rows="3"></textarea><br>

            <button>Opslaan</button>
            <button type="button" onclick="closeModal('editModal')">Sluiten</button>
        </form>
    </div>

    <footer class="index-footer"> <p> © 2026 - <a class="nav-buttons" href="../Miscellaneous/Privacyverklaring-Klokker.pdf" target="_blank">AVG document</a> </p> </footer>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        function edit(d) {
            document.getElementById('edit_id').value = d.project_id;
            document.getElementById('edit_projectnaam').value = d.projectnaam;
            document.getElementById('edit_klant_id').value = d.klant_id;
            document.getElementById('edit_status').value = d.status;
            document.getElementById('edit_omschrijving').value = d.omschrijving || '';
            openModal('editModal');
        }

        function exportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.setFontSize(14);
            doc.text('Projecten – Klokker', 14, 15);
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
                head: [['ID', 'Projectnaam', 'Klant', 'Status', 'Omschrijving']],
                body: rows,
                startY: 27,
                styles: { fontSize: 10 },
                headStyles: { fillColor: [26, 26, 46] }
            });
            doc.save('projecten.pdf');
        }
    </script>

</body>

</html>