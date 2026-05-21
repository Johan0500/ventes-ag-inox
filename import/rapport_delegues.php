<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "Rapport Délégués";
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../header.php';

// Niveau de détail
$niveau      = $_GET['niveau']      ?? 'delegues';
$delegue_id  = intval($_GET['delegue_id'] ?? 0);
$medicament  = $_GET['medicament']  ?? '';
$delegue_nom = $_GET['delegue_nom'] ?? '';

// Filtre mois (optionnel)
$moisFiltre     = $_GET['mois']      ?? 'all';
$grossisteFiltre = $_GET['grossiste'] ?? 'all';

// Récupérer les mois disponibles pour les ventes délégués
try {
    $moisDispo = $pdo->query("
        SELECT DISTINCT DATE_FORMAT(mois, '%Y-%m') as m 
        FROM ventes_attribuees 
        ORDER BY m DESC
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch(Exception $e) {
    $moisDispo = [];
}

// Construire la condition mois
$condMois       = '';
$paramsMois     = [];
$condGrossiste  = '';
$paramsGrossiste = [];
if ($moisFiltre !== 'all' && in_array($moisFiltre, $moisDispo)) {
    $condMois   = "AND DATE_FORMAT(v.mois, '%Y-%m') = :mois";
    $paramsMois = [':mois' => $moisFiltre];
}
$grossistesDispos = ['copharmed' => 'COPHARMED', 'dpci' => 'DPCI', 'laborex' => 'LABOREX', 'tedis' => 'TEDIS'];
if ($grossisteFiltre !== 'all' && array_key_exists($grossisteFiltre, $grossistesDispos)) {
    $condGrossiste   = "AND LOWER(v.grossiste_code) = :grossiste";
    $paramsGrossiste = [':grossiste' => $grossisteFiltre];
}
?>

<style>
/* ── Palette délégués ── */
:root {
    --del-accent: #6366f1;
    --del-accent2: #8b5cf6;
    --del-gold: #f59e0b;
    --del-green: #10b981;
    --del-red: #ef4444;
    --del-surface: #f8faff;
}

/* ── Hero header ── */
.del-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4f46e5 100%);
    border-radius: 20px;
    padding: 1.6rem 2rem;
    margin-bottom: 1.5rem;
    color: white;
    position: relative;
    overflow: hidden;
}
.del-hero::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 200px; height: 200px;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}
.del-hero::after {
    content: '';
    position: absolute;
    bottom: -60px; right: 60px;
    width: 150px; height: 150px;
    background: rgba(255,255,255,0.03);
    border-radius: 50%;
}
.breadcrumb-del a { color: rgba(255,255,255,0.7); text-decoration: none; transition: color .2s; }
.breadcrumb-del a:hover { color: white; }
.breadcrumb-del .sep { color: rgba(255,255,255,0.35); margin: 0 6px; }
.breadcrumb-del .current { color: white; font-weight: 600; }

/* ── KPI cards ── */
.kpi-grid { display: grid; gap: 1rem; margin-bottom: 1.5rem; }
.kpi-grid-4 { grid-template-columns: repeat(4, 1fr); }
.kpi-grid-3 { grid-template-columns: repeat(3, 1fr); }
@media(max-width:768px) {
    .kpi-grid-4, .kpi-grid-3 { grid-template-columns: repeat(2, 1fr); }
}
.kpi-card {
    border-radius: 16px;
    padding: 1.3rem 1.5rem;
    color: white;
    position: relative;
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
}
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
.kpi-card .kpi-val { font-size: 2rem; font-weight: 800; line-height: 1; }
.kpi-card .kpi-lbl { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1.2px; opacity: .8; margin-top: 4px; }
.kpi-card .kpi-icon { position: absolute; right: 16px; top: 16px; font-size: 1.8rem; opacity: .25; }

