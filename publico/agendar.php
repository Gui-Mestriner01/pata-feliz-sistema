<?php
/**
 * Página pública de agendamento.
 * O pedido entra no sistema com status "pendente" e o pet shop aprova no painel.
 */

require_once __DIR__ . '/../includes/funcoes.php';

$erros    = [];
$sucesso  = null;
$enviado  = $_POST;

// permite chegar da página inicial com o serviço já escolhido (?servico=3)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['servico'])) {
    $enviado['servico'] = (int) $_GET['servico'];
}

$servicos = bd()->query(
    'SELECT * FROM servicos WHERE ativo = 1 ORDER BY ordem, nome'
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $nome     = trim($_POST['tutor'] ?? '');
    $whatsapp = so_digitos($_POST['whatsapp'] ?? '');
    $petNome  = trim($_POST['pet'] ?? '');
    $especie  = ($_POST['especie'] ?? 'cachorro') === 'gato' ? 'gato' : 'cachorro';
    $raca     = trim($_POST['raca'] ?? '');
    $porte    = $_POST['porte'] ?? 'm';
    $peso     = ($_POST['peso'] ?? '') !== '' ? (float) str_replace(',', '.', $_POST['peso']) : null;
    $servicoId = (int) ($_POST['servico'] ?? 0);
    $data     = $_POST['data'] ?? '';
    $hora     = $_POST['hora'] ?? '';
    $obs      = trim($_POST['observacoes'] ?? '');

    if ($nome === '')                              { $erros['tutor'] = 'Informe o seu nome.'; }
    if (strlen($whatsapp) < 10)                    { $erros['whatsapp'] = 'Informe o DDD e o número completo.'; }
    if ($petNome === '')                           { $erros['pet'] = 'Informe o nome do pet.'; }
    if (!in_array($porte, ['pp','p','m','g','gg'], true)) { $erros['porte'] = 'Escolha o porte.'; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) { $erros['data'] = 'Escolha a data.'; }
    if (!preg_match('/^\d{2}:\d{2}$/', $hora))      { $erros['hora'] = 'Escolha o horário.'; }

    $servico = null;
    foreach ($servicos as $s) {
        if ((int) $s['id'] === $servicoId) { $servico = $s; }
    }
    if (!$servico) { $erros['servico'] = 'Escolha um serviço.'; }

    // só checa a agenda se o resto estiver certo
    if (!$erros) {
        $inicio = $hora . ':00';
        $motivo = motivo_indisponivel($data, $inicio, (int) $servico['duracao_min']);

        if ($motivo !== null) {
            $erros['hora'] = $motivo;
        } else {
            try {
                bd()->beginTransaction();

                $clienteId = cliente_garantir($nome, $whatsapp);
                $petId     = pet_garantir($clienteId, $petNome, $especie, $raca, $porte, $peso);
                $fim       = somar_minutos($inicio, (int) $servico['duracao_min']);
                $preco     = preco_do_servico((int) $servico['id'], $porte);

                // confere de novo dentro da transação, para duas pessoas
                // não pegarem o mesmo horário ao mesmo tempo
                if (ocupacao_na_faixa($data, $inicio, $fim) >= (int) config('capacidade_simultanea', '2')) {
                    throw new RuntimeException('Esse horário acabou de ser preenchido. Escolha outro, por favor.');
                }

                $stmt = bd()->prepare(
                    'INSERT INTO agendamentos
                        (cliente_id, pet_id, servico_id, data, hora_inicio, hora_fim, preco, observacoes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $clienteId, $petId, $servico['id'], $data, $inicio, $fim, $preco, $obs ?: null,
                ]);

                bd()->commit();

                $sucesso = [
                    'servico' => $servico['nome'],
                    'pet'     => $petNome,
                    'data'    => formatar_data($data),
                    'hora'    => $hora,
                ];
                $enviado = [];
            } catch (RuntimeException $e) {
                bd()->rollBack();
                $erros['hora'] = $e->getMessage();
            } catch (Throwable $e) {
                bd()->rollBack();
                $erros['geral'] = 'Não conseguimos registrar o pedido agora. Tente novamente em instantes.';
            }
        }
    }
}

