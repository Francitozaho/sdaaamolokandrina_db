<?php
session_start();

// Configuration base de données : modifier selon vos infos
require __DIR__ . '/config.php';


$msg = '';

// Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $msg = "Veuillez remplir tous les champs.";
    } else {
        // Préparer et exécuter la requête sécurisée
        $stmt = $mysqli->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($id, $hashed_password);
            $stmt->fetch();

            // Vérifier le mot de passe avec password_verify
            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $username;
                header('Location: dashboard.php');
                exit();
            } else {
                $msg = "Mot de passe incorrect.";
            }
        } else {
            $msg = "Nom d'utilisateur incorrect.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Connexion - SDA Ambolakandrina</title>
    <style>
        body {
            display: flex; justify-content: center; align-items: center; height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            color: #fff;
        }
        .login-container {
            background: rgba(255,255,255,0.1);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(118,75,162,0.5);
            width: 320px;
            backdrop-filter: blur(10px);
            text-align: center;
        }
        input[type=text], input[type=password] {
            width: 100%; padding: 12px; margin: 12px 0; border-radius: 10px; border: none;
            font-size: 1em;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; padding: 12px 20px; border-radius: 12px;
            font-size: 1em; cursor: pointer; transition: all 0.3s ease;
        }
        button:hover {
            filter: brightness(1.1);
        }
        .error-msg {
            color: #ff6b6b; font-weight: 600; margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Connexion</h2>
        <!-- <a href="register.php">Register</a> -->
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Nom d'utilisateur" required autofocus>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit">Se connecter</button>
        </form>
        <?php if ($msg): ?>
            <div class="error-msg"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>
