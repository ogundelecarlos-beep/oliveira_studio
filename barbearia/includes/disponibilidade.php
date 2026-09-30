<?php
/**
 * Regras de disponibilidade da agenda.
 *
 *  - Horário de funcionamento da casa: horarios.barbeiro_id IS NULL.
 *  - Jornada do barbeiro: se ele tiver registros próprios, vale a interseção
 *    entre a jornada dele e o funcionamento da casa; caso contrário, segue a casa.
 *  - Bloqueios: gerais (barbeiro_id NULL) ou por barbeiro; sem hora = dia inteiro.
 *  - Agendamentos não cancelados ocupam [hora, hora_fim).
 *
 * Todas as verificações são refeitas no servidor no momento de gravar,
 * dentro de uma transação, e o índice único do banco impede duplicidade.
 */

function to_min(string $time): int
{
    [$h, $m] = array_map('intval', explode(':', $time));
    return $h * 60 + $m;
}

function min_to_time(int $min): string
{
    return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
}

/** Interseção entre duas listas de intervalos [inicio, fim] em minutos. */
function intersect_periods(array $a, array $b): array
{
    $out = [];
    foreach ($a as [$s1, $e1]) {
        foreach ($b as [$s2, $e2]) {
            $s = max($s1, $s2);
            $e = min($e1, $e2);
            if ($s < $e) {
                $out[] = [$s, $e];
            }
        }
    }
    usort($out, fn($x, $y) => $x[0] <=> $y[0]);
    return $out;
}

function periodos_barbearia(int $diaSemana): array
{
    $st = db()->prepare('SELECT hora_inicio, hora_fim FROM horarios WHERE barbeiro_id IS NULL AND dia_semana = ? ORDER BY hora_inicio');
    $st->execute([$diaSemana]);
    return array_map(fn($r) => [to_min($r['hora_inicio']), to_min($r['hora_fim'])], $st->fetchAll());
}

/** Períodos de trabalho do barbeiro em uma data (minutos desde 00:00). */
function periodos_trabalho(int $barbeiroId, string $data): array
{
    $dow = (int) date('w', strtotime($data));
    $casa = periodos_barbearia($dow);
    if (!$casa) {
        return [];
    }
    $st = db()->prepare('SELECT COUNT(*) FROM horarios WHERE barbeiro_id = ?');
    $st->execute([$barbeiroId]);
    if ((int) $st->fetchColumn() === 0) {
        return $casa;
    }
    $st = db()->prepare('SELECT hora_inicio, hora_fim FROM horarios WHERE barbeiro_id = ? AND dia_semana = ? ORDER BY hora_inicio');
    $st->execute([$barbeiroId, $dow]);
    $proprios = array_map(fn($r) => [to_min($r['hora_inicio']), to_min($r['hora_fim'])], $st->fetchAll());
    return intersect_periods($proprios, $casa);
}

