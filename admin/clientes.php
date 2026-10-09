<?php
/** Clientes, seus pets e o histórico de atendimentos */

require_once __DIR__ . '/../includes/funcoes.php';

$usuario = exigir_login();
$titulo  = 'Clientes e pets';
$secao   = 'clientes';

$busca = trim($_GET['q'] ?? '');
$ver   = (int) ($_GET['ver'] ?? 0);

// ---------- ações ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_cliente') {
        $id = (int) $_POST['cliente_id'];
        $stmt = bd()->prepare('UPDATE clientes SET nome = ?, whatsapp = ?, email = ?, observacoes = ? WHERE id = ?');
        $stmt->execute([
            trim($_POST['nome']),
            so_digitos($_POST['whatsapp']),
            trim($_POST['email']) ?: null,
            trim($_POST['observacoes']) ?: null,
            $id,
        ]);
        recado('Dados do cliente atualizados.');
        redirecionar(BASE_URL . '/admin/clientes.php?ver=' . $id);
    }

    if ($acao === 'salvar_pet') {
        $stmt = bd()->prepare(
            'UPDATE pets SET nome = ?, especie = ?, raca = ?, porte = ?, peso_kg = ?, observacoes = ? WHERE id = ?'
        );
        $stmt->execute([
            trim($_POST['pet_nome']),
            $_POST['pet_especie'] === 'gato' ? 'gato' : 'cachorro',
            trim($_POST['pet_raca']) ?: null,
            in_array($_POST['pet_porte'], ['pp','p','m','g','gg'], true) ? $_POST['pet_porte'] : 'm',
            ($_POST['pet_peso'] ?? '') !== '' ? (float) str_replace(',', '.', $_POST['pet_peso']) : null,
            trim($_POST['pet_obs']) ?: null,
            (int) $_POST['pet_id'],
        ]);
        recado('Pet atualizado.');
        redirecionar(BASE_URL . '/admin/clientes.php?ver=' . (int) $_POST['cliente_id']);
    }

    if ($acao === 'novo_pet') {
        $clienteId = (int) $_POST['cliente_id'];
        $stmt = bd()->prepare(
            'INSERT INTO pets (cliente_id, nome, especie, raca, porte, peso_kg) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $clienteId,
            trim($_POST['pet_nome']),
            $_POST['pet_especie'] === 'gato' ? 'gato' : 'cachorro',
            trim($_POST['pet_raca']) ?: null,
            in_array($_POST['pet_porte'], ['pp','p','m','g','gg'], true) ? $_POST['pet_porte'] : 'm',
            ($_POST['pet_peso'] ?? '') !== '' ? (float) str_replace(',', '.', $_POST['pet_peso']) : null,
        ]);
        recado('Pet cadastrado.');
        redirecionar(BASE_URL . '/admin/clientes.php?ver=' . $clienteId);
    }
}

// ---------- consulta ----------
if ($busca !== '') {
    $stmt = bd()->prepare(
        'SELECT * FROM clientes WHERE nome LIKE ? OR whatsapp LIKE ? ORDER BY nome LIMIT 60'
    );
    $stmt->execute(['%' . $busca . '%', '%' . so_digitos($busca) . '%']);
} else {
    $stmt = bd()->query('SELECT * FROM clientes ORDER BY criado_em DESC LIMIT 60');
}
$clientes = $stmt->fetchAll();

$cliente = null;
$pets = [];
$historico = [];

if ($ver) {
    $stmt = bd()->prepare('SELECT * FROM clientes WHERE id = ?');
    $stmt->execute([$ver]);
    $cliente = $stmt->fetch() ?: null;

    if ($cliente) {
        $stmt = bd()->prepare('SELECT * FROM pets WHERE cliente_id = ? ORDER BY nome');
        $stmt->execute([$ver]);
        $pets = $stmt->fetchAll();

        $stmt = bd()->prepare(
            'SELECT a.*, p.nome AS pet_nome, s.nome AS servico_nome
             FROM agendamentos a
             JOIN pets p ON p.id = a.pet_id
             JOIN servicos s ON s.id = a.servico_id
             WHERE a.cliente_id = ?
             ORDER BY a.data DESC, a.hora_inicio DESC
             LIMIT 30'
        );
        $stmt->execute([$ver]);
        $historico = $stmt->fetchAll();
    }
}

