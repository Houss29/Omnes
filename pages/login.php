<?php
define('ROOT', '..');
require_once '../config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (isLoggedIn()) redirect(ROOT.'/index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    try {
       $pdo  = getPDO(); 
       $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user && $password === $user['password']) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email']   = $user['email'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['nom']     = $user['nom'];
    $_SESSION['prenom']  = $user['prenom'];
    $redirect = $_GET['redirect'] ?? ROOT.'/index.php';
    redirect($redirect);
} else {
    $error = 'Email ou mot de passe incorrect.';
}
    } catch (Exception $e) {
    $error = $e->getMessage();
}
}
$pageTitle = 'Connexion';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Connexion — Omnes MarketPlace</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-visual">
    <div style="font-size:56px;margin-bottom:24px">🏛</div>
    <h2>Omnes MarketPlace</h2>
    <p>Achetez, enchérissez et négociez des articles uniques sur la plateforme de référence.</p>
  </div>
  <div class="auth-form-wrap">
    <h1>Connexion</h1>
    <p class="sub">Bienvenue ! Connectez-vous à votre compte.</p>

    <?php if ($error): ?>
    <div class="alert alert-error"><span>❌</span><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">Adresse e-mail</label>
        <input type="email" name="email" class="form-control" placeholder="vous@exemple.fr"
               value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label">Mot de passe</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">
        Se connecter
      </button>
    </form>

    <div style="margin-top:28px;text-align:center;font-size:14px;color:var(--muted)">
      Pas encore de compte ?
      <a href="register.php" style="color:var(--accent2);font-weight:600">Créer un compte</a>
    </div>

    <hr class="divider">
    <div style="background:var(--line);border-radius:8px;padding:16px;font-size:13px">
      <strong>Comptes de test :</strong><br>
      Admin : admin@omnes.fr / password<br>
      Vendeur : sophie@vendor.fr / password<br>
      Client : marie@client.fr / password
    </div>
  </div>
</div>
</body>
</html>
