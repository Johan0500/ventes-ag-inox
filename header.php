<?php
require_once __DIR__ . '/auth.php';
requireLogin();

$currentPage = basename($_SERVER['PHP_SELF']);

// Détermine le chemin de base absolu (fonctionne depuis n'importe quel sous-dossier)
$base = '/inox-pharma-ventes/';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5; --primary-dark: #3730a3; --primary-light: #818cf8;
            --success: #10b981; --success-light: #d1fae5;
            --danger: #ef4444; --danger-light: #fee2e2;
            --warning: #f59e0b; --warning-light: #fef3c7;
            --info: #06b6d4; --info-light: #cffafe;
            --purple: #8b5cf6; --bg: #f1f5f9; --card: #ffffff;
            --text: #1e293b; --text-muted: #64748b; --border: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
            --radius-sm: 8px; --radius: 12px; --radius-lg: 16px; --radius-xl: 20px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { box-sizing: border-box; }
        body { background: var(--bg); font-family: 'Inter', system-ui, -apple-system, sans-serif; color: var(--text); line-height: 1.6; -webkit-font-smoothing: antialiased; }
        .navbar { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4f46e5 100%) !important; box-shadow: var(--shadow-lg); padding: 0.5rem 0; }
        .navbar-brand { font-weight: 800; font-size: 1.25rem; background: linear-gradient(135deg, #ffffff 0%, #c7d2fe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; text-decoration: none; }
        .nav-link { color: rgba(255,255,255,0.85) !important; font-weight: 500; padding: 0.45rem 0.75rem !important; border-radius: var(--radius-sm); margin: 0 1px; transition: var(--transition); font-size: 0.85rem; white-space: nowrap; }
        .nav-link:hover { background: rgba(255,255,255,0.12); color: white !important; }
        .nav-link.active { background: rgba(255,255,255,0.2) !important; color: white !important; font-weight: 600; }
        .dropdown-menu { border: none; box-shadow: var(--shadow-xl); border-radius: var(--radius-lg); padding: 0.5rem; margin-top: 10px; animation: dropdownFade 0.2s ease; }
        @keyframes dropdownFade { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .dropdown-item { border-radius: var(--radius-sm); padding: 0.5rem 0.9rem; font-size: 0.85rem; transition: var(--transition); }
        .dropdown-item:hover { background: #eef2ff; color: var(--primary); }
        .dropdown-header { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: #ef4444; font-weight: 700; padding: 0.5rem 0.9rem; }
        .dropdown-divider { border-color: #e2e8f0; }
        .user-badge { background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 0.35rem 0.8rem; border-radius: 50px; color: white; font-size: 0.82rem; font-weight: 500; border: 1px solid rgba(255,255,255,0.2); white-space: nowrap; }
        .user-badge a { color: white; text-decoration: none; opacity: 0.7; transition: var(--transition); }
        .user-badge a:hover { opacity: 1; }
        .labo-badge { background: rgba(255,255,255,0.2); padding: 0.2rem 0.6rem; border-radius: 50px; font-size: 0.7rem; margin-left: 5px; }
        .card { border: 1px solid var(--border); border-radius: var(--radius-xl); box-shadow: var(--shadow-sm); background: var(--card); transition: var(--transition); overflow: hidden; margin-bottom: 1.5rem; }
        .card:hover { box-shadow: var(--shadow-md); }
        .card-header { background: white; border-bottom: 1px solid var(--border); font-weight: 600; padding: 1rem 1.5rem; font-size: 0.9rem; display: flex; align-items: center; justify-content: space-between; }
        .card-body { padding: 1.5rem; }
        .chart-container { position: relative; height: 350px; width: 100%; }
        .btn { font-weight: 600; padding: 0.55rem 1.2rem; border-radius: var(--radius); transition: var(--transition); letter-spacing: 0.3px; }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); border: none; box-shadow: 0 4px 15px rgba(79,70,229,0.3); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(79,70,229,0.4); }
        .btn-success { background: linear-gradient(135deg, var(--success) 0%, #059669 100%); border: none; box-shadow: 0 4px 15px rgba(16,185,129,0.3); color: white; }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16,185,129,0.4); }
        .btn-outline-primary { border: 2px solid var(--primary); color: var(--primary); font-weight: 600; background: transparent; }
        .btn-outline-primary:hover { background: var(--primary); color: white; transform: translateY(-2px); }
        .btn-sm { padding: 0.35rem 0.8rem; font-size: 0.78rem; }
        .btn-lg { padding: 0.7rem 1.8rem; font-size: 0.95rem; }
        .form-control, .form-select { border-radius: var(--radius); border: 2px solid var(--border); padding: 0.55rem 0.9rem; transition: var(--transition); font-family: 'Inter', sans-serif; font-size: 0.85rem; }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(79,70,229,0.1); }
        .badge { font-weight: 600; padding: 0.35em 0.7em; border-radius: 50px; }
        .alert { border: none; border-radius: var(--radius-lg); padding: 0.9rem 1.2rem; font-weight: 500; }
        .table th { font-weight: 600; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); padding: 0.9rem 1rem; background: #f8fafc; border-bottom: 2px solid var(--border); }
        .table td { padding: 0.8rem 1rem; vertical-align: middle; border-bottom: 1px solid var(--border); }
        .table tbody tr { transition: var(--transition); }
        .table tbody tr:hover { background: #f8fafc; }
        .progress { background: #e2e8f0; border-radius: 50px; height: 8px; overflow: hidden; }
        .progress-bar { border-radius: 50px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fade { animation: fadeInUp 0.5s ease forwards; }
        @media (max-width: 992px) { .navbar-nav { padding: 0.5rem 0; } .nav-link { margin: 3px 0; } .user-badge { margin-top: 8px; } .chart-container { height: 280px; } }
        @media (max-width: 768px) { .chart-container { height: 230px; } }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $base; ?>index.php">🏥 INOX PHARMA</a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='index.php'?'active':''; ?>" href="<?php echo $base; ?>index.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    
                    <!-- Produits -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($currentPage,['produits.php','produit_detail.php'])?'active':''; ?>" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-box me-1"></i>Produits
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo $base; ?>produits.php"><i class="bi bi-search me-2"></i>Rechercher</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base; ?>produits.php?all=1"><i class="bi bi-list-ul me-2"></i>Tous les produits</a></li>
                        </ul>
                    </li>
                    
                    <!-- Clients -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($currentPage,['clients.php','client_detail.php'])?'active':''; ?>" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-shop me-1"></i>Clients
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo $base; ?>clients.php"><i class="bi bi-search me-2"></i>Rechercher</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base; ?>clients.php?all=1"><i class="bi bi-list-ul me-2"></i>Tous les clients</a></li>
                        </ul>
                    </li>
                    
                    <!-- Ventes par mois -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='ventes_mois.php'?'active':''; ?>" href="<?php echo $base; ?>ventes_mois.php">
                            <i class="bi bi-calendar-check me-1"></i>Ventes/Mois
                        </a>
                    </li>
                    
                    <!-- Par Province -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='ventes_province.php'?'active':''; ?>" href="<?php echo $base; ?>ventes_province.php">
                            <i class="bi bi-geo-alt me-1"></i>Par Province
                        </a>
                    </li>
                    
                    <!-- Comparaison -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='comparaison.php'?'active':''; ?>" href="<?php echo $base; ?>comparaison.php">
                            <i class="bi bi-bar-chart me-1"></i>Comparaison
                        </a>
                    </li>
                    
                    <!-- Régions -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='regions.php'?'active':''; ?>" href="<?php echo $base; ?>regions.php">
                            <i class="bi bi-map me-1"></i>Régions
                        </a>
                    </li>
                    
                    <!-- Prévisions -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='previsions.php'?'active':''; ?>" href="<?php echo $base; ?>previsions.php">
                            <i class="bi bi-graph-up-arrow me-1"></i>Prévisions
                        </a>
                    </li>
                    
                    <!-- Données (dropdown) -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($currentPage,['export.php','import.php','sectorisation.php','ajout_mois.php','import_ventes_delegues.php','mapping_provinces.php','rapport_delegues.php'])?'active':''; ?>" 
                           href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-folder-symlink me-1"></i>Données
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo $base; ?>ajout_mois.php"><i class="bi bi-plus-circle me-2"></i>Ajouter un mois</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base; ?>import/import.php"><i class="bi bi-upload me-2"></i>Importer ventes</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base; ?>import/sectorisation.php"><i class="bi bi-diagram-3 me-2"></i>Importer sectorisation</a></li>
                            
                            <!-- SECTION ADMIN UNIQUEMENT -->
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header">🔒 Espace Admin</h6></li>
                            <li><a class="dropdown-item" href="<?php echo $base; ?>import/import_ventes_delegues.php"><i class="bi bi-person-badge me-2"></i>Import Ventes Délégués</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base; ?>import/mapping_provinces.php"><i class="bi bi-geo-alt me-2"></i>Mapping Provinces</a></li>
                            <li><a class="dropdown-item" href="<?php echo $base; ?>import/rapport_delegues.php"><i class="bi bi-file-earmark-bar-graph me-2"></i>Rapport Délégués</a></li>
                            <?php endif; ?>
                            
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $base; ?>export.php"><i class="bi bi-file-earmark-excel me-2"></i>Export Excel</a></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo $base; ?>reset_data.php"><i class="bi bi-exclamation-triangle me-2"></i>Réinitialiser données</a></li>
                        </ul>
                    </li>
                </ul>
                
                <!-- Badge utilisateur -->
                <div class="user-badge d-flex align-items-center">
                    <span>👤 <?php echo htmlspecialchars($_SESSION['nom'] ?? $_SESSION['username']); ?></span>
                    <?php if (isset($_SESSION['labo']) && $_SESSION['labo'] !== 'admin'): ?>
                    <span class="labo-badge">🔬 <?php echo $_SESSION['labo'] === 'croient' ? 'Croient' : 'LIC'; ?></span>
                    <?php endif; ?>
                    <a href="<?php echo $base; ?>logout.php" class="ms-2" title="Déconnexion">🚪</a>
                </div>
            </div>
        </div>
    </nav>
    <div class="container-fluid px-4 mt-4">
