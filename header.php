<?php
require_once __DIR__ . '/auth.php';
requireLogin();

// Déterminer la page active
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Inox Pharma'; ?> - Inox Pharma</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #1a73e8;
            --success: #0d904f;
            --danger: #ea4335;
            --warning: #f9ab00;
        }
        * { box-sizing: border-box; }
        body { 
            background: #f0f2f5; 
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .navbar { 
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%) !important; 
            box-shadow: 0 2px 20px rgba(0,0,0,0.15);
            padding: 0.6rem 0;
        }
        .navbar-brand { font-weight: 700; font-size: 1.3rem; }
        .nav-link { 
            color: rgba(255,255,255,0.9) !important; 
            font-weight: 500; 
            padding: 0.5rem 1rem !important; 
            border-radius: 8px; 
            margin: 0 2px;
            transition: all 0.2s;
        }
        .nav-link:hover, .nav-link.active { 
            background: rgba(255,255,255,0.2); 
            color: white !important;
        }
        .dropdown-menu { 
            border: none; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.15); 
            border-radius: 10px; 
        }
        .card { 
            border: none; 
            border-radius: 16px; 
            box-shadow: 0 2px 12px rgba(0,0,0,0.04); 
            margin-bottom: 1.5rem;
        }
        .card-header { 
            background: white; 
            border-bottom: 1px solid #eef0f2; 
            font-weight: 600; 
            border-radius: 16px 16px 0 0 !important;
        }
        .chart-container { position: relative; height: 350px; }
        .user-badge {
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            color: white;
            font-size: 0.9rem;
        }
        .user-badge a { color: white; text-decoration: none; }
        .user-badge a:hover { opacity: 0.8; }
        
        @media (max-width: 768px) {
            .chart-container { height: 250px; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">🏥 INOX PHARMA</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='index.php'?'active':''; ?>" href="index.php">📊 Dashboard</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($currentPage,['produits.php','produit_detail.php'])?'active':''; ?>" href="#" data-bs-toggle="dropdown">📦 Produits</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="produits.php">🔍 Rechercher</a></li>
                            <li><a class="dropdown-item" href="produits.php?all=1">📋 Tous les produits</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($currentPage,['clients.php','client_detail.php'])?'active':''; ?>" href="#" data-bs-toggle="dropdown">🏪 Clients</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="clients.php">🔍 Rechercher</a></li>
                            <li><a class="dropdown-item" href="clients.php?all=1">📋 Tous les clients</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='comparaison.php'?'active':''; ?>" href="comparaison.php">📈 Comparaison</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='regions.php'?'active':''; ?>" href="regions.php">🗺️ Régions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='previsions.php'?'active':''; ?>" href="previsions.php">🔮 Prévisions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='export.php'?'active':''; ?>" href="export.php">📥 Export</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='ajout_mois.php'?'active':''; ?>" href="ajout_mois.php">➕ Nouveau mois</a>
                    </li>
                </ul>
                
                <div class="user-badge">
                    👤 <?php echo htmlspecialchars($_SESSION['nom'] ?? $_SESSION['username']); ?>
                    <a href="logout.php" class="ms-2" title="Déconnexion">🚪</a>
                </div>
            </div>
        </div>
    </nav>
    <div class="container-fluid px-4 mt-4">