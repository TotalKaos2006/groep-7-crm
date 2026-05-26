<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$message = '';

// ── Dropdowndata ophalen voor de modals ──────────────────────────────────
$allMedewerkers = $pdo->query(
    "SELECT medewerker_id, naam, achternaam 
     FROM medewerkers 
     ORDER BY achternaam ASC, naam ASC"
)->fetchAll();

$allProjecten = $pdo->query(
    "SELECT project_id, projectnaam FROM projecten ORDER BY projectnaam"
)->fetchAll();

// ── Project-filter ophalen uit de URL ────────────────────────────────────
$filterProjectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;
$isMedewerker = $_SESSION['rol'] === 'medewerker';
$user_id = $_SESSION['user_id'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pdo->prepare(
            "INSERT INTO gewerkte_uren (project_id, medewerker_id, uren, omschrijving)
             VALUES (?, ?, ?, ?)"
        )->execute([
                    (int) $_POST['project_id'],
                    (int) $_POST['medewerker_id'],
                    (float) str_replace(',', '.', $_POST['uren']),
                    trim($_POST['omschrijving'] ?? ''),
                ]);
    }

    if ($action === 'edit') {
        if ($isMedewerker) {
            $check = $pdo->prepare("SELECT medewerker_id FROM gewerkte_uren WHERE uren_id = ?");
            $check->execute([(int) $_POST['uren_id']]);
            if ($check->fetchColumn() != $user_id) {
                header('Location: uren.php');
                exit;
            }
        }
        $medewerker_id = $isMedewerker ? $user_id : (int) $_POST['medewerker_id'];
        $pdo->prepare("UPDATE gewerkte_uren SET project_id=?, medewerker_id=?, uren=?, omschrijving=? WHERE uren_id=?")
            ->execute([(int) $_POST['project_id'], $medewerker_id, (float) str_replace(',', '.', $_POST['uren']), trim($_POST['omschrijving'] ?? ''), (int) $_POST['uren_id']]);
    }

    if ($action === 'delete') {
        if ($isMedewerker) {
            $check = $pdo->prepare("SELECT medewerker_id FROM gewerkte_uren WHERE uren_id = ?");
            $check->execute([(int) $_POST['uren_id']]);
            if ($check->fetchColumn() != $user_id) {
                header('Location: uren.php');
                exit;
            }
        }
        $pdo->prepare("DELETE FROM gewerkte_uren WHERE uren_id=?")->execute([(int) $_POST['uren_id']]);
    }

    header('Location: uren.php' . ($filterProjectId ? "?project_id={$filterProjectId}" : ''));
    exit;
}

// ── Data ophalen ─────────────────────────────────────────────────────────
$search = $_GET['search'] ?? '';
$conditions = [];
$params = [];

// Medewerker ziet alleen eigen uren
if ($isMedewerker) {
    $conditions[] = 'gu.medewerker_id = ?';
    $params[] = $user_id = $_SESSION['user_id'];
}

if ($filterProjectId) {
    $conditions[] = 'gu.project_id = ?';
    $params[] = $filterProjectId;
}

if ($search) {
    $like = "%$search%";
    $conditions[] = '(m.naam LIKE ? OR m.achternaam LIKE ? OR p.projectnaam LIKE ? OR gu.omschrijving LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$stmt = $pdo->prepare(
    "SELECT gu.uren_id, gu.uren, gu.omschrijving,
            gu.medewerker_id, gu.project_id,
            CONCAT(m.naam, ' ', m.achternaam) AS medewerker_naam,
            p.projectnaam
     FROM gewerkte_uren gu
     JOIN medewerkers m ON gu.medewerker_id = m.medewerker_id
     JOIN projecten   p ON gu.project_id    = p.project_id
     {$where}
     ORDER BY gu.uren_id DESC"
);
$stmt->execute($params);
$data = $stmt->fetchAll();

