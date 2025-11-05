<?php
/**
 * Script idempotent pour ajouter une liste de postes dans la table `postes`.
 * Usage:
 *  - via navigateur: http://localhost/db1/add_postes.php
 *  - ou en CLI: php add_postes.php
 */
require __DIR__ . '/config.php';

// Liste fournie par l'utilisateur (one per line)
$postes = [
    "Loholona",
    "Trésorerie",
    "Secrétariat",
    "Fanabeazana",
    "Liberté Religieuse",
    "MIHOM",
    "Dorkasy",
    "Diakona",
    "Sekoly Sabata",
    "ASAFI",
    "Vokatra Antenaina",
    "Service Communautaire",
    "MINENF",
    "MIFEM",
    "FF sy Fampielezam-boky",
    "FIMPIA",
    "Vavaka",
    "Fahasalamana",
    "Hira sy Mozika",
    "PCM",
    "JA",
    "FIPIKRI",
    "Communication"
];

// Prepared statements
$select = $mysqli->prepare("SELECT id_poste FROM postes WHERE nom_poste_fr = ? LIMIT 1");
$insert = $mysqli->prepare("INSERT INTO postes (nom_poste_fr, nom_poste_mg, categorie, actif, description) VALUES (?,?,?,?,?)");
if (!$select || !$insert) {
    die("Erreur préparation statement: " . $mysqli->error);
}

$inserted = 0;
$skipped = 0;
$errors = [];

foreach ($postes as $p) {
    $name = trim($p);
    if ($name === '') continue;
    // check existence
    $select->bind_param('s', $name);
    if (!$select->execute()) { $errors[] = "select failed for {$name}: " . $select->error; continue; }
    $res = $select->get_result();
    if ($res && $res->num_rows > 0) { $skipped++; continue; }

    $mg = $name; // keep same value for malagasy name if not provided
    $categorie = 'Autre';
    $actif = 'Oui';
    $description = '';
    $insert->bind_param('sssss', $name, $mg, $categorie, $actif, $description);
    if ($insert->execute()) {
        $inserted++;
    } else {
        $errors[] = "insert failed for {$name}: " . $insert->error;
    }
}

$select->close();
$insert->close();

// Output
if (php_sapi_name() === 'cli') {
    echo "Add postes script finished\n";
    echo "Inserted: {$inserted}\n";
    echo "Skipped (already present): {$skipped}\n";
    if (!empty($errors)) { echo "Errors:\n" . implode("\n", $errors) . "\n"; }
} else {
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><title>Ajout postes</title></head><body>
    <h1>Ajout postes</h1>
    <p>Inserted: <?php echo $inserted; ?></p>
    <p>Skipped (already present): <?php echo $skipped; ?></p>
    <?php if (!empty($errors)): ?>
        <h2>Erreurs</h2>
        <ul>
        <?php foreach ($errors as $err): ?><li><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p><a href="membres.php">Retour aux membres</a></p>
    </body></html>
    <?php
}

?>
