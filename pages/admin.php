<?php
define('ROOT', '..');
require_once '../config.php';
requireAdmin();
$pageTitle = 'Panneau d\'administration';
$tab = $_GET['tab'] ?? 'overview';
$msg = '';

// Ajouter un vendeur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_vendeur') {
    $nom    = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $pseudo = trim($_POST['pseudo'] ?? '');
    $pass   = $_POST['password'] ?? 'Vendeur123!';
    if (!$nom || !$email) {
        $msg = '<div class="alert alert-error">Nom et email requis.</div>';
    } else {
        try {
            $pdo = getPDO();
            $check = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $msg = '<div class="alert alert-error">Email déjà utilisé.</div>';
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $pdo->prepare("INSERT INTO users (nom,prenom,email,pseudo,password,role) VALUES (?,?,?,?,?,'vendeur')")
                    ->execute([$nom,$prenom,$email,$pseudo,$hash]);
                $msg = '<div class="alert alert-success">✅ Vendeur ajouté ! Mot de passe temporaire : <strong>'.e($pass).'</strong></div>';
            }
        } catch(Exception $e) { $msg = '<div class="alert alert-error">'.$e->getMessage().'</div>'; }
    }
}

// Supprimer un vendeur
if (isset($_GET['del_vendeur'])) {
    $vId = (int)$_GET['del_vendeur'];
    if ($vId !== currentUserId()) {
        try {
            getPDO()->prepare("DELETE FROM users WHERE id=? AND role='vendeur'")->execute([$vId]);
            $msg = '<div class="alert alert-success">Vendeur supprimé.</div>';
        } catch(Exception $e) {}
    }
}

// Clôturer une enchère et désigner le gagnant
if (isset($_GET['cloture_enchere'])) {
    $eId = (int)$_GET['cloture_enchere'];
    try {
        $pdo = getPDO();
        $best = $pdo->prepare("SELECT * FROM offres_enchere WHERE enchere_id=? ORDER BY montant_max DESC LIMIT 1");
        $best->execute([$eId]);
        $best = $best->fetch();
        if ($best) {
            $pdo->prepare("UPDATE encheres SET statut='terminee' WHERE id=?")->execute([$eId]);
            $pdo->prepare("UPDATE items SET statut='vendu' WHERE id=(SELECT item_id FROM encheres WHERE id=?)")->execute([$eId]);
            // Ajouter au panier du gagnant
            $enchere = $pdo->prepare("SELECT * FROM encheres WHERE id=?")->execute([$eId]) ? null : null;
            $enchereRow = $pdo->prepare("SELECT * FROM encheres WHERE id=?"); $enchereRow->execute([$eId]); $enchereRow = $enchereRow->fetch();
            if ($enchereRow) {
                $pdo->prepare("INSERT INTO panier (acheteur_id,item_id,type_achat,prix_final) VALUES (?,?,?,?)")
                    ->execute([$best['acheteur_id'],$enchereRow['item_id'],'enchere',$best['montant_actuel']]);
                notify($best['acheteur_id'], "🏆 Félicitations ! Vous avez remporté l'enchère #$eId pour ".formatPrice((float)$best['montant_actuel'])." !", 'enchere');
            }
            $msg = '<div class="alert alert-success">✅ Enchère clôturée. Gagnant notifié.</div>';
        } else {
            $pdo->prepare("UPDATE encheres SET statut='annulee' WHERE id=?")->execute([$eId]);
            $msg = '<div class="alert alert-warn">Enchère annulée : aucune offre reçue.</div>';
        }
    } catch(Exception $e) { $msg = '<div class="alert alert-error">'.$e->getMessage().'</div>'; }
}

