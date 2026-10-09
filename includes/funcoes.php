<?php
/**
 * Pata Feliz — funções compartilhadas
 * Sessão, segurança, formatação, regras de agenda e mensagens.
 */

require_once __DIR__ . '/../config/config.php';

// =============================================================
// Sessão e autenticação
// =============================================================

function sessao_iniciar(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
        session_start();
    }
}

function usuario_logado(): ?array
{
    sessao_iniciar();
    return $_SESSION['usuario'] ?? null;
}

function exigir_login(): array
{
    $usuario = usuario_logado();
    if (!$usuario) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
    return $usuario;
}

function exigir_admin(): array
{
    $usuario = exigir_login();
    if ($usuario['papel'] !== 'admin') {
        http_response_code(403);
        exit('Acesso restrito ao administrador.');
    }
    return $usuario;
}

function autenticar(string $email, string $senha): bool
{
    $sql = 'SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1';
    $stmt = bd()->prepare($sql);
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
        return false;
    }

    sessao_iniciar();
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id'    => (int) $usuario['id'],
        'nome'  => $usuario['nome'],
        'email' => $usuario['email'],
        'papel' => $usuario['papel'],
    ];

    return true;
}

// =============================================================
// Segurança e utilidades
// =============================================================

/** Escapa texto para saída em HTML. */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    sessao_iniciar();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_validar(): void
{
    sessao_iniciar();

    $guardado = $_SESSION['csrf'] ?? '';
    $enviado  = $_POST['csrf'] ?? '';

    // Sessão sem token tem de recusar: sem este teste, hash_equals('', '')
    // devolve true e um POST sem token nenhum passaria direto.
    if ($guardado === '' || !is_string($enviado) || !hash_equals($guardado, $enviado)) {
        http_response_code(400);
        exit('Sessão expirada. Recarregue a página e tente de novo.');
    }
}

/** Guarda um recado para exibir na próxima página. */
function recado(string $texto = null, string $tipo = 'ok'): ?array
{
    sessao_iniciar();
    if ($texto !== null) {
        $_SESSION['recado'] = ['texto' => $texto, 'tipo' => $tipo];
        return null;
    }
    $recado = $_SESSION['recado'] ?? null;
    unset($_SESSION['recado']);
    return $recado;
}

function redirecionar(string $destino): void
{
    header('Location: ' . $destino);
    exit;
}

function config(string $chave, string $padrao = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (bd()->query('SELECT chave, valor FROM configuracoes') as $linha) {
            $cache[$linha['chave']] = $linha['valor'];
        }
    }
    return $cache[$chave] ?? $padrao;
}

// =============================================================
// Formatação
// =============================================================

function so_digitos(string $texto): string
{
    return preg_replace('/\D+/', '', $texto) ?? '';
}

function formatar_telefone(string $digitos): string
{
    $d = so_digitos($digitos);
    if (strlen($d) === 11) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    }
    if (strlen($d) === 10) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    }
    return $d;
}

function formatar_data(string $iso): string
{
    $d = DateTime::createFromFormat('Y-m-d', substr($iso, 0, 10));
    return $d ? $d->format('d/m/Y') : $iso;
}

function formatar_hora(string $hora): string
{
    return substr($hora, 0, 5);
}

function formatar_preco(?float $valor): string
{
    return $valor === null ? '—' : 'R$ ' . number_format($valor, 2, ',', '.');
}

function dia_semana_extenso(string $iso): string
{
    $dias = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira',
             'Quinta-feira', 'Sexta-feira', 'Sábado'];
    return $dias[(int) date('w', strtotime($iso))];
}

function rotulo_porte(string $porte): string
{
    return [
        'pp' => 'Mini (até 5kg)',
        'p'  => 'Pequeno (5–10kg)',
        'm'  => 'Médio (10–25kg)',
        'g'  => 'Grande (25–45kg)',
        'gg' => 'Gigante (45kg+)',
    ][$porte] ?? $porte;
}

function rotulo_status(string $status): string
{
    return [
        'pendente'   => 'Aguardando aprovação',
        'confirmado' => 'Confirmado',
        'remarcar'   => 'Remarcação sugerida',
        'recusado'   => 'Recusado',
        'concluido'  => 'Concluído',
        'cancelado'  => 'Cancelado',
    ][$status] ?? $status;
}

// =============================================================
// Regras da agenda
// =============================================================

/**
 * Soma minutos a um horário 'HH:MM' e devolve 'HH:MM:SS'.
 */
