<?php
/**
 * GET api/disponibilidade.php?servico=1&barbeiro=0&data=2026-10-05[&ignorar=ID]
 * barbeiro=0 => qualquer profissional disponível.
 * Parâmetro "admin=1" (somente com sessão administrativa) libera horários passados e o bloqueio de janela.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

$servicoId = input_int('servico');
$barbeiroId = input_int('barbeiro');
$data = input('data');
$admin = input('admin') === '1' && !empty($_SESSION['admin_id']);
$ignorar = $admin ? (input_int('ignorar') ?: null) : null;

if (!valid_date($data)) {
    json_response(['ok' => false, 'erro' => 'Data inválida.'], 422);
}
$st = db()->prepare('SELECT id, nome, preco, duracao, ativo FROM servicos WHERE id = ?');
$st->execute([$servicoId]);
$servico = $st->fetch();
if (!$servico || (!$admin && (int) $servico['ativo'] !== 1)) {
    json_response(['ok' => false, 'erro' => 'Serviço não encontrado.'], 404);
}

$hoje = date('Y-m-d');
if (!$admin) {
    $max = date('Y-m-d', strtotime('+' . (int) settings()['dias_antecedencia'] . ' days'));
    if ($data < $hoje || $data > $max) {
        json_response(['ok' => true, 'slots' => [], 'mensagem' => 'Data fora do período de agendamento.']);
    }
}

$duracao = (int) $servico['duracao'];
if ($barbeiroId > 0) {
    $b = db()->prepare('SELECT id FROM barbeiros WHERE id = ? AND ativo = 1');
    $b->execute([$barbeiroId]);
    if (!$b->fetch()) {
        json_response(['ok' => false, 'erro' => 'Profissional indisponível.'], 404);
    }
    $slots = horarios_barbeiro($barbeiroId, $data, $duracao, $ignorar, $admin);
} else {
    $slots = horarios_qualquer($data, $duracao, $admin);
}

$promo = promocao_para_servico((int) $servico['id'], $data);
$mensagem = '';
if (!$slots) {
    $mensagem = periodos_barbearia((int) date('w', strtotime($data)))
        ? 'Não há expediente disponível para esta data. Escolha outro dia ou profissional.'
        : 'A barbearia não abre neste dia.';
} elseif (!array_filter($slots, fn($s) => $s['disponivel'])) {
    $mensagem = 'Todos os horários desta data já foram reservados. Que tal outro dia?';
}

json_response([
    'ok'       => true,
    'data'     => $data,
    'duracao'  => $duracao,
    'preco'    => (float) ($promo ? $promo['preco_promocional'] : $servico['preco']),
    'promo'    => (bool) $promo,
    'slots'    => $slots,
    'mensagem' => $mensagem,
]);
