<?php
// financeiro_helper.php - metricas do pilar financeiro (valores em R$)

function fin_contas($apenas_ativos = true)
{
    $where = $apenas_ativos ? 'WHERE ativo = 1' : '';
    return db_all("SELECT * FROM financeiro_contas $where ORDER BY tipo, nome");
}

function fin_contas_por_tipo()
{
    $contas = fin_contas();
    $agrupado = array('trading' => array(), 'investimento' => array(), 'renda_trabalho' => array());
    foreach ($contas as $c) {
        $agrupado[$c['tipo']][] = $c;
    }
    return $agrupado;
}

// Converte saldos em indice base 100 + variacao
function fin_indice_conta($saldo_atual, $saldo_inicial)
{
    $inicial = (float)$saldo_inicial;
    if ($inicial <= 0) {
        return array('indice' => 100.0, 'variacao_pct' => 0.0, 'pontos' => 0.0);
    }
    $indice = ((float)$saldo_atual / $inicial) * 100.0;
    return array(
        'indice' => round($indice, 2),
        'variacao_pct' => round($indice - 100.0, 2),
        'pontos' => round($indice - 100.0, 2),
    );
}

function fin_patrimonio_total()
{
    $total = 0.0;
    foreach (fin_contas() as $c) {
        $total += (float)$c['saldo_atual'];
    }
    return $total;
}

function fin_resultado_total()
{
    $total = 0.0;
    foreach (fin_contas() as $c) {
        $total += (float)$c['saldo_atual'] - (float)$c['saldo_inicial'];
    }
    return $total;
}

// Reconstroi o patrimonio por data a partir do saldo atual (ancora) e das datas dos lancamentos
function fin_saldos_historicos($limite = 90)
{
    $importados = db_all("SELECT data, tipo, valor_total FROM financeiro_ativos_movimentos");
    $movimentos = db_all("SELECT data, tipo, valor FROM financeiro_movimentos");

    $porData = array();
    foreach ($importados as $m) {
        $d = $m['data'];
        if (!isset($porData[$d])) { $porData[$d] = 0.0; }
        $porData[$d] += $m['tipo'] === 'credito' ? (float)$m['valor_total'] : -(float)$m['valor_total'];
    }
    foreach ($movimentos as $m) {
        $d = $m['data'];
        if (!isset($porData[$d])) { $porData[$d] = 0.0; }
        $porData[$d] += $m['tipo'] === 'aporte' ? (float)$m['valor'] : -(float)$m['valor'];
    }

    $total = fin_patrimonio_total();
    if (empty($porData)) {
        return array(array('data' => date('Y-m-d'), 'valor' => round($total, 2)));
    }

    $datas = array_keys($porData);
    sort($datas);

    $serie = array();
    $saldo = $total;
    for ($i = count($datas) - 1; $i >= 0; $i--) {
        $d = $datas[$i];
        $serie[] = array('data' => $d, 'valor' => round($saldo, 2));
        $saldo -= $porData[$d];
    }
    $serie[] = array('data' => 'inicio', 'valor' => round($saldo, 2));
    $serie = array_reverse($serie);

    if (count($serie) > $limite) {
        $serie = array_slice($serie, -$limite);
    }
    return $serie;
}

