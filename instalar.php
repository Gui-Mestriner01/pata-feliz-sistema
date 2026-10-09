<?php
/**
 * Instalação em um passo: cria o primeiro usuário do painel.
 *
 * Rode uma única vez, depois de importar banco/schema.sql, e APAGUE este
 * arquivo. O sistema não traz nenhuma senha de fábrica justamente para que
 * não exista senha conhecida em produção.
 */

require_once __DIR__ . '/includes/funcoes.php';

$jaInstalado = (int) bd()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;

$erros   = [];
$pronto  = false;
$enviado = $_POST;

if (!$jaInstalado && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $nome     = trim($_POST['nome'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $senha    = (string) ($_POST['senha'] ?? '');
    $confirma = (string) ($_POST['confirma'] ?? '');

    if ($nome === '') {
        $erros['nome'] = 'Informe o seu nome.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'Informe um e-mail válido.';
    }
    if (strlen($senha) < 10) {
        $erros['senha'] = 'Use ao menos 10 caracteres.';
    } elseif (preg_match('/^\d+$/', $senha)) {
        $erros['senha'] = 'Não use só números.';
    }
    if ($senha !== $confirma) {
        $erros['confirma'] = 'As duas senhas não são iguais.';
    }

    if (!$erros) {
        try {
            $stmt = bd()->prepare(
                'INSERT INTO usuarios (nome, email, senha_hash, papel)
                 VALUES (?, ?, ?, \'admin\')'
            );
            $stmt->execute([
                $nome,
                $email,
                password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]),
            ]);
            $pronto = true;
        } catch (Throwable $e) {
            $erros['geral'] = 'Não foi possível criar o usuário. Confirme se o banco foi importado.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Instalação — <?= e(LOJA_NOME) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/publico.css">
</head>
<body>

<header class="topo">
  <span class="marca">
    <svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="16.5" rx="6.2" ry="5"/><ellipse cx="4.8" cy="9.4" rx="2.6" ry="3.4"/><ellipse cx="10" cy="6.2" rx="2.6" ry="3.6"/><ellipse cx="15.6" cy="6.5" rx="2.6" ry="3.6"/><ellipse cx="20" cy="10.2" rx="2.5" ry="3.2"/></svg>
    <span><strong>Pata Feliz</strong><small>Instalação</small></span>
  </span>
</header>

<main class="caixa">

<?php if ($jaInstalado): ?>
  <section class="recado-grande">
    <h1>Já está instalado</h1>
    <p>
      O painel já tem usuário cadastrado, então este instalador não faz mais nada.
    </p>
    <p class="recado-grande__nota">
      <strong>Apague o arquivo <code>instalar.php</code> do servidor.</strong>
      Se você perdeu a senha, troque direto no banco ou peça a um administrador.
    </p>
    <a class="botao botao--lima" href="<?= BASE_URL ?>/admin/login.php">Ir para o login</a>
  </section>

<?php elseif ($pronto): ?>
  <section class="recado-grande">
    <svg viewBox="0 0 24 24" class="recado-grande__ico" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8 12.5 2.6 2.6L16 9.7"/></svg>
    <h1>Tudo pronto!</h1>
    <p>Seu usuário de administrador foi criado. Já pode entrar no painel.</p>
    <p class="recado-grande__nota">
      <strong>Agora apague o arquivo <code>instalar.php</code> do servidor.</strong>
      Enquanto ele existir, fica um endereço a mais exposto sem necessidade.
    </p>
    <a class="botao botao--lima" href="<?= BASE_URL ?>/admin/login.php">Entrar no painel</a>
  </section>

<?php else: ?>
  <header class="titulo">
    <span class="rotulo">Instalação</span>
    <h1>Crie o seu acesso<br>ao painel</h1>
    <p>
      Este é o primeiro e único usuário criado por aqui. Depois dele, o
      instalador se desliga sozinho — e você apaga o arquivo.
    </p>
  </header>

  <?php if (isset($erros['geral'])): ?>
    <p class="alerta"><?= e($erros['geral']) ?></p>
  <?php endif; ?>

  <form method="post" class="form" novalidate autocomplete="off">
    <?= csrf_campo() ?>

    <div class="campo campo--largo">
      <label for="nome">Seu nome</label>
      <input id="nome" name="nome" type="text" required
             value="<?= e($enviado['nome'] ?? '') ?>" placeholder="Ex.: Maria Souza">
      <?php if (isset($erros['nome'])): ?><span class="erro"><?= e($erros['nome']) ?></span><?php endif; ?>
    </div>

    <div class="campo campo--largo">
      <label for="email">E-mail de acesso</label>
      <input id="email" name="email" type="email" required
             value="<?= e($enviado['email'] ?? '') ?>" placeholder="voce@seudominio.com.br">
      <?php if (isset($erros['email'])): ?><span class="erro"><?= e($erros['email']) ?></span><?php endif; ?>
    </div>

    <div class="campo">
      <label for="senha">Senha</label>
      <input id="senha" name="senha" type="password" required
             autocomplete="new-password" placeholder="Ao menos 10 caracteres">
      <?php if (isset($erros['senha'])): ?><span class="erro"><?= e($erros['senha']) ?></span><?php endif; ?>
    </div>

    <div class="campo">
      <label for="confirma">Repita a senha</label>
      <input id="confirma" name="confirma" type="password" required
             autocomplete="new-password" placeholder="A mesma senha">
      <?php if (isset($erros['confirma'])): ?><span class="erro"><?= e($erros['confirma']) ?></span><?php endif; ?>
    </div>

    <div class="campo campo--largo">
      <button class="botao botao--lima botao--bloco" type="submit">Criar meu acesso</button>
      <p class="form__nota">A senha é guardada como hash bcrypt — nem você nem nós conseguimos lê-la depois.</p>
    </div>
  </form>
<?php endif; ?>

</main>

<footer class="rodape">
  <p><strong><?= e(LOJA_NOME) ?></strong></p>
  <p class="rodape__credito">Desenvolvido por <strong>M&amp;M Tech</strong> ♥</p>
</footer>

</body>
</html>
