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
            --purple: #6f42c1;
        }
        
        * { box-sizing: border-box; }
        
        body { 
            background: #f0f2f5; 
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
        }
        
        /* Navbar principale */
        .navbar { 
            background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%) !important; 
            box-shadow: 0 2px 20px rgba(0,0,0,0.15);
            padding: 0.5rem 0;
        }
        
        .navbar-brand { 
            font-weight: 700; 
            font-size: 1.3rem; 
            letter-spacing: -0.5px;
            transition: opacity 0.2s;
        }
        .navbar-brand:hover { opacity: 0.9; }
        
        .nav-link { 
            color: rgba(255,255,255,0.85) !important; 
            font-weight: 500; 
            padding: 0.5rem 0.8rem !important; 
            border-radius: 8px; 
            margin: 0 1px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        
        .nav-link:hover { 
            background: rgba(255,255,255,0.15); 
            color: white !important;
        }
        
        .nav-link.active { 
            background: rgba(255,255,255,0.25); 
            color: white !important;
            font-weight: 600;
        }
        
        .nav-link i {
            margin-right: 3px;
        }
        
        .dropdown-menu { 
            border: none; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
            border-radius: 12px; 
            padding: 0.5rem;
            margin-top: 10px;
        }
        
        .dropdown-item {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background: #f0f4ff;
            color: #1a73e8;
        }
        
        .dropdown-item i {
            margin-right: 8px;
            color: #1a73e8;
        }
        
        /* Badge utilisateur */
        .user-badge {
            background: rgba(255,255,255,0.2);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            color: white;
            font-size: 0.85rem;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .user-badge a { 
            color: white; 
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .user-badge a:hover { opacity: 1; }
        
        /* Cartes */
        .card { 
            border: none; 
            border-radius: 16px; 
            box-shadow: 0 2px 12px rgba(0,0,0,0.04); 
            margin-bottom: 1.5rem;
            background: white;
        }
        
        .card-header { 
            background: white; 
            border-bottom: 1px solid #eef0f2; 
            font-weight: 600; 
            padding: 1rem 1.5rem;
            border-radius: 16px 16px 0 0 !important;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Chart */
        .chart-container { 
            position: relative; 
            height: 350px; 
            width: 100%;
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade {
            animation: fadeInUp 0.5s ease forwards;
        }
        
        /* Tableau */
        .table th {
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            border-top: none;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .navbar-nav {
                padding: 0.5rem 0;
            }
            .nav-link {
                padding: 0.5rem 1rem !important;
                margin: 2px 0;
            }
            .user-badge {
                margin-top: 10px;
                text-align: center;
            }
            .chart-container { 
                height: 250px; 
            }
            .card-body {
                padding: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            .chart-container { 
                height: 220px; 
            }
            .container-fluid {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
        }
        
        /* Scrollbar stylisée */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
        }
        
        /* Badge notification */
        .badge-notif {
            position: relative;
            top: -8px;
            right: -5px;
            font-size: 0.6rem;
            padding: 3px 6px;
        }
    </style>
</head>
<body>
    <!-- Barre de navigation principale -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand" href="index.php">
                🏥 INOX PHARMA
            </a>
            
            <!-- Bouton hamburger mobile -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Menu -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='index.php'?'active':''; ?>" href="index.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    
                    <!-- Menu Produits -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($currentPage,['produits.php','produit_detail.php'])?'active':''; ?>" 
                           href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-box"></i> Produits
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="produits.php"><i class="bi bi-search"></i> Rechercher un produit</a></li>
                            <li><a class="dropdown-item" href="produits.php?all=1"><i class="bi bi-list-ul"></i> Tous les produits</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="comparaison.php?mode=produits"><i class="bi bi-bar-chart"></i> Comparer les produits</a></li>
                        </ul>
                    </li>
                    
                    <!-- Menu Clients -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($currentPage,['clients.php','client_detail.php'])?'active':''; ?>" 
                           href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-shop"></i> Clients
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="clients.php"><i class="bi bi-search"></i> Rechercher un client</a></li>
                            <li><a class="dropdown-item" href="clients.php?all=1"><i class="bi bi-list-ul"></i> Tous les clients</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="comparaison.php?mode=clients"><i class="bi bi-bar-chart"></i> Comparer les clients</a></li>
                        </ul>
                    </li>
                    
                    <!-- Ventes par mois -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='ventes_mois.php'?'active':''; ?>" href="ventes_mois.php">
                            <i class="bi bi-calendar-check"></i> Ventes/Mois
                        </a>
                    </li>
                    
                    <!-- Comparaison -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='comparaison.php'?'active':''; ?>" href="comparaison.php">
                            <i class="bi bi-bar-chart"></i> Comparaison
                        </a>
                    </li>
                    
                    <!-- Régions -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='regions.php'?'active':''; ?>" href="regions.php">
                            <i class="bi bi-map"></i> Régions
                        </a>
                    </li>
                    
                    <!-- Prévisions -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='previsions.php'?'active':''; ?>" href="previsions.php">
                            <i class="bi bi-graph-up-arrow"></i> Prévisions
                        </a>
                    </li>
                    
                    <!-- Menu Import/Export -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($currentPage,['export.php','ajout_mois.php','import/import.php'])?'active':''; ?>" 
                           href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-folder-symlink"></i> Données
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="ajout_mois.php"><i class="bi bi-plus-circle"></i> Ajouter un mois</a></li>
                            <li><a class="dropdown-item" href="import/import.php"><i class="bi bi-upload"></i> Importer Excel</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="export.php"><i class="bi bi-file-earmark-excel"></i> Export Excel</a></li>
                            <li><a class="dropdown-item" href="export.php?format=excel&type=agences"><i class="bi bi-building"></i> Export Agences</a></li>
                        </ul>
                    </li>
                </ul>
                
                <!-- Badge utilisateur connecté -->
                <div class="user-badge d-flex align-items-center">
                    <span>👤 <?php echo htmlspecialchars($_SESSION['nom'] ?? $_SESSION['username']); ?></span>
                    <a href="logout.php" class="ms-2" title="Déconnexion" data-bs-toggle="tooltip">
                        🚪
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Contenu principal -->
    <div class="container-fluid px-4 mt-4">