/* ── Tableau délégués ── */
.del-table th { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; }
.del-table td { vertical-align: middle; }
.del-row:hover { background: #f0f4ff !important; cursor: pointer; }
.del-name { font-weight: 700; font-size: 0.95rem; color: #1e1b4b; }
.del-inactive .del-name { color: #94a3b8; }

/* ── Badge zone ── */
.zone-badge {
    display: inline-block;
    background: #eef2ff;
    color: #4338ca;
    border-radius: 50px;
    padding: 0.25em 0.75em;
    font-size: 0.72rem;
    font-weight: 600;
    border: 1px solid #c7d2fe;
    margin: 1px;
}

/* ── Portefeuille progress ── */
.ptf-bar {
    height: 6px;
    background: #e2e8f0;
    border-radius: 50px;
    overflow: hidden;
    margin-top: 4px;
}
.ptf-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    border-radius: 50px;
    transition: width .8s ease;
}

/* ── Bouton détail ── */
.btn-detail {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 0.4rem 1rem;
    font-size: 0.78rem;
    font-weight: 600;
    text-decoration: none;
    transition: all .2s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
}
.btn-detail:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(99,102,241,.4); color: white; }

/* ── Rang médaille ── */
.rank-medal { font-size: 1.2rem; }

/* ── Barre de filtres ── */
.filter-strip {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 0.8rem 1.2rem;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}

/* ── Tableau médicaments ── */
.med-row td:first-child { padding-left: 1.2rem; }
.ca-val { font-variant-numeric: tabular-nums; }

/* ── Animations ── */
@keyframes slideIn { from { opacity:0; transform:translateY(16px);} to { opacity:1; transform:translateY(0);} }
.anim { animation: slideIn .4s ease forwards; }
.anim-delay-1 { animation-delay: .05s; opacity:0; }
.anim-delay-2 { animation-delay: .10s; opacity:0; }
.anim-delay-3 { animation-delay: .15s; opacity:0; }
.anim-delay-4 { animation-delay: .20s; opacity:0; }

/* Sparkline mini chart */
.perf-indicator {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
}
</style>

<div class="container-fluid">

<?php
/* ════════════════════════════════════════════════════════
   VUE 1 : LISTE DES DÉLÉGUÉS
════════════════════════════════════════════════════════ */
if ($niveau === 'delegues'):
    try {
        // ── Récupérer délégués avec stats ventes + portefeuille réel ──
        $sql = "
            SELECT
                d.id,
                d.nom,
                d.labo,
                -- Zones (secteurs assignés)
                GROUP_CONCAT(DISTINCT s.nom_secteur ORDER BY s.nom_secteur SEPARATOR '|') AS secteurs_raw,
                -- Portefeuille réel (table delegue_produit)
                (SELECT COUNT(*) FROM delegue_produit dp WHERE dp.delegue_id = d.id) AS nb_portefeuille,
                -- Produits effectivement vendus
                COUNT(DISTINCT v.libelle_article) AS nb_produits_vendus,
                -- Nb pharmacies touchées
                COUNT(DISTINCT v.designation_client) AS nb_pharmacies,
                -- Volume
                COALESCE(SUM(v.qte_livree), 0) AS total_boites,
                -- CA
                COALESCE(SUM(v.qte_livree * v.prix_cession), 0) AS ca_total
            FROM delegues d
            LEFT JOIN secteur_delegue sd ON sd.delegue_id = d.id
            LEFT JOIN secteurs s ON s.id = sd.secteur_id
            LEFT JOIN ventes_attribuees v ON v.delegue_id_calcule = d.id
                $condMois $condGrossiste
            WHERE d.actif = 1
            GROUP BY d.id, d.nom, d.labo
            ORDER BY total_boites DESC, d.nom ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($paramsMois + $paramsGrossiste);
        $delegues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Stats globales
        $total_boites_global  = array_sum(array_column($delegues, 'total_boites'));
        $total_ca_global      = array_sum(array_column($delegues, 'ca_total'));
        $total_pharma_global  = array_sum(array_column($delegues, 'nb_pharmacies'));
        $delegues_actifs      = count(array_filter($delegues, fn($d) => $d['total_boites'] > 0));
        $max_boites           = max(array_column($delegues, 'total_boites') ?: [1]);
        $max_boites           = $max_boites ?: 1;

    } catch(Exception $e) {
        $delegues = [];
        $total_boites_global = $total_ca_global = $total_pharma_global = $delegues_actifs = 0;
        $max_boites = 1;
    }
?>

<!-- ── Hero ── -->
<div class="del-hero anim">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h4 class="mb-1 fw-800">👥 Rapport Délégués</h4>
            <div class="breadcrumb-del small">
                <span class="current">👥 Délégués</span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="import_ventes_delegues.php" class="btn btn-sm btn-light fw-600">
                <i class="bi bi-upload me-1"></i>Import
            </a>
            <a href="mapping_provinces.php" class="btn btn-sm btn-outline-light fw-600">
                <i class="bi bi-geo-alt me-1"></i>Mapping
            </a>
            <a href="../index.php" class="btn btn-sm btn-outline-light fw-600">
                <i class="bi bi-speedometer2 me-1"></i>Dashboard
            </a>
        </div>
    </div>
</div>

<!-- ── Filtre mois ── -->
<div class="filter-strip anim anim-delay-1">
    <span class="small fw-600 text-muted">📅 Mois :</span>
    <select class="form-select form-select-sm" style="width:auto;" onchange="applyFiltre('mois', this.value)">
        <option value="all" <?= $moisFiltre==='all'?'selected':'' ?>>Tous les mois</option>
        <?php foreach ($moisDispo as $m): ?>
        <option value="<?= $m ?>" <?= $moisFiltre===$m?'selected':'' ?>><?= $m ?></option>
        <?php endforeach; ?>
    </select>

    <span class="small fw-600 text-muted ms-2">🏢 Grossiste :</span>
    <select class="form-select form-select-sm" style="width:auto;" onchange="applyFiltre('grossiste', this.value)">
        <option value="all" <?= $grossisteFiltre==='all'?'selected':'' ?>>Tous les grossistes</option>
        <option value="copharmed" <?= $grossisteFiltre==='copharmed'?'selected':'' ?>>COPHARMED</option>
        <option value="dpci"      <?= $grossisteFiltre==='dpci'?'selected':'' ?>>DPCI</option>
        <option value="laborex"   <?= $grossisteFiltre==='laborex'?'selected':'' ?>>LABOREX</option>
        <option value="tedis"     <?= $grossisteFiltre==='tedis'?'selected':'' ?>>TEDIS</option>
    </select>

    <?php if ($moisFiltre !== 'all' || $grossisteFiltre !== 'all'): ?>
    <a href="?niveau=delegues" class="btn btn-sm btn-outline-secondary">✕ Tout réinitialiser</a>
    <?php endif; ?>
    <span class="ms-auto small text-muted">
        <?= count($delegues) ?> délégués · <?= $delegues_actifs ?> actifs
    </span>
</div>

<!-- ── KPIs ── -->
<div class="kpi-grid kpi-grid-4 anim anim-delay-1">
    <div class="kpi-card" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
        <div class="kpi-icon">👥</div>
        <div class="kpi-val"><?= count($delegues) ?></div>
        <div class="kpi-lbl">Délégués Total</div>
    </div>
    <div class="kpi-card" style="background:linear-gradient(135deg,#10b981,#059669);">
        <div class="kpi-icon">✅</div>
        <div class="kpi-val"><?= $delegues_actifs ?></div>
        <div class="kpi-lbl">Délégués Actifs</div>
    </div>
    <div class="kpi-card" style="background:linear-gradient(135deg,#06b6d4,#0891b2);">
        <div class="kpi-icon">📦</div>
        <div class="kpi-val"><?= number_format($total_boites_global) ?></div>
        <div class="kpi-lbl">Total Boîtes</div>
    </div>
    <div class="kpi-card" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
        <div class="kpi-icon">💰</div>
        <div class="kpi-val" style="font-size:1.3rem;"><?= number_format($total_ca_global/1000000, 1) ?>M</div>
        <div class="kpi-lbl">CA Total (F CFA)</div>
    </div>
</div>

<!-- ── Graphique performance ── -->
<?php if ($delegues_actifs > 0): ?>
<div class="card anim anim-delay-2 mb-4">
    <div class="card-header">
        <span><i class="bi bi-bar-chart-fill me-2 text-primary"></i>Performance par Délégué (boîtes vendues)</span>
        <span class="badge bg-primary-subtle text-primary"><?= $delegues_actifs ?> actifs</span>
    </div>
    <div class="card-body" style="padding:1rem 1.5rem;">
        <div class="chart-container" style="height:260px;">
            <canvas id="chartDelegues"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Tableau ── -->
<div class="card anim anim-delay-3">
    <div class="card-header">
        <span><i class="bi bi-people-fill me-2 text-primary"></i>Liste des Délégués</span>
        <input type="text" id="searchDel" class="form-control form-control-sm"
               style="max-width:240px;" placeholder="🔍 Rechercher un délégué…"
               oninput="filterTable('tableDel', this.value)">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 del-table" id="tableDel">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Délégué</th>
                        <th>Zone / Secteur</th>
                        <th class="text-center">📋 Portefeuille</th>
                        <th class="text-center">💊 Vendus</th>
                        <th class="text-center">🏪 Pharmacies</th>
                        <th class="text-end">📦 Boîtes</th>
                        <th class="text-end">💰 CA (F CFA)</th>
                        <th class="text-center">%</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($delegues as $idx => $del):
                        $boites       = (int)$del['total_boites'];
                        $ca           = (float)$del['ca_total'];
                        $ptfTotal     = (int)$del['nb_portefeuille'];
                        $ptfVendus    = (int)$del['nb_produits_vendus'];
                        $ptfPct       = $ptfTotal > 0 ? round(($ptfVendus / $ptfTotal) * 100) : 0;
                        $partGlobale  = $total_boites_global > 0 ? round(($boites / $total_boites_global) * 100, 1) : 0;
                        $perfPct      = $max_boites > 0 ? round(($boites / $max_boites) * 100) : 0;
                        $isInactive   = $boites === 0;
                        $medals       = ['🥇','🥈','🥉'];
                        $secteurs     = $del['secteurs_raw'] ? explode('|', $del['secteurs_raw']) : [];
                    ?>
                    <tr class="del-row <?= $isInactive ? 'del-inactive' : '' ?>"
                        <?= !$isInactive ? "onclick=\"window.location='?niveau=medicaments&delegue_id={$del['id']}&delegue_nom=".urlencode($del['nom'])."&mois={$moisFiltre}'\"" : '' ?>>
                        <td class="text-muted small">
                            <?= isset($medals[$idx]) ? "<span class='rank-medal'>{$medals[$idx]}</span>" : ($idx+1) ?>
                        </td>
                        <td>
                            <div class="del-name"><?= htmlspecialchars($del['nom']) ?></div>
                            <?php if ($isInactive): ?>
                                <span class="badge" style="font-size:.65rem;background:#e2e8f0;color:#64748b;">⏳ Sans ventes</span>
                            <?php else: ?>
                                <div class="ptf-bar" style="max-width:120px;">
                                    <div class="ptf-bar-fill" style="width:<?= $perfPct ?>%"></div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($secteurs)): ?>
                                <?php foreach (array_slice($secteurs, 0, 3) as $s): ?>
                                    <span class="zone-badge"><?= htmlspecialchars($s) ?></span>
                                <?php endforeach; ?>
                                <?php if (count($secteurs) > 3): ?>
                                    <span class="zone-badge" style="background:#f1f5f9;color:#64748b;">
                                        +<?= count($secteurs)-3 ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($ptfTotal > 0): ?>
                                <span class="fw-700 text-primary"><?= $ptfTotal ?></span>
                                <div class="text-muted" style="font-size:.65rem;">produits</div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($ptfVendus > 0): ?>
                                <span class="fw-600"><?= $ptfVendus ?></span>
                                <?php if ($ptfTotal > 0): ?>
                                <div style="font-size:.65rem;color:<?= $ptfPct>=70?'#10b981':($ptfPct>=40?'#f59e0b':'#ef4444') ?>;">
                                    <?= $ptfPct ?>% du ptf
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?= $isInactive ? '<span class="text-muted">—</span>' : "<span class='fw-600'>{$del['nb_pharmacies']}</span>" ?>
                        </td>
                        <td class="text-end">
                            <?php if ($boites > 0): ?>
                                <span class="fw-800 text-primary" style="font-size:1rem;"><?= number_format($boites) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end ca-val">
                            <?= $ca > 0 ? '<span class="fw-600">'.number_format($ca, 0, ',', ' ').'</span>' : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td class="text-center">
                            <?php if ($partGlobale > 0): ?>
                                <span class="badge" style="background:<?= $partGlobale>20?'#6366f1':($partGlobale>10?'#06b6d4':'#94a3b8') ?>;color:white;font-size:.7rem;">
                                    <?= $partGlobale ?>%
                                </span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center" onclick="event.stopPropagation()">
                            <?php if (!$isInactive): ?>
                            <a href="?niveau=medicaments&delegue_id=<?= $del['id'] ?>&delegue_nom=<?= urlencode($del['nom']) ?>&mois=<?= $moisFiltre ?>"
                               class="btn-detail">
                                <i class="bi bi-eye-fill"></i> Détail
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light fw-700">
                        <td colspan="6" class="text-end small text-muted">TOTAL GÉNÉRAL</td>
                        <td class="text-end text-primary fw-800"><?= number_format($total_boites_global) ?></td>
                        <td class="text-end ca-val"><?= number_format($total_ca_global, 0, ',', ' ') ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Chart JS -->