function fin_operacoes($limite = 200, $conta_id = null)
{
    $where = '';
    if ($conta_id) {
        $where = 'WHERE o.conta_id = ' . (int)$conta_id;
    }
    return db_all("SELECT o.*, s.nome AS setup_nome, c.nome AS conta_nome
        FROM financeiro_operacoes o
        LEFT JOIN financeiro_setups s ON s.id = o.setup_id
        LEFT JOIN financeiro_contas c ON c.id = o.conta_id
        $where ORDER BY o.data DESC, o.id DESC LIMIT " . (int)$limite);
}

function fin_win_rate($ops)
{
    $wins = 0; $losses = 0;
    foreach ($ops as $o) {
        if ($o['resultado'] === 'win') { $wins++; }
        elseif ($o['resultado'] === 'loss') { $losses++; }
    }
    $total = $wins + $losses;
    return $total > 0 ? round($wins / $total * 100, 1) : 0.0;
}

function fin_profit_factor($ops)
{
    $ganhos = 0.0; $perdas = 0.0;
    foreach ($ops as $o) {
        $r = (float)$o['retorno_pct'];
        if ($r >= 0) { $ganhos += $r; } else { $perdas += abs($r); }
    }
    if ($perdas <= 0) {
        return $ganhos > 0 ? 99.0 : 0.0;
    }
    return round($ganhos / $perdas, 2);
}

// Expectativa matematica em pontos percentuais por operacao
function fin_expectativa($ops)
{
    $wins = 0; $losses = 0; $somaWin = 0.0; $somaLoss = 0.0;
    foreach ($ops as $o) {
        $r = (float)$o['retorno_pct'];
        if ($o['resultado'] === 'win') { $wins++; $somaWin += $r; }
        elseif ($o['resultado'] === 'loss') { $losses++; $somaLoss += abs($r); }
    }
    $total = $wins + $losses;
    if ($total <= 0) { return 0.0; }
    $pWin = $wins / $total;
    $pLoss = $losses / $total;
    $ganhoMedio = $wins > 0 ? $somaWin / $wins : 0;
    $perdaMedia = $losses > 0 ? $somaLoss / $losses : 0;
    return round(($pWin * $ganhoMedio) - ($pLoss * $perdaMedia), 3);
}

function fin_adesao_media($ops)
{
    if (empty($ops)) { return 0.0; }
    $soma = 0;
    foreach ($ops as $o) { $soma += (int)$o['adesao_plano']; }
    return round($soma / count($ops), 1);
}

function fin_execucao_perfeita($ops)
{
    if (empty($ops)) { return 0.0; }
    $perfeitas = 0;
    foreach ($ops as $o) {
        if ((int)$o['adesao_plano'] >= 10) { $perfeitas++; }
    }
    return round($perfeitas / count($ops) * 100, 1);
}

// Serie de equity (indice base 100) reconstruida a partir das operacoes em ordem cronologica
function fin_equity_series($ops, $limite = 60)
{
    $ordenadas = $ops;
    usort($ordenadas, function ($a, $b) {
        if ($a['data'] === $b['data']) { return (int)$a['id'] - (int)$b['id']; }
        return strcmp($a['data'], $b['data']);
    });
    $indice = 100.0;
    $serie = array(array('data' => 'inicio', 'valor' => 100.0));
    foreach ($ordenadas as $o) {
        $indice = $indice * (1 + ((float)$o['retorno_pct'] / 100));
        $serie[] = array('data' => $o['data'], 'valor' => round($indice, 2));
    }
    if (count($serie) > $limite) {
        $serie = array_slice($serie, -$limite);
    }
    return $serie;
}

function fin_drawdown($ops)
{
    $serie = fin_equity_series($ops, 100000);
    $pico = 0.0; $maxDd = 0.0;
    foreach ($serie as $p) {
        if ($p['valor'] > $pico) { $pico = $p['valor']; }
        if ($pico > 0) {
            $dd = ($pico - $p['valor']) / $pico * 100;
            if ($dd > $maxDd) { $maxDd = $dd; }
        }
    }
    return round($maxDd, 2);
}

function fin_setup_stats()
{
    $rows = db_all("SELECT s.id, s.nome,
            COUNT(o.id) AS total,
            SUM(CASE WHEN o.resultado='win' THEN 1 ELSE 0 END) AS wins,
            SUM(CASE WHEN o.resultado='loss' THEN 1 ELSE 0 END) AS losses,
            COALESCE(SUM(o.retorno_pct),0) AS soma_retorno,
            COALESCE(AVG(o.retorno_pct),0) AS media_retorno,
            COALESCE(AVG(o.adesao_plano),0) AS media_adesao
        FROM financeiro_setups s
        LEFT JOIN financeiro_operacoes o ON o.setup_id = s.id
        WHERE s.ativo = 1
        GROUP BY s.id ORDER BY soma_retorno DESC");
    foreach ($rows as &$r) {
        $decididas = (int)$r['wins'] + (int)$r['losses'];
        $r['win_rate'] = $decididas > 0 ? round($r['wins'] / $decididas * 100, 1) : 0.0;
        $r['media_retorno'] = round((float)$r['media_retorno'], 2);
        $r['media_adesao'] = round((float)$r['media_adesao'], 1);
        $r['soma_retorno'] = round((float)$r['soma_retorno'], 2);
    }
    return $rows;
}

function fin_variacao_consolidada_pct()
{
    $contas = fin_contas();
    $somaInicial = 0.0; $somaAtual = 0.0;
    foreach ($contas as $c) {
        $somaInicial += (float)$c['saldo_inicial'];
        $somaAtual += (float)$c['saldo_atual'];
    }
    if ($somaInicial <= 0) { return 0.0; }
    return round(($somaAtual / $somaInicial * 100) - 100, 2);
}

// Componente de trading (0-100) e componente de contas (0-100), combinados com peso dinamico
function fin_score()
{
    $ops = fin_operacoes(100000);
    $hasOps = count($ops) > 0;

    $trading = 0.0;
    if ($hasOps) {
        $pf = fin_profit_factor($ops);
        $pfNorm = min($pf / 2.0, 1.0) * 100;
        $trading = (fin_win_rate($ops) * 0.30)
            + (fin_adesao_media($ops) * 10 * 0.25)
            + ($pfNorm * 0.25)
            + (fin_execucao_perfeita($ops) * 0.20);
    }

    $var = fin_variacao_consolidada_pct();
    $contasNorm = max(0, min(100, ($var + 10) / 20 * 100));

    if ($hasOps) {
        $score = ($trading * 0.7) + ($contasNorm * 0.3);
    } else {
        $score = $contasNorm;
    }
    return round(max(0, min(100, $score)), 1);
}

function fin_historico_mensal($meses = 6)
{
    $rows = db_all("SELECT DATE_FORMAT(data,'%Y-%m') AS periodo,
            COUNT(*) AS total,
            SUM(CASE WHEN resultado='win' THEN 1 ELSE 0 END) AS wins,
            COALESCE(SUM(retorno_pct),0) AS soma
        FROM financeiro_operacoes
        WHERE data >= DATE_SUB(CURDATE(), INTERVAL $meses MONTH)
        GROUP BY periodo ORDER BY periodo");
    return $rows;
}
