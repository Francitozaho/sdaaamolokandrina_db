<?php
session_start();
require __DIR__ . '/config.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($username && $password && $password_confirm) {
        if ($password !== $password_confirm) {
            $msg = "Les mots de passe ne correspondent pas.";
        } else {
            // Vérifier si l'utilisateur existe déjà
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $msg = "Nom d'utilisateur déjà pris.";
            } else {
                // Insérer nouvel utilisateur avec hash
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $mysqli->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $username, $hashed_password);
                if ($stmt->execute()) {
                    $msg = "Inscription réussie. Vous pouvez maintenant vous connecter.";
                } else {
                    $msg = "Erreur lors de l'inscription.";
                }
            }
            $stmt->close();
        }
    } else {
        $msg = "Veuillez remplir tous les champs.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Inscription - SDA Ambolakandrina</title>
    <style>
        body {
            display: flex; justify-content: center; align-items: center; height: 100vh;
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            color: #fff;
        }
        .register-container {
            background: rgba(255,255,255,0.1);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(102,126,234,0.5);
            width: 320px;
            backdrop-filter: blur(10px);
            text-align: center;
        }
        input[type=text], input[type=password] {
            width: 100%; padding: 12px; margin: 12px 0; border-radius: 10px; border: none;
            font-size: 1em;
        }
        button {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white; border: none; padding: 12px 20px; border-radius: 12px;
            font-size: 1em; cursor: pointer; transition: all 0.3s ease;
        }
        button:hover {
            filter: brightness(1.1);
        }
        .message {
            margin-top: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Inscription</h2>
        <form method="post" action="">
            <input type="text" name="username" placeholder="Nom d'utilisateur" required autofocus />
            <input type="password" name="password" placeholder="Mot de passe" required />
            <input type="password" name="password_confirm" placeholder="Confirmer mot de passe" required />
            <button type="submit">S'inscrire</button>
            <a href="login.php">login</a>
        </form>
        <?php if ($msg): ?>
            <div class="message"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>
</body>
</html>
