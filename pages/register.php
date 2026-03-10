<?php
define('ROOT', '..');
require_once '../config.php';
if (isLoggedIn()) redirect(ROOT.'/index.php');

$errors = [];
$data   = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'nom'       => trim($_POST['nom'] ?? ''),
        'prenom'    => trim($_POST['prenom'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'password'  => $_POST['password'] ?? '',
        'password2' => $_POST['password2'] ?? '',
        'adresse1'  => trim($_POST['adresse1'] ?? ''),
        'ville'     => trim($_POST['ville'] ?? ''),
        'code_postal'=> trim($_POST['code_postal'] ?? ''),
        'pays'      => trim($_POST['pays'] ?? ''),
        'telephone' => trim($_POST['telephone'] ?? ''),
    ];
    if (!$data['nom'])    $errors[] = 'Le nom est requis.';
    if (!$data['prenom']) $errors[] = 'Le prénom est requis.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
    if (strlen($data['password']) < 8) $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
    if ($data['password'] !== $data['password2']) $errors[] = 'Les mots de passe ne correspondent pas.';
    if (!isset($_POST['cgv'])) $errors[] = 'Vous devez accepter les conditions générales.';

    if (empty($errors)) {
        try {
            $pdo = getPDO();
            $check = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $check->execute([$data['email']]);
            if ($check->fetch()) {
                $errors[] = 'Cet email est déjà utilisé.';
            } else {
                $hash = password_hash($data['password'], PASSWORD_BCRYPT);
                $pdo->prepare("INSERT INTO users (nom,prenom,email,password,role,adresse1,ville,code_postal,pays,telephone) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$data['nom'],$data['prenom'],$data['email'],$hash,'acheteur',$data['adresse1'],$data['ville'],$data['code_postal'],$data['pays'],$data['telephone']]);
                $userId = $pdo->lastInsertId();
                $_SESSION['user_id'] = $userId;
                $_SESSION['email']   = $data['email'];
                $_SESSION['role']    = 'acheteur';
                $_SESSION['nom']     = $data['nom'];
                $_SESSION['prenom']  = $data['prenom'];
                redirect(ROOT.'/index.php');
            }
        } catch(Exception $e) {
            $errors[] = 'Erreur BDD : '.$e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Créer un compte — Omnes MarketPlace</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body style="background:var(--paper);padding:60px 20px">
<div style="max-width:600px;margin:0 auto">
  <div style="text-align:center;margin-bottom:40px">
    <a href="../index.php" style="font-family:var(--font-head);font-size:28px;font-weight:700;color:var(--ink)">
      🏛 Omnes <span style="color:var(--gold)">MarketPlace</span>
    </a>
    <h1 style="font-family:var(--font-head);font-size:36px;font-weight:400;margin-top:24px">Créer un compte</h1>
    <p style="color:var(--muted)">Rejoignez notre communauté d'acheteurs</p>
  </div>

  <?php if ($errors): ?>
  <div class="alert alert-error">
    <div><strong>Erreurs :</strong><ul style="margin:8px 0 0 20px">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul></div>
  </div>
  <?php endif; ?>

  <div style="background:var(--surface);border-radius:var(--radius);padding:40px;box-shadow:var(--shadow)">
    <form method="POST">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Prénom *</label>
          <input type="text" name="prenom" class="form-control" value="<?= e($data['prenom'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Nom *</label>
          <input type="text" name="nom" class="form-control" value="<?= e($data['nom'] ?? '') ?>" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email *</label>
        <input type="email" name="email" class="form-control" value="<?= e($data['email'] ?? '') ?>" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Mot de passe *</label>
          <input type="password" name="password" class="form-control" minlength="8" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirmer *</label>
          <input type="password" name="password2" class="form-control" minlength="8" required>
        </div>
      </div>
      <hr class="divider">
      <h3 style="font-size:16px;font-weight:600;margin-bottom:20px">Adresse de livraison</h3>
      <div class="form-group">
        <label class="form-label">Adresse</label>
        <input type="text" name="adresse1" class="form-control" value="<?= e($data['adresse1'] ?? '') ?>">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Ville</label>
          <input type="text" name="ville" class="form-control" value="<?= e($data['ville'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Code postal</label>
          <input type="text" name="code_postal" class="form-control" value="<?= e($data['code_postal'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Pays</label>
          <select name="pays" class="form-control">
            <option value="France" <?= ($data['pays']??'')=='France'?'selected':'' ?>>France</option>
            <option value="Belgique">Belgique</option>
            <option value="Suisse">Suisse</option>
            <option value="Luxembourg">Luxembourg</option>
            <option value="Autre">Autre</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Téléphone</label>
          <input type="tel" name="telephone" class="form-control" value="<?= e($data['telephone'] ?? '') ?>">
        </div>
      </div>
      <hr class="divider">
      <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:24px">
        <input type="checkbox" name="cgv" id="cgv" style="margin-top:3px;flex-shrink:0" required>
        <label for="cgv" style="font-size:14px;color:var(--muted)">
          J'accepte que si je fais une offre sur un article, je suis sous <strong>contrat légal</strong> pour l'acheter si le vendeur l'accepte.
          J'accepte également les <a href="#" style="color:var(--accent2)">conditions générales de vente</a>.
        </label>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">Créer mon compte</button>
    </form>
  </div>

  <div style="text-align:center;margin-top:24px;font-size:14px;color:var(--muted)">
    Déjà un compte ? <a href="login.php" style="color:var(--accent2);font-weight:600">Se connecter</a>
  </div>
</div>
</body>
</html>