<script>
(function(){
    var data = <?php
        $actifs = array_filter($delegues, fn($d) => $d['total_boites'] > 0);
        $actifs = array_values($actifs);
        echo json_encode(array_map(function($d){
            $parts = explode(' ', $d['nom']);
            $label = implode(' ', array_slice($parts, 0, 2));
            return ['label'=>$label, 'boites'=>(int)$d['total_boites'], 'ca'=>(float)$d['ca_total']];
        }, $actifs));
    ?>;
    if (!data.length) return;
    var ctx = document.getElementById('chartDelegues');
    if (!ctx) return;
    new Chart(ctx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.map(function(d){ return d.label; }),
            datasets: [{
                label: 'Boîtes vendues',
                data: data.map(function(d){ return d.boites; }),
                backgroundColor: data.map(function(d,i){
                    var colors = ['rgba(99,102,241,.85)','rgba(139,92,246,.85)','rgba(6,182,212,.85)',
                                  'rgba(16,185,129,.85)','rgba(245,158,11,.85)'];
                    return colors[i % colors.length];
                }),
                borderRadius: 8,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false },
                tooltip: { callbacks: { label: function(c){ return ' '+c.parsed.y.toLocaleString()+' boîtes'; }}}
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' },
                     ticks: { callback: function(v){ return v >= 1000 ? (v/1000).toFixed(0)+'k' : v; }}},
                x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 40 }}
            }
        }
    });
})();
</script>

