<?php
// Démarrer la session seulement si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';

// Si déjà connecté, rediriger
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
if (isset($_GET['error']) && $_GET['error'] == 'session_expired') {
    $error = 'Votre session a expiré. Veuillez vous reconnecter.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Veuillez réessayer.';
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
                $error = 'Nom d\'utilisateur ou mot de passe incorrect.';
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
            background: linear-gradient(135deg, #0d47a1 0%, #1a73e8 40%, #4fc3f7 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        .login-container {
            width: 100%;
            max-width: 440px;
            padding: 20px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.1);
            animation: slideUp 0.6s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo-icon {
            font-size: 3.5rem;
            margin-bottom: 0.5rem;
        }
        
        .logo-section h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1a73e8;
            margin: 0;
        }
        
        .logo-section p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #333;
            margin-bottom: 0.3rem;
        }
        
        .input-group-text {
            background: #f8f9fa;
            border-right: none;
            font-size: 1.1rem;
        }
        
        .form-control {
            border-left: none;
            padding: 0.7rem 1rem;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            box-shadow: none;
            border-color: #1a73e8;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            border: none;
            padding: 0.8rem;
            font-weight: 600;
            font-size: 1.05rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            color: white;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26,115,232,0.4);
        }
        
        .alert {
            border-radius: 10px;
            font-size: 0.9rem;
            padding: 0.75rem 1rem;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.8rem;
            color: #999;
        }
        
        .password-toggle {
            cursor: pointer;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: none;
            border-radius: 0 8px 8px 0;
            padding: 0 12px;
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-icon">🏥</div>
                <h1>INOX PHARMA</h1>
                <p>Portail de gestion des ventes</p>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-success">✅ <?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts']['count'] >= 3): ?>
            <div class="alert alert-warning">
                ⚠️ <?php echo 5 - $_SESSION['login_attempts']['count']; ?> tentative(s) restante(s)
            </div>
            <?php endif; ?>
            
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="mb-3">
                    <label class="form-label">Nom d'utilisateur</label>
                    <div class="input-group">
                        <span class="input-group-text">👤</span>
                        <input type="text" name="username" class="form-control" 
                               placeholder="admin" required autofocus autocomplete="off">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Mot de passe</label>
                    <div class="input-group">
                        <span class="input-group-text">🔒</span>
                        <input type="password" name="password" id="password" class="form-control" 
                               placeholder="••••••••" required autocomplete="off">
                        <span class="password-toggle" onclick="togglePassword()" id="toggleIcon">👁️</span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login w-100">
                    🔐 Se connecter
                </button>
            </form>
            
            <div class="login-footer">
                🔒 Connexion sécurisée • INOX PHARMA © <?php echo date('Y'); ?>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = '🙈';
            } else {
                input.type = 'password';
                icon.textContent = '👁️';
            }
        }
    </script>
</body>
</html>