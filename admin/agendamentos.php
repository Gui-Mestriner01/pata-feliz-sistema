<?php
/** Lista de agendamentos com filtros */

require_once __DIR__ . '/../includes/funcoes.php';

$usuario = exigir_login();
$titulo  = 'Agendamentos';
$secao   = 'agendamentos';

$status  = $_GET['status'] ?? '';
$data    = $_GET['data'] ?? '';
$busca   = trim($_GET['q'] ?? '');
$pagina  = max(1, (int) ($_GET['p'] ?? 1));
$porPagina = 20;

$condicoes = [];
$valores   = [];

$statusValidos = ['pendente','confirmado','remarcar','recusado','concluido','cancelado'];
if (in_array($status, $statusValidos, true)) {
    $condicoes[] = 'a.status = ?';
    $valores[]   = $status;
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    $condicoes[] = 'a.data = ?';
    $valores[]   = $data;
}
if ($busca !== '') {
    $condicoes[] = '(c.nome LIKE ? OR p.nome LIKE ? OR c.whatsapp LIKE ?)';
    $curinga = '%' . $busca . '%';
    array_push($valores, $curinga, $curinga, '%' . so_digitos($busca) . '%');
}

$onde = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

$base = "FROM agendamentos a
         JOIN clientes c ON c.id = a.cliente_id
         JOIN pets     p ON p.id = a.pet_id
         JOIN servicos s ON s.id = a.servico_id
         $onde";

$stmt = bd()->prepare("SELECT COUNT(*) $base");
$stmt->execute($valores);
$total = (int) $stmt->fetchColumn();
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina  = min($pagina, $paginas);

$sql = "SELECT a.*, c.nome AS cliente_nome, c.whatsapp,
               p.nome AS pet_nome, p.especie, p.porte,
               s.nome AS servico_nome
        $base
        ORDER BY a.data DESC, a.hora_inicio DESC
        LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina);

$stmt = bd()->prepare($sql);
$stmt->execute($valores);
$lista = $stmt->fetchAll();

/** Mantém os filtros ao trocar de página. */
function link_pagina(int $p): string
{
    $parametros = array_merge($_GET, ['p' => $p]);
    return '?' . http_build_query($parametros);
}

require __DIR__ . '/../includes/cabecalho.php';
?>

<header class="pagina__topo">
  <div>
    <span class="rotulo"><?= $total ?> registro(s)</span>
    <h1>Agendamentos</h1>
  </div>
  <a class="botao botao--roxo" href="<?= BASE_URL ?>/admin/agendamento-novo.php">+ Novo agendamento</a>
</header>

<form class="filtros" method="get">
  <div class="campo">
    <label for="f-status">Status</label>
    <select id="f-status" name="status">
      <option value="">Todos</option>
      <?php foreach ($statusValidos as $s): ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>>
          <?= e(rotulo_status($s)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="campo">
    <label for="f-data">Dia</label>
    <input id="f-data" name="data" type="date" value="<?= e($data) ?>">
  </div>

  <div class="campo">
    <label for="f-q">Buscar</label>
    <input id="f-q" name="q" type="search" value="<?= e($busca) ?>" placeholder="tutor, pet ou telefone">
  </div>

  <button class="botao botao--roxo" type="submit">Filtrar</button>
  <a class="botao botao--vazado" href="<?= BASE_URL ?>/admin/agendamentos.php">Limpar</a>
</form>

<?php if (!$lista): ?>
  <p class="vazio">Nenhum agendamento com esses filtros.</p>
<?php else: ?>
  <div class="tabela-rolagem">
    <table class="tabela">
      <thead>
        <tr>
          <th>Quando</th>
          <th>Pet</th>
          <th>Tutor</th>
          <th>Serviço</th>
          <th>Status</th>
          <th><span class="oculto">Ações</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lista as $ag): ?>
          <tr>
            <td data-rotulo="Quando">
              <strong><?= e(formatar_data($ag['data'])) ?></strong><br>
              <small><?= e(formatar_hora($ag['hora_inicio'])) ?>–<?= e(formatar_hora($ag['hora_fim'])) ?></small>
            </td>
            <td data-rotulo="Pet">
              <?= e($ag['pet_nome']) ?><br>
              <small><?= $ag['especie'] === 'gato' ? 'Gato' : 'Cachorro' ?> · <?= e(strtoupper($ag['porte'])) ?></small>
            </td>
            <td data-rotulo="Tutor">
              <?= e($ag['cliente_nome']) ?><br>
              <small><?= e(formatar_telefone($ag['whatsapp'])) ?></small>
            </td>
            <td data-rotulo="Serviço"><?= e($ag['servico_nome']) ?></td>
            <td data-rotulo="Status">
              <span class="etiqueta etiqueta--<?= e($ag['status']) ?>"><?= e(rotulo_status($ag['status'])) ?></span>
            </td>
            <td data-rotulo="">
              <a class="botao botao--pequeno botao--vazado"
                 href="<?= BASE_URL ?>/admin/agendamento.php?id=<?= (int) $ag['id'] ?>">Abrir</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($paginas > 1): ?>
    <nav class="paginacao" aria-label="Páginas">
      <?php if ($pagina > 1): ?>
        <a href="<?= e(link_pagina($pagina - 1)) ?>">← Anterior</a>
      <?php endif; ?>
      <span>Página <?= $pagina ?> de <?= $paginas ?></span>
      <?php if ($pagina < $paginas): ?>
        <a href="<?= e(link_pagina($pagina + 1)) ?>">Próxima →</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