function somar_minutos(string $hora, int $minutos): string
{
    $base = strtotime('1970-01-01 ' . $hora . ' UTC');
    return gmdate('H:i:s', $base + $minutos * 60);
}

/**
 * Quantos atendimentos já ocupam a faixa pedida.
 * Dois períodos colidem quando inicio_a < fim_b E inicio_b < fim_a.
 *
 * @param int|null $ignorar id de um agendamento a desconsiderar (ao editar)
 */
function ocupacao_na_faixa(string $data, string $inicio, string $fim, ?int $ignorar = null): int
{
    $sql = "SELECT COUNT(*) FROM agendamentos
            WHERE data = ?
              AND status IN ('pendente','confirmado')
              AND hora_inicio < ?
              AND ? < hora_fim";
    $parametros = [$data, $fim, $inicio];

    if ($ignorar !== null) {
        $sql .= ' AND id <> ?';
        $parametros[] = $ignorar;
    }

    $stmt = bd()->prepare($sql);
    $stmt->execute($parametros);

    return (int) $stmt->fetchColumn();
}

/**
 * A faixa cai dentro de algum bloqueio (feriado, almoço, manutenção)?
 */
function ha_bloqueio(string $data, string $inicio, string $fim): bool
{
    $sql = "SELECT COUNT(*) FROM bloqueios
            WHERE data = ?
              AND (
                    hora_inicio IS NULL
                 OR (hora_inicio < ? AND ? < hora_fim)
              )";
    $stmt = bd()->prepare($sql);
    $stmt->execute([$data, $fim, $inicio]);

    return $stmt->fetchColumn() > 0;
}

/**
 * O horário pedido está livre? Devolve null se estiver, ou o motivo da recusa.
 */
function motivo_indisponivel(string $data, string $inicio, int $duracao, ?int $ignorar = null): ?string
{
    $fim = somar_minutos($inicio, $duracao);

    // dia da semana permitido (1 = segunda ... 7 = domingo)
    $dias = array_map('intval', explode(',', config('dias_semana', '1,2,3,4,5,6')));
    if (!in_array((int) date('N', strtotime($data)), $dias, true)) {
        return 'Não atendemos nesse dia da semana.';
    }

    // dentro do expediente
    if ($inicio < config('abre', '08:00') . ':00' || $fim > config('fecha', '18:00') . ':00') {
        return 'Esse horário está fora do nosso expediente.';
    }

    // antecedência mínima
    $antecedencia = (int) config('antecedencia_horas', '2');
    if (strtotime("$data $inicio") < time() + $antecedencia * 3600) {
        return "Precisamos de pelo menos {$antecedencia}h de antecedência.";
    }

    if (ha_bloqueio($data, $inicio, $fim)) {
        return 'A agenda está bloqueada nesse horário.';
    }

    $capacidade = (int) config('capacidade_simultanea', '2');
    if (ocupacao_na_faixa($data, $inicio, $fim, $ignorar) >= $capacidade) {
        return 'Esse horário já está ocupado.';
    }

    return null;
}

/**
 * Lista os horários livres de um dia para um serviço.
 *
 * @return string[] ex.: ['08:00', '08:30', '09:00']
 */
function horarios_livres(string $data, int $duracao, ?int $ignorar = null): array
{
    $passo  = max(15, (int) config('intervalo_min', '30'));
    $abre   = strtotime('1970-01-01 ' . config('abre', '08:00') . ' UTC');
    $fecha  = strtotime('1970-01-01 ' . config('fecha', '18:00') . ' UTC');
    $livres = [];

    for ($t = $abre; $t + $duracao * 60 <= $fecha; $t += $passo * 60) {
        $inicio = gmdate('H:i:s', $t);
        if (motivo_indisponivel($data, $inicio, $duracao, $ignorar) === null) {
            $livres[] = gmdate('H:i', $t);
        }
    }

    return $livres;
}

/**
 * Busca o cliente pelo WhatsApp ou cria um novo. Devolve o id.
 */
