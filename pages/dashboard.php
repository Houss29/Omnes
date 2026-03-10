<?php
define('ROOT', '..');
require_once '../config.php';
requireLogin();
if (!isVendeur()) redirect(ROOT.'/pages/compte.php');
$pageTitle = 'Tableau de bord Vendeur';
$userId = currentUserId();
$tab = $_GET['tab'] ?? 'overview';

$msg = '';

// Ajouter un item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_item') {
    try {
        $pdo = getPDO();
        $pdo->prepare("INSERT INTO items (vendeur_id,categorie_id,nom,description,defaut,prix,type_vente,video_url) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$userId,$_POST['categorie_id'],$_POST['nom'],$_POST['description'],$_POST['defaut']??'',(float)$_POST['prix'],$_POST['type_vente'],$_POST['video_url']??'']);
        $itemId = $pdo->lastInsertId();
        if ($_POST['photo_url'] ?? '') {
            $pdo->prepare("INSERT INTO item_photos (item_id,url) VALUES (?,?)")->execute([$itemId,$_POST['photo_url']]);
        }
        // Si enchère, créer l'enchère
        if (str_contains($_POST['type_vente'], 'meilleure_offre') && ($_POST['date_fin'] ?? '')) {
            $pdo->prepare("INSERT INTO encheres (item_id,date_debut,date_fin,prix_depart) VALUES (?,NOW(),?,?)")
                ->execute([$itemId,$_POST['date_fin'],(float)$_POST['prix']]);
        }
        $msg = '<div class="alert alert-success">✅ Article publié avec succès !</div>';
    } catch(Exception $e) {
        $msg = '<div class="alert alert-error">Erreur : '.$e->getMessage().'</div>';
    }
}

// Supprimer item
if (isset($_GET['del_item'])) {
    try {
        $pdo = getPDO();
        $check = $pdo->prepare("SELECT id FROM items WHERE id=? AND vendeur_id=?");
        $check->execute([(int)$_GET['del_item'],$userId]);
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM items WHERE id=?")->execute([(int)$_GET['del_item']]);
            $msg = '<div class="alert alert-success">Article supprimé.</div>';
        }
    } catch(Exception $e) {}
}

