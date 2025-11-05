<?php
session_start();
require __DIR__ . '/config.php';

$id_membre = $_SESSION['id_membre'] ?? null;
if (!$id_membre) { header('Location: part1_membre.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	function sanitize_enum($value, $allowed, $default) {
		if (!is_string($value)) return $default;
		return in_array($value, $allowed, true) ? $value : $default;
	}
	$actif = sanitize_enum($_POST['actif_vie_religieuse'] ?? 'Oui', ['Oui','Non'], 'Oui');
	$conforme = sanitize_enum($_POST['conformite_principes'] ?? 'Oui', ['Oui','Non'], 'Oui');
	$dispo = sanitize_enum($_POST['disponible_2026'] ?? 'Je ne sais pas', ['Oui','Non','Je ne sais pas'], 'Je ne sais pas');
	$joign = sanitize_enum($_POST['facilement_joignable'] ?? 'Oui', ['Oui','Non'], 'Oui');
	$obs = $_POST['observations'] ?? null;

	// Upsert exigences (unique on id_membre)
	$stmt = $mysqli->prepare("INSERT INTO exigences_religieuses (id_membre, actif_vie_religieuse, conformite_principes, disponible_2026, facilement_joignable, observations) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE actif_vie_religieuse=VALUES(actif_vie_religieuse), conformite_principes=VALUES(conformite_principes), disponible_2026=VALUES(disponible_2026), facilement_joignable=VALUES(facilement_joignable), observations=VALUES(observations)");
	$stmt->bind_param('isssss', $id_membre, $actif, $conforme, $dispo, $joign, $obs);
	$stmt->execute(); $stmt->close();
	header('Location: part5_references.php');
	exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Partie IV – Exigences religieuses</title>
	<link rel="stylesheet" href="assets/styles.css">
	<link rel="icon" href="data:,">
</head>
<body>
	<nav class="nav">
		<div class="logo">SDA Ambolakandrina</div>
		<div><a href="index.php">Accueil</a></div>
	</nav>
	<div class="container">
		<form class="glass" action="part4_exigences.php" method="post">
			<div class="section-title">Partie IV : Exigences religieuses et comportementales</div>
			<div class="row two">
				<div>
					<label>Actif·ve dans la vie religieuse ?</label>
					<select name="actif_vie_religieuse"><option>Oui</option><option>Non</option></select>
				</div>
				<div>
					<label>Conformité aux principes adventistes ?</label>
					<select name="conformite_principes"><option>Oui</option><option>Non</option></select>
				</div>
			</div>
			<div class="row two">
				<div>
					<label>Disposé·e à assumer une responsabilité en 2026 ?</label>
					<select name="disponible_2026"><option>Je ne sais pas</option><option>Oui</option><option>Non</option></select>
				</div>
				<div>
					<label>Facilement joignable ?</label>
					<select name="facilement_joignable"><option>Oui</option><option>Non</option></select>
				</div>
			</div>
			<div class="row">
				<label>Commentaires / Observations</label>
				<textarea name="observations"></textarea>
			</div>
			<div class="actions">
				<a class="button" href="part3_competences.php">Retour</a>
				<button class="button primary" type="submit">Suivant: Partie V</button>
			</div>
		</form>
	</div>
	<div class="footer">© 2025 Église Adventiste Ambolakandrina</div>
</body>
</html>