<?php
/* ════════════════════════════════════════════════════════
   VUE 2 : MÉDICAMENTS D'UN DÉLÉGUÉ
════════════════════════════════════════════════════════ */
elseif ($niveau === 'medicaments' && $delegue_id):
    try {
        // Infos du délégué
        $infoDel = $pdo->prepare("
            SELECT d.nom, d.labo,
                   GROUP_CONCAT(DISTINCT s.nom_secteur ORDER BY s.nom_secteur SEPARATOR ', ') AS secteurs
            FROM delegues d
            LEFT JOIN secteur_delegue sd ON sd.delegue_id = d.id
            LEFT JOIN secteurs s ON s.id = sd.secteur_id
            WHERE d.id = :id
            GROUP BY d.id, d.nom, d.labo
        ");
        $infoDel->execute([':id' => $delegue_id]);
        $infoDel = $infoDel->fetch(PDO::FETCH_ASSOC);
        $delegue_nom = $infoDel['nom'] ?? $delegue_nom;

        // Portefeuille assigné
        $ptfStmt = $pdo->prepare("SELECT libelle_produit FROM delegue_produit WHERE delegue_id = :id ORDER BY libelle_produit");
        $ptfStmt->execute([':id' => $delegue_id]);
        $portefeuille = $ptfStmt->fetchAll(PDO::FETCH_COLUMN);

        // Médicaments vendus avec stats
        $sql = "
            SELECT
                v.libelle_article,
                COUNT(DISTINCT v.designation_client) AS nb_pharmacies,
                COALESCE(SUM(v.qte_livree), 0) AS total_boites,
                COALESCE(SUM(v.qte_livree * v.prix_cession), 0) AS ca
            FROM ventes_attribuees v
            WHERE v.delegue_id_calcule = :did
              $condMois $condGrossiste
            GROUP BY v.libelle_article
            ORDER BY total_boites DESC
        ";
        $params = [':did' => $delegue_id] + $paramsMois + $paramsGrossiste;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $medicaments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_boites = array_sum(array_column($medicaments, 'total_boites'));
        $total_ca     = array_sum(array_column($medicaments, 'ca'));
        $max_med      = max(array_column($medicaments, 'total_boites') ?: [1]) ?: 1;

        // Enrichir avec info "dans portefeuille"
        $ptfSet = array_map('strtoupper', $portefeuille);
        foreach ($medicaments as &$med) {
            $med['in_ptf'] = in_array(strtoupper($med['libelle_article']), $ptfSet);
        }
        unset($med);

        // Produits du portefeuille non encore vendus
        $venduLabels = array_map('strtoupper', array_column($medicaments, 'libelle_article'));
        $nonVendus   = array_filter($portefeuille, fn($p) => !in_array(strtoupper($p), $venduLabels));

    } catch(Exception $e) {
        $medicaments = []; $portefeuille = []; $nonVendus = [];
        $total_boites = $total_ca = 0; $max_med = 1;
        $infoDel = ['nom' => $delegue_nom, 'secteurs' => ''];
    }
?>

<!-- ── Hero ── -->
<div class="del-hero anim">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h4 class="mb-1 fw-800">💊 <?= htmlspecialchars($delegue_nom) ?></h4>
            <div class="small opacity-75 mb-2">📍 <?= htmlspecialchars($infoDel['secteurs'] ?? '') ?></div>
            <div class="breadcrumb-del small">
                <a href="?niveau=delegues&mois=<?= $moisFiltre ?>">👥 Délégués</a>
                <span class="sep">›</span>
                <span class="current">📦 Médicaments</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="?niveau=delegues&mois=<?= $moisFiltre ?>" class="btn btn-sm btn-light fw-600">
                <i class="bi bi-arrow-left me-1"></i>Retour
            </a>
        </div>
    </div>
</div>

<!-- ── Filtre mois ── -->
<div class="filter-strip anim anim-delay-1">
    <span class="small fw-600 text-muted">📅 Filtrer :</span>
    <select class="form-select form-select-sm" style="width:auto;"
            onchange="applyFiltreDel(<?= $delegue_id ?>, '<?= htmlspecialchars(addslashes($delegue_nom)) ?>', 'mois', this.value)">
        <option value="all" <?= $moisFiltre==='all'?'selected':'' ?>>Tous les mois</option>
        <?php foreach ($moisDispo as $m): ?>
        <option value="<?= $m ?>" <?= $moisFiltre===$m?'selected':'' ?>><?= $m ?></option>
        <?php endforeach; ?>
    </select>
    <span class="small fw-600 text-muted ms-2">🏢</span>
    <select class="form-select form-select-sm" style="width:auto;"
            onchange="applyFiltreDel(<?= $delegue_id ?>, '<?= htmlspecialchars(addslashes($delegue_nom)) ?>', 'grossiste', this.value)">
        <option value="all" <?= $grossisteFiltre==='all'?'selected':'' ?>>Tous grossistes</option>
        <option value="copharmed" <?= $grossisteFiltre==='copharmed'?'selected':'' ?>>COPHARMED</option>
        <option value="dpci"      <?= $grossisteFiltre==='dpci'?'selected':'' ?>>DPCI</option>
        <option value="laborex"   <?= $grossisteFiltre==='laborex'?'selected':'' ?>>LABOREX</option>
        <option value="tedis"     <?= $grossisteFiltre==='tedis'?'selected':'' ?>>TEDIS</option>
    </select>
    <?php if ($moisFiltre !== 'all' || $grossisteFiltre !== 'all'): ?>
    <a href="?niveau=medicaments&delegue_id=<?= $delegue_id ?>&delegue_nom=<?= urlencode($delegue_nom) ?>" class="btn btn-sm btn-outline-secondary">✕</a>
    <?php endif; ?>
</div>

<!-- ── KPIs ── -->
<div class="kpi-grid kpi-grid-4 anim anim-delay-1">
    <div class="kpi-card" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
        <div class="kpi-icon">📋</div>
        <div class="kpi-val"><?= count($portefeuille) ?></div>
        <div class="kpi-lbl">Produits Portefeuille</div>
    </div>
    <div class="kpi-card" style="background:linear-gradient(135deg,#10b981,#059669);">
        <div class="kpi-icon">💊</div>
        <div class="kpi-val"><?= count($medicaments) ?></div>
        <div class="kpi-lbl">Produits Vendus</div>
    </div>
    <div class="kpi-card" style="background:linear-gradient(135deg,#06b6d4,#0891b2);">
        <div class="kpi-icon">📦</div>
        <div class="kpi-val"><?= number_format($total_boites) ?></div>
        <div class="kpi-lbl">Total Boîtes</div>
    </div>
    <div class="kpi-card" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
        <div class="kpi-icon">💰</div>
        <div class="kpi-val" style="font-size:1.3rem;"><?= number_format($total_ca/1000000, 2) ?>M</div>
        <div class="kpi-lbl">CA Total (F CFA)</div>
    </div>
</div>

<!-- ── Alerte produits non vendus ── -->
<?php if (count($nonVendus) > 0): ?>
<div class="alert anim anim-delay-2 mb-3" style="background:#fef3c7;border-left:4px solid #f59e0b;border-radius:14px;padding:.9rem 1.2rem;">
    <strong>⚠️ <?= count($nonVendus) ?> produit(s) du portefeuille non encore vendus :</strong>
    <div class="mt-1">
        <?php foreach ($nonVendus as $nv): ?>
            <span class="badge" style="background:#fef9c3;color:#92400e;border:1px solid #fde68a;font-weight:600;margin:2px;font-size:.72rem;">
                <?= htmlspecialchars($nv) ?>
            </span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── Tableau médicaments ── -->
<div class="card anim anim-delay-3">
    <div class="card-header">
        <span><i class="bi bi-box-fill me-2 text-primary"></i>Médicaments vendus</span>
        <input type="text" class="form-control form-control-sm" style="max-width:240px;"
               placeholder="🔍 Rechercher…" oninput="filterTable('tableMeds', this.value)">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 del-table" id="tableMeds">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Médicament (boite)</th>
                        <th class="text-center">📋 Ptf</th>
                        <th class="text-center">🏪 Pharmacies</th>
                        <th class="text-end">📦 Boîtes</th>
                        <th class="text-end">💰 CA (F CFA)</th>
                        <th style="width:120px">Part</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medicaments as $idx => $med):
                        $pct     = $total_boites > 0 ? round(($med['total_boites'] / $total_boites) * 100, 1) : 0;
                        $barPct  = $max_med > 0 ? round(($med['total_boites'] / $max_med) * 100) : 0;
                        $medals  = ['🥇','🥈','🥉'];
                    ?>
                    <tr class="med-row"
                        onclick="window.location='?niveau=pharmacies&delegue_id=<?= $delegue_id ?>&delegue_nom=<?= urlencode($delegue_nom) ?>&medicament=<?= urlencode($med['libelle_article']) ?>&mois=<?= $moisFiltre ?>'">
                        <td class="text-muted small">
                            <?= isset($medals[$idx]) ? "<span class='rank-medal'>{$medals[$idx]}</span>" : ($idx+1) ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($med['libelle_article']) ?></strong>
                        </td>
                        <td class="text-center">
                            <?php if ($med['in_ptf']): ?>
                                <span title="Dans le portefeuille" style="color:#10b981;font-size:1.1rem;">✓</span>
                            <?php else: ?>
                                <span title="Hors portefeuille" style="color:#94a3b8;font-size:.9rem;">○</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="fw-700"><?= (int)$med['nb_pharmacies'] ?></span>
                            <div class="text-muted" style="font-size:.65rem;">pharmacies</div>
                        </td>
                        <td class="text-end">
                            <span class="fw-800 text-primary"><?= number_format($med['total_boites']) ?></span>
                        </td>
                        <td class="text-end ca-val">
                            <span class="fw-600"><?= number_format($med['ca'], 0, ',', ' ') ?></span>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="ptf-bar flex-grow-1">
                                    <div class="ptf-bar-fill" style="width:<?= $barPct ?>%"></div>
                                </div>
                                <small style="min-width:32px;text-align:right;"><?= $pct ?>%</small>
                            </div>
                        </td>
                        <td class="text-center" onclick="event.stopPropagation()">
                            <a href="?niveau=pharmacies&delegue_id=<?= $delegue_id ?>&delegue_nom=<?= urlencode($delegue_nom) ?>&medicament=<?= urlencode($med['libelle_article']) ?>&mois=<?= $moisFiltre ?>"
                               class="btn-detail">
                                <i class="bi bi-shop-window"></i> Pharmacies
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-700">
                    <tr>
                        <td colspan="4" class="text-end small text-muted">TOTAL</td>
                        <td class="text-end text-primary fw-800"><?= number_format($total_boites) ?></td>
                        <td class="text-end ca-val"><?= number_format($total_ca, 0, ',', ' ') ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php
