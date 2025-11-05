# Base de données de l'Église adventiste du septième jour - Ambolakandrina

## Description
Système de recrutement pour aider le Comité de nomination à trouver et nommer des personnes compétentes pour tous les postes d'ici 2026.

## Installation

### 1. Configuration de la base de données MySQL/MariaDB

1. Assurez-vous que XAMPP est démarré (Apache et MySQL)

2. Importez le fichier SQL dans phpMyAdmin ou via la ligne de commande :
   ```bash
   mysql -u root -p < sdaambolokandrina.sql
   ```
   
   Ou via phpMyAdmin :
   - Ouvrez phpMyAdmin (http://localhost/phpmyadmin)
   - Créez une nouvelle base de données ou sélectionnez une existante
   - Cliquez sur "Importer"
   - Sélectionnez le fichier `sdaambolokandrina.sql`

### 2. Configuration de la connexion

Modifiez les constantes dans `db_connexion.php` si nécessaire :
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Votre mot de passe MySQL
define('DB_NAME', 'sda_ambolakandrina');
```

## Structure de la base de données

### Tables principales

1. **membres** - Informations de base des membres
   - Nom complet, date de naissance, baptême
   - Genre, adresse, contact
   - Églises actuelle et précédente
   - Exigences religieuses et comportementales
   - Consentement

2. **pratique_religieuse** - Fréquentation des services religieux
   - Mercredi soir, Vendredi soir
   - Samedi matin, Samedi soir

3. **types_responsabilites** - Catalogue des responsabilités
   - Loholona, Secrétaire, Trésorier, etc.

4. **responsabilites_membres** - Responsabilités assumées ou potentielles

5. **experience_autres_eglises** - Expérience dans d'autres églises

6. **experience_eglise_actuelle** - Expérience dans l'église actuelle

7. **realisations_contributions** - Réalisations et contributions

8. **types_competences** - Catalogue des compétences

9. **competences_membres** - Compétences des membres

10. **temoignages_references** - Témoignages et références

11. **commentaires_membres** - Commentaires des membres

### Vue utile

- **vue_membres_complet** - Vue combinée pour faciliter les recherches

## Utilisation

### Exemple de connexion PHP

```php
<?php
require_once 'db_connexion.php';

// Obtenir la connexion
$db = getDB();

// Exemple de requête
$stmt = $db->prepare("SELECT * FROM membres WHERE genre = ? AND dispose_responsabilite_2026 = ?");
$stmt->execute(['Homme', 'Oui']);
$resultats = $stmt->fetchAll();

foreach ($resultats as $membre) {
    echo $membre['nom_complet'] . "\n";
}
?>
```

### Recherche de membres par compétence

```php
<?php
require_once 'db_connexion.php';

$db = getDB();

// Rechercher les membres ayant une compétence spécifique
$stmt = $db->prepare("
    SELECT m.*, tc.nom_fr as competence
    FROM membres m
    INNER JOIN competences_membres cm ON m.id = cm.membre_id
    INNER JOIN types_competences tc ON cm.competence_id = tc.id
    WHERE tc.code = ?
    AND m.consentement = 'Accept'
    AND m.dispose_responsabilite_2026 = 'Oui'
");
$stmt->execute(['fitarihana']);
$resultats = $stmt->fetchAll();
?>
```

### Recherche de membres par responsabilité

```php
<?php
require_once 'db_connexion.php';

$db = getDB();

// Rechercher les membres ayant une responsabilité spécifique
$stmt = $db->prepare("
    SELECT m.*, tr.nom_fr as responsabilite
    FROM membres m
    INNER JOIN responsabilites_membres rm ON m.id = rm.membre_id
    INNER JOIN types_responsabilites tr ON rm.responsabilite_id = tr.id
    WHERE tr.code = ?
    AND m.consentement = 'Accept'
");
$stmt->execute(['loholona']);
$resultats = $stmt->fetchAll();
?>
```

## Fonctionnalités

- Gestion complète des informations des membres
- Suivi de la pratique religieuse
- Catalogue des responsabilités et compétences
- Historique d'expérience dans les églises
- Système de témoignages et références
- Recherche avancée par compétences et responsabilités
- Vue combinée pour faciliter les recherches du Comité de nomination

## Notes importantes

- Tous les champs de texte utilisent l'encodage UTF-8 (utf8mb4) pour supporter les caractères malgaches
- Les calculs automatiques (années de baptême, années d'expérience) sont gérés par la base de données
- Le système utilise le pattern Singleton pour la connexion à la base de données
- Les requêtes utilisent des requêtes préparées pour la sécurité

