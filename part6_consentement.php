<?php
session_start();
require __DIR__ . '/config.php';

$id_membre = $_SESSION['id_membre'] ?? null;
if (!$id_membre) { header('Location: part1_membre.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$consent = ($_POST['consentement'] ?? 'Accepté') === 'Refusé' ? 'Refusé' : 'Accepté';
	$ip = $_SERVER['REMOTE_ADDR'] ?? null;
	$stmt = $mysqli->prepare("INSERT INTO consentements (id_membre, consentement, adresse_ip) VALUES (?,?,?) ON DUPLICATE KEY UPDATE consentement=VALUES(consentement), adresse_ip=VALUES(adresse_ip)");
	$stmt->bind_param('iss', $id_membre, $consent, $ip);
	$stmt->execute(); $stmt->close();
	// Clear session current flow
	unset($_SESSION['id_membre']);
	header('Location: membres.php');
	exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Partie VI – Consentement</title>
	<link rel="stylesheet" href="assets/styles.css">
	<link rel="icon" href="data:,">
</head>
<body>
	<nav class="nav">
		<div class="logo">SDA Ambolakandrina</div>
		<div><a href="index.php">Accueil</a></div>
	</nav>
	<div class="container">
		<form class="glass" action="part6_consentement.php" method="post">
			<div class="section-title">Partie VI : Consentement</div>
			<p>« Je confirme que tous les renseignements fournis sont exacts et seront utilisés dans le système informatisé de prise de rendez-vous de l’église d’Ambolakandrina, conformément aux règles de l’Église adventiste du septième jour. »</p>
			<div class="row">
				<select name="consentement"><option>Accepté</option><option>Refusé</option></select>
			</div>
			<div class="actions">
				<a class="button" href="part5_references.php">Retour</a>
				<button class="button primary" type="submit">Terminer et afficher les membres</button>
			</div>
		</form>
	</div>
	<div class="footer">© 2025 Église Adventiste Ambolakandrina</div>
</body>
</html>


