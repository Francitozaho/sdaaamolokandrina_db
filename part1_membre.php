<?php
session_start();
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	function sanitize_enum($value, $allowed, $default) {
		if (!is_string($value)) return $default;
		return in_array($value, $allowed, true) ? $value : $default;
	}

	$photoPath = null;
	if (!empty($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
		$uploads = __DIR__ . '/uploads';
		if (!is_dir($uploads)) { @mkdir($uploads, 0777, true); }
		$ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
		$fname = 'membre_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
		$target = $uploads . '/' . $fname;
		if (@move_uploaded_file($_FILES['photo']['tmp_name'], $target)) { $photoPath = 'uploads/' . $fname; }
	}

	$nom_complet = isset($_POST['nom_complet']) ? trim($_POST['nom_complet']) : '';
	$sexe = sanitize_enum($_POST['sexe'] ?? '', ['Homme','Femme'], 'Homme');
	$date_naissance = $_POST['date_naissance'] ?? null;
	$annee_bapteme = isset($_POST['annee_bapteme']) && $_POST['annee_bapteme'] !== '' ? (int)$_POST['annee_bapteme'] : null;
	$souvenir_date_bapteme = sanitize_enum($_POST['souvenir_date_bapteme'] ?? 'Non', ['Oui','Non'], 'Non');
	$date_exacte_bapteme = !empty($_POST['date_exacte_bapteme']) ? $_POST['date_exacte_bapteme'] : null;
	$telephone = $_POST['telephone'] ?? null;
	$email = $_POST['email'] ?? null;
	$eglise_actuelle = $_POST['eglise_actuelle'] ?? 'Ambolakandrina';
	$eglise_precedente = $_POST['eglise_precedente'] ?? null;
	$adresse = $_POST['adresse'] ?? null;

	$mercredi_soir = sanitize_enum($_POST['mercredi_soir'] ?? 'Rarement', ["Fréquente","Régulièrement","Rarement","N'y va jamais"], 'Rarement');
	$vendredi_soir = sanitize_enum($_POST['vendredi_soir'] ?? 'Rarement', ["Fréquente","Régulièrement","Rarement","N'y va jamais"], 'Rarement');
	$samedi_matin = sanitize_enum($_POST['samedi_matin'] ?? 'Fréquente', ["Fréquente","Régulièrement","Rarement","N'y va jamais"], 'Fréquente');
	$samedi_soir = sanitize_enum($_POST['samedi_soir'] ?? 'Rarement', ["Fréquente","Régulièrement","Rarement","N'y va jamais"], 'Rarement');

	// require name, birth date, and at least an year OR an exact date for baptism
	if ($nom_complet === '' || empty($date_naissance) || (empty($annee_bapteme) && empty($date_exacte_bapteme))) {
		die('Champs requis manquants. Veuillez préciser au moins l\'année ou la date exacte du baptême.');
	}

	// compute anciennete_bapteme (years since baptism) server-side for consistency
	$anciennete_bapteme = null;
	try {
		if (!empty($date_exacte_bapteme)) {
			$dt = new DateTime($date_exacte_bapteme);
			$now = new DateTime();
			$anciennete_bapteme = (int)$dt->diff($now)->y;
		} elseif (!empty($annee_bapteme)) {
			$anciennete_bapteme = (int)date('Y') - (int)$annee_bapteme;
		}
		if ($anciennete_bapteme !== null && $anciennete_bapteme < 0) { $anciennete_bapteme = 0; }
	} catch (Exception $e) {
		$anciennete_bapteme = null;
	}

	$mysqli->begin_transaction();
	try {
	// include anciennete_bapteme so it is stored and available for listing
	$stmt = $mysqli->prepare("INSERT INTO membres (photo, nom_complet, date_naissance, annee_bapteme, date_exacte_bapteme, anciennete_bapteme, souvenir_date_bapteme, sexe, adresse, telephone, email, eglise_actuelle, eglise_precedente) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
		if (!$stmt) throw new Exception($mysqli->error);
	// types: s(photo), s(nom), s(date_naissance), i(annee), s(date_exacte), i(anciennete), s(souvenir), s(sexe), s(adresse), s(telephone), s(email), s(eglise_actuelle), s(eglise_precedente)
	$stmt->bind_param('sssisisssssss', $photoPath, $nom_complet, $date_naissance, $annee_bapteme, $date_exacte_bapteme, $anciennete_bapteme, $souvenir_date_bapteme, $sexe, $adresse, $telephone, $email, $eglise_actuelle, $eglise_precedente);
		$stmt->execute();
		$id_membre = $stmt->insert_id; $stmt->close();

		$stmt = $mysqli->prepare("INSERT INTO pratique_religieuse (id_membre, mercredi_soir, vendredi_soir, samedi_matin, samedi_soir) VALUES (?,?,?,?,?)");
		if (!$stmt) throw new Exception($mysqli->error);
		$stmt->bind_param('issss', $id_membre, $mercredi_soir, $vendredi_soir, $samedi_matin, $samedi_soir);
		$stmt->execute(); $stmt->close();

		$mysqli->commit();
		$_SESSION['id_membre'] = $id_membre;
		header('Location: part2_responsabilites.php');
		exit;
	} catch (Throwable $e) {
		$mysqli->rollback();
		die('Erreur: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
	}
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Partie I – Informations de base</title>
	<link rel="stylesheet" href="assets/styles.css">
	<link rel="icon" href="data:,">
	<script>
		function toggleDateBapteme(v){
			var f = document.getElementById('date-exacte');
			f.style.display = (v === 'Oui') ? 'block' : 'none';
			// recalc when showing/hiding
			calcAnciennete();
		}

		function calcAnciennete(){
			var yearEl = document.getElementById('annee_bapteme');
			var dateEl = document.getElementById('date_exacte_bapteme');
			var out = document.getElementById('anciennete');
			var now = new Date();
			var val = '—';
			// prefer exact date if provided
			if(dateEl && dateEl.value){
				var d = new Date(dateEl.value);
				if (!isNaN(d.getTime())) {
					var years = now.getFullYear() - d.getFullYear();
					// adjust if birthday not reached this year
					var m = now.getMonth() - d.getMonth();
					if (m < 0 || (m === 0 && now.getDate() < d.getDate())) years--;
					if (years < 0) years = 0;
					val = years + ' ans';
				}
			} else if (yearEl && yearEl.value){
				var y = parseInt(yearEl.value, 10);
				if (!isNaN(y) && y > 0) {
					val = (now.getFullYear() - y) + ' ans';
					if (parseInt(val) < 0) val = '0 ans';
				}
			}
			if (out) out.textContent = val;
		}

		// initialize listeners after DOM load
		document.addEventListener('DOMContentLoaded', function(){
			var yearEl = document.getElementById('annee_bapteme');
			var dateEl = document.getElementById('date_exacte_bapteme');
			if (yearEl) yearEl.addEventListener('input', calcAnciennete);
			if (dateEl) dateEl.addEventListener('change', calcAnciennete);
			calcAnciennete();
		});
	</script>
</head>
<body>
	<nav class="nav">
		<div class="logo">SDA Ambolakandrina</div>
		<div><a href="index.php">Accueil</a></div>
	</nav>
	<div class="container">
		<form class="glass" action="part1_membre.php" method="post" enctype="multipart/form-data">
			<div class="section-title">Partie I : Informations de base</div>
			<div class="grid">
				<div>
					<div class="row">
						<label>Photo récente</label>
						<input type="file" name="photo" accept="image/*">
					</div>
					<div class="row two">
						<div>
							<label>Nom complet</label>
							<input type="text" name="nom_complet" required>
						</div>
						<div>
							<label>Sexe</label>
							<select name="sexe" required>
								<option>Homme</option>
								<option>Femme</option>
							</select>
						</div>
					</div>
					<div class="row two">
						<div>
							<label>Date de naissance</label>
							<input type="date" name="date_naissance" required>
						</div>
						<div>
							<label>Année de baptême</label>
							<input id="annee_bapteme" type="number" name="annee_bapteme" min="1900" max="2100">
						</div>
					</div>
					<div class="row two">
						<div>
							<label>Souvenir de la date de baptême</label>
							<select name="souvenir_date_bapteme" onchange="toggleDateBapteme(this.value)">
								<option>Non</option>
								<option>Oui</option>
							</select>
						</div>
						<div id="date-exacte" style="display:none;">
							<label>Date exacte du baptême</label>
							<input id="date_exacte_bapteme" type="date" name="date_exacte_bapteme">
							<div style="font-size:12px; color:#555; margin-top:6px;">Vous avez été baptisé(e) il y a <span id="anciennete">—</span>.</div>
						</div>
					</div>
					<div class="row">
						<label>Adresse</label>
						<textarea name="adresse"></textarea>
					</div>
					<div class="row two">
						<div>
							<label>Portable</label>
							<input type="tel" name="telephone">
						</div>
						<div>
							<label>Courriel</label>
							<input type="email" name="email">
						</div>
					</div>
					<div class="row two">
						<div>
							<label>Église actuelle</label>
							<input type="text" name="eglise_actuelle" value="Ambolakandrina">
						</div>
						<div>
							<label>Église précédente</label>
							<input type="text" name="eglise_precedente">
						</div>
					</div>
				</div>
				<div>
					<div class="section-title">Pratique religieuse</div>
					<div class="row two">
						<div>
							<label>Mercredi soir</label>
							<select name="mercredi_soir">
								<option>Fréquente</option>
								<option>Régulièrement</option>
								<option>Rarement</option>
								<option>N'y va jamais</option>
							</select>
						</div>
						<div>
							<label>Vendredi soir</label>
							<select name="vendredi_soir">
								<option>Fréquente</option>
								<option>Régulièrement</option>
								<option>Rarement</option>
								<option>N'y va jamais</option>
							</select>
						</div>
					</div>
					<div class="row two">
						<div>
							<label>Samedi matin</label>
							<select name="samedi_matin">
								<option>Fréquente</option>
								<option>Régulièrement</option>
								<option>Rarement</option>
								<option>N'y va jamais</option>
							</select>
						</div>
						<div>
							<label>Samedi soir</label>
							<select name="samedi_soir">
								<option>Fréquente</option>
								<option>Régulièrement</option>
								<option>Rarement</option>
								<option>N'y va jamais</option>
							</select>
						</div>
					</div>
				</div>
			</div>
			<div class="actions">
				<button class="button primary" type="submit">Suivant: Partie II</button>
			</div>
		</form>
	</div>
	<div class="footer">© 2025 Église Adventiste Ambolakandrina</div>
</body>
</html>


