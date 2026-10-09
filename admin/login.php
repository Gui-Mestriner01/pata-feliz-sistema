<?php
require_once __DIR__ . '/../includes/funcoes.php';

if (usuario_logado()) {
    redirecionar(BASE_URL . '/admin/index.php');
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (autenticar($email, $senha)) {
        redirecionar(BASE_URL . '/admin/index.php');
    }

    $erro = 'E-mail ou senha incorretos.';
    usleep(400000); // atrasa um pouco para desencorajar tentativa em massa
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Entrar — Painel <?= e(LOJA_NOME) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body class="tela-login">

<main class="login">
  <div class="login__marca">
    <svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="16.5" rx="6.2" ry="5"/><ellipse cx="4.8" cy="9.4" rx="2.6" ry="3.4"/><ellipse cx="10" cy="6.2" rx="2.6" ry="3.6"/><ellipse cx="15.6" cy="6.5" rx="2.6" ry="3.6"/><ellipse cx="20" cy="10.2" rx="2.5" ry="3.2"/></svg>
    <span><strong>Pata Feliz</strong><small>Painel administrativo</small></span>
  </div>

  <?php if ($erro): ?>
    <p class="alerta alerta--ruim"><?= e($erro) ?></p>
  <?php endif; ?>

  <form method="post" class="login__form">
    <?= csrf_campo() ?>
    <div class="campo">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" required autofocus autocomplete="username"
             value="<?= e($_POST['email'] ?? '') ?>">
    </div>
    <div class="campo">
      <label for="senha">Senha</label>
      <input id="senha" name="senha" type="password" required autocomplete="current-password">
    </div>
    <button class="botao botao--lima botao--bloco" type="submit">Entrar</button>
  </form>

  <p class="login__nota">Acesso restrito à equipe do pet shop.</p>
</main>

</body>
</html>