function cliente_garantir(string $nome, string $whatsapp, ?string $email = null): int
{
    $digitos = so_digitos($whatsapp);

    $stmt = bd()->prepare('SELECT id FROM clientes WHERE whatsapp = ? LIMIT 1');
    $stmt->execute([$digitos]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    $stmt = bd()->prepare('INSERT INTO clientes (nome, whatsapp, email) VALUES (?, ?, ?)');
    $stmt->execute([$nome, $digitos, $email ?: null]);

    return (int) bd()->lastInsertId();
}

/**
 * Busca o pet do cliente pelo nome ou cria um novo. Devolve o id.
 */
function pet_garantir(int $clienteId, string $nome, string $especie, ?string $raca, string $porte, ?float $peso): int
{
    $stmt = bd()->prepare('SELECT id FROM pets WHERE cliente_id = ? AND nome = ? LIMIT 1');
    $stmt->execute([$clienteId, $nome]);
    $id = $stmt->fetchColumn();

    if ($id) {
        return (int) $id;
    }

    $stmt = bd()->prepare(
        'INSERT INTO pets (cliente_id, nome, especie, raca, porte, peso_kg) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$clienteId, $nome, $especie, $raca ?: null, $porte, $peso]);

    return (int) bd()->lastInsertId();
}

function preco_do_servico(int $servicoId, string $porte): ?float
{
    $stmt = bd()->prepare('SELECT preco FROM precos WHERE servico_id = ? AND porte = ?');
    $stmt->execute([$servicoId, $porte]);
    $preco = $stmt->fetchColumn();

    return $preco === false ? null : (float) $preco;
}

/**
 * Carrega um agendamento com os dados do cliente, pet e serviço.
 */
function agendamento_completo(int $id): ?array
{
    $sql = 'SELECT a.*,
                   c.nome AS cliente_nome, c.whatsapp, c.email AS cliente_email,
                   p.nome AS pet_nome, p.especie, p.raca, p.porte, p.peso_kg,
                   s.nome AS servico_nome, s.duracao_min
            FROM agendamentos a
            JOIN clientes c ON c.id = a.cliente_id
            JOIN pets     p ON p.id = a.pet_id
            JOIN servicos s ON s.id = a.servico_id
            WHERE a.id = ?';
    $stmt = bd()->prepare($sql);
    $stmt->execute([$id]);
    $linha = $stmt->fetch();

    return $linha ?: null;
}

// =============================================================
// Mensagens para o cliente
// =============================================================

/**
 * Monta o texto que o pet shop envia ao cliente conforme o status.
 */
function mensagem_cliente(array $ag): string
{
    $pet  = $ag['pet_nome'];
    $serv = $ag['servico_nome'];
    $data = formatar_data($ag['data']);
    $hora = formatar_hora($ag['hora_inicio']);
    $oi   = "Olá, {$ag['cliente_nome']}! Aqui é da " . LOJA_NOME . ' 🐾';

    switch ($ag['status']) {
        case 'confirmado':
            return "$oi\n\n"
                 . "Seu agendamento está *confirmado*:\n"
                 . "• Pet: $pet\n• Serviço: $serv\n• Quando: $data às $hora\n\n"
                 . 'Endereço: ' . LOJA_ENDERECO . "\n"
                 . 'Até lá! Se precisar remarcar, é só responder esta mensagem.';

        case 'remarcar':
            $novaData = $ag['sugestao_data'] ? formatar_data($ag['sugestao_data']) : '';
            $novaHora = $ag['sugestao_hora'] ? formatar_hora($ag['sugestao_hora']) : '';
            $motivo   = $ag['motivo'] ? "\nMotivo: {$ag['motivo']}" : '';

            return "$oi\n\n"
                 . "Infelizmente não conseguimos atender o $pet em $data às $hora.$motivo\n\n"
                 . "Podemos remarcar para *$novaData às $novaHora*?\n"
                 . 'Responda *SIM* para confirmar, ou me diga outro horário que fique melhor para você.';

        case 'recusado':
            $motivo = $ag['motivo'] ? "\nMotivo: {$ag['motivo']}" : '';
            return "$oi\n\n"
                 . "Não vamos conseguir atender o $pet em $data às $hora.$motivo\n\n"
                 . 'Quer que eu veja outro dia para você? É só me dizer a sua preferência.';

        case 'concluido':
            return "$oi\n\n"
                 . "O $pet já está prontinho e cheiroso! 💛\n"
                 . 'Obrigado pela confiança — qualquer coisa, estamos por aqui.';

        default: // pendente
            return "$oi\n\n"
                 . "Recebemos o seu pedido de $serv para o $pet em $data às $hora.\n"
                 . 'Já estamos conferindo a agenda e te respondo em instantes.';
    }
}

/**
 * Link do WhatsApp com a mensagem pronta para o cliente.
 */
function link_whatsapp_cliente(array $ag): string
{
    $numero = '55' . so_digitos($ag['whatsapp']);
    return 'https://wa.me/' . $numero . '?text=' . rawurlencode(mensagem_cliente($ag));
}