// Stats
$stats = ['items' => 0, 'vendus' => 0, 'revenue' => 0, 'negos' => 0];
$items = [];
$negos = [];
try {
    $pdo = getPDO();
    $stats['items'] = (int)$pdo->prepare("SELECT COUNT(*) FROM items WHERE vendeur_id=?")->execute([$userId]) ? $pdo->prepare("SELECT COUNT(*) FROM items WHERE vendeur_id=?")->execute([$userId]) : 0;
    $st = $pdo->prepare("SELECT COUNT(*) FROM items WHERE vendeur_id=?"); $st->execute([$userId]); $stats['items'] = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM items WHERE vendeur_id=? AND statut='vendu'"); $st->execute([$userId]); $stats['vendus'] = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COALESCE(SUM(ci.prix),0) FROM commande_items ci JOIN items i ON i.id=ci.item_id WHERE i.vendeur_id=?"); $st->execute([$userId]); $stats['revenue'] = (float)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM negociations WHERE vendeur_id=? AND statut='en_cours'"); $st->execute([$userId]); $stats['negos'] = (int)$st->fetchColumn();

    $items = $pdo->prepare("
        SELECT i.*, c.nom AS cat_nom,
               (SELECT url FROM item_photos WHERE item_id=i.id LIMIT 1) AS photo
        FROM items i JOIN categories c ON c.id=i.categorie_id
        WHERE i.vendeur_id=? ORDER BY i.created_at DESC
    ");
    $items->execute([$userId]);
    $items = $items->fetchAll();

    $negos = $pdo->prepare("
        SELECT n.*, i.nom AS item_nom, i.prix,
               u.prenom AS buyer_prenom, u.nom AS buyer_nom,
               (SELECT montant FROM offres_negociation WHERE negociation_id=n.id ORDER BY created_at DESC LIMIT 1) AS derniere_offre,
               (SELECT statut FROM offres_negociation WHERE negociation_id=n.id ORDER BY created_at DESC LIMIT 1) AS offre_statut
        FROM negociations n JOIN items i ON i.id=n.item_id JOIN users u ON u.id=n.acheteur_id
        WHERE n.vendeur_id=? AND n.statut='en_cours' ORDER BY n.created_at DESC
    ");
    $negos->execute([$userId]);
    $negos = $negos->fetchAll();
} catch(Exception $e) {}

$cats = [];
try { $cats = getPDO()->query("SELECT * FROM categories")->fetchAll(); } catch(Exception $e) {}

include '../php/header.php';
?>
<main>
<div class="breadcrumb"><a href="<?= ROOT ?>/index.php">Accueil</a> › <a href="compte.php">Mon Compte</a> › <span>Tableau de bord</span></div>

<div class="dashboard">
  <aside class="sidebar">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?= strtoupper(substr($_SESSION['prenom']??'V',0,1)) ?></div>
      <h3><?= e(($_SESSION['prenom']??'').' '.($_SESSION['nom']??'')) ?></h3>
      <span>Vendeur</span>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section">Vendeur</div>
      <a href="?tab=overview"      class="<?= $tab==='overview'?'active':'' ?>">📊 Vue d'ensemble</a>
      <a href="?tab=items"         class="<?= $tab==='items'?'active':'' ?>">📦 Mes articles</a>
      <a href="?tab=add"           class="<?= $tab==='add'?'active':'' ?>">➕ Publier un article</a>
      <a href="?tab=negociations"  class="<?= $tab==='negociations'?'active':'' ?>">💬 Négociations <span class="nav-badge" style="background:var(--gold);color:var(--ink)"><?= $stats['negos'] ?></span></a>
      <div class="sidebar-section">Compte</div>
      <a href="compte.php">👤 Mon profil</a>
      <a href="logout.php" style="color:#ff6b6b">🚪 Déconnexion</a>
    </nav>
  </aside>

  <div class="dash-content">
    <?= $msg ?>

    <?php if ($tab === 'overview'): ?>
    <h1 class="dash-title">Vue d'ensemble</h1>
    <p class="dash-subtitle">Bienvenue, <?= e($_SESSION['prenom'] ?? '') ?> !</p>

    <div class="stats-grid">
      <div class="stat-card accent"><h3>Articles publiés</h3><p><?= $stats['items'] ?></p></div>
      <div class="stat-card green"><h3>Articles vendus</h3><p><?= $stats['vendus'] ?></p></div>
      <div class="stat-card gold"><h3>Revenus totaux</h3><p><?= formatPrice($stats['revenue']) ?></p></div>
      <div class="stat-card blue"><h3>Négos en cours</h3><p><?= $stats['negos'] ?></p></div>
    </div>

    <div style="background:var(--surface);border-radius:var(--radius);padding:28px;box-shadow:var(--shadow)">
      <h3 style="font-size:18px;font-weight:600;margin-bottom:16px">Actions rapides</h3>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <a href="?tab=add"          class="btn btn-primary">➕ Publier un article</a>
        <a href="?tab=negociations" class="btn btn-dark">💬 Voir les négociations</a>
        <a href="catalogue.php"     class="btn btn-outline-dark">🔍 Voir le catalogue</a>
      </div>
    </div>

    <?php elseif ($tab === 'items'): ?>
    <h1 class="dash-title">Mes Articles</h1>
    <p class="dash-subtitle"><?= count($items) ?> article<?= count($items)>1?'s':'' ?> publiés</p>

    <?php if (empty($items)): ?>
    <div style="text-align:center;padding:60px;background:var(--surface);border-radius:var(--radius)">
      <div style="font-size:48px;margin-bottom:12px">📦</div>
      <h3>Aucun article publié</h3>
      <a href="?tab=add" class="btn btn-primary mt-24">Publier mon premier article</a>
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Photo</th><th>Article</th><th>Catégorie</th><th>Prix</th><th>Type vente</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
        <tr>
          <td><img src="<?= e($it['photo'] ?? 'https://via.placeholder.com/50') ?>" style="width:50px;height:50px;object-fit:cover;border-radius:6px"></td>
          <td><strong><?= e($it['nom']) ?></strong></td>
          <td><?= e($it['cat_nom']) ?></td>
          <td><?= formatPrice((float)$it['prix']) ?></td>
          <td style="font-size:12px"><?= e($it['type_vente']) ?></td>
          <td><span class="tbl-badge <?= $it['statut']==='disponible'?'green':($it['statut']==='vendu'?'blue':'red') ?>"><?= $it['statut'] ?></span></td>
          <td class="tbl-actions">
            <a href="item.php?id=<?= $it['id'] ?>" class="btn btn-sm btn-outline-dark">Voir</a>
            <?php if ($it['statut'] !== 'vendu'): ?>
            <a href="?del_item=<?= $it['id'] ?>&tab=items" class="btn btn-sm" style="background:#fee;color:var(--accent)"
               onclick="return confirm('Supprimer cet article ?')">Supprimer</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php elseif ($tab === 'add'): ?>
    <h1 class="dash-title">Publier un Article</h1>
    <p class="dash-subtitle">Remplissez les informations de votre article</p>

    <div style="max-width:700px;background:var(--surface);border-radius:var(--radius);padding:40px;box-shadow:var(--shadow)">
      <form method="POST">
        <input type="hidden" name="action" value="add_item">
        <div class="form-group">
          <label class="form-label">Nom de l'article *</label>
          <input type="text" name="nom" class="form-control" required placeholder="Ex: Montre Omega Seamaster">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Catégorie *</label>
            <select name="categorie_id" class="form-control" required>
              <?php foreach ($cats as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Prix (€) *</label>
            <input type="number" name="prix" class="form-control" step="0.01" min="0.01" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description *</label>
          <textarea name="description" class="form-control" rows="4" required placeholder="Décrivez l'article, son état, ses caractéristiques…"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Défauts / Remarques</label>
          <textarea name="defaut" class="form-control" rows="2" placeholder="Ex: Légère rayure sur le boîtier"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">URL Photo principale</label>
          <input type="url" name="photo_url" class="form-control" placeholder="https://...">
        </div>
        <div class="form-group">
          <label class="form-label">URL Vidéo (optionnel)</label>
          <input type="url" name="video_url" class="form-control" placeholder="https://...">
        </div>
        <div class="form-group">
          <label class="form-label">Mode(s) de vente * <small style="font-weight:400;text-transform:none">(Enchère + Négociation interdits simultanément)</small></label>
          <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:8px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="type_vente[]" value="immediat" id="cv_imm"> ⚡ Achat Immédiat
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="type_vente[]" value="negotiation" id="cv_neg"> 🤝 Négociation
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
              <input type="checkbox" name="type_vente[]" value="meilleure_offre" id="cv_enc"> 🏆 Meilleure Offre (Enchère)
            </label>
          </div>
          <input type="hidden" name="type_vente" id="typeVenteHidden">
        </div>
        <div id="dateFinGroup" style="display:none">
          <div class="form-group">
            <label class="form-label">Date de fin d'enchère *</label>
            <input type="datetime-local" name="date_fin" class="form-control">
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="margin-top:8px">Publier l'article</button>
      </form>
    </div>

    <?php elseif ($tab === 'negociations'): ?>
    <h1 class="dash-title">Négociations reçues</h1>
    <p class="dash-subtitle"><?= $stats['negos'] ?> négociation<?= $stats['negos']>1?'s':'' ?> en cours</p>

    <?php if (empty($negos)): ?>
    <div style="text-align:center;padding:60px;background:var(--surface);border-radius:var(--radius)">
      <div style="font-size:48px;margin-bottom:12px">💬</div>
      <h3>Aucune négociation en cours</h3>
    </div>
    <?php else: ?>
    <?php foreach ($negos as $n): ?>
    <div style="background:var(--surface);border-radius:var(--radius);padding:24px;margin-bottom:16px;box-shadow:var(--shadow)">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
          <h3 style="font-size:17px;margin-bottom:4px"><?= e($n['item_nom']) ?></h3>
          <p style="font-size:13px;color:var(--muted)">Acheteur : <?= e($n['buyer_prenom'].' '.$n['buyer_nom']) ?> · Tour <?= $n['nb_tours'] ?>/5</p>
        </div>
        <div style="text-align:right">
          <div style="font-size:12px;color:var(--muted)">Offre reçue</div>
          <div style="font-size:24px;font-weight:700;color:var(--accent)"><?= formatPrice((float)$n['derniere_offre']) ?></div>
          <div style="font-size:12px;color:var(--muted)">Prix initial: <?= formatPrice((float)$n['prix']) ?></div>
        </div>
      </div>
      <div class="nego-progress" style="margin:16px 0">
        <?php for ($t=1;$t<=5;$t++): ?>
        <div class="nego-step <?= $t < $n['nb_tours'] ? 'done' : ($t == $n['nb_tours'] ? 'active' : '') ?>"></div>
        <?php endfor; ?>
      </div>
      <a href="negociation.php?id=<?= $n['id'] ?>" class="btn btn-primary btn-sm">💬 Répondre</a>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</main>
<?php include '../php/footer.php'; ?>
<script>
// Gestion type_vente checkboxes
function updateTypeVente() {
  const checks = document.querySelectorAll('input[name="type_vente[]"]:checked');
  const vals = Array.from(checks).map(c => c.value);
  document.getElementById('typeVenteHidden').value = vals.join(',');
  document.getElementById('dateFinGroup').style.display = vals.includes('meilleure_offre') ? '' : 'none';
  // Empêcher enchère + négociation
  const enc = document.getElementById('cv_enc');
  const neg = document.getElementById('cv_neg');
  if (enc.checked) neg.disabled = true;
  else neg.disabled = false;
  if (neg.checked) enc.disabled = true;
  else enc.disabled = false;
}
document.querySelectorAll('input[name="type_vente[]"]').forEach(cb => cb.addEventListener('change', updateTypeVente));
</script>