$hoje   = date('Y-m-d');
$limite = date('Y-m-d', strtotime('+' . (int) config('dias_futuros', '60') . ' days'));
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Agendar — <?= e(LOJA_NOME) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/publico.css">
</head>
<body>

<header class="topo">
  <a class="marca" href="<?= BASE_URL ?>/">
    <svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="16.5" rx="6.2" ry="5"/><ellipse cx="4.8" cy="9.4" rx="2.6" ry="3.4"/><ellipse cx="10" cy="6.2" rx="2.6" ry="3.6"/><ellipse cx="15.6" cy="6.5" rx="2.6" ry="3.6"/><ellipse cx="20" cy="10.2" rx="2.5" ry="3.2"/></svg>
    <span><strong>Pata Feliz</strong><small>Banho e tosa</small></span>
  </a>
</header>

<main class="caixa">

<?php if ($sucesso): ?>
  <section class="recado-grande">
    <svg viewBox="0 0 24 24" class="recado-grande__ico" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8 12.5 2.6 2.6L16 9.7"/></svg>
    <h1>Pedido enviado!</h1>
    <p>
      Recebemos o seu pedido de <strong><?= e($sucesso['servico']) ?></strong> para o
      <strong><?= e($sucesso['pet']) ?></strong> em
      <strong><?= e($sucesso['data']) ?> às <?= e($sucesso['hora']) ?></strong>.
    </p>
    <p class="recado-grande__nota">
      Agora é com a gente: vamos conferir a agenda e confirmar pelo WhatsApp.
      Fique de olho no seu celular.
    </p>
    <a class="botao botao--lima" href="<?= BASE_URL ?>/publico/agendar.php">Fazer outro agendamento</a>
  </section>