/* ════════════════════════════════════════════════════════
   VUE 3 : PHARMACIES POUR UN MÉDICAMENT
════════════════════════════════════════════════════════ */
elseif ($niveau === 'pharmacies' && $delegue_id && $medicament):
    try {
        $sql = "
            SELECT
                v.designation_client,
                v.province,
                COUNT(DISTINCT DATE_FORMAT(v.mois, '%Y-%m')) AS nb_mois,
                COALESCE(SUM(v.qte_livree), 0) AS total_boites,
                COALESCE(SUM(v.qte_livree * v.prix_cession), 0) AS ca
            FROM ventes_attribuees v
            WHERE v.delegue_id_calcule = :did
              AND v.libelle_article = :med
              $condMois $condGrossiste
            GROUP BY v.designation_client, v.province
            ORDER BY total_boites DESC
        ";
        $params = [':did' => $delegue_id, ':med' => $medicament] + $paramsMois + $paramsGrossiste;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pharmacies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_boites = array_sum(array_column($pharmacies, 'total_boites'));
        $total_ca     = array_sum(array_column($pharmacies, 'ca'));
        $max_ph       = max(array_column($pharmacies, 'total_boites') ?: [1]) ?: 1;

        // Regroupement par province
        $byProvince = [];
        foreach ($pharmacies as $ph) {
            $prov = $ph['province'] ?: 'Inconnue';
            if (!isset($byProvince[$prov])) $byProvince[$prov] = 0;
            $byProvince[$prov] += $ph['total_boites'];
        }
        arsort($byProvince);

    } catch(Exception $e) {
        $pharmacies = []; $total_boites = $total_ca = 0; $max_ph = 1; $byProvince = [];
    }
