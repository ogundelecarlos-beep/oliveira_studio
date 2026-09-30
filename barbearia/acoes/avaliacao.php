<?php
/**
 * Recebe avaliações do site público. Ficam pendentes até aprovação no painel.
 * Apenas clientes com atendimento concluído podem avaliar.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('index.php#avaliacoes');
}
if (!csrf_valid()) {
    flash('error', 'Sua sessão expirou. Atualize a página e tente novamente.');
    redirect('index.php#avaliacoes');
}

$nome = input('nome');
$telefone = normalize_phone(input('telefone'));
$comentario = input('comentario');
$nota = input_int('nota');

$erros = [];
if (str_len($nome) < 3) {
    $erros[] = 'Informe seu nome.';
}
if (!valid_phone($telefone)) {
    $erros[] = 'Informe um telefone válido com DDD.';
}
if ($nota < 1 || $nota > 5) {
    $erros[] = 'Escolha uma nota de 1 a 5 estrelas.';
}
if (str_len($comentario) < 10 || str_len($comentario) > 600) {
    $erros[] = 'O comentário deve ter entre 10 e 600 caracteres.';
}
if ($erros) {
    flash('error', implode(' ', $erros));
    redirect('index.php#avaliacoes');
}

$st = db()->prepare("SELECT a.id, a.cliente_id FROM agendamentos a JOIN clientes c ON c.id = a.cliente_id
                     WHERE c.telefone = ? AND a.status = 'concluido' ORDER BY a.data DESC, a.hora DESC LIMIT 1");
$st->execute([$telefone]);
$ag = $st->fetch();
if (!$ag) {
    flash('error', 'Não encontramos atendimento concluído para este telefone. Use o mesmo número informado no agendamento.');
    redirect('index.php#avaliacoes');
}

$dup = db()->prepare('SELECT COUNT(*) FROM avaliacoes WHERE agendamento_id = ?');
$dup->execute([$ag['id']]);
if ((int) $dup->fetchColumn() > 0) {
    flash('warning', 'Você já avaliou seu último atendimento. Obrigado!');
    redirect('index.php#avaliacoes');
}

db()->prepare('INSERT INTO avaliacoes (cliente_id, agendamento_id, nota, comentario, aprovado) VALUES (?, ?, ?, ?, 0)')
    ->execute([$ag['cliente_id'], $ag['id'], $nota, $comentario]);

flash('success', 'Obrigado pela avaliação! Ela será publicada após a moderação.');
redirect('index.php#avaliacoes');
