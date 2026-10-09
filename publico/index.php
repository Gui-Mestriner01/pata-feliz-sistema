<?php
/**
 * Página inicial pública: apresenta o pet shop, os serviços e os preços,
 * e leva o cliente para o formulário de agendamento.
 */

require_once __DIR__ . '/../includes/funcoes.php';

// Serviços ativos com a faixa de preço (do menor ao maior porte).
$servicos = bd()->query(
    'SELECT s.id, s.nome, s.descricao, s.duracao_min, s.especie,
            MIN(p.preco) AS preco_min, MAX(p.preco) AS preco_max
       FROM servicos s
       LEFT JOIN precos p ON p.servico_id = s.id
      WHERE s.ativo = 1
      GROUP BY s.id, s.nome, s.descricao, s.duracao_min, s.especie
      ORDER BY s.ordem, s.nome'
)->fetchAll();

/** Ícone conforme o nome do serviço, com a patinha como padrão. */
function icone_servico(string $nome): string
{
    $n = mb_strtolower($nome);

    if (str_contains($n, 'unha')) {
        return '<path d="M8 3v8a4 4 0 0 0 8 0V3"/><path d="M12 15v6"/>';
    }
    if (str_contains($n, 'hidrat')) {
        return '<path d="M12 3s6 6.4 6 10.5A6 6 0 0 1 6 13.5C6 9.4 12 3 12 3Z"/>';
    }
    if (str_contains($n, 'tosa')) {
        return '<path d="M6 4v7a6 6 0 0 0 12 0V4"/><path d="M3 20h18"/><path d="M12 17v3"/>';
    }
    if (str_contains($n, 'banho')) {
        return '<path d="M4 11h16v3a6 6 0 0 1-6 6h-4a6 6 0 0 1-6-6v-3Z"/><path d="M12 11V5a2 2 0 0 1 4 0"/>';
    }
    if (str_contains($n, 'felina') || str_contains($n, 'gato')) {
        return '<path d="M5 10 4 4l5 3h6l5-3-1 6"/><path d="M4 10v3a8 8 0 0 0 16 0v-3"/><path d="M10 14h.01M14 14h.01"/>';
    }

    return '<ellipse cx="12" cy="16.5" rx="5.6" ry="4.5"/><ellipse cx="5.2" cy="9.8" rx="2.3" ry="3"/>'
         . '<ellipse cx="10" cy="6.8" rx="2.3" ry="3.2"/><ellipse cx="15.2" cy="7" rx="2.3" ry="3.2"/>'
         . '<ellipse cx="19.4" cy="10.4" rx="2.2" ry="2.9"/>';
}

/** Transforma '1,2,3,4,5,6' em 'Segunda a sábado'. */
function dias_de_funcionamento(string $lista): string
{
    $nomes = [1 => 'segunda', 2 => 'terça', 3 => 'quarta', 4 => 'quinta',
              5 => 'sexta', 6 => 'sábado', 7 => 'domingo'];

    $dias = array_values(array_filter(
        array_map('intval', explode(',', $lista)),
        fn($d) => isset($nomes[$d])
    ));
    sort($dias);

    if (!$dias) {
        return 'Consulte nossos horários';
    }
    if (count($dias) === 1) {
        return ucfirst($nomes[$dias[0]]);
    }

    // sequência sem furos vira "X a Y"
    if (($dias[count($dias) - 1] - $dias[0] + 1) === count($dias)) {
        return ucfirst($nomes[$dias[0]]) . ' a ' . $nomes[$dias[count($dias) - 1]];
    }

    $rotulos = array_map(fn($d) => $nomes[$d], $dias);
    $ultimo  = array_pop($rotulos);

    return ucfirst(implode(', ', $rotulos)) . ' e ' . $ultimo;
}

$abre  = formatar_hora(config('abre', '08:00'));
$fecha = formatar_hora(config('fecha', '18:00'));
$dias  = dias_de_funcionamento(config('dias_semana', '1,2,3,4,5,6'));