?>

<!-- ── Hero ── -->
<div class="del-hero anim">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h4 class="mb-1 fw-800">🏪 <?= htmlspecialchars($medicament) ?></h4>
            <div class="small opacity-75 mb-2">Délégué : <?= htmlspecialchars($delegue_nom) ?></div>
            <div class="breadcrumb-del small">
                <a href="?niveau=delegues&mois=<?= $moisFiltre ?>">👥 Délégués</a>
                <span class="sep">›</span>
                <a href="?niveau=medicaments&delegue_id=<?= $delegue_id ?>&delegue_nom=<?= urlencode($delegue_nom) ?>&mois=<?= $moisFiltre ?>">
                    📦 <?= htmlspecialchars($delegue_nom) ?>
                </a>
                <span class="sep">›</span>
                <span class="current">🏪 <?= htmlspecialchars($medicament) ?></span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="?niveau=medicaments&delegue_id=<?= $delegue_id ?>&delegue_nom=<?= urlencode($delegue_nom) ?>&mois=<?= $moisFiltre ?>"
               class="btn btn-sm btn-light fw-600">
                <i class="bi bi-arrow-left me-1"></i>Retour
            </a>
        </div>
    </div>
</div>

<!-- ── KPIs ── -->
<div class="kpi-grid kpi-grid-3 anim anim-delay-1">
    <div class="kpi-card" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
        <div class="kpi-icon">🏪</div>
        <div class="kpi-val"><?= count($pharmacies) ?></div>
        <div class="kpi-lbl">Pharmacies Touchées</div>
    </div>
    <div class="kpi-card" style="background:linear-gradient(135deg,#10b981,#059669);">
        <div class="kpi-icon">📦</div>
        <div class="kpi-val"><?= number_format($total_boites) ?></div>
        <div class="kpi-lbl">Total Boîtes</div>
    </div>
    <div class="kpi-card" style="background:linear-gradient(135deg,#f59e0b,#d97706);">
        <div class="kpi-icon">💰</div>
        <div class="kpi-val" style="font-size:1.3rem;"><?= number_format($total_ca/1000000, 2) ?>M</div>
        <div class="kpi-lbl">CA Total (F CFA)</div>
    </div>
