<?php
require_once __DIR__ . '/auth.php';
requireLogin();

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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --primary-light: #818cf8;
            --success: #10b981;
            --success-light: #d1fae5;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --info: #06b6d4;
            --info-light: #cffafe;
            --purple: #8b5cf6;
            --purple-light: #ede9fe;
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --radius-sm: 8px;
            --radius: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { box-sizing: border-box; }
        
        body { 
            background: var(--bg); 
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        
        /* ======== NAVBAR ======== */
        .navbar { 
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4f46e5 100%) !important;
            box-shadow: var(--shadow-lg);
            padding: 0.6rem 0;
            backdrop-filter: blur(10px);
        }
        
        .navbar-brand { 
            font-weight: 800; 
            font-size: 1.3rem; 
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #ffffff 0%, #c7d2fe 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav-link { 
            color: rgba(255,255,255,0.8) !important; 
            font-weight: 500; 
            padding: 0.5rem 0.9rem !important; 
            border-radius: var(--radius);
            margin: 0 2px;
            transition: var(--transition);
            font-size: 0.88rem;
            position: relative;
        }
        
        .nav-link:hover { 
            background: rgba(255,255,255,0.12); 
            color: white !important;
        }
        
        .nav-link.active { 
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
            font-weight: 600;
            box-shadow: 0 0 20px rgba(255,255,255,0.1);
        }
        
        .dropdown-menu { 
            border: none; 
            box-shadow: var(--shadow-xl);
            border-radius: var(--radius-lg);
            padding: 0.5rem;
            margin-top: 12px;
            background: white;
            animation: dropdownFade 0.2s ease;
        }
        
        @keyframes dropdownFade {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .dropdown-item {
            border-radius: var(--radius-sm);
            padding: 0.6rem 1rem;
            font-size: 0.88rem;
            transition: var(--transition);
        }
        
        .dropdown-item:hover {
            background: #eef2ff;
            color: var(--primary);
        }
        
        .user-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 0.4rem 1rem;
            border-radius: 50px;
            color: white;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .user-badge a { 
            color: white; 
            text-decoration: none;
            opacity: 0.7;
            transition: var(--transition);
        }
        .user-badge a:hover { opacity: 1; }
        
        /* ======== CARDS ======== */
        .card { 
            border: 1px solid var(--border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-sm);
            background: var(--card);
            transition: var(--transition);
            overflow: hidden;
        }
        
        .card:hover { 
            box-shadow: var(--shadow-md);
        }
        
        .card-header { 
            background: white; 
            border-bottom: 1px solid var(--border);
            font-weight: 600; 
            padding: 1.1rem 1.5rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* ======== STAT CARDS ======== */
        .stat-card {
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            cursor: pointer;
            border: none;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }
        
        .stat-card:hover::after {
            width: 120px;
            height: 120px;
        }
        
        .stat-card .icon-stat {
            font-size: 2.5rem;
            opacity: 0.3;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 800;
            position: relative;
            z-index: 1;
        }
        
        .stat-card .label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.85;
            position: relative;
            z-index: 1;
        }
        
        /* ======== BUTTONS ======== */
        .btn {
            font-weight: 600;
            padding: 0.6rem 1.3rem;
            border-radius: var(--radius);
            transition: var(--transition);
            letter-spacing: 0.3px;
            position: relative;
            overflow: hidden;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            border: none;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            font-weight: 600;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 0.4rem 1rem;
            font-size: 0.82rem;
        }
        
        .btn-lg {
            padding: 0.8rem 2rem;
            font-size: 1rem;
        }
        
        /* ======== TABLES ======== */
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            padding: 1rem 1.2rem;
            background: #f8fafc;
            border-bottom: 2px solid var(--border);
        }
        
        .table td {
            padding: 0.9rem 1.2rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
        }
        
        .table tbody tr {
            transition: var(--transition);
        }
        
        .table tbody tr:hover {
            background: #f8fafc;
        }
        
        /* ======== BADGES ======== */
        .badge {
            font-weight: 600;
            padding: 0.4em 0.8em;
            border-radius: 50px;
            letter-spacing: 0.3px;
        }
        
        /* ======== ALERTS ======== */
        .alert {
            border: none;
            border-radius: var(--radius-lg);
            padding: 1rem 1.3rem;
            font-weight: 500;
            box-shadow: var(--shadow-sm);
        }
        
        .alert-flash {
            border-radius: var(--radius);
            padding: 0.8rem 1.2rem;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            border-left: 4px solid;
        }
        
        .alert-flash.danger { 
            background: var(--danger-light); 
            border-left-color: var(--danger);
            color: #991b1b;
        }
        
        .alert-flash.success { 
            background: var(--success-light); 
            border-left-color: var(--success);
            color: #065f46;
        }
        
        /* ======== PROGRESS BARS ======== */
        .progress {
            background: #e2e8f0;
            border-radius: 50px;
            height: 10px;
            overflow: hidden;
        }
        
        .progress-bar {
            border-radius: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            transition: width 0.6s ease;
        }
        
        .progress-bar.bg-success {
            background: linear-gradient(135deg, var(--success) 0%, #34d399 100%);
        }
        
        /* ======== EXECUTIVE SUMMARY ======== */
        .executive-summary {
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4f46e5 100%);
            color: white;
            border-radius: var(--radius-xl);
            padding: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
        }
        
        .executive-summary::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
        }
        
        .executive-summary::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.03);
            border-radius: 50%;
        }
        
        /* ======== CHART CONTAINER ======== */
        .chart-container { 
            position: relative; 
            height: 350px; 
            width: 100%;
        }
        
        /* ======== TOP LINKS ======== */
        .top-link {
            display: flex;
            align-items: center;
            padding: 0.7rem 1.2rem;
            text-decoration: none;
            color: var(--text);
            transition: var(--transition);
            border-bottom: 1px solid var(--border);
            gap: 10px;
        }
        
        .top-link:last-child { border-bottom: none; }
        
        .top-link:hover { 
            background: #f8fafc;
            padding-left: 1.5rem;
        }
        
        .top-link .rank { 
            width: 32px; 
            font-weight: 700; 
            text-align: center;
            font-size: 0.9rem;
        }
        
        .top-link .rank.gold { color: #f59e0b; }
        .top-link .rank.silver { color: #94a3b8; }
        .top-link .rank.bronze { color: #d97706; }
        
        /* ======== ANIMATIONS ======== */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .animate-fade {
            animation: fadeInUp 0.5s ease forwards;
        }
        
        .animate-fade:nth-child(1) { animation-delay: 0s; }
        .animate-fade:nth-child(2) { animation-delay: 0.05s; }
        .animate-fade:nth-child(3) { animation-delay: 0.1s; }
        .animate-fade:nth-child(4) { animation-delay: 0.15s; }
        .animate-fade:nth-child(5) { animation-delay: 0.2s; }
        .animate-fade:nth-child(6) { animation-delay: 0.25s; }
        
        /* ======== RESPONSIVE ======== */
        @media (max-width: 992px) {
            .navbar-nav { padding: 0.5rem 0; }
            .nav-link { margin: 3px 0; }
            .user-badge { margin-top: 10px; }
            .chart-container { height: 280px; }
        }
        
        @media (max-width: 768px) {
            .chart-container { height: 230px; }
            .stat-card .value { font-size: 1.5rem; }
            .executive-summary { padding: 1.5rem; }
        }
        
        /* ======== SCROLLBAR ======== */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* ======== FORM CONTROLS ======== */
        .form-control, .form-select {
            border-radius: var(--radius);
            border: 2px solid var(--border);
            padding: 0.6rem 1rem;
            transition: var(--transition);
            font-family: 'Inter', sans-serif;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }
        
        /* ======== SELECTION ======== */
        ::selection {
            background: var(--primary-light);
            color: white;
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <span style="font-size:1.4rem;">🏥</span> INOX PHARMA
            </a>
            
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='index.php'?'active':''; ?>" href="index.php">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($currentPage,['produits.php','produit_detail.php'])?'active':''; ?>" 
                           href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-box me-1"></i> Produits
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="produits.php"><i class="bi bi-search me-2"></i>Rechercher</a></li>
                            <li><a class="dropdown-item" href="produits.php?all=1"><i class="bi bi-list-ul me-2"></i>Tous les produits</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($currentPage,['clients.php','client_detail.php'])?'active':''; ?>" 
                           href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-shop me-1"></i> Clients
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="clients.php"><i class="bi bi-search me-2"></i>Rechercher</a></li>
                            <li><a class="dropdown-item" href="clients.php?all=1"><i class="bi bi-list-ul me-2"></i>Tous les clients</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='ventes_mois.php'?'active':''; ?>" href="ventes_mois.php">
                            <i class="bi bi-calendar-check me-1"></i> Ventes/Mois
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='comparaison.php'?'active':''; ?>" href="comparaison.php">
                            <i class="bi bi-bar-chart me-1"></i> Comparaison
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='regions.php'?'active':''; ?>" href="regions.php">
                            <i class="bi bi-map me-1"></i> Régions
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage=='previsions.php'?'active':''; ?>" href="previsions.php">
                            <i class="bi bi-graph-up-arrow me-1"></i> Prévisions
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($currentPage,['export.php','ajout_mois.php','import/import.php'])?'active':''; ?>" 
                           href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-folder-symlink me-1"></i> Données
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="ajout_mois.php"><i class="bi bi-plus-circle me-2"></i>Ajouter un mois</a></li>
                            <li><a class="dropdown-item" href="import/import.php"><i class="bi bi-upload me-2"></i>Importer Excel</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="export.php"><i class="bi bi-file-earmark-excel me-2"></i>Export Excel</a></li>
                        </ul>
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