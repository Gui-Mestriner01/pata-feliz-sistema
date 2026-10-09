<?php
/** Agendamento criado pelo balcão/telefone — já entra confirmado. */

require_once __DIR__ . '/../includes/funcoes.php';

$usuario = exigir_login();
$titulo  = 'Novo agendamento';
$secao   = 'agendamentos';

$servicos = bd()->query('SELECT * FROM servicos WHERE ativo = 1 ORDER BY ordem, nome')->fetchAll();
$erro = null;
$enviado = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $nome      = trim($_POST['tutor'] ?? '');
    $whatsapp  = so_digitos($_POST['whatsapp'] ?? '');
    $petNome   = trim($_POST['pet'] ?? '');
    $especie   = ($_POST['especie'] ?? 'cachorro') === 'gato' ? 'gato' : 'cachorro';
    $raca      = trim($_POST['raca'] ?? '');
    $porte     = in_array($_POST['porte'] ?? '', ['pp','p','m','g','gg'], true) ? $_POST['porte'] : 'm';
    $peso      = ($_POST['peso'] ?? '') !== '' ? (float) str_replace(',', '.', $_POST['peso']) : null;
    $servicoId = (int) ($_POST['servico'] ?? 0);
    $data      = $_POST['data'] ?? '';
    $hora      = $_POST['hora'] ?? '';
    $obs       = trim($_POST['observacoes'] ?? '');

    $servico = null;
    foreach ($servicos as $s) {
        if ((int) $s['id'] === $servicoId) { $servico = $s; }
    }

    try {
        if ($nome === '' || strlen($whatsapp) < 10 || $petNome === '' || !$servico) {
            throw new RuntimeException('Preencha tutor, WhatsApp, pet e serviço.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !preg_match('/^\d{2}:\d{2}$/', $hora)) {
            throw new RuntimeException('Informe a data e o horário.');
        }

        $inicio = $hora . ':00';
        $fim    = somar_minutos($inicio, (int) $servico['duracao_min']);

        // no balcão o pet shop pode furar expediente e antecedência,
        // mas nunca sobrepor além da capacidade
        if (ocupacao_na_faixa($data, $inicio, $fim) >= (int) config('capacidade_simultanea', '2')) {
            throw new RuntimeException('Já há atendimentos demais nesse horário. Escolha outro.');
        }
        if (ha_bloqueio($data, $inicio, $fim)) {
            throw new RuntimeException('A agenda está bloqueada nesse horário.');
        }

        bd()->beginTransaction();
        $clienteId = cliente_garantir($nome, $whatsapp);
        $petId     = pet_garantir($clienteId, $petNome, $especie, $raca, $porte, $peso);

        $stmt = bd()->prepare(
            "INSERT INTO agendamentos
                (cliente_id, pet_id, servico_id, data, hora_inicio, hora_fim, status, preco, observacoes, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?, 'confirmado', ?, ?, ?)"
        );
        $stmt->execute([
            $clienteId, $petId, $servico['id'], $data, $inicio, $fim,
            preco_do_servico((int) $servico['id'], $porte), $obs ?: null, $usuario['id'],
        ]);
        $novoId = (int) bd()->lastInsertId();
        bd()->commit();

        recado('Agendamento criado e já confirmado.');
        redirecionar(BASE_URL . '/admin/agendamento.php?id=' . $novoId);

    } catch (RuntimeException $e) {
        if (bd()->inTransaction()) { bd()->rollBack(); }
        $erro = $e->getMessage();
    } catch (Throwable $e) {
        if (bd()->inTransaction()) { bd()->rollBack(); }
        $erro = 'Não foi possível salvar. Confira os dados e tente de novo.';
    }
}

require __DIR__ . '/../includes/cabecalho.php';
?>

<header class="pagina__topo">
  <div>
    <span class="rotulo">Balcão / telefone</span>
    <h1>Novo agendamento</h1>
  </div>
  <a class="botao botao--vazado" href="<?= BASE_URL ?>/admin/agendamentos.php">Voltar</a>
</header>

<?php if ($erro): ?>
  <p class="alerta alerta--ruim"><?= e($erro) ?></p>
<?php endif; ?>

<section class="bloco">
  <p class="bloco__apoio">
    Agendamentos feitos aqui já entram confirmados — o cliente está na sua frente ou no telefone.
  </p>

  <form method="post" class="form-compacto">
    <?= csrf_campo() ?>

    <div class="campo">
      <label for="tutor">Nome do tutor</label>
      <input id="tutor" name="tutor" type="text" required value="<?= e($enviado['tutor'] ?? '') ?>">
    </div>

    <div class="campo">
      <label for="whatsapp">WhatsApp</label>
      <input id="whatsapp" name="whatsapp" type="tel" required maxlength="15"
             value="<?= e($enviado['whatsapp'] ?? '') ?>" placeholder="(11) 90000-0000">
    </div>

    <div class="campo">
      <label for="pet">Nome do pet</label>
      <input id="pet" name="pet" type="text" required value="<?= e($enviado['pet'] ?? '') ?>">
    </div>

    <div class="campo">
      <label for="especie">Espécie</label>
      <select id="especie" name="especie">
        <option value="cachorro" <?= ($enviado['especie'] ?? '') !== 'gato' ? 'selected' : '' ?>>Cachorro</option>
        <option value="gato" <?= ($enviado['especie'] ?? '') === 'gato' ? 'selected' : '' ?>>Gato</option>
      </select>
    </div>

    <div class="campo">
      <label for="raca">Raça <i>(opcional)</i></label>
      <input id="raca" name="raca" type="text" value="<?= e($enviado['raca'] ?? '') ?>">
    </div>

    <div class="campo">
      <label for="porte">Porte</label>
      <select id="porte" name="porte">
        <?php foreach (['pp','p','m','g','gg'] as $p): ?>
          <option value="<?= $p ?>" <?= ($enviado['porte'] ?? 'm') === $p ? 'selected' : '' ?>>
            <?= e(rotulo_porte($p)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="campo">
      <label for="peso">Peso <i>(opcional)</i></label>
      <input id="peso" name="peso" type="text" inputmode="decimal" value="<?= e($enviado['peso'] ?? '') ?>">
    </div>

    <div class="campo">
      <label for="servico">Serviço</label>
      <select id="servico" name="servico" required>
        <option value="">Escolha</option>
        <?php foreach ($servicos as $s): ?>
          <option value="<?= (int) $s['id'] ?>"
                  <?= (int) ($enviado['servico'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>>
            <?= e($s['nome']) ?> · <?= (int) $s['duracao_min'] ?> min
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="campo">
      <label for="data">Data</label>
      <input id="data" name="data" type="date" required
             value="<?= e($enviado['data'] ?? date('Y-m-d')) ?>">
    </div>

    <div class="campo">
      <label for="hora">Horário</label>
      <input id="hora" name="hora" type="time" required step="900"
             value="<?= e($enviado['hora'] ?? '') ?>">
    </div>

    <div class="campo campo--largo">
      <label for="observacoes">Observações <i>(opcional)</i></label>
      <textarea id="observacoes" name="observacoes" rows="3"><?= e($enviado['observacoes'] ?? '') ?></textarea>
    </div>

    <button class="botao botao--lima botao--bloco" type="submit">Salvar agendamento</button>
  </form>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
