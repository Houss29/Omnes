<?php
define('ROOT', '..');
require_once '../config.php';
requireLogin();
$pageTitle = 'Votre Compte';
$userId = currentUserId();
$tab = $_GET['tab'] ?? 'profil';

$user = null;
$commandes = [];
$negoAcheteur = [];
$negoVendeur = [];

try {
    $pdo = getPDO();
    $user = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $user->execute([$userId]);
    $user = $user->fetch();

    $commandes = $pdo->prepare("
        SELECT c.*, COUNT(ci.id) AS nb_items
        FROM commandes c LEFT JOIN commande_items ci ON ci.commande_id=c.id
        WHERE c.acheteur_id=? GROUP BY c.id ORDER BY c.created_at DESC LIMIT 20
    ");
    $commandes->execute([$userId]);
    $commandes = $commandes->fetchAll();

    // Négociations en cours
    $negoAcheteur = $pdo->prepare("
        SELECT n.*, i.nom AS item_nom, i.prix,
               (SELECT url FROM item_photos WHERE item_id=i.id LIMIT 1) AS photo,
               u.pseudo AS vendeur_pseudo,
               (SELECT montant FROM offres_negociation WHERE negociation_id=n.id ORDER BY created_at DESC LIMIT 1) AS derniere_offre
        FROM negociations n JOIN items i ON i.id=n.item_id JOIN users u ON u.id=n.vendeur_id
        WHERE n.acheteur_id=? AND n.statut='en_cours'
    ");
    $negoAcheteur->execute([$userId]);
    $negoAcheteur = $negoAcheteur->fetchAll();
} catch(Exception $e) {}

// Mise à jour profil
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profil') {
    try {
        $pdo = getPDO();
        $pdo->prepare("UPDATE users SET nom=?,prenom=?,adresse1=?,adresse2=?,ville=?,code_postal=?,pays=?,telephone=? WHERE id=?")
            ->execute([$_POST['nom'],$_POST['prenom'],$_POST['adresse1'],$_POST['adresse2']??'',$_POST['ville'],$_POST['code_postal'],$_POST['pays'],$_POST['telephone'],$userId]);
        if ($_POST['password'] ?? '') {
            if (strlen($_POST['password']) < 8) { $msg = '<div class="alert alert-error">Mot de passe trop court.</div>'; }
            else { $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($_POST['password'],PASSWORD_BCRYPT),$userId]); }
        }
        $_SESSION['nom']    = $_POST['nom'];
        $_SESSION['prenom'] = $_POST['prenom'];
        $user['nom']    = $_POST['nom'];
        $user['prenom'] = $_POST['prenom'];
        if (!$msg) $msg = '<div class="alert alert-success">✅ Profil mis à jour !</div>';
    } catch(Exception $e) {
        $msg = '<div class="alert alert-error">Erreur : '.$e->getMessage().'</div>';
    }
}

include '../php/header.php';
?>
<main>
<div class="breadcrumb"><a href="<?= ROOT ?>/index.php">Accueil</a> › <span>Votre Compte</span></div>

