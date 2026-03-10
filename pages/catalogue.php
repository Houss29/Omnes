<?php
define('ROOT', '..');
require_once '../config.php';
$pageTitle = 'Tout Parcourir';

// Filtres URL
$type    = $_GET['type']    ?? 'all';
$cat     = $_GET['cat']     ?? 'all';
$search  = trim($_GET['q']  ?? '');
$sort    = $_GET['sort']    ?? 'recent';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

// Build query
$where  = ["i.statut = 'disponible'"];
$params = [];

if ($type !== 'all' && in_array($type, ['immediat','negotiation','meilleure_offre'])) {
    $where[]  = "FIND_IN_SET(?, i.type_vente) > 0";
    $params[] = $type;
}
if ($cat !== 'all') {
    $where[]  = "c.slug = ?";
    $params[] = $cat;
}
if ($search !== '') {
    $where[]  = "(i.nom LIKE ? OR i.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSQL = 'WHERE '.implode(' AND ', $where);
$orderSQL = match($sort) {
    'prix_asc'  => 'i.prix ASC',
    'prix_desc' => 'i.prix DESC',
    'nom'       => 'i.nom ASC',
    default     => 'i.created_at DESC',
};

$offset = ($page - 1) * $perPage;
$items  = [];
$total  = 0;

try {
    $pdo = getPDO();
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM items i JOIN categories c ON c.id=i.categorie_id $whereSQL");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT i.*, c.nom AS cat_nom, c.slug AS cat_slug,
               u.pseudo AS vendeur_pseudo,
               (SELECT url FROM item_photos WHERE item_id=i.id ORDER BY ordre LIMIT 1) AS photo
        FROM items i
        JOIN categories c ON c.id = i.categorie_id
        JOIN users u ON u.id = i.vendeur_id
        $whereSQL
        ORDER BY $orderSQL
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $items = $stmt->fetchAll();
} catch (Exception $e) {
    // DB not connected yet — show empty state
}

$totalPages = max(1, ceil($total / $perPage));
include '../php/header.php';
?>

<main>

<!-- Breadcrumb -->
<div class="breadcrumb">
  <a href="<?= ROOT ?>/index.php">Accueil</a> › <span>Tout Parcourir</span>
</div>

<!-- Barre de recherche + filtres -->
<section class="section section-alt" style="padding:40px 60px">
  <h1 class="section-title">Tout Parcourir</h1>
  <p class="section-subtitle">Découvrez tous nos articles en vente</p>

  <form method="GET" action="catalogue.php">
    <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">
      <div class="search-bar" style="flex:1;min-width:260px">
        <input type="text" name="q" placeholder="Rechercher un article…" value="<?= e($search) ?>">
        <button type="submit"><i class="fa fa-search"></i></button>
      </div>
      <select name="sort" class="form-control" style="width:200px" onchange="this.form.submit()">
        <option value="recent"    <?= $sort==='recent'   ?'selected':'' ?>>Plus récents</option>
        <option value="prix_asc"  <?= $sort==='prix_asc' ?'selected':'' ?>>Prix croissant</option>
        <option value="prix_desc" <?= $sort==='prix_desc'?'selected':'' ?>>Prix décroissant</option>
        <option value="nom"       <?= $sort==='nom'      ?'selected':'' ?>>Nom A→Z</option>
      </select>
    </div>

    <!-- Filtres catégories -->
    <div class="filter-bar" style="margin-top:24px;margin-bottom:0">
      <?php
      $cats = [
        'all'                    => '🔍 Tous',
        'articles-rares'         => '💎 Articles rares',
        'articles-hauts-de-gamme'=> '⭐ Hauts de gamme',
        'articles-reguliers'     => '📦 Articles réguliers',
      ];
      foreach ($cats as $slug => $label): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['cat'=>$slug,'page'=>1])) ?>"
         class="filter-btn <?= $cat===$slug?'active':'' ?>">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>
  </form>
</section>

<!-- Onglets type de vente -->
<div style="background:var(--surface);border-bottom:1px solid var(--line);padding:0 60px">
  <div style="display:flex;gap:0">
    <?php
    $types = [
      'all'            => '🛍 Tous les articles',
      'immediat'       => '⚡ Achat Immédiat',
      'negotiation'    => '🤝 Négociation',
      'meilleure_offre'=> '🏆 Meilleure Offre',
    ];
    foreach ($types as $t => $label): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['type'=>$t,'page'=>1])) ?>"
       style="padding:16px 24px;font-size:14px;font-weight:600;border-bottom:3px solid <?= $type===$t?'var(--accent)':'transparent' ?>;color:<?= $type===$t?'var(--accent)':'var(--muted)' ?>;white-space:nowrap">
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Grille items -->
<section class="section">
  <p style="color:var(--muted);margin-bottom:24px;font-size:14px">
    <?= $total ?> article<?= $total>1?'s':'' ?> trouvé<?= $total>1?'s':'' ?>
    <?= $search ? ' pour « '.e($search).' »' : '' ?>
  </p>

  <?php if (empty($items)): ?>
  <div style="text-align:center;padding:80px 20px">
    <div style="font-size:60px;margin-bottom:16px">🔍</div>
    <h3 style="font-size:22px;margin-bottom:8px">Aucun article trouvé</h3>
    <p class="text-muted">Essayez d'élargir vos critères de recherche.</p>
    <a href="catalogue.php" class="btn btn-dark mt-24">Réinitialiser les filtres</a>
  </div>
  <?php else: ?>
  <div class="cards-grid">
    <?php foreach ($items as $item):
      $badgeClass = match($item['cat_slug']) {
        'articles-rares'          => 'badge-rare',
        'articles-hauts-de-gamme' => 'badge-premium',
        default                   => 'badge-regular',
      };
      $types2 = [];
      if (str_contains($item['type_vente'],'immediat'))        $types2[] = '⚡ Immédiat';
      if (str_contains($item['type_vente'],'negotiation'))     $types2[] = '🤝 Négo';
      if (str_contains($item['type_vente'],'meilleure_offre')) $types2[] = '🏆 Enchère';
    ?>
    <a href="item.php?id=<?= $item['id'] ?>" class="item-card" style="text-decoration:none;color:inherit">
      <div class="item-card-img">
        <img src="<?= e($item['photo'] ?? 'https://via.placeholder.com/400x300') ?>"
             alt="<?= e($item['nom']) ?>" loading="lazy">
        <span class="item-card-badge <?= $badgeClass ?>"><?= e($item['cat_nom']) ?></span>
      </div>
      <div class="item-card-body">
        <h3><?= e($item['nom']) ?></h3>
        <p><?= e(mb_substr($item['description'] ?? '', 0, 80)).'...' ?></p>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
          <?php foreach ($types2 as $t): ?>
          <span class="tag"><?= $t ?></span>
          <?php endforeach; ?>
        </div>
        <div class="item-card-footer">
          <div class="item-price">
            <?= formatPrice((float)$item['prix']) ?>
            <small>par <?= e($item['vendeur_pseudo']) ?></small>
          </div>
          <span class="btn btn-dark btn-sm">Voir →</span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div style="display:flex;justify-content:center;gap:8px;margin-top:48px">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$p])) ?>"
       style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:8px;border:2px solid <?= $p===$page?'var(--ink)':'var(--line)' ?>;background:<?= $p===$page?'var(--ink)':'transparent' ?>;color:<?= $p===$page?'#fff':'var(--muted)' ?>;font-weight:600;font-size:14px">
      <?= $p ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</section>

</main>
<?php include '../php/footer.php'; ?>
