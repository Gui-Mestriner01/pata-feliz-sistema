<?php
/** Serviços, duração e preço por porte */

require_once __DIR__ . '/../includes/funcoes.php';

$usuario = exigir_login();
$titulo  = 'Serviços';
$secao   = 'servicos';

$portes = ['pp','p','m','g','gg'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar') {
        $id = (int) $_POST['id'];

        $stmt = bd()->prepare(
            'UPDATE servicos SET nome = ?, descricao = ?, duracao_min = ?, especie = ?, ativo = ?, ordem = ?
             WHERE id = ?'
        );
        $stmt->execute([
            trim($_POST['nome']),
            trim($_POST['descricao']) ?: null,
            max(15, (int) $_POST['duracao_min']),
            in_array($_POST['especie'], ['ambos','cachorro','gato'], true) ? $_POST['especie'] : 'ambos',
            isset($_POST['ativo']) ? 1 : 0,
            (int) $_POST['ordem'],
            $id,
        ]);

        $precoStmt = bd()->prepare(
            'INSERT INTO precos (servico_id, porte, preco) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE preco = VALUES(preco)'
        );
        foreach ($portes as $p) {
            $valor = (float) str_replace(',', '.', $_POST['preco'][$p] ?? '0');
            $precoStmt->execute([$id, $p, $valor]);
        }

        recado('Serviço atualizado.');
        redirecionar(BASE_URL . '/admin/servicos.php');
    }

    if ($acao === 'novo') {
        $stmt = bd()->prepare(
            'INSERT INTO servicos (nome, descricao, duracao_min, especie, ordem) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($_POST['nome']),
            trim($_POST['descricao']) ?: null,
            max(15, (int) $_POST['duracao_min']),
            in_array($_POST['especie'], ['ambos','cachorro','gato'], true) ? $_POST['especie'] : 'ambos',
            (int) $_POST['ordem'],
        ]);
        recado('Serviço criado. Agora defina os preços por porte.');
        redirecionar(BASE_URL . '/admin/servicos.php');
    }
}

$servicos = bd()->query('SELECT * FROM servicos ORDER BY ordem, nome')->fetchAll();

$precos = [];
foreach (bd()->query('SELECT * FROM precos') as $linha) {
    $precos[(int) $linha['servico_id']][$linha['porte']] = (float) $linha['preco'];
}

require __DIR__ . '/../includes/cabecalho.php';
?>

<header class="pagina__topo">
  <div>
    <span class="rotulo">Catálogo</span>
    <h1>Serviços e preços</h1>
  </div>
</header>

<?php foreach ($servicos as $s): ?>
  <section class="bloco">
    <form method="post" class="form-compacto">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="salvar">
      <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">

      <div class="campo campo--largo">
        <label>Nome do serviço</label>
        <input name="nome" type="text" required value="<?= e($s['nome']) ?>">
      </div>

      <div class="campo campo--largo">
        <label>Descrição</label>
        <input name="descricao" type="text" value="<?= e($s['descricao']) ?>">
      </div>

      <div class="campo">
        <label>Duração (min)</label>
        <input name="duracao_min" type="number" min="15" step="15" required value="<?= (int) $s['duracao_min'] ?>">
      </div>

      <div class="campo">
        <label>Para</label>
        <select name="especie">
          <option value="ambos" <?= $s['especie'] === 'ambos' ? 'selected' : '' ?>>Cães e gatos</option>
          <option value="cachorro" <?= $s['especie'] === 'cachorro' ? 'selected' : '' ?>>Só cachorro</option>
          <option value="gato" <?= $s['especie'] === 'gato' ? 'selected' : '' ?>>Só gato</option>
        </select>
      </div>

      <div class="campo">
        <label>Ordem na lista</label>
        <input name="ordem" type="number" value="<?= (int) $s['ordem'] ?>">
      </div>

      <div class="campo">
        <label class="caixa-marcar">
          <input type="checkbox" name="ativo" value="1" <?= $s['ativo'] ? 'checked' : '' ?>>
          <span>Disponível para agendamento</span>
        </label>
      </div>

      <fieldset class="campo campo--largo">
        <legend>Preço por porte</legend>
        <div class="precos">
          <?php foreach ($portes as $p): ?>
            <label>
              <span><?= e(strtoupper($p)) ?></span>
              <input name="preco[<?= $p ?>]" type="text" inputmode="decimal"
                     value="<?= e(number_format($precos[(int) $s['id']][$p] ?? 0, 2, ',', '')) ?>">
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <button class="botao botao--roxo botao--bloco" type="submit">Salvar <?= e($s['nome']) ?></button>
    </form>
  </section>
<?php endforeach; ?>

<section class="bloco">
  <details class="recolhivel">
    <summary>+ Criar novo serviço</summary>
    <form method="post" class="form-compacto">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="novo">

      <div class="campo campo--largo">
        <label for="ns-nome">Nome</label>
        <input id="ns-nome" name="nome" type="text" required>
      </div>
      <div class="campo campo--largo">
        <label for="ns-desc">Descrição</label>
        <input id="ns-desc" name="descricao" type="text">
      </div>
      <div class="campo">
        <label for="ns-dur">Duração (min)</label>
        <input id="ns-dur" name="duracao_min" type="number" min="15" step="15" value="60" required>
      </div>
      <div class="campo">
        <label for="ns-esp">Para</label>
        <select id="ns-esp" name="especie">
          <option value="ambos">Cães e gatos</option>
          <option value="cachorro">Só cachorro</option>
          <option value="gato">Só gato</option>
        </select>
      </div>
      <div class="campo">
        <label for="ns-ordem">Ordem</label>
        <input id="ns-ordem" name="ordem" type="number" value="<?= count($servicos) + 1 ?>">
      </div>

      <button class="botao botao--lima botao--bloco" type="submit">Criar serviço</button>
    </form>
  </details>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