$whatsLink = 'https://wa.me/' . LOJA_WHATSAPP . '?text='
           . rawurlencode('Olá! Gostaria de saber mais sobre os serviços da ' . LOJA_NOME . '.');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(LOJA_NOME) ?> — banho, tosa e cuidado de verdade</title>
<meta name="description" content="Banho, tosa e hidratação para cães e gatos. Agende o horário do seu pet pelo site e receba a confirmação no WhatsApp.">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/publico.css">
</head>
<body>

<header class="topo topo--inicio">
  <div class="topo__interno">
    <a class="marca" href="<?= BASE_URL ?>/">
      <svg viewBox="0 0 24 24" aria-hidden="true"><ellipse cx="12" cy="16.5" rx="6.2" ry="5"/><ellipse cx="4.8" cy="9.4" rx="2.6" ry="3.4"/><ellipse cx="10" cy="6.2" rx="2.6" ry="3.6"/><ellipse cx="15.6" cy="6.5" rx="2.6" ry="3.6"/><ellipse cx="20" cy="10.2" rx="2.5" ry="3.2"/></svg>
      <span><strong>Pata Feliz</strong><small>Banho e tosa</small></span>
    </a>

    <nav class="topo__nav" aria-label="Seções do site">
      <a href="#servicos">Serviços</a>
      <a href="#como-funciona">Como funciona</a>
      <a href="#contato">Contato</a>
      <a class="botao botao--lima botao--pequeno" href="<?= BASE_URL ?>/publico/agendar.php">Agendar</a>
    </nav>
  </div>
</header>

