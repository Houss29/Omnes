<?php
define('ROOT', '..');
require_once '../config.php';
requireLogin();
$pageTitle = 'Mon Panier';
$userId = currentUserId();

$msg = '';

// Supprimer un item du panier
if (isset($_GET['supprimer'])) {
    try {
        getPDO()->prepare("DELETE FROM panier WHERE id=? AND acheteur_id=?")
            ->execute([(int)$_GET['supprimer'], $userId]);
        $msg = '<div class="alert alert-success">Article retiré du panier.</div>';
    } catch(Exception $e) {}
}

// Valider commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'commander') {
    $nom        = trim($_POST['nom_livraison'] ?? '');
    $adresse    = trim($_POST['adresse'] ?? '');
    $ville      = trim($_POST['ville'] ?? '');
    $cp         = trim($_POST['code_postal'] ?? '');
    $pays       = trim($_POST['pays'] ?? '');
    $tel        = trim($_POST['telephone'] ?? '');
    $typeCard   = $_POST['type_carte'] ?? '';
    $numCard    = preg_replace('/\s+/', '', $_POST['num_carte'] ?? '');
    $nomCard    = trim($_POST['nom_carte'] ?? '');
    $expCard    = trim($_POST['exp_carte'] ?? '');
    $cvvCard    = trim($_POST['cvv'] ?? '');
    $carteCode  = trim($_POST['carte_cadeau'] ?? '');

    // Vérifier carte cadeau
    $reduction = 0;
    if ($carteCode) {
        try {
            $cc = getPDO()->prepare("SELECT * FROM cartes_cadeaux WHERE code=? AND utilise=0 LIMIT 1");
            $cc->execute([$carteCode]);
            $cc = $cc->fetch();
            if ($cc) $reduction = (float)$cc['montant'];
            else $msg .= '<div class="alert alert-warn">Code de carte cadeau invalide ou déjà utilisé.</div>';
        } catch(Exception $e) {}
    }

    // Vérification simple carte
    if (strlen($numCard) < 13) {
        $msg .= '<div class="alert alert-error">Numéro de carte invalide.</div>';
    } else {
        try {
            $pdo = getPDO();
            // Récupérer items panier
            $panierItems = $pdo->prepare("
                SELECT p.*, i.nom, i.prix, p.prix_final,
                       (SELECT url FROM item_photos WHERE item_id=i.id LIMIT 1) AS photo
                FROM panier p JOIN items i ON i.id=p.item_id
                WHERE p.acheteur_id=?
            ");
            $panierItems->execute([$userId]);
            $panierItems = $panierItems->fetchAll();

            $total = 0;
            foreach ($panierItems as $pi) $total += $pi['prix_final'] ?? $pi['prix'];
            $total = max(0, $total - $reduction);

            $adresseLivraison = "$nom, $adresse, $ville $cp, $pays — Tél: $tel";
            $masked = '**** **** **** '.substr($numCard, -4);

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO commandes (acheteur_id, montant_total, statut, adresse_livraison, type_carte, num_carte_masked) VALUES (?,?,?,?,?,?)")
                ->execute([$userId, $total, 'payee', $adresseLivraison, $typeCard, $masked]);
            $commandeId = $pdo->lastInsertId();

            foreach ($panierItems as $pi) {
                $prix = $pi['prix_final'] ?? $pi['prix'];
                $pdo->prepare("INSERT INTO commande_items (commande_id, item_id, prix) VALUES (?,?,?)")
                    ->execute([$commandeId, $pi['item_id'], $prix]);
                $pdo->prepare("UPDATE items SET statut='vendu' WHERE id=?")->execute([$pi['item_id']]);
            }
            $pdo->prepare("DELETE FROM panier WHERE acheteur_id=?")->execute([$userId]);
            if ($cc ?? null) $pdo->prepare("UPDATE cartes_cadeaux SET utilise=1, user_id=? WHERE id=".$cc['id'])->execute([$userId]);
            notify($userId, "Votre commande #$commandeId a bien été enregistrée. Total : ".formatPrice($total), 'commande');
            $pdo->commit();
            $msg = '<div class="alert alert-success">🎉 Commande confirmée ! Vous recevrez un email de confirmation. <a href="compte.php?tab=commandes">Voir mes commandes →</a></div>';
        } catch(Exception $e) {
            if (isset($pdo)) try { $pdo->rollBack(); } catch(Exception $e2) {}
            $msg = '<div class="alert alert-error">Erreur : '.$e->getMessage().'</div>';
        }
    }
}