</div>

<div class="row g-3 mb-4 anim anim-delay-2">
    <!-- Répartition par province -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-geo-alt-fill me-2 text-primary"></i>Par Province</div>
            <div class="card-body">
                <?php
                $maxProv = max(array_values($byProvince) ?: [1]) ?: 1;
                foreach ($byProvince as $prov => $pvBoites):
                    $pvPct = round(($pvBoites / max([$total_boites, 1])) * 100, 1);
                    $pvBar = round(($pvBoites / $maxProv) * 100);
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1 small">
                        <strong><?= htmlspecialchars($prov) ?></strong>
                        <span><?= number_format($pvBoites) ?> · <?= $pvPct ?>%</span>
                    </div>
                    <div class="ptf-bar">
                        <div class="ptf-bar-fill" style="width:<?= $pvBar ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tableau pharmacies -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <span><i class="bi bi-shop-window me-2 text-primary"></i>Liste des Pharmacies</span>
                <input type="text" class="form-control form-control-sm" style="max-width:200px;"
                       placeholder="🔍 Rechercher…" oninput="filterTable('tablePharma', this.value)">
            </div>
            <div class="card-body p-0" style="max-height:500px;overflow-y:auto;">
                <table class="table table-hover mb-0 del-table" id="tablePharma">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Pharmacie</th>
                            <th>Province</th>
                            <th class="text-center">Mois</th>
                            <th class="text-end">📦 Boîtes</th>
                            <th class="text-end">💰 CA (F CFA)</th>
                            <th style="width:90px">Part</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pharmacies as $idx => $ph):
                            $pct    = $total_boites > 0 ? round(($ph['total_boites'] / $total_boites) * 100, 1) : 0;
                            $barPct = $max_ph > 0 ? round(($ph['total_boites'] / $max_ph) * 100) : 0;
                            $medals = ['🥇','🥈','🥉'];
                        ?>
                        <tr>
                            <td class="text-muted small">
                                <?= isset($medals[$idx]) ? "<span class='rank-medal'>{$medals[$idx]}</span>" : ($idx+1) ?>
                            </td>
                            <td><strong><?= htmlspecialchars($ph['designation_client']) ?></strong></td>
                            <td><span class="zone-badge"><?= htmlspecialchars($ph['province'] ?: '—') ?></span></td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark"><?= (int)$ph['nb_mois'] ?> mois</span>
                            </td>
                            <td class="text-end fw-800 text-primary"><?= number_format($ph['total_boites']) ?></td>
                            <td class="text-end fw-600 ca-val"><?= number_format($ph['ca'], 0, ',', ' ') ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-1">
                                    <div class="ptf-bar flex-grow-1">
                                        <div class="ptf-bar-fill" style="width:<?= $barPct ?>%"></div>
                                    </div>
                                    <small style="min-width:28px;text-align:right;font-size:.65rem;"><?= $pct ?>%</small>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light fw-700">
                        <tr>
                            <td colspan="4" class="text-end small text-muted">TOTAL</td>
                            <td class="text-end text-primary fw-800"><?= number_format($total_boites) ?></td>
                            <td class="text-end ca-val"><?= number_format($total_ca, 0, ',', ' ') ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

</div><!-- /container-fluid -->

<!-- ── JS global ── -->
<script>
function filterTable(tableId, q) {
    q = q.toLowerCase();
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(function(tr) {
        tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

function applyFiltre(champ, val) {
    const url = new URL(window.location.href);
    url.searchParams.set(champ, val);
    url.searchParams.set('niveau', 'delegues');
    window.location.href = url.toString();
}

function applyFiltreDel(id, nom, champ, val) {
    const url = new URL(window.location.href);
    url.searchParams.set('niveau', 'medicaments');
    url.searchParams.set('delegue_id', id);
    url.searchParams.set('delegue_nom', nom);
    url.searchParams.set(champ, val);
    window.location.href = url.toString();
}
</script>

<?php require_once __DIR__ . '/../footer.php'; ?>