<?php
/**
 * Script idempotent pour ajouter une liste de compétences dans la table `competences`.
 * Usage:
 *  - via navigateur: http://localhost/db1/add_competences.php
 *  - ou en CLI: php add_competences.php
 */
require __DIR__ . '/config.php';

$competences = [
    'Fitantanana',
    'Fahaiza-mamantatra fanahy',
    'Evanjelistra',
    'Fampaherezana',
    'Finoana',
    'Fahalalahan-tanana',
    'Fanasitranana',
    'Fanampiana',
    'Fampiantranoam-bahiny',
    'Famelan-keloka',
    'Fahalalana',
    'Fitarihana',
    'Faminaniana',
    'Misionera',
    'Pastora',
    'Fanompoana',
    'Mpampianatra',
    'Fahaendrena'
];

// Prepared statements
$select = $mysqli->prepare("SELECT id_competence FROM competences WHERE nom_competence_fr = ? LIMIT 1");
$insert = $mysqli->prepare("INSERT INTO competences (nom_competence_fr, nom_competence_mg, categorie, description) VALUES (?,?,?,?)");
if (!$select || !$insert) {
    die("Erreur préparation statement: " . $mysqli->error);
}

$inserted = 0;
$skipped = 0;
$errors = [];

foreach ($competences as $c) {
    $name = trim($c);
    if ($name === '') continue;
    $select->bind_param('s', $name);
    if (!$select->execute()) { $errors[] = "select failed for {$name}: " . $select->error; continue; }
    $res = $select->get_result();
    if ($res && $res->num_rows > 0) { $skipped++; continue; }

    $mg = $name; // fallback
    $categorie = 'Autre';
    $description = '';
    $insert->bind_param('ssss', $name, $mg, $categorie, $description);
    if ($insert->execute()) {
        $inserted++;
    } else {
        $errors[] = "insert failed for {$name}: " . $insert->error;
    }
}

$select->close();
$insert->close();

if (php_sapi_name() === 'cli') {
    echo "Add competences script finished\n";
    echo "Inserted: {$inserted}\n";
    echo "Skipped (already present): {$skipped}\n";
    if (!empty($errors)) { echo "Errors:\n" . implode("\n", $errors) . "\n"; }
} else {
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><title>Ajout compétences</title></head><body>
    <h1>Ajout compétences</h1>
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