<div class="dashboard">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?= strtoupper(substr($user['prenom'] ?? 'U', 0, 1)) ?></div>
      <h3><?= e(($user['prenom']??'').' '.($user['nom']??'')) ?></h3>
      <span><?= match(currentRole()) { 'admin'=>'Administrateur','vendeur'=>'Vendeur','acheteur'=>'Acheteur', default=>'Membre' } ?></span>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section">Mon compte</div>
      <a href="?tab=profil"     class="<?= $tab==='profil'?'active':'' ?>">👤 Mon profil</a>
      <a href="?tab=commandes"  class="<?= $tab==='commandes'?'active':'' ?>">🛍 Mes commandes</a>
      <a href="?tab=negociations" class="<?= $tab==='negociations'?'active':'' ?>">🤝 Négociations</a>
      <a href="notifications.php">🔔 Notifications</a>
      <a href="panier.php">🛒 Panier</a>
      <?php if (isVendeur()): ?>
      <div class="sidebar-section">Vendeur</div>
      <a href="dashboard.php">📊 Tableau de bord</a>
      <a href="dashboard.php?tab=items">📦 Mes articles</a>
      <a href="dashboard.php?tab=negociations">💬 Négociations reçues</a>
      <?php endif; ?>
      <?php if (isAdmin()): ?>
      <div class="sidebar-section">Administration</div>
      <a href="admin.php">⚙️ Panneau admin</a>
      <a href="admin.php?tab=vendeurs">👥 Gérer les vendeurs</a>
      <a href="admin.php?tab=encheres">🏆 Superviser enchères</a>
      <?php endif; ?>
      <div class="sidebar-section">Compte</div>
      <a href="logout.php" style="color:#ff6b6b">🚪 Déconnexion</a>
    </nav>
  </aside>

  <!-- Contenu -->
  <div class="dash-content">
    <?= $msg ?>

    <?php if ($tab === 'profil'): ?>
    <h1 class="dash-title">Mon Profil</h1>
    <p class="dash-subtitle">Gérez vos informations personnelles</p>

    <div style="max-width:640px;background:var(--surface);border-radius:var(--radius);padding:40px;box-shadow:var(--shadow)">
      <form method="POST">
        <input type="hidden" name="action" value="update_profil">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Prénom</label>
            <input type="text" name="prenom" class="form-control" value="<?= e($user['prenom'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nom</label>
            <input type="text" name="nom" class="form-control" value="<?= e($user['nom'] ?? '') ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" disabled style="opacity:.6">
        </div>
        <div class="form-group">
          <label class="form-label">Adresse ligne 1</label>
          <input type="text" name="adresse1" class="form-control" value="<?= e($user['adresse1'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Adresse ligne 2</label>
          <input type="text" name="adresse2" class="form-control" value="<?= e($user['adresse2'] ?? '') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Ville</label>
            <input type="text" name="ville" class="form-control" value="<?= e($user['ville'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Code postal</label>
            <input type="text" name="code_postal" class="form-control" value="<?= e($user['code_postal'] ?? '') ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Pays</label>
            <select name="pays" class="form-control">
              <option <?= ($user['pays']??'')==='France'?'selected':'' ?>>France</option>
              <option <?= ($user['pays']??'')==='Belgique'?'selected':'' ?>>Belgique</option>
              <option <?= ($user['pays']??'')==='Suisse'?'selected':'' ?>>Suisse</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="tel" name="telephone" class="form-control" value="<?= e($user['telephone'] ?? '') ?>">
          </div>
        </div>
        <hr class="divider">
        <h4 style="font-size:15px;font-weight:600;margin-bottom:16px">Changer le mot de passe</h4>
        <div class="form-group">
          <label class="form-label">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
          <input type="password" name="password" class="form-control" minlength="8">
        </div>
        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
      </form>
    </div>

    <?php elseif ($tab === 'commandes'): ?>
    <h1 class="dash-title">Mes Commandes</h1>
    <p class="dash-subtitle"><?= count($commandes) ?> commande<?= count($commandes)>1?'s':'' ?></p>

    <?php if (empty($commandes)): ?>
    <div style="text-align:center;padding:60px;background:var(--surface);border-radius:var(--radius)">
      <div style="font-size:48px;margin-bottom:12px">🛍</div>
      <h3>Aucune commande</h3>
      <p class="text-muted mt-8">Vous n'avez pas encore passé de commande.</p>
      <a href="catalogue.php" class="btn btn-dark mt-24">Commencer à acheter</a>
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Date</th><th>Articles</th><th>Total</th><th>Statut</th><th>Paiement</th></tr>
        </thead>
        <tbody>
          <?php foreach ($commandes as $c): ?>
          <tr>
            <td><strong>#<?= $c['id'] ?></strong></td>
            <td><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
            <td><?= $c['nb_items'] ?> article<?= $c['nb_items']>1?'s':'' ?></td>
            <td><strong><?= formatPrice((float)$c['montant_total']) ?></strong></td>
            <td>
              <span class="tbl-badge <?= match($c['statut']) {
                'payee'=>'green','expediee'=>'blue','livree'=>'green','annulee'=>'red',default=>'orange'
              } ?>"><?= e($c['statut']) ?></span>
            </td>
            <td style="font-size:12px;color:var(--muted)"><?= e($c['num_carte_masked'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php elseif ($tab === 'negociations'): ?>
    <h1 class="dash-title">Mes Négociations</h1>
    <p class="dash-subtitle">Négociations en cours en tant qu'acheteur</p>

    <?php if (empty($negoAcheteur)): ?>
    <div style="text-align:center;padding:60px;background:var(--surface);border-radius:var(--radius)">
      <div style="font-size:48px;margin-bottom:12px">🤝</div>
      <h3>Aucune négociation en cours</h3>
      <a href="catalogue.php?type=negotiation" class="btn btn-dark mt-24">Trouver des articles à négocier</a>
    </div>
    <?php else: ?>
    <?php foreach ($negoAcheteur as $n): ?>
    <div style="background:var(--surface);border-radius:var(--radius);padding:24px;margin-bottom:16px;box-shadow:var(--shadow);display:flex;gap:20px;align-items:center">
      <img src="<?= e($n['photo'] ?? 'https://via.placeholder.com/80') ?>" style="width:80px;height:80px;border-radius:8px;object-fit:cover">
      <div style="flex:1">
        <h3 style="font-size:16px;margin-bottom:4px"><?= e($n['item_nom']) ?></h3>
        <p style="font-size:13px;color:var(--muted)">Vendeur: @<?= e($n['vendeur_pseudo']) ?> · Tour <?= $n['nb_tours'] ?>/5</p>
        <div class="nego-progress" style="margin-top:10px">
          <?php for ($t = 1; $t <= 5; $t++): ?>
          <div class="nego-step <?= $t < $n['nb_tours'] ? 'done' : ($t == $n['nb_tours'] ? 'active' : '') ?>"></div>
          <?php endfor; ?>
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-size:12px;color:var(--muted)">Votre offre</div>
        <div style="font-size:22px;font-weight:700"><?= formatPrice((float)$n['derniere_offre']) ?></div>
        <div style="font-size:12px;color:var(--muted)">Prix initial: <?= formatPrice((float)$n['prix']) ?></div>
        <a href="negociation.php?id=<?= $n['id'] ?>" class="btn btn-dark btn-sm" style="margin-top:8px">Voir →</a>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</main>
<?php include '../php/footer.php'; ?>
