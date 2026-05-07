<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($username, $password)) {
        header('Location: index.php');
        exit;
    } else {
        $error = 'Nom d\'utilisateur ou mot de passe incorrect';
    }
}

// Si déjà connecté
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Inox Pharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo h1 { font-size: 1.8rem; font-weight: 700; }
        .btn-login {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26,115,232,0.4);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <h1>🏥 INOX PHARMA</h1>
            <p class="text-muted">Portail de gestion des ventes</p>
        </div>
        
        <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Nom d'utilisateur</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person">👤</i></span>
                    <input type="text" name="username" class="form-control" 
                           placeholder="admin" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Mot de passe</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock">🔒</i></span>
                    <input type="password" name="password" class="form-control" 
                           placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-login w-100">
                Se connecter
            </button>
        </form>
        
        <p class="text-center mt-4 text-muted small">
            <strong>Démo :</strong> admin / admin123
        </p>
    </div>
</body>
</html>