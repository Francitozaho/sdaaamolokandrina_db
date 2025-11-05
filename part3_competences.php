<?php
session_start();
require __DIR__ . '/config.php';

$id_membre = $_SESSION['id_membre'] ?? null;
if (!$id_membre) { header('Location: part1_membre.php'); exit; }

// Fetch competences for checklist
$competences = [];
$res = $mysqli->query("SELECT id_competence, nom_competence_fr FROM competences ORDER BY nom_competence_fr");
if ($res) { while ($r = $res->fetch_assoc()) { $competences[] = $r; } $res->free(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$sel = $_POST['competences'] ?? [];
	$niveau = $_POST['niveau'] ?? [];
	$stmt = $mysqli->prepare("INSERT INTO membres_competences (id_membre, id_competence, niveau, note_auto_evaluation) VALUES (?,?,?,NULL) ON DUPLICATE KEY UPDATE niveau=VALUES(niveau)");
	foreach ($sel as $idc) {
		if (!$idc) continue;
		$nv = $niveau[$idc] ?? 'Intermédiaire';
		$stmt->bind_param('iis', $id_membre, $idc, $nv);
		$stmt->execute();
	}
	$stmt->close();
	header('Location: part4_exigences.php');
	exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Partie III – Compétences et talents</title>
	<link rel="stylesheet" href="assets/styles.css">
	<link rel="icon" href="data:,">
</head>
<body>
	<nav class="nav">
		<div class="logo">SDA Ambolakandrina</div>
		<div><a href="index.php">Accueil</a></div>
	</nav>
	<div class="container">
		<form class="glass" action="part3_competences.php" method="post">
			<div class="section-title">Partie III : Compétences et talents</div>
			<div class="row">
				<div class="grid">
					<?php foreach ($competences as $c): $idc=(int)$c['id_competence']; ?>
						<div class="glass" style="padding:12px;">
							<label style="display:flex; align-items:center; gap:8px;">
								<input type="checkbox" name="competences[]" value="<?php echo $idc; ?>">
								<span><?php echo htmlspecialchars($c['nom_competence_fr'], ENT_QUOTES, 'UTF-8'); ?></span>
							</label>
							<div style="margin-top:8px;">
								<select name="niveau[<?php echo $idc; ?>]">
									<option>Débutant</option>
									<option selected>Intermédiaire</option>
									<option>Avancé</option>
									<option>Expert</option>
								</select>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<div class="actions">
				<a class="button" href="part2_responsabilites.php">Retour</a>
				<button class="button primary" type="submit">Suivant: Partie IV</button>
			</div>
		</form>
	</div>
	<div class="footer">© 2025 Église Adventiste Ambolakandrina</div>
</body>
</html>


