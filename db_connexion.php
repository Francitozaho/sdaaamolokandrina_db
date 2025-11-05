<?php
/**
 * Fichier de connexion à la base de données
 * Base de données de l'Église adventiste du septième jour - Ambolakandrina
 */

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sda_ambolakandrina');
define('DB_CHARSET', 'utf8mb4');

/**
 * Classe de connexion à la base de données
 */
class Database {
    private static $instance = null;
    private $conn;
    
    /**
     * Constructeur privé pour le pattern Singleton
     */
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }
    
    /**
     * Obtenir l'instance unique de la connexion (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtenir la connexion PDO
     */
    public function getConnection() {
        return $this->conn;
    }
    
    /**
     * Empêcher le clonage de l'instance
     */
    private function __clone() {}
    
    /**
     * Empêcher la désérialisation
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Fonction helper pour obtenir la connexion
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Fonction pour tester la connexion
 */
function testConnection() {
    try {
        $db = getDB();
        return [
            'success' => true,
            'message' => 'Connexion réussie à la base de données',
            'database' => DB_NAME
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Erreur de connexion : ' . $e->getMessage()
        ];
    }
}

// Exemple d'utilisation (commenté)
/*
try {
    $db = getDB();
    echo "Connexion réussie !";
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
*/

