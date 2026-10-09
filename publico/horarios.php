<?php
/**
 * API simples: devolve os horários livres de um dia para um serviço.
 * Uso: horarios.php?data=2026-10-20&servico=3
 */

require_once __DIR__ . '/../includes/funcoes.php';

header('Content-Type: application/json; charset=utf-8');

$data    = $_GET['data'] ?? '';
$servico = (int) ($_GET['servico'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $servico <= 0) {
    http_response_code(400);
    echo json_encode(['erro' => 'Informe uma data e um serviço válidos.']);
    exit;
}

// a data precisa estar dentro da janela que aceitamos
$limite = date('Y-m-d', strtotime('+' . (int) config('dias_futuros', '60') . ' days'));
if ($data < date('Y-m-d') || $data > $limite) {
    echo json_encode(['horarios' => [], 'aviso' => 'Escolha uma data dentro dos próximos dias.']);
    exit;
}

$stmt = bd()->prepare('SELECT duracao_min FROM servicos WHERE id = ? AND ativo = 1');
$stmt->execute([$servico]);
$duracao = $stmt->fetchColumn();

if ($duracao === false) {
    http_response_code(404);
    echo json_encode(['erro' => 'Serviço não encontrado.']);
    exit;
}

$livres = horarios_livres($data, (int) $duracao);

echo json_encode([
    'horarios' => $livres,
    'aviso'    => $livres ? null : 'Não temos horário livre nesse dia. Tente outra data.',
], JSON_UNESCAPED_UNICODE);
