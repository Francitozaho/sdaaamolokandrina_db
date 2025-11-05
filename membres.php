<?php
require __DIR__ . '/config.php';

function fetch_membres($mysqli) {
	$sql = "SELECT * FROM vue_membres_complet";
	$res = $mysqli->query($sql);
	if ($res === false) {
		// Fallback join if view not available
		$sql = "SELECT 
			m.id_membre,m.photo,m.nom_complet,m.date_naissance,m.annee_bapteme,m.date_exacte_bapteme,m.souvenir_date_bapteme,m.anciennete_bapteme,
			m.sexe,m.adresse,m.telephone,m.email,m.eglise_actuelle,m.eglise_precedente,m.statut,
			pr.mercredi_soir,pr.vendredi_soir,pr.samedi_matin,pr.samedi_soir,pr.score_assiduite,
			er.actif_vie_religieuse,er.conformite_principes,er.disponible_2026,er.facilement_joignable,er.observations,
			c.consentement,
			GROUP_CONCAT(DISTINCT p.nom_poste_fr SEPARATOR ', ') AS postes,
			GROUP_CONCAT(DISTINCT comp.nom_competence_fr SEPARATOR ', ') AS competences,
			GROUP_CONCAT(DISTINCT CONCAT(comp.nom_competence_fr, ' (', mc.niveau, ')') SEPARATOR ', ') AS competences_niveaux
		FROM membres m
		LEFT JOIN pratique_religieuse pr ON m.id_membre = pr.id_membre
		LEFT JOIN exigences_religieuses er ON m.id_membre = er.id_membre
		LEFT JOIN consentements c ON m.id_membre = c.id_membre
		LEFT JOIN responsabilites r ON m.id_membre = r.id_membre
		LEFT JOIN postes p ON r.id_poste = p.id_poste
		LEFT JOIN membres_competences mc ON m.id_membre = mc.id_membre
		LEFT JOIN competences comp ON mc.id_competence = comp.id_competence
		WHERE m.statut = 'Actif'
		GROUP BY m.id_membre
		ORDER BY m.id_membre DESC";
		$res = $mysqli->query($sql);
	}
 
 // Extra details per membre
 function fetch_responsabilites($mysqli, $id_membre) {
 	$rows = [];
 	$sql = "SELECT r.id_responsabilite, p.nom_poste_fr, r.type_experience, r.eglise, r.type_eglise, r.annee_debut, r.annee_fin, r.annees_experience, r.realisations
 		FROM responsabilites r
 		JOIN postes p ON p.id_poste = r.id_poste
 		WHERE r.id_membre = ?
 		ORDER BY COALESCE(r.annee_fin, 9999) DESC, COALESCE(r.annee_debut, 0) DESC";
 	$stmt = $mysqli->prepare($sql);
 	if ($stmt) {
 		$stmt->bind_param('i', $id_membre);
 		$stmt->execute();
 		$res = $stmt->get_result();
 		while ($res && ($row = $res->fetch_assoc())) { $rows[] = $row; }
 		$stmt->close();
 	}
 	return $rows;
 }
 
 function fetch_competences_detail($mysqli, $id_membre) {
 	$rows = [];
 	$sql = "SELECT c.nom_competence_fr, mc.niveau, mc.note_auto_evaluation
 		FROM membres_competences mc
 		JOIN competences c ON c.id_competence = mc.id_competence
 		WHERE mc.id_membre = ?
 		ORDER BY c.nom_competence_fr";
 	$stmt = $mysqli->prepare($sql);
 	if ($stmt) {
 		$stmt->bind_param('i', $id_membre);
 		$stmt->execute();
 		$res = $stmt->get_result();
 		while ($res && ($row = $res->fetch_assoc())) { $rows[] = $row; }
 		$stmt->close();
 	}
 	return $rows;
 }
 
 function fetch_references($mysqli, $id_membre) {
 	$rows = [];
 	$sql = "SELECT nom_referent, fonction_referent, telephone_referent, email_referent, commentaire
 		FROM `references`
 		WHERE id_membre = ?
 		ORDER BY date_ajout DESC";
 	$stmt = $mysqli->prepare($sql);
 	if ($stmt) {
 		$stmt->bind_param('i', $id_membre);
 		$stmt->execute();
 		$res = $stmt->get_result();
 		while ($res && ($row = $res->fetch_assoc())) { $rows[] = $row; }
 		$stmt->close();
 	}
 	return $rows;
 }
	$rows = [];
	if ($res) {
		while ($row = $res->fetch_assoc()) { $rows[] = $row; }
		$res->free();
	}
	return $rows;
}

$membres = fetch_membres($mysqli);

