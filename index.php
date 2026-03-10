<?php
define('ROOT', '.');
require_once 'config.php';
$pageTitle = 'Accueil';

// Récupérer sélection du jour (6 items récents)
$items = [];
try {
    $stmt = getPDO()->query("
        SELECT i.*, c.nom AS cat_nom, c.slug AS cat_slug,
               u.pseudo AS vendeur_pseudo,
               (SELECT url FROM item_photos WHERE item_id=i.id ORDER BY ordre LIMIT 1) AS photo
        FROM items i
        JOIN categories c ON c.id = i.categorie_id
        JOIN users u ON u.id = i.vendeur_id
        WHERE i.statut = 'disponible'
        ORDER BY i.created_at DESC
        LIMIT 6
    ");
    $items = $stmt->fetchAll();
} catch (Exception $e) {}

// Best-sellers (items les plus dans les commandes)
$bestSellers = [];
try {
    $stmt = getPDO()->query("
        SELECT i.*, COUNT(ci.id) AS nb_ventes,
               (SELECT url FROM item_photos WHERE item_id=i.id ORDER BY ordre LIMIT 1) AS photo
        FROM commande_items ci
        JOIN items i ON i.id = ci.item_id
        GROUP BY i.id
        ORDER BY nb_ventes DESC
        LIMIT 4
    ");
    $bestSellers = $stmt->fetchAll();
} catch (Exception $e) {}

// Enchères actives
$encheres = [];
try {
    $stmt = getPDO()->query("
        SELECT e.*, i.nom, i.prix,
               (SELECT url FROM item_photos WHERE item_id=i.id ORDER BY ordre LIMIT 1) AS photo,
               (SELECT MAX(montant_actuel) FROM offres_enchere WHERE enchere_id=e.id) AS meilleure
        FROM encheres e
        JOIN items i ON i.id = e.item_id
        WHERE e.statut = 'en_cours' AND e.date_fin > NOW()
        LIMIT 3
    ");
    $encheres = $stmt->fetchAll();
} catch (Exception $e) {}

include 'php/header.php';
?>

<main>

<!-- ── HERO ── -->
<section class="hero">
  <div class="hero-text">
    <h1>Le marché en ligne <em>nouvelle génération</em></h1>
    <p>Achetez, enchérissez et négociez des articles rares, haut de gamme et réguliers. Une expérience d'achat unique, transparente et sécurisée.</p>
    <div class="hero-actions">
      <a href="pages/catalogue.php" class="btn btn-gold btn-lg">Tout Parcourir</a>
      <?php if (!isLoggedIn()): ?>
      <a href="pages/register.php" class="btn btn-secondary btn-lg">Créer un compte</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="hero-cards">
    <div class="hero-card">
      <div class="hero-card-icon">⚡</div>
      <h3>Achat Immédiat</h3>
      <p>Trouvez et achetez en un clic au prix affiché.</p>
    </div>
    <div class="hero-card">
      <div class="hero-card-icon">🤝</div>
      <h3>Négociation</h3>
      <p>Discutez du prix avec le vendeur, jusqu'à 5 tours.</p>
    </div>
    <div class="hero-card">
      <div class="hero-card-icon">🏆</div>
      <h3>Meilleure Offre</h3>
      <p>Enchérissez sur des articles rares et uniques.</p>
    </div>
    <div class="hero-card">
      <div class="hero-card-icon">🔒</div>
      <h3>Paiement Sécurisé</h3>
      <p>Visa, MasterCard, Amex, PayPal & chèques-cadeaux.</p>
    </div>
  </div>
</section>

<!-- ── ENCHÈRES EN COURS ── -->
<?php if (!empty($encheres)): ?>
<section class="section section-alt">
  <div class="section-header">
    <div>
      <div class="flash-badge">🔴 EN DIRECT</div>
      <h2 class="section-title mt-8">Enchères en cours</h2>
      <p class="section-subtitle">Ces articles ferment bientôt — ne manquez pas votre chance</p>
    </div>
    <a href="pages/catalogue.php?type=meilleure_offre" class="btn btn-outline-dark">Voir toutes →</a>
  </div>
  <div class="cards-grid">
    <?php foreach ($encheres as $e): ?>
    <a href="pages/item.php?id=<?= $e['item_id'] ?>" class="item-card" style="text-decoration:none;color:inherit">
      <div class="item-card-img">
        <img src="<?= e($e['photo'] ?? 'https://via.placeholder.com/400x300') ?>" alt="<?= e($e['nom']) ?>">
        <span class="item-card-badge badge-enchere">🏆 Enchère</span>
      </div>
      <div class="item-card-body">
        <h3><?= e($e['nom']) ?></h3>
        <p>Départ : <?= formatPrice((float)$e['prix_depart']) ?></p>
        <div class="item-card-footer">
          <div class="item-price">
            <?= formatPrice((float)($e['meilleure'] ?? $e['prix_depart'])) ?>
            <small>Offre actuelle</small>
          </div>
          <div class="auction-timer" data-end="<?= e($e['date_fin']) ?>">
            <!-- rempli par JS -->
          </div>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ── SÉLECTION DU JOUR ── -->
<section class="section">
  <div class="section-header">
    <div>
      <h2 class="section-title">Sélection du jour</h2>
      <p class="section-subtitle">Les derniers articles mis en vente sur la plateforme</p>
    </div>
    <a href="pages/catalogue.php" class="btn btn-outline-dark">Tout voir →</a>
  </div>

  <div class="filter-bar" id="homeFilter">
    <button class="filter-btn active" data-filter="all">Tous</button>
    <button class="filter-btn" data-filter="articles-rares">Articles rares</button>
    <button class="filter-btn" data-filter="articles-hauts-de-gamme">Hauts de gamme</button>
    <button class="filter-btn" data-filter="articles-reguliers">Articles réguliers</button>
  </div>

  <div class="cards-grid" id="homeGrid">
    <?php foreach ($items as $item): ?>
    <?php
      $badgeClass = match($item['cat_slug']) {
        'articles-rares'          => 'badge-rare',
        'articles-hauts-de-gamme' => 'badge-premium',
        default                   => 'badge-regular',
      };
      $typeLabels = [];
      if (str_contains($item['type_vente'], 'immediat'))        $typeLabels[] = '<span class="tag">⚡ Immédiat</span>';
      if (str_contains($item['type_vente'], 'negotiation'))     $typeLabels[] = '<span class="tag">🤝 Négo</span>';
      if (str_contains($item['type_vente'], 'meilleure_offre')) $typeLabels[] = '<span class="tag">🏆 Enchère</span>';
    ?>
    <a href="pages/item.php?id=<?= $item['id'] ?>"
       class="item-card" data-cat="<?= e($item['cat_slug']) ?>"
       style="text-decoration:none;color:inherit">
      <div class="item-card-img">
        <img src="<?= e($item['photo'] ?? 'https://via.placeholder.com/400x300') ?>"
             alt="<?= e($item['nom']) ?>" loading="lazy">
        <span class="item-card-badge <?= $badgeClass ?>"><?= e($item['cat_nom']) ?></span>
      </div>
      <div class="item-card-body">
        <h3><?= e($item['nom']) ?></h3>
        <p><?= e(mb_substr($item['description'] ?? '', 0, 80)).'...' ?></p>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
          <?= implode('', $typeLabels) ?>
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
    <?php if (empty($items)): ?>
    <p class="text-muted" style="grid-column:1/-1;text-align:center;padding:40px">
      Aucun article disponible pour le moment.
    </p>
    <?php endif; ?>
  </div>
</section>

<!-- ── VENTES FLASH / BEST-SELLERS ── -->
<?php if (!empty($bestSellers)): ?>
<section class="section section-alt">
  <div class="section-header">
    <div>
      <span class="flash-badge">🔥 Ventes Flash</span>
      <h2 class="section-title mt-8">Best-sellers de la semaine</h2>
    </div>
  </div>
  <div class="cards-grid">
    <?php foreach ($bestSellers as $bs): ?>
    <a href="pages/item.php?id=<?= $bs['id'] ?>" class="item-card" style="text-decoration:none;color:inherit">
      <div class="item-card-img">
        <img src="<?= e($bs['photo'] ?? 'https://via.placeholder.com/400x300') ?>" alt="<?= e($bs['nom']) ?>">
        <span class="item-card-badge badge-rare">🔥 <?= $bs['nb_ventes'] ?> ventes</span>
      </div>
      <div class="item-card-body">
        <h3><?= e($bs['nom']) ?></h3>
        <div class="item-card-footer">
          <div class="item-price"><?= formatPrice((float)$bs['prix']) ?></div>
          <span class="btn btn-gold btn-sm">Acheter</span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- ── HOW IT WORKS ── -->
<section class="section">
  <h2 class="section-title text-center">Comment ça marche ?</h2>
  <p class="section-subtitle text-center">Simple, rapide, sécurisé</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:32px;margin-top:16px">
    <?php
    $steps = [
      ['🔍','Trouvez votre article','Parcourez notre catalogue par catégorie ou utilisez la barre de recherche.'],
      ['🎯','Choisissez votre mode','Achat immédiat, négociation ou enchère selon le type de vente.'],
      ['💳','Payez en sécurité','Carte bancaire, PayPal ou chèque-cadeau Omnes MarketPlace.'],
      ['📦','Recevez votre colis','Confirmation par email et suivi de livraison en temps réel.'],
    ];
    foreach ($steps as $i => [$icon, $title, $desc]):
    ?>
    <div style="text-align:center;padding:32px 24px;background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow)">
      <div style="font-size:40px;margin-bottom:16px"><?= $icon ?></div>
      <div style="font-family:var(--font-mono);font-size:11px;color:var(--muted);margin-bottom:8px">ÉTAPE <?= $i+1 ?></div>
      <h3 style="font-size:17px;margin-bottom:8px"><?= $title ?></h3>
      <p style="font-size:14px;color:var(--muted)"><?= $desc ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ── CTA INSCRIPTION ── -->
<?php if (!isLoggedIn()): ?>
<section style="background:linear-gradient(135deg,var(--ink),var(--accent2));color:#fff;padding:80px 60px;text-align:center">
  <h2 style="font-family:var(--font-head);font-size:42px;margin-bottom:16px">Rejoignez Omnes MarketPlace</h2>
  <p style="font-size:18px;opacity:.7;margin-bottom:36px">Créez votre compte gratuitement et commencez à acheter dès aujourd'hui.</p>
  <a href="pages/register.php" class="btn btn-gold btn-lg">Créer mon compte</a>
</section>
<?php endif; ?>

</main>

<?php include 'php/footer.php'; ?>

<script>
// Filtre catégories accueil
document.querySelectorAll('#homeFilter .filter-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#homeFilter .filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const f = btn.dataset.filter;
    document.querySelectorAll('#homeGrid .item-card').forEach(card => {
      card.style.display = (f === 'all' || card.dataset.cat === f) ? '' : 'none';
    });
  });
});

// Timers enchères
document.querySelectorAll('.auction-timer[data-end]').forEach(el => {
  function tick() {
    const diff = new Date(el.dataset.end) - Date.now();
    if (diff <= 0) { el.textContent = 'Terminée'; return; }
    const d = Math.floor(diff / 86400000);
    const h = Math.floor((diff % 86400000) / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    el.innerHTML = `
      <div class="timer-unit"><span class="timer-num" style="font-size:18px;padding:4px 10px;min-width:36px">${String(h).padStart(2,'0')}</span><div class="timer-label">h</div></div>
      <div class="timer-unit"><span class="timer-num" style="font-size:18px;padding:4px 10px;min-width:36px">${String(m).padStart(2,'0')}</span><div class="timer-label">min</div></div>
      <div class="timer-unit"><span class="timer-num" style="font-size:18px;padding:4px 10px;min-width:36px">${String(s).padStart(2,'0')}</span><div class="timer-label">sec</div></div>`;
  }
  tick(); setInterval(tick, 1000);
});
</script>
