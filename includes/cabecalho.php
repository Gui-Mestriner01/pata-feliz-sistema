<?php
/**
 * Cabeçalho do painel. Antes de incluir, defina:
 *   $usuario  — resultado de exigir_login()
 *   $titulo   — título da página
 *   $secao    — item do menu que fica ativo
 */

$titulo = $titulo ?? 'Painel';
$secao  = $secao ?? '';

$pendentes = (int) bd()->query(
    "SELECT COUNT(*) FROM agendamentos WHERE status = 'pendente'"
)->fetchColumn();

$itens = [
    'inicio'       => ['Início',        'index.php'],
    'agendamentos' => ['Agendamentos',  'agendamentos.php'],
    'clientes'     => ['Clientes e pets', 'clientes.php'],
    'servicos'     => ['Serviços',      'servicos.php'],
    'agenda'       => ['Agenda',        'configuracoes.php'],
];

$recado = recado();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> — Painel <?= e(LOJA_NOME) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body>

<header class="barra-topo">
  <button class="abrir-menu" type="button" id="abrirMenu" aria-label="Abrir menu" aria-expanded="false">
    <span></span><span></span><span></span>
  </button>

  <a class="barra-topo__marca" href="<?= BASE_URL ?>/admin/index.php">
    <svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="16.5" rx="6.2" ry="5"/><ellipse cx="4.8" cy="9.4" rx="2.6" ry="3.4"/><ellipse cx="10" cy="6.2" rx="2.6" ry="3.6"/><ellipse cx="15.6" cy="6.5" rx="2.6" ry="3.6"/><ellipse cx="20" cy="10.2" rx="2.5" ry="3.2"/></svg>
    <span>Pata Feliz</span>
  </a>

  <div class="barra-topo__usuario">
    <span><?= e($usuario['nome']) ?></span>
    <a href="<?= BASE_URL ?>/admin/logout.php">Sair</a>
  </div>
</header>

<div class="moldura">

  <nav class="lateral" id="menuLateral" aria-label="Menu do painel">
    <?php foreach ($itens as $chave => [$rotulo, $arquivo]): ?>
      <a href="<?= BASE_URL ?>/admin/<?= $arquivo ?>" class="<?= $secao === $chave ? 'ativo' : '' ?>">
        <?= e($rotulo) ?>
        <?php if ($chave === 'agendamentos' && $pendentes > 0): ?>
          <span class="selo-contador"><?= $pendentes ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>

    <a href="<?= BASE_URL ?>/publico/agendar.php" target="_blank" class="lateral__externo">
      Ver página pública ↗
    </a>
  </nav>

  <main class="conteudo">
    <?php if ($recado): ?>
      <p class="alerta alerta--<?= $recado['tipo'] === 'ruim' ? 'ruim' : 'ok' ?>">
        <?= e($recado['texto']) ?>
      </p>
    <?php endif; ?>
