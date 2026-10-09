<?php
/**
 * Detalhe do agendamento: aprovar, recusar, sugerir remarcação,
 * concluir ou cancelar — e enviar a mensagem ao cliente.
 */

require_once __DIR__ . '/../includes/funcoes.php';

$usuario = exigir_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$ag = $id ? agendamento_completo($id) : null;

if (!$ag) {
    http_response_code(404);
    $titulo = 'Agendamento não encontrado';
    $secao  = 'agendamentos';
    require __DIR__ . '/../includes/cabecalho.php';
    echo '<p class="vazio">Esse agendamento não existe mais.</p>';
    require __DIR__ . '/../includes/rodape.php';
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = $_POST['acao'] ?? '';

    try {
        switch ($acao) {

            // ---------- aprovar ----------
            case 'confirmar':
                $conflito = motivo_indisponivel(
                    $ag['data'], $ag['hora_inicio'], (int) $ag['duracao_min'], $ag['id']
                );
                // só barra se o problema for ocupação; expediente/antecedência
                // o pet shop pode decidir atender assim mesmo
                if ($conflito === 'Esse horário já está ocupado.') {
                    throw new RuntimeException(
                        'A agenda já está cheia nesse horário. Sugira uma remarcação ou libere outro atendimento.'
                    );
                }
                $stmt = bd()->prepare(
                    "UPDATE agendamentos
                        SET status = 'confirmado', motivo = NULL,
                            sugestao_data = NULL, sugestao_hora = NULL, usuario_id = ?
                      WHERE id = ?"
                );
                $stmt->execute([$usuario['id'], $ag['id']]);
                recado('Agendamento confirmado. Agora é só avisar o cliente.');
                break;

            // ---------- recusar ----------
            case 'recusar':
                $motivo = trim($_POST['motivo'] ?? '');
                if ($motivo === '') {
                    throw new RuntimeException('Escreva o motivo da recusa — ele vai na mensagem ao cliente.');
                }
                $stmt = bd()->prepare(
                    "UPDATE agendamentos
                        SET status = 'recusado', motivo = ?,
                            sugestao_data = NULL, sugestao_hora = NULL, usuario_id = ?
                      WHERE id = ?"
                );
                $stmt->execute([$motivo, $usuario['id'], $ag['id']]);
                recado('Agendamento recusado. Envie a mensagem ao cliente.');
                break;

            // ---------- sugerir outro horário ----------
            case 'remarcar':
                $novaData = $_POST['sugestao_data'] ?? '';
                $novaHora = $_POST['sugestao_hora'] ?? '';
                $motivo   = trim($_POST['motivo'] ?? '');

                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $novaData)
                    || !preg_match('/^\d{2}:\d{2}$/', $novaHora)) {
                    throw new RuntimeException('Escolha a nova data e o novo horário.');
                }

                $impedimento = motivo_indisponivel(
                    $novaData, $novaHora . ':00', (int) $ag['duracao_min'], $ag['id']
                );
                if ($impedimento !== null) {
                    throw new RuntimeException('Não dá para sugerir esse horário: ' . $impedimento);
                }

                $stmt = bd()->prepare(
                    "UPDATE agendamentos
                        SET status = 'remarcar', sugestao_data = ?, sugestao_hora = ?,
                            motivo = ?, usuario_id = ?
                      WHERE id = ?"
                );
                $stmt->execute([$novaData, $novaHora . ':00', $motivo ?: null, $usuario['id'], $ag['id']]);
                recado('Sugestão registrada. Mande a proposta ao cliente pelo WhatsApp.');
                break;

            // ---------- cliente aceitou a sugestão ----------
            case 'aplicar_sugestao':
                if (!$ag['sugestao_data'] || !$ag['sugestao_hora']) {
                    throw new RuntimeException('Não há sugestão registrada.');
                }
                $impedimento = motivo_indisponivel(
                    $ag['sugestao_data'], $ag['sugestao_hora'], (int) $ag['duracao_min'], $ag['id']
                );
                if ($impedimento === 'Esse horário já está ocupado.') {
                    throw new RuntimeException('Esse horário foi ocupado enquanto isso. Sugira outro.');
                }
                $novoFim = somar_minutos($ag['sugestao_hora'], (int) $ag['duracao_min']);
                $stmt = bd()->prepare(
                    "UPDATE agendamentos
                        SET data = sugestao_data, hora_inicio = sugestao_hora, hora_fim = ?,
                            status = 'confirmado', sugestao_data = NULL, sugestao_hora = NULL,
                            motivo = NULL, usuario_id = ?
                      WHERE id = ?"
                );
                $stmt->execute([$novoFim, $usuario['id'], $ag['id']]);
                recado('Remarcado e confirmado no novo horário.');
                break;

            // ---------- concluir / cancelar / voltar ----------
            case 'concluir':
            case 'cancelar':
            case 'reabrir':
                $novoStatus = ['concluir' => 'concluido', 'cancelar' => 'cancelado', 'reabrir' => 'pendente'][$acao];
                $stmt = bd()->prepare('UPDATE agendamentos SET status = ?, usuario_id = ? WHERE id = ?');
                $stmt->execute([$novoStatus, $usuario['id'], $ag['id']]);
                recado('Status atualizado para ' . rotulo_status($novoStatus) . '.');
                break;

            // ---------- marcar que o cliente já foi avisado ----------
            case 'marcar_avisado':
                $stmt = bd()->prepare('UPDATE agendamentos SET avisado_em = NOW() WHERE id = ?');
                $stmt->execute([$ag['id']]);
                recado('Marcado como avisado.');
                break;

            default:
                throw new RuntimeException('Ação desconhecida.');
        }

        redirecionar(BASE_URL . '/admin/agendamento.php?id=' . $ag['id']);

    } catch (RuntimeException $e) {
        $erro = $e->getMessage();
    }
}

