<?php
/** Painel — visão do dia */

require_once __DIR__ . '/../includes/funcoes.php';

$usuario = exigir_login();
$titulo  = 'Início';
$secao   = 'inicio';

$hoje = date('Y-m-d');

// --- números do topo ---
$numeros = bd()->prepare(
    "SELECT
        (SELECT COUNT(*) FROM agendamentos WHERE status = 'pendente') AS pendentes,
        (SELECT COUNT(*) FROM agendamentos WHERE data = ? AND status = 'confirmado') AS hoje_confirmados,
        (SELECT COUNT(*) FROM agendamentos WHERE data = ? AND status = 'concluido') AS hoje_concluidos,
        (SELECT COUNT(*) FROM clientes) AS clientes"
);
$numeros->execute([$hoje, $hoje]);
$n = $numeros->fetch();

// --- pedidos esperando resposta ---
$sqlLista = "SELECT a.*, c.nome AS cliente_nome, c.whatsapp,
                    p.nome AS pet_nome, p.especie, p.porte,
                    s.nome AS servico_nome
             FROM agendamentos a
             JOIN clientes c ON c.id = a.cliente_id
             JOIN pets     p ON p.id = a.pet_id
             JOIN servicos s ON s.id = a.servico_id";

$listaPendentes = bd()->query(
    "$sqlLista WHERE a.status = 'pendente' ORDER BY a.criado_em ASC LIMIT 8"
)->fetchAll();

// --- agenda de hoje ---
$stmt = bd()->prepare(
    "$sqlLista WHERE a.data = ? AND a.status IN ('confirmado','concluido')
     ORDER BY a.hora_inicio"
);
$stmt->execute([$hoje]);
$agendaHoje = $stmt->fetchAll();

require __DIR__ . '/../includes/cabecalho.php';
?>

<header class="pagina__topo">
  <div>
    <span class="rotulo">Hoje · <?= e(dia_semana_extenso($hoje)) ?>, <?= e(formatar_data($hoje)) ?></span>
    <h1>Olá, <?= e(explode(' ', $usuario['nome'])[0]) ?></h1>
  </div>
  <a class="botao botao--roxo" href="<?= BASE_URL ?>/admin/agendamento-novo.php">+ Novo agendamento</a>
</header>

<section class="numeros">
  <article class="numero <?= $n['pendentes'] > 0 ? 'numero--atencao' : '' ?>">
    <span class="numero__valor"><?= (int) $n['pendentes'] ?></span>
    <span class="numero__rotulo">Aguardando aprovação</span>
  </article>
  <article class="numero">
    <span class="numero__valor"><?= (int) $n['hoje_confirmados'] ?></span>
    <span class="numero__rotulo">Confirmados hoje</span>
  </article>
  <article class="numero">
    <span class="numero__valor"><?= (int) $n['hoje_concluidos'] ?></span>
    <span class="numero__rotulo">Concluídos hoje</span>
  </article>
  <article class="numero">
    <span class="numero__valor"><?= (int) $n['clientes'] ?></span>
    <span class="numero__rotulo">Clientes cadastrados</span>
  </article>
</section>

<section class="bloco">
  <header class="bloco__topo">
    <h2>Pedidos esperando resposta</h2>
    <a href="<?= BASE_URL ?>/admin/agendamentos.php?status=pendente">Ver todos →</a>
  </header>

  <?php if (!$listaPendentes): ?>
    <p class="vazio">Nenhum pedido pendente. Agenda em dia 🎉</p>
  <?php else: ?>
    <ul class="lista">
      <?php foreach ($listaPendentes as $ag): ?>
        <li class="lista__item">
          <div class="lista__quando">
            <strong><?= e(formatar_data($ag['data'])) ?></strong>
            <span><?= e(formatar_hora($ag['hora_inicio'])) ?></span>
          </div>
          <div class="lista__quem">
            <strong><?= e($ag['pet_nome']) ?></strong>
            <span><?= e($ag['servico_nome']) ?> · <?= e($ag['cliente_nome']) ?></span>
          </div>
          <a class="botao botao--pequeno botao--roxo"
             href="<?= BASE_URL ?>/admin/agendamento.php?id=<?= (int) $ag['id'] ?>">Analisar</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="bloco">
  <header class="bloco__topo">
    <h2>Agenda de hoje</h2>
    <a href="<?= BASE_URL ?>/admin/agendamentos.php?data=<?= $hoje ?>">Ver o dia →</a>
  </header>

  <?php if (!$agendaHoje): ?>
    <p class="vazio">Nenhum atendimento confirmado para hoje.</p>
  <?php else: ?>
    <ul class="lista">
      <?php foreach ($agendaHoje as $ag): ?>
        <li class="lista__item">
          <div class="lista__quando">
            <strong><?= e(formatar_hora($ag['hora_inicio'])) ?></strong>
            <span>às <?= e(formatar_hora($ag['hora_fim'])) ?></span>
          </div>
          <div class="lista__quem">
            <strong><?= e($ag['pet_nome']) ?></strong>
            <span><?= e($ag['servico_nome']) ?> · <?= e($ag['cliente_nome']) ?></span>
          </div>
          <span class="etiqueta etiqueta--<?= e($ag['status']) ?>"><?= e(rotulo_status($ag['status'])) ?></span>
          <a class="botao botao--pequeno botao--vazado"
             href="<?= BASE_URL ?>/admin/agendamento.php?id=<?= (int) $ag['id'] ?>">Abrir</a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