// Stats globales
$stats = [];
$vendeurs = [];
$encheres = [];
$allUsers = [];
try {
    $pdo = getPDO();
    $st = $pdo->query("SELECT COUNT(*) FROM users WHERE role='acheteur'"); $stats['acheteurs'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(*) FROM users WHERE role='vendeur'"); $stats['vendeurs'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COUNT(*) FROM items WHERE statut='disponible'"); $stats['items'] = (int)$st->fetchColumn();
    $st = $pdo->query("SELECT COALESCE(SUM(montant_total),0) FROM commandes WHERE statut='payee'"); $stats['revenue'] = (float)$st->fetchColumn();

    $vendeurs = $pdo->query("SELECT * FROM users WHERE role='vendeur' ORDER BY created_at DESC")->fetchAll();
    $encheres = $pdo->query("
        SELECT e.*, i.nom AS item_nom, u.pseudo AS vendeur,
               (SELECT MAX(montant_actuel) FROM offres_enchere WHERE enchere_id=e.id) AS meilleure,
               (SELECT COUNT(*) FROM offres_enchere WHERE enchere_id=e.id) AS nb_offres
        FROM encheres e JOIN items i ON i.id=e.item_id JOIN users u ON u.id=i.vendeur_id
        WHERE e.statut='en_cours' ORDER BY e.date_fin ASC
    ")->fetchAll();
    $allUsers = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 50")->fetchAll();
} catch(Exception $e) {}

include '../php/header.php';
?>
<main>
<div class="breadcrumb"><a href="<?= ROOT ?>/index.php">Accueil</a> › <span>Administration</span></div>

<div class="dashboard">
  <aside class="sidebar">
    <div class="sidebar-user">
      <div class="sidebar-avatar">👑</div>
      <h3><?= e(($_SESSION['prenom']??'').' '.($_SESSION['nom']??'')) ?></h3>
      <span>Administrateur</span>
    </div>
    <nav class="sidebar-nav">
      <div class="sidebar-section">Administration</div>
      <a href="?tab=overview"  class="<?= $tab==='overview'?'active':'' ?>">📊 Vue d'ensemble</a>
      <a href="?tab=vendeurs"  class="<?= $tab==='vendeurs'?'active':'' ?>">👥 Vendeurs</a>
      <a href="?tab=encheres"  class="<?= $tab==='encheres'?'active':'' ?>">🏆 Enchères</a>
      <a href="?tab=users"     class="<?= $tab==='users'?'active':'' ?>">👤 Utilisateurs</a>
      <div class="sidebar-section">Vendeur</div>
      <a href="dashboard.php">📦 Mon espace vendeur</a>
      <div class="sidebar-section">Compte</div>
      <a href="compte.php">👤 Mon profil</a>
      <a href="logout.php" style="color:#ff6b6b">🚪 Déconnexion</a>
    </nav>
  </aside>

  <div class="dash-content">
    <?= $msg ?>

    <?php if ($tab === 'overview'): ?>
    <h1 class="dash-title">Panneau d'Administration</h1>
    <p class="dash-subtitle">Vue globale de la plateforme</p>
    <div class="stats-grid">
      <div class="stat-card blue"><h3>Acheteurs</h3><p><?= $stats['acheteurs'] ?></p></div>
      <div class="stat-card accent"><h3>Vendeurs</h3><p><?= $stats['vendeurs'] ?></p></div>
      <div class="stat-card green"><h3>Articles actifs</h3><p><?= $stats['items'] ?></p></div>
      <div class="stat-card gold"><h3>Revenus totaux</h3><p><?= formatPrice($stats['revenue']) ?></p></div>
    </div>
    <div style="background:var(--surface);border-radius:var(--radius);padding:28px;box-shadow:var(--shadow)">
      <h3 style="font-size:18px;font-weight:600;margin-bottom:16px">Actions rapides</h3>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <a href="?tab=vendeurs" class="btn btn-primary">👥 Gérer les vendeurs</a>
        <a href="?tab=encheres" class="btn btn-gold">🏆 Superviser les enchères</a>
        <a href="?tab=users"    class="btn btn-dark">👤 Tous les utilisateurs</a>
      </div>
    </div>

    <?php elseif ($tab === 'vendeurs'): ?>
    <h1 class="dash-title">Gestion des Vendeurs</h1>
    <p class="dash-subtitle">Ajouter ou supprimer des comptes vendeurs</p>

    <!-- Formulaire ajout vendeur -->
    <div style="background:var(--surface);border-radius:var(--radius);padding:32px;box-shadow:var(--shadow);max-width:600px;margin-bottom:40px">
      <h2 style="font-size:20px;font-weight:600;margin-bottom:20px">➕ Ajouter un vendeur</h2>
      <form method="POST">
        <input type="hidden" name="action" value="add_vendeur">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Prénom</label><input type="text" name="prenom" class="form-control"></div>
          <div class="form-group"><label class="form-label">Nom *</label><input type="text" name="nom" class="form-control" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Pseudo</label><input type="text" name="pseudo" class="form-control"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Mot de passe temporaire</label>
          <input type="text" name="password" class="form-control" value="Vendeur123!" required>
        </div>
        <button type="submit" class="btn btn-primary">Créer le compte vendeur</button>
      </form>
    </div>

    <!-- Liste vendeurs -->
    <h2 style="font-size:20px;font-weight:600;margin-bottom:16px">Liste des vendeurs (<?= count($vendeurs) ?>)</h2>
    <?php if (empty($vendeurs)): ?>
    <p class="text-muted">Aucun vendeur enregistré.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Nom</th><th>Email</th><th>Pseudo</th><th>Inscrit le</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($vendeurs as $v): ?>
        <tr>
          <td><?= e($v['prenom'].' '.$v['nom']) ?></td>
          <td><?= e($v['email']) ?></td>
          <td><?= e($v['pseudo'] ?? '—') ?></td>
          <td><?= date('d/m/Y', strtotime($v['created_at'])) ?></td>
          <td>
            <a href="?del_vendeur=<?= $v['id'] ?>&tab=vendeurs" class="btn btn-sm" style="background:#fee;color:var(--accent)"
               onclick="return confirm('Supprimer ce vendeur et tous ses articles ?')">Supprimer</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php elseif ($tab === 'encheres'): ?>
    <h1 class="dash-title">Supervision des Enchères</h1>
    <p class="dash-subtitle"><?= count($encheres) ?> enchère<?= count($encheres)>1?'s':'' ?> en cours</p>

    <?php if (empty($encheres)): ?>
    <div style="text-align:center;padding:60px;background:var(--surface);border-radius:var(--radius)">
      <div style="font-size:48px;margin-bottom:12px">🏆</div>
      <h3>Aucune enchère active</h3>
    </div>
    <?php else: ?>
    <?php foreach ($encheres as $e): ?>
    <div style="background:var(--surface);border-radius:var(--radius);padding:24px;margin-bottom:16px;box-shadow:var(--shadow)">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px">
        <div>
          <h3 style="font-size:17px;margin-bottom:6px"><?= e($e['item_nom']) ?></h3>
          <p style="font-size:13px;color:var(--muted)">
            Vendeur: @<?= e($e['vendeur']) ?> ·
            <?= $e['nb_offres'] ?> offre<?= $e['nb_offres']>1?'s':'' ?> ·
            Fin: <?= date('d/m/Y H:i', strtotime($e['date_fin'])) ?>
          </p>
        </div>
        <div style="text-align:right">
          <div style="font-size:12px;color:var(--muted)">Meilleure offre</div>
          <div style="font-size:24px;font-weight:700;color:var(--accent)"><?= formatPrice((float)($e['meilleure'] ?? $e['prix_depart'])) ?></div>
          <div class="auction-timer" data-end="<?= e($e['date_fin']) ?>" style="font-size:13px;color:var(--muted)"></div>
        </div>
      </div>
      <div style="margin-top:16px;display:flex;gap:10px">
        <a href="item.php?id=<?= $e['item_id'] ?>" class="btn btn-sm btn-outline-dark">Voir l'article</a>
        <a href="?cloture_enchere=<?= $e['id'] ?>&tab=encheres" class="btn btn-sm btn-primary"
           onclick="return confirm('Clôturer cette enchère et désigner le gagnant ?')">🏆 Clôturer et désigner gagnant</a>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php elseif ($tab === 'users'): ?>
    <h1 class="dash-title">Tous les Utilisateurs</h1>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Nom</th><th>Email</th><th>Rôle</th><th>Inscrit le</th></tr></thead>
        <tbody>
        <?php foreach ($allUsers as $u): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><?= e($u['prenom'].' '.$u['nom']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><span class="tbl-badge <?= match($u['role']) {'admin'=>'red','vendeur'=>'orange',default=>'green'} ?>"><?= $u['role'] ?></span></td>
          <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
</main>
<?php include '../php/footer.php'; ?>
<script>
document.querySelectorAll('.auction-timer[data-end]').forEach(el => {
  function tick() {
    const diff = new Date(el.dataset.end) - Date.now();
    if (diff <= 0) { el.textContent = 'Terminée'; return; }
    const d = Math.floor(diff/86400000);
    const h = Math.floor((diff%86400000)/3600000);
    const m = Math.floor((diff%3600000)/60000);
    el.textContent = `${d}j ${h}h ${m}min`;
  }
  tick(); setInterval(tick, 60000);
});
</script>