// recarrega com os dados já atualizados
$ag      = agendamento_completo($id);
$titulo  = 'Agendamento #' . $ag['id'];
$secao   = 'agendamentos';

// horários livres para sugerir uma remarcação
$dataSugestao = $_POST['sugestao_data'] ?? $ag['data'];
$livres = horarios_livres($dataSugestao, (int) $ag['duracao_min'], $ag['id']);

require __DIR__ . '/../includes/cabecalho.php';
?>

<header class="pagina__topo">
  <div>
    <span class="rotulo">Agendamento #<?= (int) $ag['id'] ?></span>
    <h1><?= e($ag['pet_nome']) ?> · <?= e($ag['servico_nome']) ?></h1>
  </div>
  <span class="etiqueta etiqueta--grande etiqueta--<?= e($ag['status']) ?>">
    <?= e(rotulo_status($ag['status'])) ?>
  </span>
</header>

<?php if ($erro): ?>
  <p class="alerta alerta--ruim"><?= e($erro) ?></p>
<?php endif; ?>

<div class="colunas">

  <!-- ================= dados ================= -->
  <section class="bloco">
    <h2 class="bloco__titulo">Dados do atendimento</h2>

    <dl class="ficha">
      <div><dt>Quando</dt><dd>
        <?= e(dia_semana_extenso($ag['data'])) ?>, <?= e(formatar_data($ag['data'])) ?>
        das <?= e(formatar_hora($ag['hora_inicio'])) ?> às <?= e(formatar_hora($ag['hora_fim'])) ?>
      </dd></div>
      <div><dt>Serviço</dt><dd><?= e($ag['servico_nome']) ?> (<?= (int) $ag['duracao_min'] ?> min)</dd></div>
      <div><dt>Valor</dt><dd><?= e(formatar_preco($ag['preco'] !== null ? (float) $ag['preco'] : null)) ?></dd></div>
      <div><dt>Tutor</dt><dd><?= e($ag['cliente_nome']) ?></dd></div>
      <div><dt>WhatsApp</dt><dd>
        <a href="https://wa.me/55<?= e(so_digitos($ag['whatsapp'])) ?>" target="_blank">
          <?= e(formatar_telefone($ag['whatsapp'])) ?>
        </a>
      </dd></div>
      <div><dt>Pet</dt><dd>
        <?= e($ag['pet_nome']) ?> ·
        <?= $ag['especie'] === 'gato' ? 'Gato' : 'Cachorro' ?>
        <?= $ag['raca'] ? ' · ' . e($ag['raca']) : '' ?> ·
        <?= e(rotulo_porte($ag['porte'])) ?>
        <?= (float) $ag['peso_kg'] > 0 ? ' · ' . e(number_format((float) $ag['peso_kg'], 1, ',', '')) . ' kg' : '' ?>
      </dd></div>
      <?php if ($ag['observacoes']): ?>
        <div><dt>Observação do cliente</dt><dd><?= nl2br(e($ag['observacoes'])) ?></dd></div>
      <?php endif; ?>
      <?php if ($ag['motivo']): ?>
        <div><dt>Motivo registrado</dt><dd><?= e($ag['motivo']) ?></dd></div>
      <?php endif; ?>
      <?php if ($ag['sugestao_data']): ?>
        <div><dt>Sugestão enviada</dt><dd>
          <?= e(formatar_data($ag['sugestao_data'])) ?> às <?= e(formatar_hora($ag['sugestao_hora'])) ?>
        </dd></div>
      <?php endif; ?>
      <div><dt>Pedido feito em</dt><dd><?= e(date('d/m/Y H:i', strtotime($ag['criado_em']))) ?></dd></div>
      <div><dt>Cliente avisado</dt><dd>
        <?= $ag['avisado_em'] ? e(date('d/m/Y H:i', strtotime($ag['avisado_em']))) : 'ainda não' ?>
      </dd></div>
    </dl>
  </section>

  <!-- ================= ações ================= -->
  <div class="pilha">

    <?php if (in_array($ag['status'], ['pendente', 'remarcar'], true)): ?>
      <section class="bloco">
        <h2 class="bloco__titulo">Decisão</h2>

        <form method="post" class="acoes-linha">
          <?= csrf_campo() ?>
          <input type="hidden" name="id" value="<?= (int) $ag['id'] ?>">
          <button class="botao botao--lima" name="acao" value="confirmar" type="submit">
            Aprovar no horário pedido
          </button>
        </form>

        <?php if ($ag['status'] === 'remarcar' && $ag['sugestao_data']): ?>
          <form method="post" class="acoes-linha">
            <?= csrf_campo() ?>
            <input type="hidden" name="id" value="<?= (int) $ag['id'] ?>">
            <button class="botao botao--roxo" name="acao" value="aplicar_sugestao" type="submit">
              O cliente aceitou — mover para
              <?= e(formatar_data($ag['sugestao_data'])) ?> às <?= e(formatar_hora($ag['sugestao_hora'])) ?>
            </button>
          </form>
        <?php endif; ?>
      </section>

      <section class="bloco">
        <h2 class="bloco__titulo">Sugerir outro horário</h2>
        <p class="bloco__apoio">
          Mostramos só os horários livres do dia escolhido. Troque a data e a lista se atualiza.
        </p>

        <form method="post" class="form-compacto">
          <?= csrf_campo() ?>
          <input type="hidden" name="id" value="<?= (int) $ag['id'] ?>">

          <div class="campo">
            <label for="sugestao_data">Nova data</label>
            <input id="sugestao_data" name="sugestao_data" type="date" required
                   min="<?= date('Y-m-d') ?>" value="<?= e($dataSugestao) ?>"
                   data-recarrega-horarios data-servico="<?= (int) $ag['servico_id'] ?>"
                   data-ignorar="<?= (int) $ag['id'] ?>">
          </div>

          <div class="campo">
            <label for="sugestao_hora">Novo horário</label>
            <select id="sugestao_hora" name="sugestao_hora" required <?= $livres ? '' : 'disabled' ?>>
              <?php if (!$livres): ?>
                <option value="">Sem horário livre nesse dia</option>
              <?php else: ?>
                <option value="">Escolha um horário</option>
                <?php foreach ($livres as $h): ?>
                  <option value="<?= e($h) ?>"><?= e($h) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>

          <div class="campo campo--largo">
            <label for="motivo">Motivo <i>(vai na mensagem)</i></label>
            <input id="motivo" name="motivo" type="text"
                   placeholder="Ex.: a agenda desse horário encheu"
                   value="<?= e($ag['motivo'] ?? '') ?>">
          </div>

          <button class="botao botao--roxo botao--bloco" name="acao" value="remarcar" type="submit">
            Registrar sugestão de remarcação
          </button>
        </form>
      </section>

      <section class="bloco">
        <h2 class="bloco__titulo">Recusar o pedido</h2>
        <form method="post" class="form-compacto">
          <?= csrf_campo() ?>
          <input type="hidden" name="id" value="<?= (int) $ag['id'] ?>">
          <div class="campo campo--largo">
            <label for="motivo_recusa">Motivo</label>
            <input id="motivo_recusa" name="motivo" type="text" required
                   placeholder="Ex.: não atendemos esse porte nesse dia">
          </div>
          <button class="botao botao--perigo botao--bloco" name="acao" value="recusar" type="submit">
            Recusar agendamento
          </button>
        </form>
      </section>
    <?php endif; ?>

    <?php if ($ag['status'] === 'confirmado'): ?>
      <section class="bloco">
        <h2 class="bloco__titulo">Andamento</h2>
        <form method="post" class="acoes-linha">
          <?= csrf_campo() ?>
          <input type="hidden" name="id" value="<?= (int) $ag['id'] ?>">
          <button class="botao botao--lima" name="acao" value="concluir" type="submit">
            Marcar como concluído
          </button>
          <button class="botao botao--vazado" name="acao" value="cancelar" type="submit">
            Cancelar
          </button>
        </form>
      </section>
    <?php endif; ?>

    <?php if (in_array($ag['status'], ['recusado', 'cancelado'], true)): ?>
      <section class="bloco">
        <form method="post" class="acoes-linha">
          <?= csrf_campo() ?>
          <input type="hidden" name="id" value="<?= (int) $ag['id'] ?>">
          <button class="botao botao--vazado" name="acao" value="reabrir" type="submit">
            Reabrir como pendente
          </button>
        </form>
      </section>
    <?php endif; ?>

    <!-- ================= mensagem ================= -->
    <section class="bloco bloco--zap">
      <h2 class="bloco__titulo">Mensagem para o cliente</h2>
      <p class="bloco__apoio">
        Texto montado conforme o status atual. Confira, ajuste se quiser e envie.
      </p>

      <textarea class="mensagem" id="mensagemCliente" rows="9"><?= e(mensagem_cliente($ag)) ?></textarea>

      <div class="acoes-linha">
        <a class="botao botao--zap" id="linkZap"
           href="<?= e(link_whatsapp_cliente($ag)) ?>" target="_blank"
           data-numero="55<?= e(so_digitos($ag['whatsapp'])) ?>">
          Abrir no WhatsApp
        </a>
        <button class="botao botao--vazado" type="button" id="copiarMensagem">Copiar texto</button>
      </div>

      <form method="post" class="acoes-linha">
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= (int) $ag['id'] ?>">
        <button class="botao botao--pequeno botao--vazado" name="acao" value="marcar_avisado" type="submit">
          Já avisei o cliente
        </button>
      </form>
    </section>

  </div>
</div>

<?php require __DIR__ . '/../includes/rodape.php'; ?>
