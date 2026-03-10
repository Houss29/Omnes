<?php
define('ROOT', '..');
require_once '../config.php';
requireLogin();
$pageTitle = 'Notifications';
$userId = currentUserId();

// Marquer tout comme lu
if (isset($_GET['marquer_lu'])) {
    try { getPDO()->prepare("UPDATE notifications SET lu=1 WHERE user_id=?")->execute([$userId]); }
    catch(Exception $e) {}
}

// Créer une alerte
$msgAlerte = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'creer_alerte') {
    try {
        getPDO()->prepare("INSERT INTO alertes (user_id, mot_cle, categorie_id, prix_max) VALUES (?,?,?,?)")
            ->execute([$userId, $_POST['mot_cle']??null, $_POST['categorie_id']??null, $_POST['prix_max']??null]);
        $msgAlerte = '<div class="alert alert-success">✅ Alerte créée !</div>';
    } catch(Exception $e) {
        $msgAlerte = '<div class="alert alert-error">Erreur : '.$e->getMessage().'</div>';
    }
}

// Supprimer alerte
if (isset($_GET['del_alerte'])) {
    try { getPDO()->prepare("DELETE FROM alertes WHERE id=? AND user_id=?")->execute([(int)$_GET['del_alerte'],$userId]); }
    catch(Exception $e) {}
}

$notifs = [];
$alertes = [];
try {
    $pdo = getPDO();
    $notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
    $notifs->execute([$userId]);
    $notifs = $notifs->fetchAll();
    // Marquer comme lus après récupération
    $pdo->prepare("UPDATE notifications SET lu=1 WHERE user_id=?")->execute([$userId]);

    $alertes = $pdo->prepare("SELECT a.*, c.nom AS cat_nom FROM alertes a LEFT JOIN categories c ON c.id=a.categorie_id WHERE a.user_id=? ORDER BY a.created_at DESC");
    $alertes->execute([$userId]);
    $alertes = $alertes->fetchAll();
} catch(Exception $e) {}

$cats = [];
try {
    $cats = getPDO()->query("SELECT * FROM categories")->fetchAll();
} catch(Exception $e) {}

include '../php/header.php';
?>
<main>
<div class="breadcrumb"><a href="<?= ROOT ?>/index.php">Accueil</a> › <span>Notifications</span></div>

<section class="section">
  <div style="display:grid;grid-template-columns:1fr 360px;gap:40px;align-items:start">
    <!-- Notifications -->
    <div>
      <div class="section-header">
        <div>
          <h1 class="section-title">Notifications</h1>
          <p class="section-subtitle"><?= count($notifs) ?> notification<?= count($notifs)>1?'s':'' ?></p>
        </div>
        <?php if (!empty($notifs)): ?>
        <a href="?marquer_lu=1" class="btn btn-sm btn-outline-dark">Tout marquer lu</a>
        <?php endif; ?>
      </div>

      <div class="notif-list">
        <?php if (empty($notifs)): ?>
        <div style="text-align:center;padding:60px;background:var(--surface);border-radius:var(--radius)">
          <div style="font-size:48px;margin-bottom:12px">🔔</div>
          <h3>Aucune notification</h3>
          <p class="text-muted mt-8">Vous n'avez pas encore de notifications.</p>
        </div>
        <?php else: ?>
        <?php foreach ($notifs as $n):
          $icon = match($n['type']) {
            'commande'   => '🛍',
            'negociation'=> '🤝',
            'enchere'    => '🏆',
            'alerte'     => '🔔',
            default      => 'ℹ️',
          };
        ?>
        <div class="notif-item <?= $n['lu'] ? '' : 'unread' ?>">
          <div class="notif-icon"><?= $icon ?></div>
          <div class="notif-text">
            <p><?= e($n['message']) ?></p>
            <small><?= date('d/m/Y à H:i', strtotime($n['created_at'])) ?></small>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Alertes de recherche -->
    <div>
      <div style="background:var(--surface);border-radius:var(--radius);padding:28px;box-shadow:var(--shadow)">
        <h2 style="font-family:var(--font-head);font-size:24px;margin-bottom:8px">🔍 Mes Alertes</h2>
        <p style="font-size:14px;color:var(--muted);margin-bottom:24px">Soyez averti(e) dès qu'un article correspondant à vos critères est mis en vente.</p>

        <?= $msgAlerte ?>

        <form method="POST">
          <input type="hidden" name="action" value="creer_alerte">
          <div class="form-group">
            <label class="form-label">Mot-clé</label>
            <input type="text" name="mot_cle" class="form-control" placeholder="Ex: montre, vélo, bague…">
          </div>
          <div class="form-group">
            <label class="form-label">Catégorie</label>
            <select name="categorie_id" class="form-control">
              <option value="">Toutes catégories</option>
              <?php foreach ($cats as $c): ?>
              <option value="<?= $c['id'] ?>"><?= e($c['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Prix maximum (€)</label>
            <input type="number" name="prix_max" class="form-control" step="0.01" placeholder="Ex: 500">
          </div>
          <button type="submit" class="btn btn-dark" style="width:100%">+ Créer l'alerte</button>
        </form>

        <?php if (!empty($alertes)): ?>
        <hr class="divider">
        <h3 style="font-size:15px;font-weight:600;margin-bottom:16px">Mes alertes actives</h3>
        <?php foreach ($alertes as $al): ?>
        <div style="background:var(--paper);border-radius:8px;padding:12px 16px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:flex-start">
          <div>
            <?php if ($al['mot_cle']): ?><div style="font-size:14px;font-weight:600">🔍 <?= e($al['mot_cle']) ?></div><?php endif; ?>
            <?php if ($al['cat_nom']): ?><div style="font-size:12px;color:var(--muted)">📂 <?= e($al['cat_nom']) ?></div><?php endif; ?>
            <?php if ($al['prix_max']): ?><div style="font-size:12px;color:var(--muted)">💰 Max <?= formatPrice((float)$al['prix_max']) ?></div><?php endif; ?>
          </div>
          <a href="?del_alerte=<?= $al['id'] ?>" style="color:var(--accent);font-size:18px" title="Supprimer"
             onclick="return confirm('Supprimer cette alerte ?')">×</a>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
</main>
<?php include '../php/footer.php'; ?>