require __DIR__ . '/../includes/cabecalho.php';
?>

<header class="pagina__topo">
  <div>
    <span class="rotulo">Cadastro</span>
    <h1>Clientes e pets</h1>
  </div>
</header>

<form class="filtros" method="get">
  <div class="campo">
    <label for="q">Buscar cliente</label>
    <input id="q" name="q" type="search" value="<?= e($busca) ?>" placeholder="nome ou telefone">
  </div>
  <button class="botao botao--roxo" type="submit">Buscar</button>
  <?php if ($busca): ?>
    <a class="botao botao--vazado" href="<?= BASE_URL ?>/admin/clientes.php">Limpar</a>
  <?php endif; ?>
</form>

<div class="colunas">

  <section class="bloco">
    <h2 class="bloco__titulo"><?= $busca ? 'Resultados' : 'Cadastrados recentemente' ?></h2>

    <?php if (!$clientes): ?>
      <p class="vazio">Nenhum cliente encontrado.</p>
    <?php else: ?>
      <ul class="lista">
        <?php foreach ($clientes as $c): ?>
          <li class="lista__item <?= $ver === (int) $c['id'] ? 'lista__item--ativo' : '' ?>">
            <div class="lista__quem">
              <strong><?= e($c['nome']) ?></strong>
              <span><?= e(formatar_telefone($c['whatsapp'])) ?></span>
            </div>
            <a class="botao botao--pequeno botao--vazado"
               href="<?= BASE_URL ?>/admin/clientes.php?ver=<?= (int) $c['id'] ?>">Ver ficha</a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <div class="pilha">
    <?php if (!$cliente): ?>
      <section class="bloco">
        <p class="vazio">Escolha um cliente na lista para ver a ficha completa.</p>
      </section>
    <?php else: ?>

      <section class="bloco">
        <h2 class="bloco__titulo">Ficha de <?= e($cliente['nome']) ?></h2>
        <form method="post" class="form-compacto">
          <?= csrf_campo() ?>
          <input type="hidden" name="acao" value="salvar_cliente">
          <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">

          <div class="campo">
            <label for="nome">Nome</label>
            <input id="nome" name="nome" type="text" required value="<?= e($cliente['nome']) ?>">
          </div>
          <div class="campo">
            <label for="whatsapp">WhatsApp</label>
            <input id="whatsapp" name="whatsapp" type="tel" required maxlength="15"
                   value="<?= e(formatar_telefone($cliente['whatsapp'])) ?>">
          </div>
          <div class="campo campo--largo">
            <label for="email">E-mail <i>(opcional)</i></label>
            <input id="email" name="email" type="email" value="<?= e($cliente['email']) ?>">
          </div>
          <div class="campo campo--largo">
            <label for="observacoes">Observações internas</label>
            <textarea id="observacoes" name="observacoes" rows="2"><?= e($cliente['observacoes']) ?></textarea>
          </div>

          <button class="botao botao--roxo botao--bloco" type="submit">Salvar cliente</button>
        </form>
      </section>

      <section class="bloco">
        <h2 class="bloco__titulo">Pets</h2>

        <?php foreach ($pets as $p): ?>
          <form method="post" class="form-compacto form-compacto--caixa">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="salvar_pet">
            <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">
            <input type="hidden" name="pet_id" value="<?= (int) $p['id'] ?>">

            <div class="campo">
              <label>Nome</label>
              <input name="pet_nome" type="text" required value="<?= e($p['nome']) ?>">
            </div>
            <div class="campo">
              <label>Espécie</label>
              <select name="pet_especie">
                <option value="cachorro" <?= $p['especie'] === 'cachorro' ? 'selected' : '' ?>>Cachorro</option>
                <option value="gato" <?= $p['especie'] === 'gato' ? 'selected' : '' ?>>Gato</option>
              </select>
            </div>
            <div class="campo">
              <label>Raça</label>
              <input name="pet_raca" type="text" value="<?= e($p['raca']) ?>">
            </div>
            <div class="campo">
              <label>Porte</label>
              <select name="pet_porte">
                <?php foreach (['pp','p','m','g','gg'] as $op): ?>
                  <option value="<?= $op ?>" <?= $p['porte'] === $op ? 'selected' : '' ?>>
                    <?= e(rotulo_porte($op)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="campo">
              <label>Peso (kg)</label>
              <input name="pet_peso" type="text" inputmode="decimal"
                     value="<?= (float) $p['peso_kg'] > 0 ? e(number_format((float) $p['peso_kg'], 1, ',', '')) : '' ?>">
            </div>
            <div class="campo campo--largo">
              <label>Observações</label>
              <textarea name="pet_obs" rows="2"><?= e($p['observacoes']) ?></textarea>
            </div>

            <button class="botao botao--pequeno botao--vazado" type="submit">Salvar <?= e($p['nome']) ?></button>
          </form>
        <?php endforeach; ?>

        <details class="recolhivel">
          <summary>+ Cadastrar outro pet</summary>
          <form method="post" class="form-compacto">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="novo_pet">
            <input type="hidden" name="cliente_id" value="<?= (int) $cliente['id'] ?>">

            <div class="campo">
              <label for="np-nome">Nome</label>
              <input id="np-nome" name="pet_nome" type="text" required>
            </div>
            <div class="campo">
              <label for="np-especie">Espécie</label>
              <select id="np-especie" name="pet_especie">
                <option value="cachorro">Cachorro</option>
                <option value="gato">Gato</option>
              </select>
            </div>
            <div class="campo">
              <label for="np-raca">Raça</label>
              <input id="np-raca" name="pet_raca" type="text">
            </div>
            <div class="campo">
              <label for="np-porte">Porte</label>
              <select id="np-porte" name="pet_porte">
                <?php foreach (['pp','p','m','g','gg'] as $op): ?>
                  <option value="<?= $op ?>" <?= $op === 'm' ? 'selected' : '' ?>><?= e(rotulo_porte($op)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="campo">
              <label for="np-peso">Peso (kg)</label>
              <input id="np-peso" name="pet_peso" type="text" inputmode="decimal">
            </div>

            <button class="botao botao--roxo botao--bloco" type="submit">Cadastrar pet</button>
          </form>
        </details>
      </section>

      <section class="bloco">
        <h2 class="bloco__titulo">Histórico de atendimentos</h2>
        <?php if (!$historico): ?>
          <p class="vazio">Esse cliente ainda não tem atendimentos.</p>
        <?php else: ?>
          <ul class="lista">
            <?php foreach ($historico as $h): ?>
              <li class="lista__item">
                <div class="lista__quando">
                  <strong><?= e(formatar_data($h['data'])) ?></strong>
                  <span><?= e(formatar_hora($h['hora_inicio'])) ?></span>
                </div>
                <div class="lista__quem">
                  <strong><?= e($h['pet_nome']) ?></strong>
                  <span><?= e($h['servico_nome']) ?></span>
                </div>
                <span class="etiqueta etiqueta--<?= e($h['status']) ?>"><?= e(rotulo_status($h['status'])) ?></span>
                <a class="botao botao--pequeno botao--vazado"
                   href="<?= BASE_URL ?>/admin/agendamento.php?id=<?= (int) $h['id'] ?>">Abrir</a>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>

    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