// Bulk recompute anciennete_bapteme for records missing a value.
function recompute_anciennetes_missing($mysqli) {
	$out = ['processed' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
	// select rows where anciennete_bapteme IS NULL or empty
	$sql = "SELECT id_membre, annee_bapteme, date_exacte_bapteme FROM membres WHERE anciennete_bapteme IS NULL OR anciennete_bapteme = ''";
	$res = $mysqli->query($sql);
	if ($res === false) {
		$out['errors'][] = $mysqli->error;
		return $out;
	}
	$now = new DateTime();
	$updateStmt = $mysqli->prepare("UPDATE membres SET anciennete_bapteme = ? WHERE id_membre = ?");
	if (!$updateStmt) {
		$out['errors'][] = $mysqli->error;
		return $out;
	}
	while ($row = $res->fetch_assoc()) {
		$out['processed']++;
		$id = (int)$row['id_membre'];
		$anciennete = null;
		try {
			if (!empty($row['date_exacte_bapteme'])) {
				$dt = new DateTime($row['date_exacte_bapteme']);
				$anciennete = (int)$dt->diff($now)->y;
			} elseif (!empty($row['annee_bapteme'])) {
				$y = (int)$row['annee_bapteme'];
				$anciennete = (int)$now->format('Y') - $y;
			}
			if ($anciennete === null) {
				$out['skipped']++;
				continue;
			}
			if ($anciennete < 0) $anciennete = 0;
			$updateStmt->bind_param('ii', $anciennete, $id);
			if ($updateStmt->execute()) {
				$out['updated']++;
			} else {
				$out['errors'][] = "id {$id}: " . $updateStmt->error;
			}
		} catch (Exception $e) {
			$out['errors'][] = "id {$id}: " . $e->getMessage();
		}
	}
	$updateStmt->close();
	$res->free();
	return $out;
}

// If triggered via query param ?recalc_anciennete=1, run the bulk recompute and show a short report.
if (isset($_GET['recalc_anciennete']) && $_GET['recalc_anciennete'] === '1') {
	$report = recompute_anciennetes_missing($mysqli);
	header('Content-Type: text/plain; charset=utf-8');
	echo "Recompute anciennete report:\n";
	echo "Processed: " . (int)$report['processed'] . "\n";
	echo "Updated: " . (int)$report['updated'] . "\n";
	echo "Skipped (no date/year): " . (int)$report['skipped'] . "\n";
	if (!empty($report['errors'])) {
		echo "Errors:\n" . implode("\n", $report['errors']) . "\n";
	}
	exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Membres inscrits</title>
	<link rel="stylesheet" href="assets/styles.css">
	<link rel="stylesheet" href="hover-effects.css">
	<link rel="stylesheet" href="competence-level-styles.css">
	<link rel="stylesheet" href="poste-comp-styles.css">
	<style>
		.card { border: 1px solid rgba(255,255,255,0.45); border-radius: 20px; }
		.details { display:none; }
		.details.open { display:block; }

		/* Hover 3D effect for member cards */
		.card {
			transform-style: preserve-3d;
			transform: perspective(1000px) translateZ(0);
			will-change: transform, box-shadow;
			transition: transform 220ms ease-out, box-shadow 220ms ease-out;
		}

		/* Lift a bit on hover for pointer devices */
		@media (hover: hover) and (pointer: fine) {
			.card:hover { transform: perspective(1000px) scale(1.02) translateZ(0); }
		}

		/* Sub-elements get a subtle pop when card is hovered */
		.card .chip, .card .badge, .card .section-title, .card img {
			transition: transform 180ms ease-out;
		}
		.card:hover .chip,
		.card:hover .badge,
		.card:hover .section-title {
			transform: translateZ(20px);
		}

		/* Constrain image and give it a smooth transform */
		.member-photo { width:64px; height:64px; object-fit:cover; border-radius:12px; }
		.member-photo-large { max-width: 240px; border-radius:12px; }

		/* Subtle shadow while hovered */
		.card.hovering, .card.expanded { box-shadow: 0 18px 40px rgba(2,6,23,0.12); }

		/* Respect dark mode */
		body[data-theme="dark"] .card { border-color: rgba(255,255,255,0.06); }
		body[data-theme="dark"] .card.hovering, body[data-theme="dark"] .card.expanded { box-shadow: 0 18px 40px rgba(2,6,23,0.36); }

		/* Highlight for competence rows */
		.highlight-row {
			background-color: rgba(59, 130, 246, 0.1);
			transition: background-color 0.3s ease;
		}
		body[data-theme="dark"] .highlight-row {
			background-color: rgba(59, 130, 246, 0.15);
		}
		.table tr.highlight-row td {
			position: relative;
		}
		.table tr.highlight-row td::before {
			content: "";
			position: absolute;
			left: 0;
			top: 0;
			bottom: 0;
			width: 3px;
			background-color: #3b82f6;
		}
	</style>
</head>
<body class="membres">
	<!-- <div class="theme-fab">
		<a class="button ghost" href="javascript:void(0)" data-theme="dark">Dark mode</a>
		<a class="button ghost" href="javascript:void(0)" data-theme="light">Light mode</a>
	</div> -->
	<nav class="nav">
		<div class="logo">SDA Ambolakandrina</div>
		<div style="display:flex; gap:10px; align-items:center;">
			<a href="index.php">Nouvelle inscription</a>
			<div class="theme-switch" style="margin-top:0;">
				<a class="button ghost" href="javascript:void(0)" data-theme="dark">Dark</a>
				<a class="button ghost" href="javascript:void(0)" data-theme="light">Light</a>
			</div>
		</div>
	</nav>
	<div class="parallax" style="min-height: 34vh;">
		<div class="hero" style="padding: 70px 20px 110px 20px;">
			<div class="glass liquid fade-in" style="display:inline-block; max-width: 980px;">
				<h1>Membres inscrits</h1>
				
				<div class="search-container">
					<input type="text" id="searchInput" class="search-input" placeholder="Rechercher par nom, ID, téléphone, compétences...">
					<div class="search-filters">
						<select id="searchField" class="search-select">
							<option value="all">Tous les champs</option>
							<option value="id_membre">ID</option>
							<option value="nom_complet">Nom</option>
							<option value="telephone">Téléphone</option>
							<option value="email">Email</option>
							<option value="anciennete_bapteme">Ancienneté baptême</option>
							<option value="score_assiduite">Score d'assiduité</option>
							<option value="disponible_2026">Disponible 2026</option>
							<option value="competences_avancees">Compétences avancées</option>
							<option value="competences_expert">Compétences expert</option>
						</select>
						<button id="searchClear" class="search-clear" type="button">×</button>
					</div>
					<div id="searchStatus" class="search-status"></div>
				</div>

				<p class="muted">Parcourez les fiches et cliquez sur « Voir plus » pour les détails.</p>
				<!-- theme switch moved to floating control -->
			</div>
		</div>
		<div class="bubble b1" data-parallax-y="0.08"></div>
		<div class="bubble b2" data-parallax-y="0.06"></div>
		<div class="bubble b3" data-parallax-y="0.04"></div>
	</div>
	<!-- <nav class="nav">
		<div class="logo">SDA Ambolakandrina</div>
		<div><a href="index.php">Nouvelle inscription</a></div>
	</nav> -->
	<div class="container">
		<div class="glass liquid">
			<div class="section-title">Membres inscrits</div>
			<?php if (count($membres) === 0): ?>
				<p>Aucun membre pour le moment.</p>
			<?php else: ?>
				<a id="backButton" class="button" style="display:none; margin-bottom:12px;" href="javascript:void(0)">Voir tous</a>
				<div class="grid" id="membersGrid">
				<?php foreach ($membres as $i => $m): $cardId = 'details_' . (int)($m['id_membre'] ?? 0); $rid=(int)($m['id_membre'] ?? 0); $resps = fetch_responsabilites($mysqli, $rid); $comps = fetch_competences_detail($mysqli, $rid); $refs = fetch_references($mysqli, $rid); ?>
					<div class="glass liquid card fade-in" style="padding:16px;" data-parallax-y="<?php echo 0.004 * (($i % 5) + 1); ?>" data-card="<?php echo $cardId; ?>">
						<div style="display:flex; gap:14px; align-items:center;">
							<?php if (!empty($m['photo'])): ?>
								<img class="member-photo" loading="lazy" src="<?php echo htmlspecialchars($m['photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Photo de <?php echo htmlspecialchars($m['nom_complet'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
							<?php else: ?>
								<div class="badge">Pas de photo</div>
							<?php endif; ?>
							<div>
								<div style="font-weight:800; font-size:18px;" data-field="nom_complet"><?php echo htmlspecialchars($m['nom_complet'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
								<div class="muted" data-field="id_membre">ID #<?php echo (int)($m['id_membre'] ?? 0); ?> • Statut: <?php echo htmlspecialchars($m['statut'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
							</div>
						</div>
						<div class="chips">
							<span class="chip">Sexe: <?php echo htmlspecialchars($m['sexe'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
							<span class="chip">Église: <?php echo htmlspecialchars($m['eglise_actuelle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
							<span class="chip" data-field="score_assiduite">Assiduité: <?php echo htmlspecialchars($m['score_assiduite'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
							<span class="chip">Consentement: <?php echo htmlspecialchars($m['consentement'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span>
							<a class="button voir-plus" href="javascript:void(0)" data-toggle-details="<?php echo $cardId; ?>" aria-expanded="false">
								<span class="vp-label">Voir plus</span>
								<span class="vp-icon">▸</span>
							</a>
						</div>
							<div id="<?php echo $cardId; ?>" class="details">
								<button type="button" class="details-close" aria-label="Fermer les détails">✕</button>
						<?php if (!empty($m['photo'])): ?>
							<div class="details-photo">
								<img class="member-photo-large" loading="lazy" src="<?php echo htmlspecialchars($m['photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Photo de <?php echo htmlspecialchars($m['nom_complet'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
							</div>
						<?php endif; ?>
						<div class="grid">
							<div>
								<div class="section-title">Informations de base</div>
								<div class="row">
									<label>Sexe</label>
									<div><?php echo htmlspecialchars($m['sexe'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
								</div>
								<div class="row two">
									<div>
										<label>Date de naissance</label>
										<div><?php echo htmlspecialchars($m['date_naissance'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
									<div>
										<label>Année de baptême</label>
										<div data-field="anciennete_bapteme"><?php echo htmlspecialchars($m['annee_bapteme'] ?? '', ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($m['anciennete_bapteme'] ?? '', ENT_QUOTES, 'UTF-8'); ?> ans)</div>
									</div>
								</div>
								<div class="row two">
									<div>
										<label>Souvenir date baptême</label>
										<div><?php echo htmlspecialchars($m['souvenir_date_bapteme'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
									<div>
										<label>Date exacte baptême</label>
										<div><?php echo htmlspecialchars($m['date_exacte_bapteme'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
										<div style="font-size:13px; color:#374151; margin-top:6px;" class="anciennete-display" data-annee="<?php echo htmlspecialchars($m['annee_bapteme'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-date="<?php echo htmlspecialchars($m['date_exacte_bapteme'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
											Ancienneté: <strong><?php echo ($m['anciennete_bapteme'] !== null && $m['anciennete_bapteme'] !== '') ? ((int)$m['anciennete_bapteme'] . ' ans') : '—'; ?></strong>
										</div>
									</div>
								</div>
								<div class="row">
									<label>Adresse</label>
									<div><?php echo nl2br(htmlspecialchars($m['adresse'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div>
								</div>
								<div class="row two">
									<div>
										<label>Portable</label>
										<div data-field="telephone"><?php echo htmlspecialchars($m['telephone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
									<div>
										<label>Courriel</label>
										<div data-field="email"><?php echo htmlspecialchars($m['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
								</div>
								<div class="row two">
									<div>
										<label>Église actuelle</label>
										<div><?php echo htmlspecialchars($m['eglise_actuelle'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
									<div>
										<label>Église précédente</label>
										<div><?php echo htmlspecialchars($m['eglise_precedente'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
									</div>
								</div>
							</div>
							<div>
								<div class="section-title">Pratique religieuse</div>
								<div class="row two">
									<div><label>Mercredi soir</label><div><?php echo htmlspecialchars($m['mercredi_soir'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div></div>
									<div><label>Vendredi soir</label><div><?php echo htmlspecialchars($m['vendredi_soir'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div></div>
								</div>
								<div class="row two">
									<div><label>Samedi matin</label><div><?php echo htmlspecialchars($m['samedi_matin'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div></div>
									<div><label>Samedi soir</label><div><?php echo htmlspecialchars($m['samedi_soir'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div></div>
								</div>
								<div class="row"><label>Score d'assiduité</label><div><?php echo htmlspecialchars($m['score_assiduite'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div></div>
							</div>
							<div>
								<div class="section-title">Exigences & Consentement</div>
								<div class="row two">
									<div><label>Actif spirituellement</label><div><?php echo htmlspecialchars($m['actif_vie_religieuse'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div></div>
									<div><label>Conformité principes</label><div><?php echo htmlspecialchars($m['conformite_principes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div></div>
								</div>
								<div class="row two">
									<div><label>Disponible 2026</label><div data-field="disponible_2026"><?php echo htmlspecialchars($m['disponible_2026'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div></div>
									<div><label>Facilement joignable</label><div><?php echo htmlspecialchars($m['facilement_joignable'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div></div>
								</div>
								<div class="row"><label>Observations</label><div><?php echo nl2br(htmlspecialchars($m['observations'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div></div>
								<div class="row"><label>Consentement</label><div><?php echo htmlspecialchars($m['consentement'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div></div>
							</div>
						</div>
						<div class="row two" style="margin-top:10px;">
							<div>
								<div class="section-title">Postes (détails)</div>
								<?php if (!$resps): ?>
									<div class="muted">Aucun poste enregistré.</div>
								<?php else: ?>
									<table class="table"><thead><tr><th>Poste</th><th>Type</th><th>Église</th><th>Période</th><th>Réalisations</th></tr></thead><tbody>
									<?php foreach ($resps as $r): ?>
										<tr>
											<td class="poste-title"><?php echo htmlspecialchars($r['nom_poste_fr'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
											<td><span class="type-badge <?php echo strtolower(str_replace(' ', '-', $r['type_experience'] ?? '')); ?>"><?php echo htmlspecialchars($r['type_experience'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
											<td><span class="eglise-info"><?php echo htmlspecialchars(($r['eglise'] ?? '') . ' • ' . ($r['type_eglise'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
											<td><span class="poste-period"><?php echo htmlspecialchars(($r['annee_debut'] ?? '') . ' - ' . ($r['annee_fin'] ?? 'Présent'), ENT_QUOTES, 'UTF-8'); ?></span></td>
											<td class="poste-realisations"><?php echo nl2br(htmlspecialchars($r['realisations'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
										</tr>
									<?php endforeach; ?>
									</tbody></table>
								<?php endif; ?>
							</div>
							<div>
								<div class="section-title">Compétences (niveaux)</div>
								<?php if (!$comps): ?>
									<div class="muted">Aucune compétence enregistrée.</div>
								<?php else: ?>
									<table class="table"><thead><tr><th>Compétence</th><th>Niveau</th><th>Note</th></tr></thead><tbody>
									<?php foreach ($comps as $c): ?>
										<?php
											$niveau_raw = trim((string)($c['niveau'] ?? ''));
											$niveau = htmlspecialchars($niveau_raw, ENT_QUOTES, 'UTF-8');
											// map textual levels to a width percentage for visual bar
											$lvl_lower = strtolower($niveau_raw);
											$width = '60%';
											if (is_numeric($niveau_raw)) {
												$n = (int)$niveau_raw;
												$width = max(8, min(100, $n)) . '%';
											} elseif (strpos($lvl_lower, 'debut') !== false || strpos($lvl_lower, 'début') !== false) {
												$width = '20%';
											} elseif (strpos($lvl_lower, 'inter') !== false) {
												$width = '60%';
											} elseif (strpos($lvl_lower, 'avance') !== false || strpos($lvl_lower, 'avancé') !== false) {
												$width = '85%';
											} elseif (strpos($lvl_lower, 'expert') !== false) {
												$width = '98%';
											}
										?>
										<tr>
											<td><span class="competence-name"><?php echo htmlspecialchars($c['nom_competence_fr'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></td>
											<td>
												<div class="level-badge" data-level="<?php echo strtolower($niveau_raw); ?>"><?php echo $niveau ?: '-'; ?></div>
												<div class="level-bar" aria-hidden="true">
													<span style="width: <?php echo $width; ?>;" title="<?php echo $niveau; ?>"></span>
												</div>
											</td>
											<td><span class="auto-eval"><?php echo htmlspecialchars($c['note_auto_evaluation'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></td>
										</tr>
									<?php endforeach; ?>
									</tbody></table>
								<?php endif; ?>
							</div>
						</div>
						<div class="row" style="margin-top:10px;">
							<div>
								<div class="section-title">Références</div>
								<?php if (!$refs): ?>
									<div class="muted">Aucune référence enregistrée.</div>
								<?php else: ?>
									<table class="table"><thead><tr><th>Nom</th><th>Fonction</th><th>Contact</th><th>Commentaires</th></tr></thead><tbody>
									<?php foreach ($refs as $rf): ?>
										<tr>
											<td><?php echo htmlspecialchars($rf['nom_referent'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars($rf['fonction_referent'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars(trim(($rf['telephone_referent'] ?? '') . ' ' . ($rf['email_referent'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo nl2br(htmlspecialchars($rf['commentaire'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
										</tr>
									<?php endforeach; ?>
									</tbody></table>
								<?php endif; ?>
							</div>
						</div>
						</div>
					</div>
				<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<div class="footer">© 2025 Église Adventiste Ambolakandrina</div>
	<script src="assets/app.js"></script>
	<script>
		(function(){
			// Theme restore (fallback to system preference)
			var saved = null;
			try { saved = localStorage.getItem('theme'); } catch(e) {}
			if (saved === 'dark') { document.body.setAttribute('data-theme', 'dark'); }
			else if (!saved) {
				var prefersDark = false;
				try { prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches; } catch(e) {}
				if (prefersDark) document.body.setAttribute('data-theme','dark');
			}
			// Theme toggle (only buttons in theme-switch)
			var themeButtons = document.querySelectorAll('.theme-switch [data-theme], .theme-fab [data-theme]');
			themeButtons.forEach(function(b){
				b.addEventListener('click', function(){
					var mode = b.getAttribute('data-theme');
					if (mode === 'dark') document.body.setAttribute('data-theme','dark');
					else document.body.removeAttribute('data-theme');
					try { localStorage.setItem('theme', mode === 'dark' ? 'dark' : ''); } catch(e) {}
				});
			});

			var buttons = document.querySelectorAll('[data-toggle-details]');
			var grid = document.getElementById('membersGrid');
			var back = document.getElementById('backButton');
			buttons.forEach(function(btn){
				btn.addEventListener('click', function(){
					var id = btn.getAttribute('data-toggle-details');
					var el = document.getElementById(id);
					if (!el) return;
					var card = btn.closest('.card');
					var isOpen = el.classList.contains('open');
					if (!isOpen) {
						// OPEN
						el.classList.add('open');
						btn.setAttribute('aria-expanded','true');
						var label = btn.querySelector('.vp-label'); if (label) label.textContent = 'Voir moins';
						if (card) card.classList.add('expanded');
						if (grid) {
							var cards = grid.querySelectorAll('[data-card]');
							cards.forEach(function(c){ if (c.getAttribute('data-card') !== id) c.style.display = 'none'; });
						}
						if (back) back.style.display = '';
						// animate height
						el.style.maxHeight = '0px';
						var target = el.scrollHeight;
						requestAnimationFrame(function(){ el.style.maxHeight = target + 'px'; });
						el.addEventListener('transitionend', function te(){ el.style.maxHeight = ''; el.removeEventListener('transitionend', te); });
						if (card && card.scrollIntoView) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
					} else {
						// CLOSE
						el.style.maxHeight = el.scrollHeight + 'px';
						requestAnimationFrame(function(){ el.style.maxHeight = '0px'; });
						el.addEventListener('transitionend', function tc(){ el.classList.remove('open'); el.style.maxHeight = ''; el.removeEventListener('transitionend', tc); });
						btn.setAttribute('aria-expanded','false');
						var label = btn.querySelector('.vp-label'); if (label) label.textContent = 'Voir plus';
						if (card) card.classList.remove('expanded');
						if (grid) {
							var cards = grid.querySelectorAll('[data-card]');
							cards.forEach(function(c){ c.style.display = ''; });
						}
						if (back) back.style.display = 'none';
					}
				});
			});
			if (back) {
				back.addEventListener('click', function(){
					if (!grid) return;
					var cards = grid.querySelectorAll('[data-card]');
					cards.forEach(function(card){ card.style.display = '';
						var id = card.getAttribute('data-card');
						var el = document.getElementById(id);
						if (el) el.classList.remove('open');
						var btn = document.querySelector('[data-toggle-details="' + id + '"]');
						if (btn) { 
							btn.setAttribute('aria-expanded','false');
							var label = btn.querySelector && btn.querySelector('.vp-label');
							if (label) label.textContent = 'Voir plus';
						}
						card.classList.remove('expanded');
					});
					back.style.display = 'none';
					window.scrollTo({ top: 0, behavior: 'smooth' });
				});
			}

			// --- Mobile accordions for subsections inside details and lightbox for photos ---
			function debounce(fn, wait) {
				var t;
				return function() { clearTimeout(t); t = setTimeout(fn, wait); };
			}

			function initMobileAccordions() {
				var mobile = window.matchMedia('(max-width: 640px)').matches;
				var details = document.querySelectorAll('.details');
				details.forEach(function(det){
					var grid = det.querySelector('.grid');
					if (!grid) return;
					var sections = Array.from(grid.children).filter(function(n){ return n.nodeType === 1; });
					sections.forEach(function(sec){
						if (sec.dataset.accordion === '1') return; // already initialized
						var title = sec.querySelector('.section-title');
						if (!title) return;
						// create toggle button that mirrors the title
						var btn = document.createElement('button');
						btn.type = 'button';
						btn.className = 'accordion-toggle';
						btn.innerHTML = title.innerHTML + ' <span class="chev">▸</span>';
						// hide the original title visually but keep for semantics
						title.style.display = 'none';
						sec.insertBefore(btn, title);

						// wrap remaining content in accordion-content
						var content = document.createElement('div');
						content.className = 'accordion-content open';
						// move all nodes except btn and title into content
						var nodes = Array.from(sec.childNodes).filter(function(n){ return n !== btn && n !== title; });
						nodes.forEach(function(n){ content.appendChild(n); });
						sec.appendChild(content);
						sec.dataset.accordion = '1';
						if (mobile) {
							content.classList.remove('open');
							btn.setAttribute('aria-expanded','false');
						} else {
							content.classList.add('open');
							btn.setAttribute('aria-expanded','true');
						}

						btn.addEventListener('click', function(){
							var isOpen = content.classList.toggle('open');
							btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
							var chev = btn.querySelector('.chev'); if (chev) chev.style.transform = isOpen ? 'rotate(90deg)' : '';
						});
					});
				});
			}

			// Lightbox implementation (delegated)
			function openLightbox(src, alt) {
				if (!src) return;
				var overlay = document.createElement('div');
				overlay.className = 'lightbox-overlay';
				var img = document.createElement('img'); img.src = src; img.alt = alt || '';
				var close = document.createElement('button'); close.className = 'lightbox-close'; close.textContent = 'Fermer';
				overlay.appendChild(img); overlay.appendChild(close);
				document.body.appendChild(overlay); document.body.classList.add('lb-open');
				function destroy() { document.body.classList.remove('lb-open'); overlay.remove(); document.removeEventListener('keydown', onKey); }
				overlay.addEventListener('click', function(e){ if (e.target === overlay) destroy(); });
				close.addEventListener('click', destroy);
				function onKey(e){ if (e.key === 'Escape') destroy(); }
				document.addEventListener('keydown', onKey);
			}

			// delegate clicks to open lightbox on member photos
			document.addEventListener('click', function(e){
			var img = e.target.closest && e.target.closest('.member-photo-large, .member-photo');
			if (img) {
				openLightbox(img.src || img.getAttribute('src'), img.alt || '');
			}

			// close button inside details (delegated)
			var closeBtn = e.target.closest && e.target.closest('.details-close');
			if (closeBtn) {
				var detailsEl = closeBtn.closest('.details');
				if (!detailsEl) return;
				var id = detailsEl.id;
				// trigger the original toggle button if present to keep behaviors consistent
				var toggleBtn = document.querySelector('[data-toggle-details="' + id + '"]');
				if (toggleBtn) {
					toggleBtn.click();
				} else {
					// fallback: simply close and reset layout
					detailsEl.classList.remove('open');
					var cardEl = detailsEl.closest('.card'); if (cardEl) cardEl.classList.remove('expanded');
					var gridEl = document.getElementById('membersGrid');
					if (gridEl) {
						var cards = gridEl.querySelectorAll('[data-card]');
						cards.forEach(function(c){ c.style.display = ''; });
					}
					var back = document.getElementById('backButton'); if (back) back.style.display = 'none';
				}
			}
			});

			// initialize and re-init on resize
			initMobileAccordions();
			window.addEventListener('resize', debounce(function(){ initMobileAccordions(); }, 220));

			// Search functionality
			const searchInput = document.getElementById('searchInput');
			const searchField = document.getElementById('searchField');
			const searchClear = document.getElementById('searchClear');
			const searchStatus = document.getElementById('searchStatus');
			const membersGrid = document.getElementById('membersGrid');
			let allCards = Array.from(membersGrid.querySelectorAll('.card'));
			let timeout = null;

			function escapeRegExp(s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

			function clearAllHighlights() {
				allCards.forEach(card => {
					var elems = card.querySelectorAll('[data-field]');
					elems.forEach(function(el){ if (el.dataset.orig) { el.innerHTML = el.dataset.orig; delete el.dataset.orig; } });
				});
			}

			function highlightMatches(card, query, field) {
				if (!query) return;
				var targets = [];
				if (field && field !== 'all') {
					var el = card.querySelector('[data-field="' + field + '"]'); if (el) targets.push(el);
				} else {
					targets = Array.from(card.querySelectorAll('[data-field]'));
				}
				targets.forEach(function(el){
					if (!el) return;
					if (!el.dataset.orig) el.dataset.orig = el.innerHTML;
					// operate on text content fallback
					var text = el.textContent || '';
					if (text.toLowerCase().indexOf(query) === -1) return;
					var re = new RegExp('('+escapeRegExp(query)+')','ig');
					el.innerHTML = el.dataset.orig.replace(re, '<mark class="highlight">$1</mark>');
				});
			}

			function performSearch() {
				var raw = searchInput.value || '';
				var query = raw.trim().toLowerCase();
				var field = searchField.value;
				var matches = 0;
				clearAllHighlights();

				// Si aucun texte n'est saisi mais qu'on a sélectionné un filtre de compétence
				if (!query && (field === 'competences_avancees' || field === 'competences_expert')) {
					// Afficher tous les membres ayant des compétences du niveau sélectionné
					allCards.forEach(card => {
						const details = card.querySelector('.details');
						var hasLevel = false;
						if (details) {
							const levelBadges = details.querySelectorAll('.level-badge');
							levelBadges.forEach(badge => {
								const level = badge.getAttribute('data-level') || '';
								if ((field === 'competences_avancees' && level.includes('avanc')) ||
									(field === 'competences_expert' && level.includes('expert'))) {
									hasLevel = true;
									badge.closest('tr').classList.add('highlight-row');
								}
							});
						}
						card.style.display = hasLevel ? '' : 'none';
						if (hasLevel) matches++;
					});
					searchStatus.textContent = matches + ' membre' + (matches>1?'s':'') + ' trouvé' + (matches>1?'s':'') + ' avec des compétences ' + 
						(field === 'competences_expert' ? 'expert' : 'avancées');
					return;
				}

				// Si aucun texte n'est saisi et pas de filtre spécial
				if (!query) { 
					clearAllHighlights(); 
					allCards.forEach(c => c.style.display = ''); 
					searchStatus.textContent = ''; 
					return; 
				}

				allCards.forEach(card => {
					var found = false;
					if (field === 'competences_avancees' || field === 'competences_expert') {
						const details = card.querySelector('.details');
						if (details) {
							const competenceRows = details.querySelectorAll('.table tbody tr');
							var hasMatchingCompetence = false;
							competenceRows.forEach(row => {
								const competenceName = row.querySelector('.competence-name');
								const levelBadge = row.querySelector('.level-badge');
								if (competenceName && levelBadge) {
									const name = competenceName.textContent.toLowerCase();
									const level = levelBadge.getAttribute('data-level') || '';
									
									const levelMatch = (field === 'competences_avancees' && level.includes('avanc')) ||
													(field === 'competences_expert' && level.includes('expert'));
									
									if (levelMatch && (query === '' || name.includes(query))) {
										hasMatchingCompetence = true;
										row.classList.add('highlight-row');
										if (query) {
											highlightMatches(competenceName, query, 'competence');
										}
									}
								}
							});
							found = hasMatchingCompetence;
						}
					} else if (field === 'all') {
						const searchableFields = ['id_membre','nom_complet','telephone','email','anciennete_bapteme','score_assiduite','disponible_2026'];
						for (var i=0;i<searchableFields.length;i++){
							var f = searchableFields[i];
							var el = card.querySelector('[data-field="'+f+'"]');
							if (!el && f==='id_membre') el = card.querySelector('.muted');
							if (el && el.textContent.toLowerCase().indexOf(query) !== -1) { found = true; highlightMatches(card, query, f); break; }
						}
					} else {
						var el = card.querySelector('[data-field="'+field+'"]');
						if (el && el.textContent.toLowerCase().indexOf(query) !== -1) { found = true; highlightMatches(card, query, field); }
					}

					if (!found) { card.style.display = 'none'; }
					else { card.style.display = ''; matches++; }
				});

				searchStatus.textContent = matches === 0 ? 'Aucun résultat trouvé' : matches + ' membre' + (matches>1?'s':'') + ' trouvé' + (matches>1?'s':'');
			}

			searchInput.addEventListener('input', debounce(performSearch, 220));
			searchField.addEventListener('change', performSearch);

			searchClear.addEventListener('click', () => {
				searchInput.value = '';
				searchStatus.textContent = '';
				allCards.forEach(card => card.style.display = '');
				clearAllHighlights();
			});

			// Add data attributes for searchable fields
			allCards.forEach(card => {
				const muted = card.querySelector('.muted');
				if (muted) {
					const idMatch = muted.textContent.match(/ID #(\d+)/);
					if (idMatch) {
						muted.setAttribute('data-field', 'id_membre');
					}
				}
				// Add other data attributes as needed
			});

			// Compute/display anciennete for member cards when needed (if server value missing)
			var computeAncienneteForCards = function(){
				// also add small parallax attr-based transform on load
				var bubbles = document.querySelectorAll('.parallax .bubble[data-parallax-y]');
				if (bubbles.length) {
					var y = window.scrollY || 0;
					bubbles.forEach(function(b){
						var factor = parseFloat(b.getAttribute('data-parallax-y') || 0.04);
						b.style.transform = 'translateY(' + (-y * factor) + 'px)';
					});
				}

				var elems = document.querySelectorAll('.anciennete-display');
				elems.forEach(function(el){
					var strong = el.querySelector('strong');
					if (!strong) return;
					var current = strong.textContent && strong.textContent.trim();
					if (current && current !== '—') return; // already set by server
					var date = el.getAttribute('data-date');
					var annee = el.getAttribute('data-annee');
					var val = '—';
					var now = new Date();
					if (date) {
						var d = new Date(date);
						if (!isNaN(d.getTime())) {
							var years = now.getFullYear() - d.getFullYear();
							var m = now.getMonth() - d.getMonth();
							if (m < 0 || (m === 0 && now.getDate() < d.getDate())) years--;
							if (years < 0) years = 0;
							val = years + ' ans';
						}
					} else if (annee) {
						var y = parseInt(annee, 10);
						if (!isNaN(y) && y > 0) {
							val = (now.getFullYear() - y) + ' ans';
						}
					}
					strong.textContent = val;
				});
			};
			computeAncienneteForCards();

			// Scroll behavior: toggle classes for scroll state and direction, throttled with rAF
			var lastScroll = window.scrollY || 0;
			var ticking = false;
			function onScroll() {
				if (!ticking) {
					window.requestAnimationFrame(function() {
						var y = window.scrollY || 0;
						var body = document.body;
						// direction
						if (y > lastScroll && y > 20) {
							body.classList.add('scroll-down');
							body.classList.remove('scroll-up');
						} else if (y < lastScroll) {
							body.classList.add('scroll-up');
							body.classList.remove('scroll-down');
						}
						// scrolled threshold toggle
						if (y > 100) body.classList.add('scrolled'); else body.classList.remove('scrolled');
						// parallax bubbles subtle shift
						var bubbles = document.querySelectorAll('.parallax .bubble[data-parallax-y]');
						bubbles.forEach(function(b){
							var factor = parseFloat(b.getAttribute('data-parallax-y') || 0.04);
							b.style.transform = 'translateY(' + (-y * factor) + 'px)';
						});
						lastScroll = y;
						ticking = false;
					});
					ticking = true;
				}
			}
			window.addEventListener('scroll', onScroll, { passive: true });

			// keyboard shortcut: press '/' to focus search
			document.addEventListener('keydown', function(e){
				var tag = (document.activeElement && document.activeElement.tagName) || '';
				if (e.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA') {
					e.preventDefault();
					searchInput.focus();
					searchInput.select();
				}
			});
		})();
	</script>
</body>
</html>


