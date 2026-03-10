<?php
define('ROOT', '..');
require_once '../config.php';

$id   = (int)($_GET['id'] ?? 0);
$item = null;
$photos = [];
$enchere = null;
$negos = [];

try {
    $pdo = getPDO();
    $item = $pdo->prepare("
        SELECT i.*, c.nom AS cat_nom, c.slug AS cat_slug,
               u.id AS vendeur_id, u.pseudo AS vendeur_pseudo, u.nom AS vendeur_nom,
               u.prenom AS vendeur_prenom, u.photo AS vendeur_photo
        FROM items i
        JOIN categories c ON c.id=i.categorie_id
        JOIN users u ON u.id=i.vendeur_id
        WHERE i.id=? AND i.statut='disponible'
    ");
    $item->execute([$id]);
    $item = $item->fetch();
    if (!$item) { header('Location: catalogue.php'); exit; }

    $photos = $pdo->prepare("SELECT * FROM item_photos WHERE item_id=? ORDER BY ordre");
    $photos->execute([$id]);
    $photos = $photos->fetchAll();

    // Enchère active
    $enchere = $pdo->prepare("SELECT * FROM encheres WHERE item_id=? AND statut='en_cours' AND date_fin>NOW() LIMIT 1");
    $enchere->execute([$id]);
    $enchere = $enchere->fetch();

    if ($enchere) {
        $meilleureOffre = $pdo->prepare("SELECT MAX(montant_actuel) FROM offres_enchere WHERE enchere_id=?");
        $meilleureOffre->execute([$enchere['id']]);
        $enchere['meilleure'] = $meilleureOffre->fetchColumn() ?: $enchere['prix_depart'];
        $enchere['nb_offres'] = (int)$pdo->prepare("SELECT COUNT(*) FROM offres_enchere WHERE enchere_id=?")
            ->execute([$enchere['id']]) ? 0 : 0; // simplified
    }
} catch (Exception $e) { }

if (!$item) { echo 'Article introuvable.'; exit; }

$pageTitle = $item['nom'];

// ── TRAITEMENT DES ACTIONS POST ──
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $action = $_POST['action'] ?? '';
    $pdo = getPDO();

    // Achat immédiat → panier
    if ($action === 'panier' && str_contains($item['type_vente'], 'immediat')) {
        try {
            $check = $pdo->prepare("SELECT id FROM panier WHERE acheteur_id=? AND item_id=?");
            $check->execute([currentUserId(), $id]);
            if (!$check->fetch()) {
                $pdo->prepare("INSERT INTO panier (acheteur_id, item_id, type_achat, prix_final) VALUES (?,?,?,?)")
                    ->execute([currentUserId(), $id, 'immediat', $item['prix']]);
            }
            $msg = '<div class="alert alert-success">✅ Article ajouté au panier !</div>';
        } catch(Exception $e) { $msg = '<div class="alert alert-error">Erreur : '.$e->getMessage().'</div>'; }
    }

    // Démarrer négociation
    if ($action === 'nego_start' && str_contains($item['type_vente'], 'negotiation')) {
        $montant = (float)($_POST['montant'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        if ($montant <= 0) {
            $msg = '<div class="alert alert-error">Veuillez entrer un montant valide.</div>';
        } elseif ($montant >= $item['prix']) {
            $msg = '<div class="alert alert-error">Votre offre doit être inférieure au prix initial.</div>';
        } else {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("INSERT INTO negociations (item_id, acheteur_id, vendeur_id, nb_tours) VALUES (?,?,?,1)")
                    ->execute([$id, currentUserId(), $item['vendeur_id']]);
                $negoId = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO offres_negociation (negociation_id, emetteur_id, montant, message) VALUES (?,?,?,?)")
                    ->execute([$negoId, currentUserId(), $montant, $message]);
                notify($item['vendeur_id'], "Nouvelle offre de négociation sur « {$item['nom']} » : ".formatPrice($montant), 'negociation');
                $pdo->commit();
                $msg = '<div class="alert alert-success">✅ Offre envoyée ! Le vendeur va vous répondre bientôt.</div>';
            } catch(Exception $e) {
                $pdo->rollBack();
                $msg = '<div class="alert alert-error">Erreur : '.$e->getMessage().'</div>';
            }
        }
    }

    // Enchérir
    if ($action === 'encherir' && $enchere) {
        $montantMax = (float)($_POST['montant_max'] ?? 0);
        if ($montantMax <= $enchere['meilleure']) {
            $msg = '<div class="alert alert-error">Votre offre maximum doit dépasser la meilleure offre actuelle ('.formatPrice((float)$enchere['meilleure']).').</div>';
        } else {
            try {
                $pdo->prepare("INSERT INTO offres_enchere (enchere_id, acheteur_id, montant_max, montant_actuel) VALUES (?,?,?,?)")
                    ->execute([$enchere['id'], currentUserId(), $montantMax, $enchere['meilleure'] + 1]);
                // Simplification : on met à jour toutes les offres existantes
                $pdo->prepare("UPDATE offres_enchere SET montant_actuel = montant_actuel + 1 WHERE enchere_id=? AND acheteur_id!=? ORDER BY montant_max DESC LIMIT 1")
                    ->execute([$enchere['id'], currentUserId()]);
                notify($item['vendeur_id'], "Nouvelle enchère sur « {$item['nom']} »", 'enchere');
                $msg = '<div class="alert alert-success">✅ Votre enchère a été enregistrée ! Omnes MarketPlace enchérira automatiquement pour vous.</div>';
            } catch(Exception $e) {
                $msg = '<div class="alert alert-error">Erreur : '.$e->getMessage().'</div>';
            }
        }
    }
}

include '../php/header.php';
?>
<main>
<div class="breadcrumb">
  <a href="<?= ROOT ?>/index.php">Accueil</a> ›
  <a href="catalogue.php">Tout Parcourir</a> ›
  <span><?= e($item['nom']) ?></span>
</div>

<div class="item-detail">

  <!-- Galerie -->
  <div class="item-gallery">
    <?php $mainPhoto = $photos[0]['url'] ?? 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800'; ?>
    <img id="mainPhoto" src="<?= e($mainPhoto) ?>" alt="<?= e($item['nom']) ?>"
         style="width:100%;max-height:520px;object-fit:cover;border-radius:var(--radius)">
    <?php if (count($photos) > 1): ?>
    <div class="thumb-row" style="margin-top:12px">
      <?php foreach ($photos as $i => $ph): ?>
      <img src="<?= e($ph['url']) ?>" alt="" class="thumb <?= $i===0?'active':'' ?>"
           onclick="document.getElementById('mainPhoto').src=this.src;document.querySelectorAll('.thumb').forEach(t=>t.classList.remove('active'));this.classList.add('active')">
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Vidéo -->
    <?php if ($item['video_url']): ?>
    <div style="margin-top:24px">
      <h3 style="font-size:16px;font-weight:600;margin-bottom:12px">📹 Vidéo de présentation</h3>
      <video controls style="width:100%;border-radius:8px">
        <source src="<?= e($item['video_url']) ?>">
      </video>
    </div>
    <?php endif; ?>

    <!-- Description complète -->
    <div style="margin-top:32px;background:var(--surface);border-radius:var(--radius);padding:28px;box-shadow:var(--shadow)">
      <h3 style="font-size:18px;font-weight:600;margin-bottom:16px">Description</h3>
      <p style="color:var(--muted);line-height:1.8"><?= nl2br(e($item['description'] ?? '')) ?></p>
      <?php if ($item['defaut']): ?>
      <div class="alert alert-warn" style="margin-top:16px">
        <span>⚠️</span> <div><strong>Défaut signalé :</strong> <?= e($item['defaut']) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Infos & actions -->
  <div>
    <div class="item-info-box">
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
        <?php
        $bc = match($item['cat_slug']) {
          'articles-rares'          => ['badge-rare','💎'],
          'articles-hauts-de-gamme' => ['badge-premium','⭐'],
          default                   => ['badge-regular','📦'],
        };
        ?>
        <span class="tbl-badge" style="background:var(--accent);color:#fff"><?= $bc[1] ?> <?= e($item['cat_nom']) ?></span>
        <?php if (str_contains($item['type_vente'],'immediat')): ?><span class="tag">⚡ Achat immédiat</span><?php endif; ?>
        <?php if (str_contains($item['type_vente'],'negotiation')): ?><span class="tag">🤝 Négociation</span><?php endif; ?>
        <?php if (str_contains($item['type_vente'],'meilleure_offre')): ?><span class="tag">🏆 Enchère</span><?php endif; ?>
      </div>

      <h1><?= e($item['nom']) ?></h1>
      <div class="item-price-big"><?= formatPrice((float)$item['prix']) ?></div>

      <!-- Vendeur -->
      <div class="seller-mini">
        <div class="user-avatar" style="width:42px;height:42px;font-size:16px">
          <?= strtoupper(substr($item['vendeur_prenom'], 0, 1)) ?>
        </div>
        <div>
          <div style="font-size:14px;font-weight:600"><?= e($item['vendeur_prenom'].' '.$item['vendeur_nom']) ?></div>
          <div style="font-size:12px;color:var(--muted)">@<?= e($item['vendeur_pseudo']) ?> · Vendeur vérifié ✅</div>
        </div>
      </div>

      <?= $msg ?>

      <?php if (!isLoggedIn()): ?>
      <div class="alert alert-info">
        <span>ℹ️</span> <div><a href="login.php" style="color:var(--accent2);font-weight:600">Connectez-vous</a> pour acheter cet article.</div>
      </div>

      <?php elseif (currentUserId() == $item['vendeur_id']): ?>
      <div class="alert alert-warn"><span>ℹ️</span> Ceci est votre propre article.</div>

      <?php else: ?>

        <!-- ── ACHAT IMMÉDIAT ── -->
        <?php if (str_contains($item['type_vente'],'immediat')): ?>
        <div style="border:2px solid var(--line);border-radius:10px;padding:20px;margin-bottom:16px">
          <h3 style="font-size:16px;font-weight:700;margin-bottom:4px">⚡ Achat Immédiat</h3>
          <p style="font-size:13px;color:var(--muted);margin-bottom:16px">Achetez maintenant au prix affiché.</p>
          <form method="POST">
            <input type="hidden" name="action" value="panier">
            <button class="btn btn-gold" style="width:100%">🛒 Ajouter au panier — <?= formatPrice((float)$item['prix']) ?></button>
          </form>
        </div>
        <?php endif; ?>

        <!-- ── ENCHÈRE ── -->
        <?php if ($enchere): ?>
        <div style="border:2px solid var(--accent);border-radius:10px;padding:20px;margin-bottom:16px;background:#fff8f6">
          <h3 style="font-size:16px;font-weight:700;margin-bottom:4px;color:var(--accent)">🏆 Enchère en cours</h3>
          <div style="display:flex;justify-content:space-between;margin:12px 0">
            <div>
              <div style="font-size:12px;color:var(--muted)">Meilleure offre</div>
              <div style="font-size:24px;font-weight:700;color:var(--accent)"><?= formatPrice((float)$enchere['meilleure']) ?></div>
            </div>
            <div>
              <div style="font-size:12px;color:var(--muted)">Se termine dans</div>
              <div class="auction-timer" data-end="<?= e($enchere['date_fin']) ?>" style="font-size:13px;font-weight:700;color:var(--accent)">…</div>
            </div>
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="encherir">
            <div class="form-group" style="margin-bottom:12px">
              <label class="form-label">Mon offre maximum (€)</label>
              <input type="number" name="montant_max" class="form-control"
                     min="<?= $enchere['meilleure'] + 1 ?>" step="1"
                     placeholder="Ex: <?= round($enchere['meilleure'] * 1.1) ?>" required>
              <div class="form-error">Entrez le maximum que vous êtes prêt(e) à payer. Nous encherissons automatiquement pour vous.</div>
            </div>
            <button class="btn btn-primary" style="width:100%">📤 Placer mon enchère</button>
          </form>
        </div>
        <?php endif; ?>

        <!-- ── NÉGOCIATION ── -->
        <?php if (str_contains($item['type_vente'],'negotiation')): ?>
        <div style="border:2px solid var(--line);border-radius:10px;padding:20px;margin-bottom:16px">
          <h3 style="font-size:16px;font-weight:700;margin-bottom:4px">🤝 Négociation</h3>
          <p style="font-size:13px;color:var(--muted);margin-bottom:16px">Proposez un prix inférieur. Jusqu'à 5 tours de négociation.</p>
          <form method="POST">
            <input type="hidden" name="action" value="nego_start">
            <div class="form-group" style="margin-bottom:12px">
              <label class="form-label">Mon offre (€)</label>
              <input type="number" name="montant" class="form-control" step="0.01"
                     max="<?= $item['prix'] - 0.01 ?>" placeholder="Moins de <?= formatPrice((float)$item['prix']) ?>" required>
            </div>
            <div class="form-group" style="margin-bottom:12px">
              <label class="form-label">Message (optionnel)</label>
              <textarea name="message" class="form-control" rows="2" placeholder="Ex: L'article présente un défaut, je propose…"></textarea>
            </div>
            <div class="alert alert-warn" style="margin-bottom:12px;font-size:12px">
              <span>⚖️</span> En soumettant une offre, vous acceptez un contrat légal d'achat si le vendeur accepte.
            </div>
            <button class="btn btn-dark" style="width:100%">💬 Envoyer mon offre</button>
          </form>
        </div>
        <?php endif; ?>

      <?php endif; // loggedIn ?>

      <!-- Infos supplémentaires -->
      <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--line)">
        <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
          <div style="display:flex;gap:8px"><span>🔒</span><span>Paiement 100% sécurisé</span></div>
          <div style="display:flex;gap:8px"><span>📦</span><span>Livraison sous 3–5 jours ouvrés</span></div>
          <div style="display:flex;gap:8px"><span>↩️</span><span>Retour sous 14 jours</span></div>
          <div style="display:flex;gap:8px"><span>✅</span><span>Vendeur vérifié par Omnes MarketPlace</span></div>
        </div>
      </div>

    </div><!-- /item-info-box -->
  </div>
</div>
</main>
<?php include '../php/footer.php'; ?>
<script>
document.querySelectorAll('.auction-timer[data-end]').forEach(el => {
  function tick() {
    const diff = new Date(el.dataset.end) - Date.now();
    if (diff <= 0) { el.textContent = 'Terminée'; return; }
    const d = Math.floor(diff / 86400000);
    const h = Math.floor((diff % 86400000) / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    el.textContent = `${d}j ${String(h).padStart(2,'0')}h ${String(m).padStart(2,'0')}min ${String(s).padStart(2,'0')}s`;
  }
  tick(); setInterval(tick, 1000);
});
</script>