$totalUren = array_sum(array_column($data, 'uren'));
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Uren | Klokker</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <header>
        <div class="nav-buttons">
            <a href="index.php">Home</a>
            <?php if ($_SESSION['rol'] !== 'medewerker'): ?>
                <a href="projecten.php">Projecten</a>
            <?php endif; ?>
            <a class="nav-buttons active" href="uren.php">Uren</a>
            <?php if ($_SESSION['rol'] !== 'medewerker'): ?>
                <a href="medewerkers.php">Medewerkers</a>
                <a href="klanten.php">Klanten</a>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <?= htmlspecialchars($_SESSION['naam']) ?>
            &nbsp;|&nbsp;
            <a href="auth/logout.php">Uitloggen</a>
        </div>
    </header>

    <div class="container">
        <div class="navbar">
            <div class="navbar-left"></div>
            <div class="navbar-center">
                <form method="get">
                    <?php if ($filterProjectId): ?>
                        <input type="hidden" name="project_id" value="<?= $filterProjectId ?>">
                    <?php endif; ?>
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
                <th>Medewerker</th>
                <th>Project</th>
                <th>Uren</th>
                <th>Omschrijving</th>
                <th>Acties</th>
            </tr>

            <?php foreach ($data as $r): ?>
                <tr>
                    <td><?= $r['uren_id'] ?></td>
                    <td><?= htmlspecialchars($r['medewerker_naam']) ?></td>
                    <td><?= htmlspecialchars($r['projectnaam']) ?></td>
                    <td><?= number_format((float) $r['uren'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars($r['omschrijving']) ?></td>
                    <td>
                        <button class="edit" onclick='edit(<?= json_encode($r) ?>)'>Edit</button>
                        <form method="post" style="display:inline" onsubmit="return confirm('Verwijderen?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="uren_id" value="<?= $r['uren_id'] ?>">
                            <button class="delete">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!empty($data)): ?>
                <tr>
                    <td colspan="3" style="text-align:right;font-weight:600;">Totaal:</td>
                    <td><strong><?= number_format($totalUren, 2, ',', '.') ?></strong></td>
                    <td colspan="2"></td>
                </tr>
            <?php endif; ?>

        </table>
    </div>

    <!-- ── Modal: Toevoegen ──────────────────────────────────────────── -->
    <div id="addModal" class="modal">
        <form method="post">
            <input type="hidden" name="action" value="add">

            <?php if ($isMedewerker): ?>
                <input type="hidden" name="medewerker_id" value="<?= $user_id ?>">
            <?php else: ?>
                <select name="medewerker_id" class="smart-select" required>
                    <option value="">-- Selecteer medewerker --</option>
                    <?php foreach ($allMedewerkers as $m): ?>
                        <option value="<?= $m['medewerker_id'] ?>">
                            <?= htmlspecialchars($m['achternaam'] . ' ' . $m['naam']) ?>
                        </option>
                    <?php endforeach; ?>
                </select><br>
            <?php endif; ?>

            <select name="project_id" class="smart-select" required>
                <option value="">-- Selecteer project --</option>
                <?php foreach ($allProjecten as $p): ?>
                    <option value="<?= $p['project_id'] ?>" <?= ($filterProjectId === $p['project_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['projectnaam']) ?>
                    </option>
                <?php endforeach; ?>
            </select><br>

            <input name="uren" type="number" min="0" max="24" step="0.25" placeholder="Uren (bijv. 7.5)" required><br>

            <textarea name="omschrijving" placeholder="Omschrijving (optioneel)" rows="3"></textarea><br>

            <button>Opslaan</button>
            <button type="button" onclick="closeModal('addModal')">Sluiten</button>
        </form>
    </div>

    <!-- ── Modal: Bewerken ───────────────────────────────────────────── -->
    <div id="editModal" class="modal">
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="uren_id" id="edit_id">

            <?php if ($isMedewerker): ?>
                <input type="hidden" name="medewerker_id" value="<?= $user_id ?>">
            <?php else: ?>
                <select name="medewerker_id" id="edit_medewerker_id" class="smart-select" required>
                    <option value="">-- Selecteer medewerker --</option>
                    <?php foreach ($allMedewerkers as $m): ?>
                        <option value="<?= $m['medewerker_id'] ?>">
                            <?= htmlspecialchars($m['achternaam'] . ' ' . $m['naam']) ?>
                        </option>
                    <?php endforeach; ?>
                </select><br>
            <?php endif; ?>

            <select name="project_id" id="edit_project_id" class="smart-select" required>
                <option value="">-- Selecteer project --</option>
                <?php foreach ($allProjecten as $p): ?>
                    <option value="<?= $p['project_id'] ?>">
                        <?= htmlspecialchars($p['projectnaam']) ?>
                    </option>
                <?php endforeach; ?>
            </select><br>

            <input name="uren" id="edit_uren" type="number" min="0" max="24" step="0.25" required><br>

            <textarea name="omschrijving" id="edit_omschrijving" rows="3"></textarea><br>

            <button>Opslaan</button>
            <button type="button" onclick="closeModal('editModal')">Sluiten</button>
        </form>
    </div>

    <footer class="index-footer">
        <p> © 2026 - <a class="nav-buttons" href="../Miscellaneous/Privacyverklaring-Klokker.pdf" target="_blank">AVG document</a> </p>
    </footer>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

    <script>
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        function edit(d) {
            document.getElementById('edit_id').value = d.uren_id;
            document.getElementById('edit_project_id').value = d.project_id;
            document.getElementById('edit_uren').value = d.uren;
            document.getElementById('edit_omschrijving').value = d.omschrijving || '';
            const medEl = document.getElementById('edit_medewerker_id');
            if (medEl) medEl.value = d.medewerker_id; // ← alleen invullen als het bestaat
            openModal('editModal');
        }

        function exportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.setFontSize(14);
            doc.text('Gewerkte Uren – Klokker', 14, 15);
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

            const totaal = rows.reduce((sum, r) => {
                const val = parseFloat(r[3].replace(',', '.'));
                return sum + (isNaN(val) ? 0 : val);
            }, 0);
            rows.push(['', '', 'Totaal:', totaal.toFixed(1).replace('.', ','), '']);

            doc.autoTable({
                head: [['ID', 'Medewerker', 'Project', 'Uren', 'Omschrijving']],
                body: rows,
                startY: 27,
                styles: { fontSize: 10 },
                headStyles: { fillColor: [26, 26, 46] },
                didParseCell: function (data) {
                    const lastRow = data.table.body.length - 1;
                    if (data.row.index === lastRow) {
                        data.cell.styles.fontStyle = 'bold';
                    }
                }
            });
            doc.save('uren.pdf');
        }


        function enableSmartSelect(select) {
            let typed = '';
            let timeout;

            select.addEventListener('keydown', function (e) {

                if (e.key.length === 1) {

                    typed += e.key.toLowerCase();

                    clearTimeout(timeout);

                    timeout = setTimeout(() => {
                        typed = '';
                    }, 1000);

                    let options = Array.from(select.options);

                    options.sort((a, b) => {

                        let aStarts = a.text.toLowerCase().startsWith(typed);
                        let bStarts = b.text.toLowerCase().startsWith(typed);

                        if (aStarts && !bStarts) return -1;
                        if (!aStarts && bStarts) return 1;

                        return a.text.localeCompare(b.text);
                    });

                    options.forEach(option => select.appendChild(option));

                    let firstMatch = options.find(option =>
                        option.text.toLowerCase().startsWith(typed)
                    );

                    if (firstMatch) {
                        select.value = firstMatch.value;
                    }
                }
            });
        }

        document.querySelectorAll('.smart-select').forEach(enableSmartSelect);
    </script>

</body>

</html>