<?php
define('ROOT', '..');
require_once '../config.php';
requireLogin();
$pageTitle = 'Négociation';
$negoId = (int)($_GET['id'] ?? 0);
$userId = currentUserId();

$nego = null;
$offres = [];
$msg = '';

try {
    $pdo = getPDO();
    $nego = $pdo->prepare("
        SELECT n.*, i.nom AS item_nom, i.prix AS prix_initial, i.id AS item_id,
               i.vendeur_id,
               ua.prenom AS acheteur_prenom, ua.nom AS acheteur_nom,
               uv.prenom AS vendeur_prenom, uv.nom AS vendeur_nom, uv.pseudo AS vendeur_pseudo,
               (SELECT url FROM item_photos WHERE item_id=i.id LIMIT 1) AS photo
        FROM negociations n
        JOIN items i ON i.id=n.item_id
        JOIN users ua ON ua.id=n.acheteur_id
        JOIN users uv ON uv.id=n.vendeur_id
        WHERE n.id=? AND (n.acheteur_id=? OR n.vendeur_id=?)
    ");
    $nego->execute([$negoId, $userId, $userId]);
    $nego = $nego->fetch();
    if (!$nego) { redirect(ROOT.'/pages/compte.php?tab=negociations'); }

    $offres = $pdo->prepare("SELECT o.*, u.prenom, u.nom FROM offres_negociation o JOIN users u ON u.id=o.emetteur_id WHERE o.negociation_id=? ORDER BY o.created_at ASC");
    $offres->execute([$negoId]);
    $offres = $offres->fetchAll();
} catch(Exception $e) {}

// Traitement réponse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $nego && $nego['statut'] === 'en_cours') {
    $pdo = getPDO();
    $action = $_POST['action'] ?? '';

    // Accepter l'offre (vendeur)
    if ($action === 'accepter' && $userId == $nego['vendeur_id']) {
        try {
            $derniere = end($offres);
            $pdo->prepare("UPDATE negociations SET statut='acceptee' WHERE id=?")->execute([$negoId]);
            $pdo->prepare("UPDATE offres_negociation SET statut='acceptee' WHERE id=?")->execute([$derniere['id']]);
            $pdo->prepare("INSERT INTO panier (acheteur_id, item_id, type_achat, prix_final) VALUES (?,?,?,?)")
                ->execute([$nego['acheteur_id'], $nego['item_id'], 'negociation', $derniere['montant']]);
            notify($nego['acheteur_id'], "🎉 Le vendeur a accepté votre offre de ".formatPrice((float)$derniere['montant'])." pour « {$nego['item_nom']} » ! L'article a été ajouté à votre panier.", 'negociation');
            $msg = '<div class="alert alert-success">✅ Offre acceptée ! L\'article a été ajouté au panier de l\'acheteur.</div>';
        } catch(Exception $e) { $msg = '<div class="alert alert-error">'.$e->getMessage().'</div>'; }
    }

    // Refuser
    if ($action === 'refuser' && $userId == $nego['vendeur_id']) {
        try {
            $pdo->prepare("UPDATE negociations SET statut='refusee' WHERE id=?")->execute([$negoId]);
            notify($nego['acheteur_id'], "❌ Le vendeur a refusé votre offre pour « {$nego['item_nom']} ».", 'negociation');
            $msg = '<div class="alert alert-warn">Offre refusée.</div>';
        } catch(Exception $e) { $msg = '<div class="alert alert-error">'.$e->getMessage().'</div>'; }
    }

    // Contre-offre (vendeur) ou nouvelle offre (acheteur)
    if ($action === 'contre_offre') {
        $montant = (float)($_POST['montant'] ?? 0);
        if ($nego['nb_tours'] >= 5) {
            $msg = '<div class="alert alert-error">Nombre maximum de tours (5) atteint.</div>';
        } elseif ($montant <= 0) {
            $msg = '<div class="alert alert-error">Montant invalide.</div>';
        } else {
            try {
                $pdo->prepare("INSERT INTO offres_negociation (negociation_id, emetteur_id, montant, message, statut) VALUES (?,?,?,?,'contre_offre')")
                    ->execute([$negoId, $userId, $montant, $_POST['message'] ?? '']);
                $pdo->prepare("UPDATE negociations SET nb_tours=nb_tours+1 WHERE id=?")->execute([$negoId]);
                $notifUser = ($userId == $nego['vendeur_id']) ? $nego['acheteur_id'] : $nego['vendeur_id'];
                notify($notifUser, "Nouvelle contre-offre de ".formatPrice($montant)." sur « {$nego['item_nom']} ».", 'negociation');
                $msg = '<div class="alert alert-success">✅ Contre-offre envoyée.</div>';
                // Reload
                header('Location: negociation.php?id='.$negoId);
                exit;
            } catch(Exception $e) { $msg = '<div class="alert alert-error">'.$e->getMessage().'</div>'; }
        }
    }

    // Recharger les offres
    if (!$msg) {
        $offres = $pdo->prepare("SELECT o.*, u.prenom, u.nom FROM offres_negociation o JOIN users u ON u.id=o.emetteur_id WHERE o.negociation_id=? ORDER BY o.created_at ASC");
        $offres->execute([$negoId]);
        $offres = $offres->fetchAll();
        $nego = $pdo->prepare("SELECT n.* FROM negociations n WHERE n.id=?")->execute([$negoId]) ? $nego : $nego;
        $negoRow = $pdo->prepare("SELECT * FROM negociations WHERE id=?"); $negoRow->execute([$negoId]); $negoResult = $negoRow->fetch();
        if ($negoResult) $nego = array_merge($nego, $negoResult);
    }
}