// Récupérer panier
$panierItems = [];
$total = 0;
try {
    $pdo = getPDO();
    $stmt = $pdo->prepare("
        SELECT p.id AS panier_id, p.type_achat, p.prix_final,
               i.id AS item_id, i.nom, i.prix, i.description,
               (SELECT url FROM item_photos WHERE item_id=i.id ORDER BY ordre LIMIT 1) AS photo
        FROM panier p JOIN items i ON i.id=p.item_id
        WHERE p.acheteur_id=?
    ");
    $stmt->execute([$userId]);
    $panierItems = $stmt->fetchAll();
    foreach ($panierItems as $pi) $total += $pi['prix_final'] ?? $pi['prix'];
} catch(Exception $e) {}

$tva = $total * 0.20;
$subtotal = $total - $tva;

include '../php/header.php';
?>
<main>
<div class="breadcrumb"><a href="<?= ROOT ?>/index.php">Accueil</a> › <span>Mon Panier</span></div>

<section class="section">
  <h1 class="section-title">Mon Panier</h1>
  <p class="section-subtitle"><?= count($panierItems) ?> article<?= count($panierItems)>1?'s':'' ?></p>
  <?= $msg ?>

  <?php if (empty($panierItems)): ?>
  <div style="text-align:center;padding:80px 20px">
    <div style="font-size:60px;margin-bottom:16px">🛒</div>
    <h3 style="font-size:22px;margin-bottom:8px">Votre panier est vide</h3>
    <p class="text-muted">Commencez par explorer notre catalogue.</p>
    <a href="catalogue.php" class="btn btn-dark mt-24">Parcourir les articles</a>
  </div>
  <?php else: ?>
  <div class="cart-layout">
    <!-- Items -->
    <div>
      <?php foreach ($panierItems as $pi): ?>
      <div class="cart-item">
        <img src="<?= e($pi['photo'] ?? 'https://via.placeholder.com/90') ?>" alt="">
        <div class="cart-item-info">
          <h4><a href="item.php?id=<?= $pi['item_id'] ?>"><?= e($pi['nom']) ?></a></h4>
          <p><?= e(mb_substr($pi['description'] ?? '', 0, 80)) ?>…</p>
          <span class="tag"><?= match($pi['type_achat']) {
            'immediat'   => '⚡ Achat immédiat',
            'negociation'=> '🤝 Négociation',
            'enchere'    => '🏆 Enchère',
            default      => $pi['type_achat']
          } ?></span>
        </div>
        <div style="text-align:right;flex-shrink:0">
          <div class="cart-item-price"><?= formatPrice((float)($pi['prix_final'] ?? $pi['prix'])) ?></div>
          <?php if ($pi['type_achat'] === 'immediat'): ?>
          <a href="?supprimer=<?= $pi['panier_id'] ?>" class="btn btn-sm"
             style="background:#fee;color:var(--accent);margin-top:12px"
             onclick="return confirm('Retirer cet article ?')">Supprimer</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Résumé & Paiement -->
    <div>
      <div class="order-summary">
        <h3>Récapitulatif</h3>
        <div class="summary-row"><span>Sous-total HT</span><span><?= formatPrice($subtotal) ?></span></div>
        <div class="summary-row"><span>TVA (20%)</span><span><?= formatPrice($tva) ?></span></div>
        <div class="summary-row"><span>Livraison</span><span style="color:var(--success)">Gratuite</span></div>
        <div class="summary-row total"><span>Total TTC</span><span style="color:var(--accent)"><?= formatPrice($total) ?></span></div>

        <form method="POST" style="margin-top:24px" id="checkoutForm">
          <input type="hidden" name="action" value="commander">
          <h4 style="font-size:15px;font-weight:600;margin-bottom:16px">📦 Livraison</h4>
          <div class="form-group">
            <label class="form-label">Nom complet</label>
            <input type="text" name="nom_livraison" class="form-control" required
                   value="<?= e(($_SESSION['prenom']??'').' '.($_SESSION['nom']??'')) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Adresse</label>
            <input type="text" name="adresse" class="form-control" required>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Ville</label>
              <input type="text" name="ville" class="form-control" required>
            </div>
            <div class="form-group">
              <label class="form-label">Code postal</label>
              <input type="text" name="code_postal" class="form-control" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Pays</label>
              <select name="pays" class="form-control">
                <option>France</option><option>Belgique</option><option>Suisse</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Téléphone</label>
              <input type="tel" name="telephone" class="form-control">
            </div>
          </div>

          <h4 style="font-size:15px;font-weight:600;margin:20px 0 16px">💳 Paiement</h4>
          <div class="form-group">
            <label class="form-label">Type de carte</label>
            <select name="type_carte" class="form-control">
              <option value="visa">💳 Visa</option>
              <option value="mastercard">💳 MasterCard</option>
              <option value="amex">💳 American Express</option>
              <option value="paypal">🔵 PayPal</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Numéro de carte</label>
            <input type="text" name="num_carte" class="form-control" placeholder="1234 5678 9012 3456"
                   maxlength="19" id="cardNum" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom sur la carte</label>
            <input type="text" name="nom_carte" class="form-control" required>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Expiration</label>
              <input type="text" name="exp_carte" class="form-control" placeholder="MM/AA" maxlength="5" required>
            </div>
            <div class="form-group">
              <label class="form-label">CVV</label>
              <input type="text" name="cvv" class="form-control" placeholder="123" maxlength="4" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">🎁 Code carte cadeau (optionnel)</label>
            <input type="text" name="carte_cadeau" class="form-control" placeholder="OMNES2026-XXXX">
          </div>

          <button type="submit" class="btn btn-gold" style="width:100%;margin-top:8px">
            ✅ Passer la commande — <?= formatPrice($total) ?>
          </button>
          <p style="font-size:11px;color:var(--muted);margin-top:10px;text-align:center">
            🔒 Paiement sécurisé · Vos données sont protégées
          </p>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
</section>
</main>
<?php include '../php/footer.php'; ?>
<script>
// Format numéro carte
document.getElementById('cardNum')?.addEventListener('input', function() {
  let v = this.value.replace(/\D/g,'').substring(0,16);
  this.value = v.replace(/(.{4})/g,'$1 ').trim();
});
</script>
