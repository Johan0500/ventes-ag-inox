<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    $redirect = $_SESSION['redirect_url'] ?? 'index.php';
    unset($_SESSION['redirect_url']);
    header("Location: $redirect");
    exit;
}

$error = '';
$message = '';

if (isset($_GET['message']) && $_GET['message'] == 'logged_out') {
    $message = 'Vous avez été déconnecté avec succès.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if (empty($username) || empty($password)) {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            if (login($username, $password)) {
                $redirect = $_SESSION['redirect_url'] ?? 'index.php';
                unset($_SESSION['redirect_url']);
                header("Location: $redirect");
                exit;
            } else {
                $error = 'Identifiants incorrects.';
                if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts']['count'] >= 3) {
                    sleep(2);
                }
            }
        }
    }
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Connexion - Inox Pharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4f46e5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .login-container { width: 100%; max-width: 500px; padding: 20px; }
        .login-card {
            background: white;
            border-radius: 24px;
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 60px rgba(0,0,0,0.35);
            animation: slideUp 0.6s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .logo-section { text-align: center; margin-bottom: 2rem; }
        .logo-section h1 { font-size: 1.6rem; font-weight: 700; color: #4f46e5; }
        .logo-section p { color: #6c757d; font-size: 0.9rem; }
        .btn-login {
            background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
            border: none; padding: 0.9rem; font-weight: 600; font-size: 1.05rem;
            border-radius: 12px; color: white; transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(79,70,229,0.4);
        }
        .form-control {
            border-radius: 10px; padding: 0.8rem 1rem;
            border: 2px solid #e2e8f0; font-size: 0.95rem;
        }
        .form-control:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79,70,229,0.1);
        }
        .alert { border-radius: 12px; }
        .password-toggle {
            cursor: pointer; background: #f8f9fa;
            border: 2px solid #e2e8f0; border-left: none;
            border-radius: 0 10px 10px 0; padding: 0 15px;
            display: flex; align-items: center;
        }
        .labo-info {
            background: #f0f4ff; border-radius: 12px;
            padding: 1rem; margin-top: 1.5rem; font-size: 0.85rem;
            text-align: center; color: #4f46e5;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div style="font-size:3rem;">🏥</div>
                <h1>INOX PHARMA</h1>
                <p>Portail de gestion des ventes</p>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Nom d'utilisateur</label>
                    <input type="text" name="username" class="form-control" 
                           placeholder="Ex: croient, licpharma ou admin" required autofocus>
                </div>
                
                <div class="mb-4">
                    <label class="form-label fw-bold">Mot de passe</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control" 
                               placeholder="••••••••" required>
                        <span class="password-toggle" onclick="togglePassword()" id="toggleIcon">👁️</span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login w-100">
                    🔐 Se connecter
                </button>
            </form>
            
            <div class="labo-info">
                <strong>🔬 Comptes disponibles :</strong><br>
                <code>admin</code> → Tous les labos | 
                <code>croient</code> → Croient Pharma |
                <code>licpharma</code> → LIC Pharma
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text'; icon.textContent = '🙈';
            } else {
                input.type = 'password'; icon.textContent = '👁️';
            }
        }
    </script>
</body>
</html>