include '../php/header.php';
?>
<main>
<div class="breadcrumb">
  <a href="<?= ROOT ?>/index.php">Accueil</a> ›
  <a href="compte.php?tab=negociations">Négociations</a> ›
  <span><?= e($nego['item_nom'] ?? '') ?></span>
</div>

<section class="section">
  <div style="display:grid;grid-template-columns:1fr 360px;gap:40px;align-items:start">

    <!-- Thread de négociation -->
    <div class="nego-thread">
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px">
        <img src="<?= e($nego['photo'] ?? 'https://via.placeholder.com/60') ?>"
             style="width:60px;height:60px;border-radius:8px;object-fit:cover">
        <div>
          <h2 style="font-family:var(--font-head);font-size:24px"><?= e($nego['item_nom']) ?></h2>
          <p style="font-size:14px;color:var(--muted)">
            Prix initial : <?= formatPrice((float)$nego['prix_initial']) ?> ·
            Tour <?= $nego['nb_tours'] ?>/5
          </p>
        </div>
      </div>

      <!-- Barre de progression -->
      <div class="nego-progress">
        <?php for ($t=1;$t<=5;$t++): ?>
        <div class="nego-step <?= $t < $nego['nb_tours'] ? 'done' : ($t == $nego['nb_tours'] ? 'active' : '') ?>"></div>
        <?php endfor; ?>
      </div>

      <?= $msg ?>

      <!-- Statut -->
      <?php if ($nego['statut'] !== 'en_cours'): ?>
      <div class="alert <?= $nego['statut']==='acceptee'?'alert-success':'alert-error' ?>">
        <?= $nego['statut'] === 'acceptee' ? '✅ Offre acceptée ! Voir votre panier.' : '❌ Négociation terminée.' ?>
      </div>
      <?php endif; ?>

      <!-- Messages -->
      <div style="min-height:200px;margin:20px 0">
        <?php foreach ($offres as $o):
          $isMe = ($o['emetteur_id'] == $userId);
        ?>
        <div style="display:flex;flex-direction:column;align-items:<?= $isMe?'flex-end':'flex-start' ?>;margin-bottom:16px">
          <div class="message-bubble <?= $isMe?'bubble-buyer':'bubble-seller' ?>">
            <strong><?= e($o['prenom'].' '.$o['nom']) ?></strong> propose
            <span style="font-size:18px;font-weight:700;color:var(--accent)"><?= formatPrice((float)$o['montant']) ?></span>
            <?php if ($o['message']): ?>
            <p style="font-size:13px;margin-top:6px;color:var(--muted)">"<?= e($o['message']) ?>"</p>
            <?php endif; ?>
          </div>
          <div class="bubble-meta"><?= date('d/m H:i', strtotime($o['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Actions -->
      <?php if ($nego['statut'] === 'en_cours' && $nego['nb_tours'] < 5): ?>
        <?php
        $lastOffer = end($offres);
        $isVendeur = ($userId == $nego['vendeur_id']);
        $isAcheteur = ($userId == $nego['acheteur_id']);
        $lastIsMe = $lastOffer && ($lastOffer['emetteur_id'] == $userId);
        ?>

        <?php if ($isVendeur && !$lastIsMe): ?>
        <!-- Le vendeur peut accepter, refuser ou contre-offrir -->
        <div style="border-top:1px solid var(--line);padding-top:20px">
          <div style="display:flex;gap:12px;margin-bottom:16px">
            <form method="POST" style="flex:1">
              <input type="hidden" name="action" value="accepter">
              <button class="btn btn-success" style="width:100%">✅ Accepter — <?= $lastOffer ? formatPrice((float)$lastOffer['montant']) : '' ?></button>
            </form>
            <form method="POST">
              <input type="hidden" name="action" value="refuser">
              <button class="btn" style="background:#fee;color:var(--accent)">❌ Refuser</button>
            </form>
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="contre_offre">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Ma contre-offre (€)</label>
                <input type="number" name="montant" class="form-control" step="0.01" required
                       placeholder="Ex: <?= $lastOffer ? round($lastOffer['montant'] * 1.1) : '' ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Message</label>
                <input type="text" name="message" class="form-control" placeholder="Optionnel">
              </div>
            </div>
            <button class="btn btn-dark" style="width:100%">💬 Envoyer la contre-offre</button>
          </form>
        </div>

        <?php elseif ($isAcheteur && $lastIsMe): ?>
        <div class="alert alert-info">
          <span>⏳</span> En attente de la réponse du vendeur (@<?= e($nego['vendeur_pseudo']) ?>)…
        </div>

        <?php elseif ($isAcheteur && !$lastIsMe): ?>
        <div style="border-top:1px solid var(--line);padding-top:20px">
          <form method="POST">
            <input type="hidden" name="action" value="contre_offre">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Ma nouvelle offre (€)</label>
                <input type="number" name="montant" class="form-control" step="0.01" required>
              </div>
              <div class="form-group">
                <label class="form-label">Message</label>
                <input type="text" name="message" class="form-control">
              </div>
            </div>
            <button class="btn btn-primary" style="width:100%">📤 Envoyer mon offre</button>
          </form>
        </div>
        <?php endif; ?>

      <?php elseif ($nego['nb_tours'] >= 5 && $nego['statut'] === 'en_cours'): ?>
      <div class="alert alert-warn">⚠️ Nombre maximum de tours atteint. La négociation doit être conclue.</div>
      <?php if ($userId == $nego['vendeur_id']): ?>
      <div style="display:flex;gap:12px;margin-top:12px">
        <form method="POST"><input type="hidden" name="action" value="accepter">
          <button class="btn btn-success">✅ Accepter</button>
        </form>
        <form method="POST"><input type="hidden" name="action" value="refuser">
          <button class="btn" style="background:#fee;color:var(--accent)">❌ Refuser</button>
        </form>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Infos latérales -->
    <div>
      <div style="background:var(--surface);border-radius:var(--radius);padding:28px;box-shadow:var(--shadow)">
        <h3 style="font-size:18px;font-weight:600;margin-bottom:20px">Participants</h3>
        <div style="display:flex;flex-direction:column;gap:16px">
          <div style="display:flex;align-items:center;gap:12px">
            <div class="user-avatar">A</div>
            <div>
              <div style="font-weight:600"><?= e($nego['acheteur_prenom'].' '.$nego['acheteur_nom']) ?></div>
              <div style="font-size:12px;color:var(--muted)">Acheteur</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:12px">
            <div class="user-avatar" style="background:var(--accent2)">V</div>
            <div>
              <div style="font-weight:600"><?= e($nego['vendeur_prenom'].' '.$nego['vendeur_nom']) ?></div>
              <div style="font-size:12px;color:var(--muted)">@<?= e($nego['vendeur_pseudo']) ?> · Vendeur</div>
            </div>
          </div>
        </div>
        <hr class="divider">
        <div style="font-size:14px">
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--line)">
            <span class="text-muted">Prix initial</span>
            <strong><?= formatPrice((float)$nego['prix_initial']) ?></strong>
          </div>
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--line)">
            <span class="text-muted">Tours</span>
            <strong><?= $nego['nb_tours'] ?>/5</strong>
          </div>
          <div style="display:flex;justify-content:space-between;padding:8px 0">
            <span class="text-muted">Statut</span>
            <span class="tbl-badge <?= $nego['statut']==='en_cours'?'orange':($nego['statut']==='acceptee'?'green':'red') ?>"><?= $nego['statut'] ?></span>
          </div>
        </div>
        <a href="item.php?id=<?= $nego['item_id'] ?>" class="btn btn-outline-dark" style="width:100%;margin-top:20px;text-align:center">
          Voir l'article →
        </a>
      </div>
    </div>
  </div>
</section>
</main>
<?php include '../php/footer.php'; ?>
