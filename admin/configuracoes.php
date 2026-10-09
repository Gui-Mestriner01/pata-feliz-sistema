<?php
/** Regras da agenda, bloqueios e usuários do painel */

require_once __DIR__ . '/../includes/funcoes.php';

$usuario = exigir_login();
$titulo  = 'Agenda';
$secao   = 'agenda';

$diasNomes = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta',
              5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'salvar_regras') {
        $dias = array_filter(($_POST['dias'] ?? []), fn($d) => in_array((int) $d, range(1, 7), true));

        $valores = [
            'abre'                  => $_POST['abre'] ?: '08:00',
            'fecha'                 => $_POST['fecha'] ?: '18:00',
            'dias_semana'           => implode(',', $dias) ?: '1,2,3,4,5,6',
            'intervalo_min'         => (string) max(15, (int) $_POST['intervalo_min']),
            'capacidade_simultanea' => (string) max(1, (int) $_POST['capacidade_simultanea']),
            'antecedencia_horas'    => (string) max(0, (int) $_POST['antecedencia_horas']),
            'dias_futuros'          => (string) max(1, (int) $_POST['dias_futuros']),
        ];

        $stmt = bd()->prepare(
            'INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        foreach ($valores as $chave => $valor) {
            $stmt->execute([$chave, $valor]);
        }

        recado('Regras da agenda atualizadas.');
        redirecionar(BASE_URL . '/admin/configuracoes.php');
    }

    if ($acao === 'novo_bloqueio') {
        $data   = $_POST['bl_data'] ?? '';
        $inicio = $_POST['bl_inicio'] ?: null;
        $fim    = $_POST['bl_fim'] ?: null;
        $motivo = trim($_POST['bl_motivo'] ?? '');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) && $motivo !== '') {
            // dia inteiro quando não informam horário
            if ($inicio && !$fim) { $fim = '23:59'; }
            $stmt = bd()->prepare(
                'INSERT INTO bloqueios (data, hora_inicio, hora_fim, motivo) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$data, $inicio, $fim, $motivo]);
            recado('Bloqueio criado.');
        } else {
            recado('Informe a data e o motivo do bloqueio.', 'ruim');
        }
        redirecionar(BASE_URL . '/admin/configuracoes.php');
    }

    if ($acao === 'remover_bloqueio') {
        $stmt = bd()->prepare('DELETE FROM bloqueios WHERE id = ?');
        $stmt->execute([(int) $_POST['bloqueio_id']]);
        recado('Bloqueio removido.');
        redirecionar(BASE_URL . '/admin/configuracoes.php');
    }

    if ($acao === 'nova_senha') {
        $atual = $_POST['senha_atual'] ?? '';
        $nova  = $_POST['senha_nova'] ?? '';

        $stmt = bd()->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
        $stmt->execute([$usuario['id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($atual, $hash)) {
            recado('A senha atual não confere.', 'ruim');
        } elseif (strlen($nova) < 8) {
            recado('A nova senha precisa ter pelo menos 8 caracteres.', 'ruim');
        } else {
            $stmt = bd()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($nova, PASSWORD_BCRYPT, ['cost' => 12]), $usuario['id']]);
            recado('Senha alterada.');
        }
        redirecionar(BASE_URL . '/admin/configuracoes.php');
    }

    if ($acao === 'novo_usuario' && $usuario['papel'] === 'admin') {
        $nome  = trim($_POST['u_nome'] ?? '');
        $email = trim($_POST['u_email'] ?? '');
        $senha = $_POST['u_senha'] ?? '';
        $papel = ($_POST['u_papel'] ?? 'atendente') === 'admin' ? 'admin' : 'atendente';

        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8) {
            recado('Preencha nome, e-mail válido e senha com 8+ caracteres.', 'ruim');
        } else {
            try {
                $stmt = bd()->prepare(
                    'INSERT INTO usuarios (nome, email, senha_hash, papel) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]), $papel]);
                recado('Usuário criado.');
            } catch (PDOException $e) {
                recado('Já existe um usuário com esse e-mail.', 'ruim');
            }
        }
        redirecionar(BASE_URL . '/admin/configuracoes.php');
    }
}

$diasAtivos = array_map('intval', explode(',', config('dias_semana', '1,2,3,4,5,6')));

$bloqueios = bd()->query(
    'SELECT * FROM bloqueios WHERE data >= CURDATE() ORDER BY data, hora_inicio LIMIT 40'
)->fetchAll();

$usuarios = bd()->query('SELECT id, nome, email, papel, ativo FROM usuarios ORDER BY nome')->fetchAll();

require __DIR__ . '/../includes/cabecalho.php';
?>

<header class="pagina__topo">
  <div>
    <span class="rotulo">Configuração</span>
    <h1>Regras da agenda</h1>
  </div>
</header>