<main>

  <!-- Chamada principal -------------------------------------------------- -->
  <section class="heroi">
    <div class="heroi__interno">
      <span class="rotulo rotulo--claro">Banho · Tosa · Hidratação</span>
      <h1>O dia de cuidado<br>que o seu pet merece</h1>
      <p class="heroi__texto">
        Cada pet tem o seu tempo e o seu jeito — e a gente respeita os dois.
        Escolha o serviço, veja os horários que estão <strong>realmente livres</strong>
        e peça o seu. A confirmação chega no seu WhatsApp.
      </p>

      <div class="heroi__acoes">
        <a class="botao botao--lima" href="<?= BASE_URL ?>/publico/agendar.php">
          <svg viewBox="0 0 24 24" class="botao__ico" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
          Agendar horário
        </a>
        <a class="botao botao--contorno" href="<?= e($whatsLink) ?>" target="_blank" rel="noopener">
          <svg viewBox="0 0 24 24" class="botao__ico" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-12.3 7.4L3 20.5l1.7-5.5A8.4 8.4 0 1 1 21 11.5Z"/></svg>
          Falar no WhatsApp
        </a>
      </div>

      <ul class="heroi__selos">
        <li><strong><?= e($dias) ?></strong><span><?= e($abre) ?> às <?= e($fecha) ?></span></li>
        <li><strong>Sala exclusiva</strong><span>para gatos</span></li>
        <li><strong>Sem fila de espera</strong><span>horário marcado</span></li>
      </ul>
    </div>
  </section>

  <!-- Serviços ----------------------------------------------------------- -->
  <section class="secao" id="servicos">
    <div class="secao__interno">
      <header class="titulo titulo--centro">
        <span class="rotulo">Nossos serviços</span>
        <h2>Escolha o cuidado do dia</h2>
        <p>O preço varia conforme o porte do pet. O valor exato aparece quando você agenda.</p>
      </header>

      <div class="cartoes">
        <?php foreach ($servicos as $s): ?>
          <article class="servico">
            <svg viewBox="0 0 24 24" class="servico__ico" aria-hidden="true"><?= icone_servico($s['nome']) ?></svg>
            <h3><?= e($s['nome']) ?></h3>

            <?php if ($s['descricao']): ?>
              <p class="servico__texto"><?= e($s['descricao']) ?></p>
            <?php endif; ?>

            <?php if ($s['especie'] !== 'ambos'): ?>
              <span class="etiqueta">Só para <?= $s['especie'] === 'gato' ? 'gatos' : 'cães' ?></span>
            <?php endif; ?>

            <dl class="servico__dados">
              <div>
                <dt>Duração</dt>
                <dd><?= (int) $s['duracao_min'] ?> min</dd>
              </div>
              <?php if ($s['preco_min'] !== null): ?>
                <div>
                  <dt>A partir de</dt>
                  <dd><?= e(formatar_preco((float) $s['preco_min'])) ?></dd>
                </div>
              <?php endif; ?>
            </dl>

            <a class="servico__link" href="<?= BASE_URL ?>/publico/agendar.php?servico=<?= (int) $s['id'] ?>">
              Agendar <?= e(mb_strtolower($s['nome'])) ?>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Como funciona ------------------------------------------------------ -->
  <section class="secao secao--areia" id="como-funciona">
    <div class="secao__interno">
      <header class="titulo titulo--centro">
        <span class="rotulo">Como funciona</span>
        <h2>Três passos e pronto</h2>
      </header>

      <ol class="passos">
        <li class="passo">
          <span class="passo__num">1</span>
          <h3>Você escolhe</h3>
          <p>Informe os dados do pet, o serviço e o dia. O site mostra só os horários livres de verdade — nada de disputa por vaga.</p>
        </li>
        <li class="passo">
          <span class="passo__num">2</span>
          <h3>A gente confere</h3>
          <p>Seu pedido chega na nossa agenda. Conferimos tudo e aprovamos — ou sugerimos um horário melhor, se precisar.</p>
        </li>
        <li class="passo">
          <span class="passo__num">3</span>
          <h3>Confirmamos no WhatsApp</h3>
          <p>Você recebe a confirmação com dia, horário e valor. Só aparecer com o pet no horário combinado.</p>
        </li>
      </ol>
    </div>
  </section>

  <!-- Contato ------------------------------------------------------------ -->
  <section class="secao" id="contato">
    <div class="secao__interno">
      <header class="titulo titulo--centro">
        <span class="rotulo">Onde estamos</span>
        <h2>Venha nos visitar</h2>
      </header>

      <div class="infos">
        <div class="info">
          <svg viewBox="0 0 24 24" class="info__ico" aria-hidden="true"><path d="M20 10c0 6-8 11-8 11s-8-5-8-11a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="2.8"/></svg>
          <h3>Endereço</h3>
          <p><?= e(LOJA_ENDERECO) ?></p>
        </div>
        <div class="info">
          <svg viewBox="0 0 24 24" class="info__ico" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5.5l4 2"/></svg>
          <h3>Funcionamento</h3>
          <p><?= e($dias) ?><br><?= e($abre) ?> às <?= e($fecha) ?></p>
        </div>
        <div class="info">
          <svg viewBox="0 0 24 24" class="info__ico" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-12.3 7.4L3 20.5l1.7-5.5A8.4 8.4 0 1 1 21 11.5Z"/></svg>
          <h3>WhatsApp</h3>
          <p>
            <a class="info__link" href="<?= e($whatsLink) ?>" target="_blank" rel="noopener">
              <?= e(formatar_telefone(substr(LOJA_WHATSAPP, 2))) ?>
            </a>
          </p>
        </div>
        <div class="info">
          <svg viewBox="0 0 24 24" class="info__ico" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m3.5 7 8.5 6 8.5-6"/></svg>
          <h3>E-mail</h3>
          <p><a class="info__link" href="mailto:<?= e(LOJA_EMAIL) ?>"><?= e(LOJA_EMAIL) ?></a></p>
        </div>
      </div>
    </div>
  </section>

  <!-- Chamada final ------------------------------------------------------ -->
  <section class="chamada">
    <div class="chamada__interno">
      <h2>Bora marcar o banho?</h2>
      <p>Leva menos de um minuto. A gente confirma pelo WhatsApp.</p>
      <a class="botao botao--lima" href="<?= BASE_URL ?>/publico/agendar.php">Agendar horário</a>
    </div>
  </section>

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

</body>
</html>
