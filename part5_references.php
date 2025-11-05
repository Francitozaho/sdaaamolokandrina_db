<?php
session_start();
require __DIR__ . '/config.php';

$id_membre = $_SESSION['id_membre'] ?? null;
if (!$id_membre) { header('Location: part1_membre.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Ensure the `references` table exists (reserved word, so backticks are required)
	$ddl = "CREATE TABLE IF NOT EXISTS `references` (
		id_reference INT AUTO_INCREMENT PRIMARY KEY,
		id_membre INT NOT NULL,
		nom_referent VARCHAR(255) NOT NULL,
		fonction_referent VARCHAR(255),
		telephone_referent VARCHAR(20),
		email_referent VARCHAR(100),
		commentaire TEXT,
		date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		FOREIGN KEY (id_membre) REFERENCES membres(id_membre) ON DELETE CASCADE,
		INDEX idx_membre (id_membre)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
	$mysqli->query($ddl);
	$nom = $_POST['nom_referent'] ?? [];
	$fonction = $_POST['fonction_referent'] ?? [];
	$tel = $_POST['telephone_referent'] ?? [];
	$email = $_POST['email_referent'] ?? [];
	$comment = $_POST['commentaire'] ?? [];
$stmt = $mysqli->prepare("INSERT INTO `references` (id_membre, nom_referent, fonction_referent, telephone_referent, email_referent, commentaire) VALUES (?,?,?,?,?,?)");
	for ($i = 0; $i < count($nom); $i++) {
		$n = trim($nom[$i] ?? ''); if ($n === '') continue;
		$f = trim($fonction[$i] ?? '');
		$t = trim($tel[$i] ?? '');
		$e = trim($email[$i] ?? '');
		$c = trim($comment[$i] ?? '');
		$stmt->bind_param('isssss', $id_membre, $n, $f, $t, $e, $c);
		$stmt->execute();
	}
	$stmt->close();
	header('Location: part6_consentement.php');
	exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Partie V – Évaluation et témoignages</title>
	<link rel="stylesheet" href="assets/styles.css">
	<link rel="icon" href="data:,">
</head>
<body>
	<nav class="nav">
		<div class="logo">SDA Ambolakandrina</div>
		<div><a href="index.php">Accueil</a></div>
	</nav>
	<div class="container">
		<form class="glass" action="part5_references.php" method="post">
			<div class="section-title">Partie V : Évaluation et témoignages</div>
			<?php for ($i=0;$i<2;$i++): ?>
				<div class="glass" style="padding:16px; margin-bottom:12px;">
					<div class="row two">
						<div>
							<label>Nom</label>
							<input type="text" name="nom_referent[]">
						</div>
						<div>
							<label>Fonction</label>
							<input type="text" name="fonction_referent[]">
						</div>
					</div>
					<div class="row two">
						<div>
							<label>Téléphone</label>
							<input type="text" name="telephone_referent[]">
						</div>
						<div>
							<label>Email</label>
							<input type="email" name="email_referent[]">
						</div>
					</div>
					<div class="row">
						<label>Commentaires</label>
						<textarea name="commentaire[]"></textarea>
					</div>
				</div>
			<?php endfor; ?>
			<div class="actions">
				<a class="button" href="part4_exigences.php">Retour</a>
				<button class="button primary" type="submit">Suivant: Partie VI</button>
			</div>
		</form>
	</div>
	<div class="footer">© 2025 Église Adventiste Ambolakandrina</div>
</body>
</html>


