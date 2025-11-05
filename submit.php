<?php
require __DIR__ . '/config.php';

function sanitize_enum($value, $allowed, $default) {
	if (!is_string($value)) return $default;
	return in_array($value, $allowed, true) ? $value : $default;
}

// Handle upload
$photoPath = null;
if (!empty($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
	$uploads = __DIR__ . '/uploads';
	if (!is_dir($uploads)) {
		@mkdir($uploads, 0777, true);
	}
	$ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
	$fname = 'membre_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
	$target = $uploads . '/' . $fname;
	if (@move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
		$photoPath = 'uploads/' . $fname;
	}
}

// Collect inputs
$nom_complet = isset($_POST['nom_complet']) ? trim($_POST['nom_complet']) : '';
$sexe = sanitize_enum($_POST['sexe'] ?? '', ['Homme','Femme'], 'Homme');
$date_naissance = $_POST['date_naissance'] ?? null;
$annee_bapteme = isset($_POST['annee_bapteme']) ? (int)$_POST['annee_bapteme'] : null;
$souvenir_date_bapteme = sanitize_enum($_POST['souvenir_date_bapteme'] ?? 'Non', ['Oui','Non'], 'Non');
$date_exacte_bapteme = $_POST['date_exacte_bapteme'] ?? null;
$telephone = $_POST['telephone'] ?? null;
$email = $_POST['email'] ?? null;
$eglise_actuelle = $_POST['eglise_actuelle'] ?? 'Ambolakandrina';
$eglise_precedente = $_POST['eglise_precedente'] ?? null;
$adresse = $_POST['adresse'] ?? null;

$mercredi_soir = sanitize_enum($_POST['mercredi_soir'] ?? 'Rarement', ["Fréquente","Régulièrement","Rarement","N'y va jamais"], 'Rarement');
$vendredi_soir = sanitize_enum($_POST['vendredi_soir'] ?? 'Rarement', ["Fréquente","Régulièrement","Rarement","N'y va jamais"], 'Rarement');
$samedi_matin = sanitize_enum($_POST['samedi_matin'] ?? 'Fréquente', ["Fréquente","Régulièrement","Rarement","N'y va jamais"], 'Fréquente');
$samedi_soir = sanitize_enum($_POST['samedi_soir'] ?? 'Rarement', ["Fréquente","Régulièrement","Rarement","N'y va jamais"], 'Rarement');

$actif_vie_religieuse = sanitize_enum($_POST['actif_vie_religieuse'] ?? 'Oui', ['Oui','Non'], 'Oui');
$conformite_principes = sanitize_enum($_POST['conformite_principes'] ?? 'Oui', ['Oui','Non'], 'Oui');
$disponible_2026 = sanitize_enum($_POST['disponible_2026'] ?? 'Je ne sais pas', ['Oui','Non','Je ne sais pas'], 'Je ne sais pas');
$facilement_joignable = sanitize_enum($_POST['facilement_joignable'] ?? 'Oui', ['Oui','Non'], 'Oui');
$observations = $_POST['observations'] ?? null;

$consentement = sanitize_enum($_POST['consentement'] ?? 'Accepté', ['Accepté','Refusé'], 'Accepté');

if ($nom_complet === '' || empty($date_naissance) || empty($annee_bapteme)) {
	die('Champs requis manquants.');
}

$mysqli->begin_transaction();
try {
	// Insert membre
	$stmt = $mysqli->prepare("INSERT INTO membres (photo, nom_complet, date_naissance, annee_bapteme, souvenir_date_bapteme, date_exacte_bapteme, sexe, adresse, telephone, email, eglise_actuelle, eglise_precedente) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
	if (!$stmt) throw new Exception($mysqli->error);
	$stmt->bind_param(
		'sssissssssss',
		$photoPath,
		$nom_complet,
		$date_naissance,
		$annee_bapteme,
		$souvenir_date_bapteme,
		$date_exacte_bapteme,
		$sexe,
		$adresse,
		$telephone,
		$email,
		$eglise_actuelle,
		$eglise_precedente
	);
	$stmt->execute();
	$id_membre = $stmt->insert_id;
	$stmt->close();

	// Insert pratique_religieuse
	$stmt = $mysqli->prepare("INSERT INTO pratique_religieuse (id_membre, mercredi_soir, vendredi_soir, samedi_matin, samedi_soir) VALUES (?,?,?,?,?)");
	if (!$stmt) throw new Exception($mysqli->error);
	$stmt->bind_param('issss', $id_membre, $mercredi_soir, $vendredi_soir, $samedi_matin, $samedi_soir);
	$stmt->execute();
	$stmt->close();

	// Insert exigences_religieuses
	$stmt = $mysqli->prepare("INSERT INTO exigences_religieuses (id_membre, actif_vie_religieuse, conformite_principes, disponible_2026, facilement_joignable, observations) VALUES (?,?,?,?,?,?)");
	if (!$stmt) throw new Exception($mysqli->error);
	$stmt->bind_param('isssss', $id_membre, $actif_vie_religieuse, $conformite_principes, $disponible_2026, $facilement_joignable, $observations);
	$stmt->execute();
	$stmt->close();

	// Insert consentements
	$stmt = $mysqli->prepare("INSERT INTO consentements (id_membre, consentement, adresse_ip) VALUES (?,?,?)");
	if (!$stmt) throw new Exception($mysqli->error);
	$ip = $_SERVER['REMOTE_ADDR'] ?? null;
	$stmt->bind_param('iss', $id_membre, $consentement, $ip);
	$stmt->execute();
	$stmt->close();

	$mysqli->commit();
} catch (Throwable $e) {
	$mysqli->rollback();
	die('Erreur lors de l\'enregistrement: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Inscription enregistrée</title>
	<link rel="stylesheet" href="assets/styles.css">
	<script>
		window.addEventListener('load', function() {
			try { window.open('membres.php', '_blank'); } catch (e) {}
		});
	</script>
</head>
<body>
	<div class="parallax" style="min-height: 30vh;">
		<div class="hero" style="padding: 60px 20px 90px 20px;">
			<div class="glass" style="display:inline-block; max-width: 840px;">
				<h1>Merci, inscription enregistrée.</h1>
				<p>Une nouvelle fenêtre s'ouvre avec la liste des membres.</p>
				<div style="margin-top:14px; display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
					<a class="button primary" href="membres.php" target="_blank">Ouvrir la liste</a>
					<a class="button" href="index.php">Nouvelle inscription</a>
				</div>
			</div>
		</div>
		<div class="bubble b1"></div>
		<div class="bubble b2"></div>
		<div class="bubble b3"></div>
	</div>
	<div class="footer">© 2025 Église Adventiste Ambolakandrina</div>
</body>
</html>