<?php else: ?>
  <header class="titulo">
    <span class="rotulo">Agendamento</span>
    <h1>Reserve o horário<br>do seu pet</h1>
    <p>Escolha o serviço e o dia — mostramos só os horários que estão realmente livres.</p>
  </header>

  <?php if (isset($erros['geral'])): ?>
    <p class="alerta"><?= e($erros['geral']) ?></p>
  <?php endif; ?>

  <form method="post" class="form" novalidate>
    <?= csrf_campo() ?>

    <div class="campo">
      <label for="tutor">Nome do tutor</label>
      <input id="tutor" name="tutor" type="text" required
             value="<?= e($enviado['tutor'] ?? '') ?>" placeholder="Como podemos te chamar?">
      <?php if (isset($erros['tutor'])): ?><span class="erro"><?= e($erros['tutor']) ?></span><?php endif; ?>
    </div>

    <div class="campo">
      <label for="whatsapp">WhatsApp</label>
      <input id="whatsapp" name="whatsapp" type="tel" required inputmode="numeric" maxlength="15"
             value="<?= e($enviado['whatsapp'] ?? '') ?>" placeholder="(11) 90000-0000">
      <?php if (isset($erros['whatsapp'])): ?><span class="erro"><?= e($erros['whatsapp']) ?></span><?php endif; ?>
    </div>

    <div class="campo">
      <label for="pet">Nome do pet</label>
      <input id="pet" name="pet" type="text" required
             value="<?= e($enviado['pet'] ?? '') ?>" placeholder="Ex.: Thor">
      <?php if (isset($erros['pet'])): ?><span class="erro"><?= e($erros['pet']) ?></span><?php endif; ?>
    </div>

    <fieldset class="campo">
      <legend>Tipo</legend>
      <div class="pilulas">
        <label>
          <input type="radio" name="especie" value="cachorro"
                 <?= ($enviado['especie'] ?? 'cachorro') !== 'gato' ? 'checked' : '' ?>>
          <span>Cachorro</span>
        </label>
        <label>
          <input type="radio" name="especie" value="gato"
                 <?= ($enviado['especie'] ?? '') === 'gato' ? 'checked' : '' ?>>
          <span>Gato</span>
        </label>
      </div>
    </fieldset>

    <div class="campo">
      <label for="raca">Raça <i>(opcional)</i></label>
      <input id="raca" name="raca" type="text"
             value="<?= e($enviado['raca'] ?? '') ?>" placeholder="Ex.: Shih-tzu">
    </div>

    <div class="campo">
      <label for="porte">Porte do pet</label>
      <select id="porte" name="porte" required>
        <?php foreach (['pp','p','m','g','gg'] as $p): ?>
          <option value="<?= $p ?>" <?= ($enviado['porte'] ?? 'm') === $p ? 'selected' : '' ?>>
            <?= e(rotulo_porte($p)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="campo">
      <label for="peso">Peso <i>(opcional)</i></label>
      <input id="peso" name="peso" type="text" inputmode="decimal"
             value="<?= e($enviado['peso'] ?? '') ?>" placeholder="Ex.: 8">
    </div>

    <div class="campo campo--largo">
      <label for="servico">Serviço desejado</label>
      <select id="servico" name="servico" required>
        <option value="">Escolha um serviço</option>
        <?php foreach ($servicos as $s): ?>
          <option value="<?= (int) $s['id'] ?>"
                  data-duracao="<?= (int) $s['duracao_min'] ?>"
                  <?= (int) ($enviado['servico'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
            <?= e($s['nome']) ?> · <?= (int) $s['duracao_min'] ?> min
          </option>
        <?php endforeach; ?>
      </select>
      <?php if (isset($erros['servico'])): ?><span class="erro"><?= e($erros['servico']) ?></span><?php endif; ?>
    </div>

    <div class="campo">
      <label for="data">Data</label>
      <input id="data" name="data" type="date" required
             min="<?= $hoje ?>" max="<?= $limite ?>"
             value="<?= e($enviado['data'] ?? '') ?>">
      <?php if (isset($erros['data'])): ?><span class="erro"><?= e($erros['data']) ?></span><?php endif; ?>
    </div>

    <div class="campo">
      <label for="hora">Horário</label>
      <select id="hora" name="hora" required disabled
              data-escolhido="<?= e($enviado['hora'] ?? '') ?>">
        <option value="">Escolha o serviço e a data</option>
      </select>
      <span class="dica" id="dicaHorarios"></span>
      <?php if (isset($erros['hora'])): ?><span class="erro"><?= e($erros['hora']) ?></span><?php endif; ?>
    </div>

    <div class="campo campo--largo">
      <label for="observacoes">Alguma observação? <i>(opcional)</i></label>
      <textarea id="observacoes" name="observacoes" rows="3"
                placeholder="Ex.: ele fica nervoso com secador"><?= e($enviado['observacoes'] ?? '') ?></textarea>
    </div>

    <div class="campo campo--largo">
      <button class="botao botao--lima botao--bloco" type="submit">Enviar pedido de agendamento</button>
      <p class="form__nota">O pedido passa por aprovação do pet shop. Confirmamos pelo WhatsApp.</p>
    </div>
  </form>
<?php endif; ?>

</main>

<footer class="rodape">
  <p><strong><?= e(LOJA_NOME) ?></strong></p>
  <p><?= e(LOJA_ENDERECO) ?> · <?= e(formatar_telefone(substr(LOJA_WHATSAPP, 2))) ?></p>
  <p class="rodape__credito">
    Desenvolvido por <strong>M&amp;M Tech</strong>
    <!-- o coração é o atalho discreto para o painel da equipe -->
    <a class="rodape__chave" href="<?= BASE_URL ?>/admin/login.php"
       title="Acesso da equipe" aria-label="Acesso da equipe">♥</a>
  </p>
</footer>

<script>
window.BASE_URL = <?= json_encode(BASE_URL) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/agendar.js" defer></script>
</body>
</html>
