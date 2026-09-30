<?php
/**
 * POST api/agendar.php — cria um agendamento a partir do site público.
 * Campos: servico_id, barbeiro_id (0 = qualquer), data, hora, nome, telefone, email, observacao, _csrf
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

if (!is_post()) {
    json_response(['ok' => false, 'erro' => 'Método não permitido.'], 405);
}
if (!csrf_valid()) {
    json_response(['ok' => false, 'erro' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 419);
}

// Limite simples: até 5 agendamentos por sessão a cada hora
$_SESSION['_bookings'] = array_values(array_filter($_SESSION['_bookings'] ?? [], fn($t) => $t > time() - 3600));
if (count($_SESSION['_bookings']) >= 5) {
    json_response(['ok' => false, 'erro' => 'Muitas tentativas em pouco tempo. Aguarde alguns minutos ou fale conosco pelo WhatsApp.'], 429);
}

$d = [
    'servico_id'  => input_int('servico_id'),
    'barbeiro_id' => input_int('barbeiro_id'),
    'data'        => input('data'),
    'hora'        => substr(input('hora'), 0, 5),
    'nome'        => input('nome'),
    'telefone'    => normalize_phone(input('telefone')),
    'email'       => strtolower(input('email')),
    'observacao'  => input('observacao'),
];

$erros = [];
if ($d['servico_id'] <= 0) {
    $erros['servico_id'] = 'Escolha um serviço.';
}
if ($d['barbeiro_id'] < 0) {
    $erros['barbeiro_id'] = 'Escolha um profissional.';
}
if (!valid_date($d['data']) || $d['data'] < date('Y-m-d')) {
    $erros['data'] = 'Escolha uma data válida.';
}
if (!valid_time($d['hora'])) {
    $erros['hora'] = 'Escolha um horário.';
}
if (str_len($d['nome']) < 3 || str_len($d['nome']) > 120 || !preg_match('/^[\p{L}\s\'.-]+$/u', $d['nome'])) {
    $erros['nome'] = 'Informe seu nome completo (apenas letras).';
}
if (!valid_phone($d['telefone'])) {
    $erros['telefone'] = 'Informe um telefone válido com DDD. Ex.: (11) 98765-4321.';
}
if (!valid_email($d['email'])) {
    $erros['email'] = 'Informe um e-mail válido. Ex.: nome@email.com.';
}
if (str_len($d['observacao']) > 500) {
    $erros['observacao'] = 'A observação pode ter no máximo 500 caracteres.';
}
if ($erros) {
    json_response(['ok' => false, 'erro' => 'Verifique os campos destacados.', 'campos' => $erros], 422);
}

try {
    $d['cliente_id'] = cliente_por_telefone($d['nome'], $d['telefone'], $d['email']);
} catch (PDOException $e) {
    error_log('Erro ao salvar cliente: ' . $e->getMessage());
    json_response(['ok' => false, 'erro' => 'Não foi possível registrar seus dados. Tente novamente.'], 500);
}
$d['status'] = 'pendente';

$res = salvar_agendamento($d);
if (!$res['ok']) {
    json_response(['ok' => false, 'erro' => $res['erro']], 409);
}

$_SESSION['_bookings'][] = time();
$a = agendamento_completo($res['id']);

json_response([
    'ok'           => true,
    'mensagem'     => 'Agendamento realizado com sucesso!',
    'agendamento'  => [
        'protocolo' => str_pad((string) $a['id'], 5, '0', STR_PAD_LEFT),
        'servico'   => $a['servico_nome'],
        'barbeiro'  => $a['barbeiro_nome'],
        'data'      => date_br($a['data']),
        'data_extenso' => data_extenso($a['data']),
        'hora'      => hm($a['hora']),
        'valor'     => money($a['valor']),
        'nome'      => $a['cliente_nome'],
    ],
    'whatsapp_url' => whatsapp_link(mensagem_whatsapp_agendamento($a)),
    'cancelar_url' => url('cancelar.php?t=' . $a['token']),
]);
