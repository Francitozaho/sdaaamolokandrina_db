<?php
session_start();
require __DIR__ . '/config.php';

$id_membre = $_SESSION['id_membre'] ?? null;
if (!$id_membre) { header('Location: part1_membre.php'); exit; }

// Fetch postes for checklist
$postes = [];
$res = $mysqli->query("SELECT id_poste, nom_poste_fr FROM postes ORDER BY nom_poste_fr");
if ($res) { while ($r = $res->fetch_assoc()) { $postes[] = $r; } $res->free(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// 1) responsabilités assumed/could assume/wish
	$roles = $_POST['poste'] ?? [];
	$types = $_POST['type_experience'] ?? [];
	$eglise = 'Ambolakandrina';
	$type_eglise = 'Église principale';

	$insCount = 0;
	if (is_array($roles)) {
		$stmt = $mysqli->prepare("INSERT INTO responsabilites (id_membre, id_poste, type_experience, eglise, type_eglise, annee_debut, annee_fin, realisations) VALUES (?,?,?,?,?,?,?,?)");
		foreach ($roles as $i => $id_poste) {
			if (empty($id_poste)) continue;
			$te = $types[$i] ?? 'Assumé';
			$adeb = null; $afin = null; $real = null;
			$stmt->bind_param('iisssiss', $id_membre, $id_poste, $te, $eglise, $type_eglise, $adeb, $afin, $real);
			$stmt->execute();
			$insCount++;
		}
		$stmt->close();
	}

	// 2) expériences dans d'autres églises (array rows)
	$autre_eglise = $_POST['exp_autre_eglise'] ?? [];
	$autre_type_eglise = $_POST['exp_autre_type'] ?? [];
	$autre_poste = $_POST['exp_autre_poste'] ?? [];
	$autre_debut = $_POST['exp_autre_debut'] ?? [];
	$autre_fin = $_POST['exp_autre_fin'] ?? [];
	$autre_real = $_POST['exp_autre_real'] ?? [];
	if (is_array($autre_eglise)) {
		$stmt = $mysqli->prepare("INSERT INTO responsabilites (id_membre, id_poste, type_experience, eglise, type_eglise, annee_debut, annee_fin, realisations) VALUES (?,?,?,?,?,?,?,?)");
		// Map poste name to id via helper: use stored procedure through select
		$getId = $mysqli->prepare("SELECT id_poste FROM postes WHERE nom_poste_fr = ? LIMIT 1");
		foreach ($autre_eglise as $i => $eg) {
			$eg = trim($eg ?? '');
			if ($eg === '') continue;
			$tp = $autre_type_eglise[$i] ?? 'Église principale';
			$pn = trim($autre_poste[$i] ?? '');
			$adeb = !empty($autre_debut[$i]) ? (int)$autre_debut[$i] : null;
			$afin = !empty($autre_fin[$i]) ? (int)$autre_fin[$i] : null;
			$real = trim($autre_real[$i] ?? '');
			$id_poste = null;
			if ($pn !== '') {
				$getId->bind_param('s', $pn);
				$getId->execute(); $res2 = $getId->get_result();
				if ($res2 && ($row=$res2->fetch_assoc())) { $id_poste = (int)$row['id_poste']; }
				if (!$id_poste) { // create if missing
					$mysqli->query("INSERT INTO postes (nom_poste_fr, nom_poste_mg, categorie) VALUES ('".$mysqli->real_escape_string($pn)."', '".$mysqli->real_escape_string($pn)."', 'Autre')");
					$id_poste = (int)$mysqli->insert_id;
				}
			}
			if ($id_poste) {
				$te = 'Assumé';
				$stmt->bind_param('iisssiss', $id_membre, $id_poste, $te, $eg, $tp, $adeb, $afin, $real);
				$stmt->execute();
			}
		}
		$getId->close();
		$stmt->close();
	}

	header('Location: part3_competences.php');
	exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Partie II – Expérience et engagement</title>
	<link rel="stylesheet" href="assets/styles.css">
	<link rel="icon" href="data:,">
	<style>.small{font-size:12px;color:#6b7280}</style>
</head>
<body>
	<nav class="nav">
		<div class="logo">SDA Ambolakandrina</div>
		<div><a href="index.php">Accueil</a></div>
	</nav>
	<div class="container">
		<form class="glass" action="part2_responsabilites.php" method="post">
			<div class="section-title">Partie II : Expérience et engagement</div>
			<div class="section-title" style="margin-top:0">1. Responsabilités assumées / possibles</div>
			<div class="row">
				<div class="small">Sélectionnez un poste et un type d'expérience puis cliquez Suivant. Vous pouvez ajouter d'autres postes dans la section suivante en détaillant.</div>
			</div>
			<div class="row two">
				<div>
					<label>Poste</label>
					<select name="poste[]">
						<option value="">—</option>
						<?php foreach ($postes as $p): ?>
							<option value="<?php echo (int)$p['id_poste']; ?>"><?php echo htmlspecialchars($p['nom_poste_fr'], ENT_QUOTES, 'UTF-8'); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label>Type d'expérience</label>
					<select name="type_experience[]">
						<option>Assumé</option>
						<option>Pourrait assumer</option>
						<option>Souhaite assumer</option>
					</select>
				</div>
			</div>

			<div class="section-title" style="margin-top:16px">2. Expérience dans d'autres églises</div>
			<div class="grid">
				<div>
					<div class="row two">
						<div>
							<label>Nom de l'église</label>
							<input type="text" name="exp_autre_eglise[]">
						</div>
						<div>
							<label>Type d'église</label>
							<select name="exp_autre_type[]"><option>Église principale</option><option>Église annexe</option></select>
						</div>
					</div>
					<div class="row two">
						<div>
							<label>Responsabilités exercées (nom du poste)</label>
							<input type="text" name="exp_autre_poste[]">
						</div>
						<div class="row two">
							<div>
								<label>Année début</label>
								<input type="number" name="exp_autre_debut[]" min="1900" max="2100">
							</div>
							<div>
								<label>Année fin</label>
								<input type="number" name="exp_autre_fin[]" min="1900" max="2100">
							</div>
						</div>
					</div>
					<div class="row">
						<label>Réalisations particulières</label>
						<textarea name="exp_autre_real[]"></textarea>
					</div>
				</div>
			</div>
			<div class="actions">
				<a class="button" href="part1_membre.php">Retour</a>
				<button class="button primary" type="submit">Suivant: Partie III</button>
			</div>
		</form>
	</div>
	<div class="footer">© 2025 Église Adventiste Ambolakandrina</div>
</body>
</html>