/** Intervalos ocupados (agendamentos + bloqueios) do barbeiro na data. */
function intervalos_ocupados(int $barbeiroId, string $data, ?int $ignorarAgendamento = null): array
{
    $ocupado = [];

    $sql = "SELECT hora, hora_fim FROM agendamentos WHERE barbeiro_id = ? AND data = ? AND status <> 'cancelado'";
    $params = [$barbeiroId, $data];
    if ($ignorarAgendamento) {
        $sql .= ' AND id <> ?';
        $params[] = $ignorarAgendamento;
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    foreach ($st->fetchAll() as $r) {
        $ocupado[] = [to_min($r['hora']), to_min($r['hora_fim']), 'agendamento'];
    }

    $st = db()->prepare('SELECT hora_inicio, hora_fim FROM bloqueios WHERE data = ? AND (barbeiro_id IS NULL OR barbeiro_id = ?)');
    $st->execute([$data, $barbeiroId]);
    foreach ($st->fetchAll() as $r) {
        if ($r['hora_inicio'] === null || $r['hora_fim'] === null) {
            $ocupado[] = [0, 24 * 60, 'bloqueio'];
        } else {
            $ocupado[] = [to_min($r['hora_inicio']), to_min($r['hora_fim']), 'bloqueio'];
        }
    }
    return $ocupado;
}

function overlaps(int $s1, int $e1, int $s2, int $e2): bool
{
    return $s1 < $e2 && $s2 < $e1;
}

/** Primeiro minuto do dia a partir do qual é possível agendar (considera antecedência mínima). */
function minuto_minimo(string $data, bool $permitirPassado): int
{
    if ($permitirPassado) {
        return 0;
    }
    $hoje = date('Y-m-d');
    if ($data < $hoje) {
        return PHP_INT_MAX;
    }
    if ($data > $hoje) {
        return 0;
    }
    return (int) date('G') * 60 + (int) date('i') + (int) settings()['antecedencia_minima'];
}

/** Passo da grade de horários: a própria duração do serviço (mínimo 15 min). */
function passo_grade(int $duracao): int
{
    return max(15, $duracao);
}

/**
 * Lista de horários do barbeiro para o serviço na data:
 * [['hora' => '09:00', 'disponivel' => true], ...]
 */
function horarios_barbeiro(int $barbeiroId, string $data, int $duracao, ?int $ignorar = null, bool $permitirPassado = false): array
{
    $periodos = periodos_trabalho($barbeiroId, $data);
    if (!$periodos) {
        return [];
    }
    $ocupado = intervalos_ocupados($barbeiroId, $data, $ignorar);
    foreach ($ocupado as [$s, $e, $tipo]) {
        if ($tipo === 'bloqueio' && $s === 0 && $e >= 1440) {
            return [];
        }
    }
    $minimo = minuto_minimo($data, $permitirPassado);
    $passo = passo_grade($duracao);
    $slots = [];
    foreach ($periodos as [$ini, $fim]) {
        for ($t = $ini; $t + $duracao <= $fim; $t += $passo) {
            $livre = $t >= $minimo;
            if ($livre) {
                foreach ($ocupado as [$s, $e]) {
                    if (overlaps($t, $t + $duracao, $s, $e)) {
                        $livre = false;
                        break;
                    }
                }
            }
            $slots[] = ['hora' => min_to_time($t), 'disponivel' => $livre];
        }
    }
    return $slots;
}

function barbeiros_ativos(): array
{
    return db()->query('SELECT * FROM barbeiros WHERE ativo = 1 ORDER BY ordem, nome')->fetchAll();
}

/** Horários considerando "qualquer profissional": disponível se ao menos um barbeiro estiver livre. */
function horarios_qualquer(string $data, int $duracao, bool $permitirPassado = false): array
{
    $mapa = [];
    foreach (barbeiros_ativos() as $b) {
        foreach (horarios_barbeiro((int) $b['id'], $data, $duracao, null, $permitirPassado) as $slot) {
            $h = $slot['hora'];
            $mapa[$h] = ($mapa[$h] ?? false) || $slot['disponivel'];
        }
    }
    ksort($mapa);
    $out = [];
    foreach ($mapa as $hora => $disp) {
        $out[] = ['hora' => $hora, 'disponivel' => $disp];
    }
    return $out;
}

/**
 * Valida se o barbeiro pode atender [hora, hora+duração) na data.
 * Retorna null quando está tudo certo ou uma mensagem de erro clara.
 */
function validar_horario(int $barbeiroId, string $data, string $hora, int $duracao, ?int $ignorar = null, bool $permitirPassado = false, bool $limitarJanela = true): ?string
{
    if (!valid_date($data)) {
        return 'Data inválida.';
    }
    if (!valid_time($hora)) {
        return 'Horário inválido.';
    }
    if ($limitarJanela) {
        $max = date('Y-m-d', strtotime('+' . (int) settings()['dias_antecedencia'] . ' days'));
        if ($data > $max) {
            return 'Agendamentos só podem ser feitos com até ' . (int) settings()['dias_antecedencia'] . ' dias de antecedência.';
        }
    }
    $st = db()->prepare('SELECT ativo FROM barbeiros WHERE id = ?');
    $st->execute([$barbeiroId]);
    $ativo = $st->fetchColumn();
    if ($ativo === false) {
        return 'Barbeiro não encontrado.';
    }
    if ((int) $ativo !== 1) {
        return 'Este barbeiro não está atendendo no momento.';
    }

    $ini = to_min($hora);
    $fim = $ini + $duracao;

    if ($ini < minuto_minimo($data, $permitirPassado)) {
        return 'Este horário já passou ou está muito próximo. Escolha outro horário.';
    }

    $dentro = false;
    foreach (periodos_trabalho($barbeiroId, $data) as [$s, $e]) {
        if ($ini >= $s && $fim <= $e) {
            $dentro = true;
            break;
        }
    }
    if (!$dentro) {
        return 'Horário fora do expediente da barbearia ou do profissional.';
    }

    foreach (intervalos_ocupados($barbeiroId, $data, $ignorar) as [$s, $e, $tipo]) {
        if (overlaps($ini, $fim, $s, $e)) {
            return $tipo === 'bloqueio'
                ? 'Este horário está bloqueado na agenda.'
                : 'Este horário acabou de ser reservado. Escolha outro horário.';
        }
    }
    return null;
}

/** Escolhe o barbeiro livre com menos agendamentos no dia (distribuição equilibrada). */
function escolher_barbeiro(string $data, string $hora, int $duracao): ?int
{
    $candidatos = [];
    foreach (barbeiros_ativos() as $b) {
        if (validar_horario((int) $b['id'], $data, $hora, $duracao) === null) {
            $candidatos[] = (int) $b['id'];
        }
    }
    if (!$candidatos) {
        return null;
    }
    $in = implode(',', array_fill(0, count($candidatos), '?'));
    $st = db()->prepare("SELECT barbeiro_id, COUNT(*) c FROM agendamentos WHERE data = ? AND status <> 'cancelado' AND barbeiro_id IN ($in) GROUP BY barbeiro_id");
    $st->execute(array_merge([$data], $candidatos));
    $contagem = array_fill_keys($candidatos, 0);
    foreach ($st->fetchAll() as $r) {
        $contagem[(int) $r['barbeiro_id']] = (int) $r['c'];
    }
    asort($contagem);
    return (int) array_key_first($contagem);
}

/** Localiza cliente pelo telefone ou cadastra um novo; atualiza nome/e-mail. */
function cliente_por_telefone(string $nome, string $telefone, ?string $email): int
{
    $st = db()->prepare('SELECT id, email FROM clientes WHERE telefone = ?');
    $st->execute([$telefone]);
    $c = $st->fetch();
    if ($c) {
        $up = db()->prepare('UPDATE clientes SET nome = ?, email = COALESCE(?, email) WHERE id = ?');
        $up->execute([$nome, $email ?: null, $c['id']]);
        return (int) $c['id'];
    }
    $ins = db()->prepare('INSERT INTO clientes (nome, telefone, email) VALUES (?, ?, ?)');
    $ins->execute([$nome, $telefone, $email ?: null]);
    return (int) db()->lastInsertId();
}

/**
 * Cria (ou atualiza) um agendamento com todas as validações, dentro de transação.
 * Retorna ['ok' => true, 'id' => ...] ou ['ok' => false, 'erro' => '...'].
 */
function salvar_agendamento(array $d, ?int $id = null, bool $admin = false): array
{
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM servicos WHERE id = ?');
    $st->execute([(int) $d['servico_id']]);
    $servico = $st->fetch();
    if (!$servico || (!$admin && (int) $servico['ativo'] !== 1)) {
        return ['ok' => false, 'erro' => 'Serviço indisponível.'];
    }
    $duracao = (int) $servico['duracao'];

    $pdo->beginTransaction();
    try {
        $barbeiroId = (int) $d['barbeiro_id'];
        if ($barbeiroId === 0) {
            $barbeiroId = (int) escolher_barbeiro($d['data'], $d['hora'], $duracao);
            if ($barbeiroId === 0) {
                $pdo->rollBack();
                return ['ok' => false, 'erro' => 'Nenhum profissional está disponível neste horário. Escolha outro.'];
            }
        }
        // Trava a linha do barbeiro para serializar agendamentos concorrentes
        $lock = $pdo->prepare('SELECT id FROM barbeiros WHERE id = ? FOR UPDATE');
        $lock->execute([$barbeiroId]);
        $lock->fetchAll();

        $erro = validar_horario($barbeiroId, $d['data'], $d['hora'], $duracao, $id, $admin, !$admin);
        if ($erro !== null) {
            $pdo->rollBack();
            return ['ok' => false, 'erro' => $erro];
        }

        if (isset($d['valor']) && $d['valor'] !== null) {
            $valor = (float) $d['valor'];
        } else {
            $promo = promocao_para_servico((int) $servico['id'], $d['data']);
            $valor = $promo ? (float) $promo['preco_promocional'] : (float) $servico['preco'];
        }
        $horaFim = min_to_time(to_min($d['hora']) + $duracao);

        if ($id === null) {
            $token = bin2hex(random_bytes(16));
            $ins = $pdo->prepare('INSERT INTO agendamentos (cliente_id, barbeiro_id, servico_id, data, hora, hora_fim, valor, status, observacao, token)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $ins->execute([
                (int) $d['cliente_id'], $barbeiroId, (int) $servico['id'], $d['data'], $d['hora'], $horaFim,
                $valor, $d['status'] ?? 'pendente', ($d['observacao'] ?? '') !== '' ? $d['observacao'] : null, $token,
            ]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $up = $pdo->prepare('UPDATE agendamentos SET cliente_id = ?, barbeiro_id = ?, servico_id = ?, data = ?, hora = ?, hora_fim = ?,
                                 valor = ?, status = ?, observacao = ? WHERE id = ?');
            $up->execute([
                (int) $d['cliente_id'], $barbeiroId, (int) $servico['id'], $d['data'], $d['hora'], $horaFim,
                $valor, $d['status'] ?? 'pendente', ($d['observacao'] ?? '') !== '' ? $d['observacao'] : null, $id,
            ]);
        }
        $pdo->commit();
        return ['ok' => true, 'id' => $id];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            return ['ok' => false, 'erro' => 'Este horário acabou de ser reservado. Escolha outro horário.'];
        }
        error_log('Erro ao salvar agendamento: ' . $e->getMessage());
        return ['ok' => false, 'erro' => 'Não foi possível salvar o agendamento. Tente novamente.'];
    }
}

function agendamento_completo(int $id): ?array
{
    $st = db()->prepare('SELECT a.*, c.nome AS cliente_nome, c.telefone AS cliente_telefone, c.email AS cliente_email,
                                b.nome AS barbeiro_nome, s.nome AS servico_nome, s.duracao AS servico_duracao
                         FROM agendamentos a
                         JOIN clientes c ON c.id = a.cliente_id
                         JOIN barbeiros b ON b.id = a.barbeiro_id
                         JOIN servicos s ON s.id = a.servico_id
                         WHERE a.id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function mensagem_whatsapp_agendamento(array $a): string
{
    $cfg = settings();
    return "Olá, {$cfg['nome_barbearia']}! Acabei de agendar pelo site e gostaria de confirmar:\n\n"
        . "*Serviço:* {$a['servico_nome']}\n"
        . "*Barbeiro:* {$a['barbeiro_nome']}\n"
        . '*Data:* ' . date_br($a['data']) . "\n"
        . '*Horário:* ' . hm($a['hora']) . "\n"
        . '*Valor:* ' . money($a['valor']) . "\n"
        . "*Nome:* {$a['cliente_nome']}\n"
        . '*Telefone:* ' . format_phone($a['cliente_telefone'])
        . (!empty($a['observacao']) ? "\n*Obs.:* {$a['observacao']}" : '')
        . "\n\nProtocolo #" . str_pad((string) $a['id'], 5, '0', STR_PAD_LEFT);
}
