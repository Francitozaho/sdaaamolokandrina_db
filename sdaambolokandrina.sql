-- ============================================
-- Base de données : sdaambolokandrina
-- Église adventiste du septième jour - Ambolakandrina
-- Système de gestion des nominations 2026
-- ============================================

CREATE DATABASE IF NOT EXISTS sdaambolokandrinaa 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE sdaambolokandrinaa;

-- ============================================
-- Table 1: Informations de base des membres
-- ============================================
CREATE TABLE IF NOT EXISTS membres (
    id_membre INT AUTO_INCREMENT PRIMARY KEY,
    photo VARCHAR(255) DEFAULT NULL,
    nom_complet VARCHAR(255) NOT NULL,
    date_naissance DATE NOT NULL,
    annee_bapteme INT NOT NULL,
    souvenir_date_bapteme ENUM('Oui', 'Non') DEFAULT 'Non',
    date_exacte_bapteme DATE DEFAULT NULL,
    anciennete_bapteme INT DEFAULT NULL,
    sexe ENUM('Homme', 'Femme') NOT NULL,
    adresse TEXT,
    telephone VARCHAR(20),
    email VARCHAR(100),
    eglise_actuelle VARCHAR(255) NOT NULL DEFAULT 'Ambolakandrina',
    eglise_precedente VARCHAR(255) DEFAULT NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_modification TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    statut ENUM('Actif', 'Inactif', 'Transfere') DEFAULT 'Actif',
    INDEX idx_nom (nom_complet),
    INDEX idx_eglise (eglise_actuelle),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colonnes pour ancien poste et poste désiré (par défaut: 'Rien')
ALTER TABLE membres
    ADD COLUMN IF NOT EXISTS id_poste_ancien INT NULL AFTER eglise_precedente,
    ADD COLUMN IF NOT EXISTS id_poste_desire INT NULL AFTER id_poste_ancien;

-- ============================================
-- Table 2: Pratique religieuse
-- ============================================
CREATE TABLE IF NOT EXISTS pratique_religieuse (
    id_pratique INT AUTO_INCREMENT PRIMARY KEY,
    id_membre INT NOT NULL,
    mercredi_soir ENUM('Fréquente', 'Régulièrement', 'Rarement', 'N''y va jamais') DEFAULT 'Rarement',
    vendredi_soir ENUM('Fréquente', 'Régulièrement', 'Rarement', 'N''y va jamais') DEFAULT 'Rarement',
    samedi_matin ENUM('Fréquente', 'Régulièrement', 'Rarement', 'N''y va jamais') DEFAULT 'Fréquente',
    samedi_soir ENUM('Fréquente', 'Régulièrement', 'Rarement', 'N''y va jamais') DEFAULT 'Rarement',
    score_assiduite INT GENERATED ALWAYS AS (
        (CASE mercredi_soir 
            WHEN 'Fréquente' THEN 4 
            WHEN 'Régulièrement' THEN 3 
            WHEN 'Rarement' THEN 2 
            ELSE 1 END) +
        (CASE vendredi_soir 
            WHEN 'Fréquente' THEN 4 
            WHEN 'Régulièrement' THEN 3 
            WHEN 'Rarement' THEN 2 
            ELSE 1 END) +
        (CASE samedi_matin 
            WHEN 'Fréquente' THEN 4 
            WHEN 'Régulièrement' THEN 3 
            WHEN 'Rarement' THEN 2 
            ELSE 1 END) +
        (CASE samedi_soir 
            WHEN 'Fréquente' THEN 4 
            WHEN 'Régulièrement' THEN 3 
            WHEN 'Rarement' THEN 2 
            ELSE 1 END)
    ) STORED,
    date_maj TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_membre) REFERENCES membres(id_membre) ON DELETE CASCADE,
    INDEX idx_membre (id_membre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 3: Postes disponibles dans l'église
-- ============================================
CREATE TABLE IF NOT EXISTS postes (
    id_poste INT AUTO_INCREMENT PRIMARY KEY,
    nom_poste_fr VARCHAR(255) NOT NULL,
    nom_poste_mg VARCHAR(255) NOT NULL,
    description TEXT,
    categorie ENUM('Direction', 'Administration', 'Jeunesse', 'Femmes', 'Musique', 'Diaconie', 'Education', 'Technique', 'Autre') DEFAULT 'Autre',
    competences_requises TEXT,
    actif ENUM('Oui', 'Non') DEFAULT 'Oui',
    UNIQUE KEY uq_postes_nom_fr (nom_poste_fr),
    INDEX idx_categorie (categorie),
    INDEX idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des postes standards
INSERT IGNORE INTO postes (nom_poste_fr, nom_poste_mg, categorie) VALUES
('Ancien', 'Loholona', 'Direction'),
('Secrétaire d''église', 'Sekretera fiangonana', 'Administration'),
('Trésorier', 'Mpitam-bola', 'Administration'),
('Directeur des jeunes', 'Filohan''ny tanora (AY)', 'Jeunesse'),
('Présidente des Dorcas', 'Filohan''ny Dorkasy', 'Femmes'),
('Chef de chœur', 'Filohan''ny Choral', 'Musique'),
('Musicien', 'Mpitendry zava-maneno', 'Musique'),
('Diacre', 'Diakona', 'Diaconie'),
('Directeur École du sabbat', 'Filohan''ny Sekolo Sabata', 'Education'),
('Membre du comité', 'Mpikambana amin''ny komity', 'Direction'),
('Responsable technique', 'Asa ara-teknika (IT, sonorisation, média)', 'Technique');

-- S'assurer que le poste 'Rien' existe (utilisé comme valeur par défaut logique)
INSERT INTO postes (nom_poste_fr, nom_poste_mg, categorie)
SELECT 'Rien', 'Tsy misy', 'Autre'
WHERE NOT EXISTS (
    SELECT 1 FROM postes WHERE nom_poste_fr = 'Rien' LIMIT 1
);

-- Ajout des postes fournis par l'utilisateur (idempotent)
INSERT IGNORE INTO postes (nom_poste_fr, nom_poste_mg, categorie) VALUES
('Loholona', 'Loholona', 'Direction'),
('Trésorerie', 'Trésorerie', 'Administration'),
('Secrétariat', 'Secrétariat', 'Administration'),
('Fanabeazana', 'Fanabeazana', 'Education'),
('Liberté Religieuse', 'Liberté Religieuse', 'Autre'),
('MIHOM', 'MIHOM', 'Autre'),
('Dorkasy', 'Dorkasy', 'Femmes'),
('Diakona', 'Diakona', 'Diaconie'),
('Sekoly Sabata', 'Sekoly Sabata', 'Education'),
('ASAFI', 'ASAFI', 'Autre'),
('Vokatra Antenaina', 'Vokatra Antenaina', 'Autre'),
('Service Communautaire', 'Service Communautaire', 'Social'),
('MINENF', 'MINENF', 'Autre'),
('MIFEM', 'MIFEM', 'Autre'),
('FF sy Fampielezam-boky', 'FF sy Fampielezam-boky', 'Autre'),
('FIMPIA', 'FIMPIA', 'Autre'),
('Vavaka', 'Vavaka', 'Autre'),
('Fahasalamana', 'Fahasalamana', 'Autre'),
('Hira sy Mozika', 'Hira sy Mozika', 'Musique'),
('PCM', 'PCM', 'Autre'),
('JA', 'JA', 'Autre'),
('FIPIKRI', 'FIPIKRI', 'Autre'),
('Communication', 'Communication', 'Autre');

-- Ajout des clés étrangères vers postes (après création de postes)
-- Idempotent: supprimer si elles existent déjà
SET @fk1 := (SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'membres' AND CONSTRAINT_NAME = 'fk_membres_poste_ancien');
SET @sql := IF(@fk1 IS NOT NULL, CONCAT('ALTER TABLE membres DROP FOREIGN KEY ', @fk1), NULL);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk2 := (SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'membres' AND CONSTRAINT_NAME = 'fk_membres_poste_desire');
SET @sql := IF(@fk2 IS NOT NULL, CONCAT('ALTER TABLE membres DROP FOREIGN KEY ', @fk2), NULL);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ix1 := (SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membres' AND INDEX_NAME = 'idx_membres_poste_ancien');
SET @sql := IF(@ix1 IS NOT NULL, 'ALTER TABLE membres DROP INDEX idx_membres_poste_ancien', NULL);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ix2 := (SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'membres' AND INDEX_NAME = 'idx_membres_poste_desire');
SET @sql := IF(@ix2 IS NOT NULL, 'ALTER TABLE membres DROP INDEX idx_membres_poste_desire', NULL);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE membres
    ADD CONSTRAINT fk_membres_poste_ancien FOREIGN KEY (id_poste_ancien) REFERENCES postes(id_poste) ON DELETE SET NULL;

ALTER TABLE membres
    ADD CONSTRAINT fk_membres_poste_desire FOREIGN KEY (id_poste_desire) REFERENCES postes(id_poste) ON DELETE SET NULL;

CREATE INDEX idx_membres_poste_ancien ON membres(id_poste_ancien);
CREATE INDEX idx_membres_poste_desire ON membres(id_poste_desire);

-- ============================================
-- Table 4: Responsabilités des membres
-- ============================================
CREATE TABLE IF NOT EXISTS responsabilites (
    id_responsabilite INT AUTO_INCREMENT PRIMARY KEY,
    id_membre INT NOT NULL,
    id_poste INT NOT NULL,
    type_experience ENUM('Assumé', 'Pourrait assumer', 'Souhaite assumer') DEFAULT 'Assumé',
    eglise VARCHAR(255) DEFAULT 'Ambolakandrina',
    type_eglise ENUM('Église principale', 'Église annexe') DEFAULT 'Église principale',
    annee_debut INT,
    annee_fin INT,
    annees_experience INT DEFAULT NULL,
    realisations TEXT,
    date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_membre) REFERENCES membres(id_membre) ON DELETE CASCADE,
    FOREIGN KEY (id_poste) REFERENCES postes(id_poste) ON DELETE CASCADE,
    INDEX idx_membre_poste (id_membre, id_poste),
    INDEX idx_type (type_experience)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 5: Compétences et talents
-- ============================================
CREATE TABLE IF NOT EXISTS competences (
    id_competence INT AUTO_INCREMENT PRIMARY KEY,
    nom_competence_fr VARCHAR(255) NOT NULL,
    nom_competence_mg VARCHAR(255) NOT NULL,
    categorie ENUM('Leadership', 'Enseignement', 'Technique', 'Artistique', 'Social', 'Financier', 'Evangélisation', 'Autre') DEFAULT 'Autre',
    description TEXT,
    UNIQUE KEY uq_competences_nom_fr (nom_competence_fr),
    INDEX idx_categorie (categorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des compétences standards
INSERT IGNORE INTO competences (nom_competence_fr, nom_competence_mg, categorie) VALUES
('Leadership', 'Fitarihana', 'Leadership'),
('Enseignement/Formation', 'Fampianarana / Fampiofanana', 'Enseignement'),
('Compétences technologiques', 'Fahaiza-manao ara-teknolojia (Informatique, média)', 'Technique'),
('Musique/Chant', 'Fampihirana / Hira', 'Artistique'),
('Animation musicale', 'Fampiarahana zavamaneno', 'Artistique'),
('Développement des jeunes', 'Fampandrosoana ny tanora', 'Social'),
('Action sociale', 'Asa sosialy sy fiahiana', 'Social'),
('Enseignement biblique', 'Fampianarana Baiboly / Fampihavanana', 'Enseignement'),
('Gestion d''événements', 'Fikarakarana hetsika', 'Leadership'),
('Gestion financière', 'Fahaiza-mitantana vola / Fitantanam-bola', 'Financier'),
('Évangélisation', 'Asa an-tsaha / Evanjelisma', 'Evangélisation');

-- Ajout des compétences supplémentaires fournies par l'utilisateur (idempotent)
INSERT IGNORE INTO competences (nom_competence_fr, nom_competence_mg, categorie) VALUES
('Fitantanana', 'Fitantanana', 'Leadership'),
('Fahaiza-mamantatra fanahy', 'Fahaiza-mamantatra fanahy', 'Autre'),
('Evanjelistra', 'Evanjelistra', 'Evangélisation'),
('Fampaherezana', 'Fampaherezana', 'Autre'),
('Finoana', 'Finoana', 'Autre'),
('Fahalalahan-tanana', 'Fahalalahan-tanana', 'Autre'),
('Fanasitranana', 'Fanasitranana', 'Autre'),
('Fanampiana', 'Fanampiana', 'Social'),
('Fampiantranoam-bahiny', 'Fampiantranoam-bahiny', 'Autre'),
('Famelan-keloka', 'Famelan-keloka', 'Autre'),
('Fahalalana', 'Fahalalana', 'Autre'),
('Fitarihana', 'Fitarihana', 'Leadership'),
('Faminaniana', 'Faminaniana', 'Autre'),
('Misionera', 'Misionera', 'Autre'),
('Pastora', 'Pastora', 'Autre'),
('Fanompoana', 'Fanompoana', 'Autre'),
('Mpampianatra', 'Mpampianatra', 'Enseignement'),
('Fahaendrena', 'Fahaendrena', 'Autre');

-- ============================================
-- Table 6: Compétences des membres
-- ============================================
CREATE TABLE IF NOT EXISTS membres_competences (
    id_membre_competence INT AUTO_INCREMENT PRIMARY KEY,
    id_membre INT NOT NULL,
    id_competence INT NOT NULL,
    niveau ENUM('Débutant', 'Intermédiaire', 'Avancé', 'Expert') DEFAULT 'Intermédiaire',
    note_auto_evaluation INT CHECK (note_auto_evaluation BETWEEN 1 AND 5),
    date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_membre) REFERENCES membres(id_membre) ON DELETE CASCADE,
    FOREIGN KEY (id_competence) REFERENCES competences(id_competence) ON DELETE CASCADE,
    UNIQUE KEY unique_membre_competence (id_membre, id_competence),
    INDEX idx_membre (id_membre),
    INDEX idx_competence (id_competence)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 7: Exigences comportementales
-- ============================================
CREATE TABLE IF NOT EXISTS exigences_religieuses (
    id_exigence INT AUTO_INCREMENT PRIMARY KEY,
    id_membre INT NOT NULL,
    actif_vie_religieuse ENUM('Oui', 'Non') DEFAULT 'Oui',
    conformite_principes ENUM('Oui', 'Non') DEFAULT 'Oui',
    disponible_2026 ENUM('Oui', 'Non', 'Je ne sais pas') DEFAULT 'Je ne sais pas',
    facilement_joignable ENUM('Oui', 'Non') DEFAULT 'Oui',
    observations TEXT,
    date_evaluation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_maj TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_membre) REFERENCES membres(id_membre) ON DELETE CASCADE,
    UNIQUE KEY unique_membre_exigence (id_membre),
    INDEX idx_disponible (disponible_2026)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 8: Références et témoignages
-- ============================================
CREATE TABLE IF NOT EXISTS references (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 9: Consentements
-- ============================================
CREATE TABLE IF NOT EXISTS consentements (
    id_consentement INT AUTO_INCREMENT PRIMARY KEY,
    id_membre INT NOT NULL,
    consentement ENUM('Accepté', 'Refusé') NOT NULL,
    date_consentement TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    adresse_ip VARCHAR(45),
    FOREIGN KEY (id_membre) REFERENCES membres(id_membre) ON DELETE CASCADE,
    INDEX idx_membre (id_membre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 10: Nominations (historique et futures)
-- ============================================
CREATE TABLE IF NOT EXISTS nominations (
    id_nomination INT AUTO_INCREMENT PRIMARY KEY,
    id_membre INT NOT NULL,
    id_poste INT NOT NULL,
    annee INT NOT NULL,
    date_nomination DATE,
    date_debut DATE,
    date_fin DATE,
    statut ENUM('Proposé', 'Approuvé', 'En cours', 'Terminé', 'Refusé') DEFAULT 'Proposé',
    notes TEXT,
    nomme_par VARCHAR(255),
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_membre) REFERENCES membres(id_membre) ON DELETE CASCADE,
    FOREIGN KEY (id_poste) REFERENCES postes(id_poste) ON DELETE CASCADE,
    INDEX idx_annee (annee),
    INDEX idx_statut (statut),
    INDEX idx_membre_annee (id_membre, annee)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table 11: Utilisateurs du système (Comité)
-- ============================================
CREATE TABLE IF NOT EXISTS utilisateurs (
    id_utilisateur INT AUTO_INCREMENT PRIMARY KEY,
    nom_utilisateur VARCHAR(100) NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255) NOT NULL,
    nom_complet VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    role ENUM('Administrateur', 'Comité de nomination', 'Lecteur') DEFAULT 'Lecteur',
    actif ENUM('Oui', 'Non') DEFAULT 'Oui',
    derniere_connexion TIMESTAMP NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nom_utilisateur (nom_utilisateur),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Création d'un utilisateur administrateur par défaut
-- Mot de passe: Admin2026! (à changer immédiatement)
INSERT INTO utilisateurs (nom_utilisateur, mot_de_passe, nom_complet, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrateur Système', 'Administrateur');

-- ============================================
-- Table 12: Logs d'activité
-- ============================================
CREATE TABLE IF NOT EXISTS logs_activite (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    id_utilisateur INT,
    action VARCHAR(255) NOT NULL,
    table_concernee VARCHAR(100),
    id_enregistrement INT,
    details TEXT,
    adresse_ip VARCHAR(45),
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utilisateur) REFERENCES utilisateurs(id_utilisateur) ON DELETE SET NULL,
    INDEX idx_date (date_action),
    INDEX idx_utilisateur (id_utilisateur)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- VUES UTILES POUR REQUÊTES FRÉQUENTES
-- ============================================

-- Vue: Profil complet des membres
CREATE OR REPLACE VIEW vue_profil_complet AS
SELECT 
    m.id_membre,
    m.nom_complet,
    m.sexe,
    m.anciennete_bapteme,
    m.telephone,
    m.email,
    m.eglise_actuelle,
    pr.samedi_matin,
    pr.score_assiduite,
    er.actif_vie_religieuse,
    er.conformite_principes,
    er.disponible_2026,
    er.facilement_joignable,
    c.consentement,
    m.statut
FROM membres m
LEFT JOIN pratique_religieuse pr ON m.id_membre = pr.id_membre
LEFT JOIN exigences_religieuses er ON m.id_membre = er.id_membre
LEFT JOIN consentements c ON m.id_membre = c.id_membre
WHERE m.statut = 'Actif';

-- Vue: Expérience par poste
CREATE OR REPLACE VIEW vue_experience_postes AS
SELECT 
    m.id_membre,
    m.nom_complet,
    p.nom_poste_fr,
    p.nom_poste_mg,
    p.categorie,
    r.type_experience,
    r.annees_experience,
    r.realisations
FROM membres m
JOIN responsabilites r ON m.id_membre = r.id_membre
JOIN postes p ON r.id_poste = p.id_poste
WHERE m.statut = 'Actif';

-- Vue: Compétences des membres
CREATE OR REPLACE VIEW vue_competences_membres AS
SELECT 
    m.id_membre,
    m.nom_complet,
    c.nom_competence_fr,
    c.nom_competence_mg,
    c.categorie,
    mc.niveau,
    mc.note_auto_evaluation
FROM membres m
JOIN membres_competences mc ON m.id_membre = mc.id_membre
JOIN competences c ON mc.id_competence = c.id_competence
WHERE m.statut = 'Actif';

-- Vue: Membres complets avec toutes les informations
CREATE OR REPLACE VIEW vue_membres_complet AS
SELECT 
    m.id_membre,
    m.nom_complet,
    m.date_naissance,
    m.annee_bapteme,
    m.date_exacte_bapteme,
    m.souvenir_date_bapteme,
    m.anciennete_bapteme,
    m.sexe,
    m.adresse,
    m.telephone,
    m.email,
    m.eglise_actuelle,
    m.eglise_precedente,
    m.photo,
    m.statut,
    pr.mercredi_soir,
    pr.vendredi_soir,
    pr.samedi_matin,
    pr.samedi_soir,
    pr.score_assiduite,
    er.actif_vie_religieuse,
    er.conformite_principes,
    er.disponible_2026,
    er.facilement_joignable,
    er.observations,
    c.consentement,
    GROUP_CONCAT(DISTINCT p.nom_poste_fr SEPARATOR ', ') AS postes,
    GROUP_CONCAT(DISTINCT comp.nom_competence_fr SEPARATOR ', ') AS competences
FROM membres m
LEFT JOIN pratique_religieuse pr ON m.id_membre = pr.id_membre
LEFT JOIN exigences_religieuses er ON m.id_membre = er.id_membre
LEFT JOIN consentements c ON m.id_membre = c.id_membre
LEFT JOIN responsabilites r ON m.id_membre = r.id_membre
LEFT JOIN postes p ON r.id_poste = p.id_poste
LEFT JOIN membres_competences mc ON m.id_membre = mc.id_membre
LEFT JOIN competences comp ON mc.id_competence = comp.id_competence
GROUP BY m.id_membre;

-- ============================================
-- PROCÉDURES STOCKÉES
-- ============================================

-- Procédure: Rechercher des candidats pour un poste
DELIMITER //
CREATE PROCEDURE rechercher_candidats_poste(
    IN p_id_poste INT,
    IN p_annee INT
)
BEGIN
    SELECT DISTINCT
        m.id_membre,
        m.nom_complet,
        m.telephone,
        m.email,
        m.anciennete_bapteme,
        r.annees_experience,
        r.realisations,
        pr.score_assiduite,
        er.disponible_2026,
        GROUP_CONCAT(DISTINCT c.nom_competence_fr SEPARATOR ', ') as competences
    FROM membres m
    JOIN responsabilites r ON m.id_membre = r.id_membre
    LEFT JOIN pratique_religieuse pr ON m.id_membre = pr.id_membre
    LEFT JOIN exigences_religieuses er ON m.id_membre = er.id_membre
    LEFT JOIN membres_competences mc ON m.id_membre = mc.id_membre
    LEFT JOIN competences c ON mc.id_competence = c.id_competence
    WHERE r.id_poste = p_id_poste
        AND m.statut = 'Actif'
        AND er.actif_vie_religieuse = 'Oui'
        AND er.conformite_principes = 'Oui'
        AND (er.disponible_2026 = 'Oui' OR er.disponible_2026 = 'Je ne sais pas')
        AND NOT EXISTS (
            SELECT 1 FROM nominations n 
            WHERE n.id_membre = m.id_membre 
            AND n.annee = p_annee 
            AND n.statut IN ('Approuvé', 'En cours')
        )
    GROUP BY m.id_membre
    ORDER BY r.annees_experience DESC, pr.score_assiduite DESC;
END //
DELIMITER ;

-- Procédure: Calculer le score de qualification
DELIMITER //
CREATE PROCEDURE calculer_score_qualification(
    IN p_id_membre INT,
    IN p_id_poste INT
)
BEGIN
    SELECT 
        m.nom_complet,
        (
            COALESCE(r.annees_experience * 10, 0) +
            COALESCE(pr.score_assiduite * 5, 0) +
            COALESCE(mc.nb_competences * 8, 0) +
            CASE WHEN er.disponible_2026 = 'Oui' THEN 20 ELSE 0 END +
            CASE WHEN m.anciennete_bapteme >= 5 THEN 15 ELSE m.anciennete_bapteme * 3 END
        ) AS score_qualification
    FROM membres m
    LEFT JOIN responsabilites r ON m.id_membre = r.id_membre AND r.id_poste = p_id_poste
    LEFT JOIN pratique_religieuse pr ON m.id_membre = pr.id_membre
    LEFT JOIN exigences_religieuses er ON m.id_membre = er.id_membre
    LEFT JOIN (
        SELECT id_membre, COUNT(*) as nb_competences 
        FROM membres_competences 
        GROUP BY id_membre
    ) mc ON m.id_membre = mc.id_membre
    WHERE m.id_membre = p_id_membre;
END //
DELIMITER ;

-- Procédures d'aide: créer ou récupérer un poste/compétence par nom (permet d'ajouter librement)
DELIMITER //
CREATE PROCEDURE get_or_create_poste(
    IN p_nom_fr VARCHAR(255),
    IN p_nom_mg VARCHAR(255),
    IN p_categorie ENUM('Direction','Administration','Jeunesse','Femmes','Musique','Diaconie','Education','Technique','Autre'),
    OUT p_id_poste INT
)
BEGIN
    DECLARE v_id INT;
    IF p_nom_fr IS NULL OR TRIM(p_nom_fr) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Le nom du poste (fr) est requis';
    END IF;
    SELECT id_poste INTO v_id FROM postes WHERE nom_poste_fr = p_nom_fr LIMIT 1;
    IF v_id IS NULL THEN
        INSERT INTO postes (nom_poste_fr, nom_poste_mg, categorie, actif)
        VALUES (p_nom_fr, COALESCE(NULLIF(p_nom_mg,''), p_nom_fr), COALESCE(p_categorie,'Autre'), 'Oui');
        SET v_id = LAST_INSERT_ID();
    END IF;
    SET p_id_poste = v_id;
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE get_or_create_competence(
    IN p_nom_fr VARCHAR(255),
    IN p_nom_mg VARCHAR(255),
    IN p_categorie ENUM('Leadership','Enseignement','Technique','Artistique','Social','Financier','Evangélisation','Autre'),
    OUT p_id_competence INT
)
BEGIN
    DECLARE v_id INT;
    IF p_nom_fr IS NULL OR TRIM(p_nom_fr) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Le nom de la compétence (fr) est requis';
    END IF;
    SELECT id_competence INTO v_id FROM competences WHERE nom_competence_fr = p_nom_fr LIMIT 1;
    IF v_id IS NULL THEN
        INSERT INTO competences (nom_competence_fr, nom_competence_mg, categorie)
        VALUES (p_nom_fr, COALESCE(NULLIF(p_nom_mg,''), p_nom_fr), COALESCE(p_categorie,'Autre'));
        SET v_id = LAST_INSERT_ID();
    END IF;
    SET p_id_competence = v_id;
END //
DELIMITER ;

-- Procédures utilitaires: assigner via noms
DELIMITER //
CREATE PROCEDURE assigner_responsabilite_par_nom(
    IN p_id_membre INT,
    IN p_nom_poste_fr VARCHAR(255),
    IN p_type_experience ENUM('Assumé','Pourrait assumer','Souhaite assumer'),
    IN p_eglise VARCHAR(255),
    IN p_type_eglise ENUM('Église principale','Église annexe'),
    IN p_annee_debut INT,
    IN p_annee_fin INT,
    IN p_realisations TEXT
)
BEGIN
    DECLARE v_id_poste INT;
    CALL get_or_create_poste(p_nom_poste_fr, NULL, 'Autre', v_id_poste);
    INSERT INTO responsabilites (
        id_membre, id_poste, type_experience, eglise, type_eglise, annee_debut, annee_fin, realisations
    ) VALUES (
        p_id_membre, v_id_poste, COALESCE(p_type_experience,'Assumé'), COALESCE(p_eglise,'Ambolakandrina'), COALESCE(p_type_eglise,'Église principale'), p_annee_debut, p_annee_fin, p_realisations
    );
END //
DELIMITER ;

DELIMITER //
CREATE PROCEDURE ajouter_competence_membre_par_nom(
    IN p_id_membre INT,
    IN p_nom_competence_fr VARCHAR(255),
    IN p_niveau ENUM('Débutant','Intermédiaire','Avancé','Expert'),
    IN p_note_auto INT
)
BEGIN
    DECLARE v_id_competence INT;
    CALL get_or_create_competence(p_nom_competence_fr, NULL, 'Autre', v_id_competence);
    INSERT INTO membres_competences (id_membre, id_competence, niveau, note_auto_evaluation)
    VALUES (p_id_membre, v_id_competence, COALESCE(p_niveau,'Intermédiaire'), NULLIF(p_note_auto,0))
    ON DUPLICATE KEY UPDATE
        niveau = VALUES(niveau),
        note_auto_evaluation = VALUES(note_auto_evaluation);
END //
DELIMITER ;

-- Définir par défaut l'ancien poste et le poste désiré à 'Rien' si non fournis
DELIMITER //
CREATE TRIGGER before_membre_insert_default_postes
BEFORE INSERT ON membres
FOR EACH ROW
BEGIN
    DECLARE v_id_rien INT;
    SELECT id_poste INTO v_id_rien FROM postes WHERE nom_poste_fr = 'Rien' LIMIT 1;
    IF NEW.id_poste_ancien IS NULL THEN
        SET NEW.id_poste_ancien = v_id_rien;
    END IF;
    IF NEW.id_poste_desire IS NULL THEN
        SET NEW.id_poste_desire = v_id_rien;
    END IF;
END //
DELIMITER ;

-- ============================================
-- TRIGGERS
-- ============================================

-- Trigger: Calculer anciennete_bapteme à l'insertion
DELIMITER //
CREATE TRIGGER before_membre_insert_set_anciennete
BEFORE INSERT ON membres
FOR EACH ROW
BEGIN
    IF NEW.annee_bapteme IS NULL THEN
        SET NEW.anciennete_bapteme = NULL;
    ELSE
        SET NEW.anciennete_bapteme = YEAR(CURDATE()) - NEW.annee_bapteme;
    END IF;
END //
DELIMITER ;

-- Trigger: Recalculer anciennete_bapteme à la mise à jour
DELIMITER //
CREATE TRIGGER before_membre_update_set_anciennete
BEFORE UPDATE ON membres
FOR EACH ROW
BEGIN
    IF NEW.annee_bapteme IS NULL THEN
        SET NEW.anciennete_bapteme = NULL;
    ELSEIF NEW.annee_bapteme <> OLD.annee_bapteme THEN
        SET NEW.anciennete_bapteme = YEAR(CURDATE()) - NEW.annee_bapteme;
    END IF;
END //
DELIMITER ;

-- Trigger: Logger les modifications de membres
DELIMITER //
CREATE TRIGGER after_membre_update
AFTER UPDATE ON membres
FOR EACH ROW
BEGIN
    INSERT INTO logs_activite (action, table_concernee, id_enregistrement, details)
    VALUES ('UPDATE', 'membres', NEW.id_membre, 
            CONCAT('Modification de ', OLD.nom_complet, ' -> ', NEW.nom_complet));
END //
DELIMITER ;

-- Triggers: Calculer annees_experience pour responsabilites
DELIMITER //
CREATE TRIGGER before_responsabilite_insert_set_experience
BEFORE INSERT ON responsabilites
FOR EACH ROW
BEGIN
    IF NEW.annee_debut IS NULL THEN
        SET NEW.annees_experience = NULL;
    ELSE
        IF NEW.annee_fin IS NOT NULL THEN
            SET NEW.annees_experience = NEW.annee_fin - NEW.annee_debut;
        ELSE
            SET NEW.annees_experience = YEAR(CURDATE()) - NEW.annee_debut;
        END IF;
    END IF;
END //
DELIMITER ;

DELIMITER //
CREATE TRIGGER before_responsabilite_update_set_experience
BEFORE UPDATE ON responsabilites
FOR EACH ROW
BEGIN
    IF NEW.annee_debut IS NULL THEN
        SET NEW.annees_experience = NULL;
    ELSE
        IF NEW.annee_fin IS NOT NULL THEN
            SET NEW.annees_experience = NEW.annee_fin - NEW.annee_debut;
        ELSE
            SET NEW.annees_experience = YEAR(CURDATE()) - NEW.annee_debut;
        END IF;
    END IF;
END //
DELIMITER ;

-- Trigger: Vérifier l'unicité des nominations
DELIMITER //
CREATE TRIGGER before_nomination_insert
BEFORE INSERT ON nominations
FOR EACH ROW
BEGIN
    DECLARE nb_nominations INT;
    
    SELECT COUNT(*) INTO nb_nominations
    FROM nominations
    WHERE id_membre = NEW.id_membre
        AND id_poste = NEW.id_poste
        AND annee = NEW.annee
        AND statut IN ('Proposé', 'Approuvé', 'En cours');
    
    IF nb_nominations > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ce membre est déjà nominé pour ce poste cette année';
    END IF;
END //
DELIMITER ;

DELIMITER ;

-- ============================================
-- INDEX SUPPLÉMENTAIRES POUR PERFORMANCES
-- ============================================
CREATE INDEX idx_membres_bapteme ON membres(anciennete_bapteme);
CREATE INDEX idx_responsabilites_experience ON responsabilites(annees_experience);
CREATE INDEX idx_nominations_annee_statut ON nominations(annee, statut);
CREATE INDEX idx_actif_vie_religieuse ON exigences_religieuses(actif_vie_religieuse);
CREATE INDEX idx_conforme_principes ON exigences_religieuses(conformite_principes);
CREATE INDEX idx_dispose_2026 ON exigences_religieuses(disponible_2026);
CREATE INDEX idx_consentement ON consentements(consentement);
CREATE INDEX idx_genre ON membres(sexe);

-- ============================================
-- FIN DU SCRIPT
-- ============================================