<div class="colunas">

  <section class="bloco">
    <h2 class="bloco__titulo">Funcionamento</h2>
    <p class="bloco__apoio">
      É daqui que sai a lista de horários livres mostrada ao cliente.
    </p>

    <form method="post" class="form-compacto">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="salvar_regras">

      <div class="campo">
        <label for="abre">Abre às</label>
        <input id="abre" name="abre" type="time" required value="<?= e(config('abre', '08:00')) ?>">
      </div>
      <div class="campo">
        <label for="fecha">Fecha às</label>
        <input id="fecha" name="fecha" type="time" required value="<?= e(config('fecha', '18:00')) ?>">
      </div>

      <fieldset class="campo campo--largo">
        <legend>Dias de atendimento</legend>
        <div class="dias">
          <?php foreach ($diasNomes as $num => $nome): ?>
            <label class="caixa-marcar">
              <input type="checkbox" name="dias[]" value="<?= $num ?>"
                     <?= in_array($num, $diasAtivos, true) ? 'checked' : '' ?>>
              <span><?= e($nome) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <div class="campo">
        <label for="intervalo_min">Horários de quantos em quantos minutos</label>
        <input id="intervalo_min" name="intervalo_min" type="number" min="15" step="15"
               value="<?= e(config('intervalo_min', '30')) ?>">
      </div>

      <div class="campo">
        <label for="capacidade_simultanea">Pets atendidos ao mesmo tempo</label>
        <input id="capacidade_simultanea" name="capacidade_simultanea" type="number" min="1"
               value="<?= e(config('capacidade_simultanea', '2')) ?>">
      </div>

      <div class="campo">
        <label for="antecedencia_horas">Antecedência mínima (horas)</label>
        <input id="antecedencia_horas" name="antecedencia_horas" type="number" min="0"
               value="<?= e(config('antecedencia_horas', '2')) ?>">
      </div>

      <div class="campo">
        <label for="dias_futuros">Aceita pedido até quantos dias à frente</label>
        <input id="dias_futuros" name="dias_futuros" type="number" min="1"
               value="<?= e(config('dias_futuros', '60')) ?>">
      </div>

      <button class="botao botao--roxo botao--bloco" type="submit">Salvar regras</button>
    </form>
  </section>

  <div class="pilha">

    <section class="bloco">
      <h2 class="bloco__titulo">Bloqueios da agenda</h2>
      <p class="bloco__apoio">
        Feriado, almoço, manutenção. Sem horário, bloqueia o dia inteiro.
      </p>

      <?php if ($bloqueios): ?>
        <ul class="lista">
          <?php foreach ($bloqueios as $b): ?>
            <li class="lista__item">
              <div class="lista__quando">
                <strong><?= e(formatar_data($b['data'])) ?></strong>
                <span>
                  <?= $b['hora_inicio']
                        ? e(formatar_hora($b['hora_inicio'])) . '–' . e(formatar_hora($b['hora_fim']))
                        : 'dia inteiro' ?>
                </span>
              </div>
              <div class="lista__quem"><strong><?= e($b['motivo']) ?></strong></div>
              <form method="post">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="remover_bloqueio">
                <input type="hidden" name="bloqueio_id" value="<?= (int) $b['id'] ?>">
                <button class="botao botao--pequeno botao--vazado" type="submit">Remover</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="vazio">Nenhum bloqueio futuro.</p>
      <?php endif; ?>

      <details class="recolhivel">
        <summary>+ Novo bloqueio</summary>
        <form method="post" class="form-compacto">
          <?= csrf_campo() ?>
          <input type="hidden" name="acao" value="novo_bloqueio">

          <div class="campo">
            <label for="bl_data">Data</label>
            <input id="bl_data" name="bl_data" type="date" required min="<?= date('Y-m-d') ?>">
          </div>
          <div class="campo">
            <label for="bl_motivo">Motivo</label>
            <input id="bl_motivo" name="bl_motivo" type="text" required placeholder="Ex.: feriado">
          </div>
          <div class="campo">
            <label for="bl_inicio">Das <i>(vazio = dia todo)</i></label>
            <input id="bl_inicio" name="bl_inicio" type="time">
          </div>
          <div class="campo">
            <label for="bl_fim">Até</label>
            <input id="bl_fim" name="bl_fim" type="time">
          </div>

          <button class="botao botao--roxo botao--bloco" type="submit">Criar bloqueio</button>
        </form>
      </details>
    </section>

    <section class="bloco">
      <h2 class="bloco__titulo">Minha senha</h2>
      <form method="post" class="form-compacto">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="nova_senha">
        <div class="campo">
          <label for="senha_atual">Senha atual</label>
          <input id="senha_atual" name="senha_atual" type="password" required autocomplete="current-password">
        </div>
        <div class="campo">
          <label for="senha_nova">Nova senha</label>
          <input id="senha_nova" name="senha_nova" type="password" required minlength="8" autocomplete="new-password">
        </div>
        <button class="botao botao--roxo botao--bloco" type="submit">Trocar senha</button>
      </form>
    </section>

    <?php if ($usuario['papel'] === 'admin'): ?>
      <section class="bloco">
        <h2 class="bloco__titulo">Equipe com acesso</h2>
        <ul class="lista">
          <?php foreach ($usuarios as $u): ?>
            <li class="lista__item">
              <div class="lista__quem">
                <strong><?= e($u['nome']) ?></strong>
                <span><?= e($u['email']) ?></span>
              </div>
              <span class="etiqueta"><?= $u['papel'] === 'admin' ? 'Administrador' : 'Atendente' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <details class="recolhivel">
          <summary>+ Novo acesso</summary>
          <form method="post" class="form-compacto">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="novo_usuario">
            <div class="campo">
              <label for="u_nome">Nome</label>
              <input id="u_nome" name="u_nome" type="text" required>
            </div>
            <div class="campo">
              <label for="u_email">E-mail</label>
              <input id="u_email" name="u_email" type="email" required>
            </div>
            <div class="campo">
              <label for="u_senha">Senha inicial</label>
              <input id="u_senha" name="u_senha" type="password" required minlength="8">
            </div>
            <div class="campo">
              <label for="u_papel">Permissão</label>
              <select id="u_papel" name="u_papel">
                <option value="atendente">Atendente</option>
                <option value="admin">Administrador</option>
              </select>
            </div>
            <button class="botao botao--lima botao--bloco" type="submit">Criar acesso</button>
          </form>
        </details>
      </section>
    <?php endif; ?>

  </div>
</div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
