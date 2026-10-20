<?php
require_once __DIR__ . '/../config.php';

$page_title = 'Financeiro';
$page_sub = 'Trading, investimentos e renda · saldo atual informado + historico importado por data';
$active = 'financeiro';

$contas = fin_contas();
$contasTipo = fin_contas_por_tipo();
$ops = fin_operacoes(500);
$setups = db_all("SELECT * FROM financeiro_setups WHERE ativo = 1 ORDER BY nome");

$winRate = fin_win_rate($ops);
$pf = fin_profit_factor($ops);
$expect = fin_expectativa($ops);
$drawdown = fin_drawdown($ops);
$execucao = fin_execucao_perfeita($ops);
$adesao = fin_adesao_media($ops);
$variacao = fin_variacao_consolidada_pct();
$score = fin_score();

$patrimonio = fin_patrimonio_total();
$resultado = fin_resultado_total();
$saldoSerie = fin_saldos_historicos(90);
$importadas = fin_movimentos_importados(2000);
$setupStats = fin_setup_stats();

// Panorama do historico importado (mesmo motor da pagina de analise; sem filtros aqui)
$panF = array('de' => '', 'ate' => '', 'classe' => '', 'corretora' => '', 'ativo' => '', 'contrato' => '');
$panMovs = imp_movimentos($panF);
$panAn = imp_analisar($panMovs);
$panEvA = array();
$panEvF = array();
foreach ($panAn['eventos'] as $pe) {
    if ($pe['kind'] === 'acao') {
        $panEvA[] = $pe;
    } else {
        $panEvF[] = $pe;
    }
}
$panResA = imp_resumo_eventos($panEvA);
$panResF = imp_resumo_reais($panEvF);
$panConjunta = imp_linha_conjunta(imp_serie_realizada($panEvA), imp_serie_realizada_reais($panEvF));
$panLeituraA = imp_leitura_resumo($panResA, 'acao');
$panLeituraF = imp_leitura_resumo($panResF, 'futuro');
$panAbA = 0;
$panAbF = 0;
foreach (imp_posicoes_abertas($panAn['posicoes']) as $pa) {
    if ($pa['kind'] === 'futuro') {
        $panAbF++;
    } else {
        $panAbA++;
    }
}
$panMovA = 0;
$panMovF = 0;
foreach ($panMovs as $pm) {
    if (imp_e_futuro($pm)) {
        $panMovF++;
    } else {
        $panMovA++;
    }
}

$wins = 0;
$losses = 0;
$be = 0;
foreach ($ops as $o) {
    if ($o['resultado'] === 'win') {
        $wins++;
    } elseif ($o['resultado'] === 'loss') {
        $losses++;
    } else {
        $be++;
    }
}

// Tamanho da amostra e confianca da leitura estatistica
$amostra = count($ops);
if ($amostra >= 100) {
    $confianca = array('rotulo' => 'Suficiente', 'cor' => '#22c55e', 'nota' => 'Amostra >= 100 operacoes: as medias tendem a ser estaveis o suficiente para comparar metodos.');
} elseif ($amostra >= 30) {
    $confianca = array('rotulo' => 'Limitada', 'cor' => '#fbbf24', 'nota' => 'Amostra entre 30 e 99 operacoes: indicadores positivos ainda podem ser sorte. Compare com cuidado.');
} else {
    $confianca = array('rotulo' => 'Baixa', 'cor' => '#f87171', 'nota' => 'Amostra pequena (<' . 30 . ' operacoes): qualquer resultado pode ser acaso. Nao conclua nada sobre o metodo ainda.');
}

$buscaInicial = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$rotuloTipo = array('trading' => 'Trading', 'investimento' => 'Investimentos', 'renda_trabalho' => 'Renda do Trabalho');
$rotuloResultado = array('win' => 'Win', 'loss' => 'Loss', 'breakeven' => 'Breakeven');
$rotuloClasse = array('acao' => 'Acao/BDR/FII', 'indice' => 'Indice/Futuro', 'outro' => 'Outro');

/* ---------- Detalhamento (drill) dos KPIs do dashboard ---------- */
if (!function_exists('dash_fa')) {
    function dash_fa($v, $dec = 2, $sinal = false)
    {
        $n = (float)$v;
        $prefixo = $n < 0 ? '-' : ($sinal ? '+' : '');
        return '<span class="abs">' . $prefixo . 'R$ ' . number_format(abs($n), $dec, ',', '.') . '</span>';
    }
}
if (!function_exists('dash_fcor')) {
    function dash_fcor($v)
    {
        return (float)$v >= 0 ? ' style="color:var(--green)"' : ' style="color:var(--red)"';
    }
}
if (!function_exists('dash_row_fech')) {
    function dash_row_fech($e, $painel)
    {
        $fut = ($painel === 'Trading');
        $r = $fut ? (float)$e['realizado_reais'] : (float)$e['realizado'];
        return array(
            date('d/m/Y', strtotime($e['data'])),
            $painel,
            e($e['rotulo']),
            number_format($e['qtd'], 0, ',', '.'),
            number_format($e['preco'], 2, ',', '.'),
            number_format($e['pmp'], 2, ',', '.'),
            '<span' . dash_fcor($r) . '>' . dash_fa($r, 2, true) . '</span>',
            isset($e['aberta']) ? date('d/m/Y', strtotime($e['aberta'])) : '-',
            imp_e_daytrade($e) ? 'Day trade' : 'Swing',
        );
    }
}
if (!function_exists('dash_drill_payload')) {
    function dash_drill_payload($chave)
    {
        global $contas, $ops, $patrimonio, $resultado, $variacao, $winRate, $pf, $expect,
            $drawdown, $execucao, $amostra, $panMovs, $panAn, $panEvA, $panEvF,
            $panResA, $panResF, $panAbA, $panAbF, $rotuloTipo, $rotuloResultado;

        $p = array('titulo' => 'Origem dos dados', 'nota' => '', 'cols' => array(), 'rows' => array());

        if ($chave === 'd_patrimonio' || $chave === 'd_resultado' || $chave === 'd_variacao') {
            $p['cols'] = array('Conta', 'Tipo', 'Saldo inicial', 'Saldo atual', 'Resultado', 'Variacao');
            foreach ($contas as $c) {
                $delta = (float)$c['saldo_atual'] - (float)$c['saldo_inicial'];
                $vp = (float)$c['saldo_inicial'] > 0 ? ($delta / (float)$c['saldo_inicial']) * 100 : 0.0;
                $p['rows'][] = array(
                    e($c['nome']),
                    e(isset($rotuloTipo[$c['tipo']]) ? $rotuloTipo[$c['tipo']] : $c['tipo']),
                    dash_fa($c['saldo_inicial']),
                    dash_fa($c['saldo_atual']),
                    '<span' . dash_fcor($delta) . '>' . dash_fa($delta, 2, true) . '</span>',
                    '<span' . dash_fcor($vp) . '>' . number_format($vp, 2, ',', '.') . '%</span>',
                );
            }
            if ($chave === 'd_patrimonio') {
                $p['titulo'] = 'Origem: patrimonio total';
                $p['nota'] = 'Soma dos saldos atuais informados em Cadastros, por conta: <b>' . dash_fa($patrimonio) . '</b> no total.';
            } elseif ($chave === 'd_resultado') {
                $p['titulo'] = 'Origem: resultado';
                $p['nota'] = 'Saldo atual - saldo inicial de cada conta; somando as contas: <b>' . dash_fa($resultado, 2, true) . '</b>.';
            } else {
                $p['titulo'] = 'Origem: variacao consolidada';
                $p['nota'] = 'Variacao de cada conta (saldo atual vs inicial) e consolidado de <b>' . number_format($variacao, 2, ',', '.') . '%</b>.';
            }
            return $p;
        }

        if (in_array($chave, array('d_acerto', 'd_pf', 'd_expectativa', 'd_drawdown', 'd_execucao', 'd_amostra'), true)) {
            $corRes = array('win' => '#22c55e', 'loss' => '#f87171', 'breakeven' => '#94a3b8');
            $lista = $ops;
            if ($chave === 'd_drawdown') {
                usort($lista, function ($a, $b) {
                    if ($a['data'] === $b['data']) { return (int)$a['id'] - (int)$b['id']; }
                    return strcmp($a['data'], $b['data']);
                });
            }
            if ($chave === 'd_execucao') {
                usort($lista, function ($a, $b) {
                    $ad = (int)$a['adesao_plano'] - (int)$b['adesao_plano'];
                    return $ad !== 0 ? $ad : ((int)$a['id'] - (int)$b['id']);
                });
            }
            $comIndice = ($chave === 'd_drawdown');
            $p['cols'] = $comIndice
                ? array('Data', 'Conta', 'Setup', 'Direcao', 'Resultado', 'Risco %', 'Retorno %', 'Adesao', 'Indice', 'Notas')
                : array('Data', 'Conta', 'Setup', 'Direcao', 'Resultado', 'Risco %', 'Retorno %', 'Adesao', 'Notas');
            $indice = 100.0;
            foreach ($lista as $o) {
                $ret = (float)$o['retorno_pct'];
                $indice = $indice * (1 + $ret / 100);
                $linha = array(
                    date('d/m/Y', strtotime($o['data'])),
                    e($o['conta_nome'] ? $o['conta_nome'] : '—'),
                    e($o['setup_nome'] ? $o['setup_nome'] : '—'),
                    strtoupper($o['direcao']),
                    badge(isset($rotuloResultado[$o['resultado']]) ? $rotuloResultado[$o['resultado']] : $o['resultado'], isset($corRes[$o['resultado']]) ? $corRes[$o['resultado']] : '#94a3b8'),
                    number_format((float)$o['risco_pct'], 2, ',', '.'),
                    '<span' . dash_fcor($ret) . '>' . number_format($ret, 2, ',', '.') . '%</span>',
                    (int)$o['adesao_plano'] . '/10',
                    e($o['notas'] ? $o['notas'] : '—'),
                );
                if ($comIndice) {
                    array_splice($linha, 8, 0, array(number_format($indice, 2, ',', '.')));
                }
                $p['rows'][] = $linha;
            }
            $rotulos = array(
                'd_acerto' => array('Origem: taxa de acerto', '<b>' . number_format($winRate, 1, ',', '.') . '%</b> de acerto (wins / wins+losses) sobre as <b>' . $amostra . '</b> operacoes; breakevens ficam fora do calculo.'),
                'd_pf' => array('Origem: profit factor', 'Soma dos retornos positivos dividida pela soma dos negativos em valor absoluto: <b>' . number_format($pf, 2, ',', '.') . '</b>.'),
                'd_expectativa' => array('Origem: expectativa', 'E = (p_g x ganho medio) - (p_p x perda media) = <b>' . number_format($expect, 3, ',', '.') . ' pp</b> por operacao.'),
                'd_drawdown' => array('Origem: drawdown maximo', 'Maior queda do indice composto (base 100, coluna Indice) ate o maximo historico: <b>' . number_format($drawdown, 2, ',', '.') . '%</b>.'),
                'd_execucao' => array('Origem: executacao perfeita', '<b>' . number_format($execucao, 1, ',', '.') . '%</b> das operacoes com adesao ao plano 10/10 (ordenadas do menor para o maior).'),
                'd_amostra' => array('Origem: amostra', 'As <b>' . $amostra . '</b> operacoes manuais documentadas (limite de ' . count($ops) . ' carregadas da mais recente para a mais antiga).'),
            );
            $p['titulo'] = $rotulos[$chave][0];
            $p['nota'] = $rotulos[$chave][1];
            return $p;
        }

        if ($chave === 'd_lancamentos') {
            $p['cols'] = array('Data', 'Painel', 'Ativo', 'Operacao', 'Tipo', 'Qtd', 'Preco', 'Total', 'Corretora');
            $nOps = 0;
            foreach (imp_consolidar_operacoes($panMovs) as $cImp) {
                if ($cImp['op']) {
                    $nOps++;
                    $d = drill_op_resumo($cImp['a'], $cImp['b']);
                    $p['rows'][] = array(
                        date('d/m/Y', strtotime($d['data'])),
                        $d['fut'] ? 'Trading' : 'Investimento',
                        e($d['cod']),
                        e($d['rotulo']) . ' (zerou)',
                        'Operacao (2 lanc.)',
                        $d['fut'] ? '—' : number_format($d['qtd'], 2, ',', '.'),
                        dash_fa($d['preco_a']) . ' <span class="muted">&rarr;</span> ' . dash_fa($d['preco_b']),
                        $d['resultado'] === null ? '—' : '<span' . dash_fcor($d['resultado']) . '>' . dash_fa($d['resultado'], 2, true) . '</span>',
                        e($d['corretora']),
                    );
                } else {
                    $m = $cImp['a'];
                    $fut = imp_e_futuro($m);
                    $p['rows'][] = array(
                        date('d/m/Y', strtotime($m['data'])),
                        $fut ? 'Trading' : 'Investimento',
                        e($fut ? $m['ativo_nome'] : $m['ativo_codigo']),
                        e($m['operacao']),
                        $m['tipo'] === 'credito' ? 'Entrada' : 'Saida',
                        $fut ? '—' : number_format((float)$m['quantidade'], 2, ',', '.'),
                        dash_fa($m['preco_unitario']),
                        dash_fa($m['valor_total']),
                        e($m['corretora']),
                    );
                }
            }
            $p['titulo'] = 'Origem: lancamentos importados';
            $p['nota'] = '<b>' . count($panMovs) . '</b> lancamentos consolidados em <b>' . count($p['rows']) . '</b> linhas: <b>' . $nOps
                . '</b> operacao(oes) que zeraram a posicao no dia (futuros pareados por FIFO; acoes por par de mesma quantidade) + <b>'
                . (count($p['rows']) - $nOps) . '</b> lancamentos avulsos.';
            return $p;
        }

        if ($chave === 'd_periodo') {
            $p['cols'] = array('Mes', 'Lancamentos', 'Investimento', 'Trading');
            $porMes = array();
            foreach ($panMovs as $m) {
                $k = substr($m['data'], 0, 7);
                if (!isset($porMes[$k])) { $porMes[$k] = array('n' => 0, 'inv' => 0, 'trad' => 0); }
                $porMes[$k]['n']++;
                if (imp_e_futuro($m)) { $porMes[$k]['trad']++; } else { $porMes[$k]['inv']++; }
            }
            krsort($porMes);
            foreach ($porMes as $k => $v) {
                $p['rows'][] = array(
                    date('m/Y', strtotime($k . '-01')),
                    number_format($v['n'], 0, ',', '.'),
                    number_format($v['inv'], 0, ',', '.'),
                    number_format($v['trad'], 0, ',', '.'),
                );
            }
            $p['titulo'] = 'Origem: periodo importado';
            $p['nota'] = 'Lancamentos por mes, de <b>'
                . ($panAn['contadores']['min_data'] ? date('d/m/Y', strtotime($panAn['contadores']['min_data'])) : '-')
                . '</b> a <b>'
                . ($panAn['contadores']['max_data'] ? date('d/m/Y', strtotime($panAn['contadores']['max_data'])) : '-')
                . '</b> (' . (int)$panAn['contadores']['dias'] . ' dias com lancamento, ' . count($panMovs) . ' no total).';
            return $p;
        }

        if (in_array($chave, array('d_fechamentos', 'd_realizado', 'd_fator_inv', 'd_fator_trad'), true)) {
            $p['cols'] = array('Data', 'Painel', 'Ativo', 'Qtd', 'Preco', 'Preco medio', 'Realizado', 'Abertura', 'Tipo');
            $lista = array();
            foreach ($panEvA as $pe) { $lista[] = array('e' => $pe, 'painel' => 'Investimento', 'r' => (float)$pe['realizado']); }
            foreach ($panEvF as $pe) { $lista[] = array('e' => $pe, 'painel' => 'Trading', 'r' => (float)$pe['realizado_reais']); }
            if ($chave === 'd_fator_inv') {
                $lista = array_values(array_filter($lista, function ($x) { return $x['painel'] === 'Investimento'; }));
            }
            if ($chave === 'd_fator_trad') {
                $lista = array_values(array_filter($lista, function ($x) { return $x['painel'] === 'Trading'; }));
            }
            usort($lista, function ($a, $b) { return $b['r'] - $a['r']; });
            foreach ($lista as $x) {
                $p['rows'][] = dash_row_fech($x['e'], $x['painel']);
            }
            $notas = array(
                'd_fechamentos' => array('Origem: fechamentos importados',
                    '<b>' . count($panEvA) . '</b> fechamentos de investimento + <b>' . count($panEvF)
                    . '</b> de trading (pares que zeraram a posicao no motor de preco medio), do maior realizado para o menor.'),
                'd_realizado' => array('Origem: realizado consolidado',
                    'Investimento <b>' . dash_fa($panResA['liquido'], 2, true) . '</b> · Trading <b>' . dash_fa($panResF['liquido'], 2, true)
                    . '</b> somando a coluna Realizado de todos os fechamentos, do maior para o menor.'),
                'd_fator_inv' => array('Origem: fator lucro investimento',
                    'Fator <b>' . ($panResA['fator'] !== null ? number_format($panResA['fator'], 2, ',', '.') : 'sem perdas') . '</b> (soma de ganhos / soma de perdas) em <b>'
                    . (int)$panResA['n'] . '</b> fechamentos de investimento, do maior realizado para o menor.'),
                'd_fator_trad' => array('Origem: fator lucro trading',
                    'Fator <b>' . ($panResF['fator'] !== null ? number_format($panResF['fator'], 2, ',', '.') : 'sem perdas') . '</b> (soma de ganhos / soma de perdas) em <b>'
                    . (int)$panResF['n'] . '</b> fechamentos de trading, do maior realizado para o menor.'),
            );
            $p['titulo'] = $notas[$chave][0];
            $p['nota'] = $notas[$chave][1];
            return $p;
        }

        if ($chave === 'd_abertas') {
            $p['cols'] = array('Painel', 'Ativo', 'Classe', 'Lado', 'Qtd', 'Preco medio', 'Primeira', 'Ultima', 'Realizado parcial');
            foreach (imp_posicoes_abertas($panAn['posicoes']) as $pa) {
                $fut = ($pa['kind'] === 'futuro');
                $r = $fut ? (isset($pa['realizado_reais']) ? (float)$pa['realizado_reais'] : 0.0) : (float)$pa['realizado'];
                $p['rows'][] = array(
                    $fut ? 'Trading' : 'Investimento',
                    e($pa['rotulo']),
                    $fut ? 'Futuro' : e(isset($rotuloTipo[$pa['classe']]) ? $pa['classe'] : $pa['classe']),
                    $pa['dir'] > 0 ? 'Comprado' : 'Vendido',
                    number_format($pa['qtd'], 0, ',', '.') . ($fut ? ' contrato(s)' : ''),
                    number_format($pa['pmp'], 2, ',', '.'),
                    date('d/m/Y', strtotime($pa['primeira'])),
                    date('d/m/Y', strtotime($pa['ultima'])),
                    '<span' . dash_fcor($r) . '>' . dash_fa($r, 2, true) . '</span>',
                );
            }
            $p['titulo'] = 'Origem: posicoes abertas';
            $p['nota'] = '<b>' . ($panAbA + $panAbF) . '</b> posicoes (' . $panAbA . ' investimento / ' . $panAbF
                . ' trading) que nao zeraram no fim do recorte, com preco medio da pilha.';
            return $p;
        }

        return null;
    }
}

if (isset($_GET['drill']) && $_GET['drill'] !== '') {
    require_once __DIR__ . '/../includes/auth_check.php';
    header('Content-Type: application/json; charset=utf-8');
    $pl = dash_drill_payload((string)$_GET['drill']);
    if ($pl === null) {
        http_response_code(404);
        echo json_encode(array('erro' => 'Origem de dados desconhecida.'));
    } else {
        echo json_encode($pl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="barra-acoes">
    <span class="muted">O saldo atual e definido em Cadastros. A planilha importada traz o historico por data, sem somar os lancamentos antigos de novo. Use <strong>Valores absolutos</strong> no topo para ocultar os valores em R$ e manter apenas percentuais.</span>
    <a class="btn btn-neutro btn-mini" href="/ranksistem/pages/financeiro-analise.php"><?php echo icone('grafico', 16); ?> Analisar historico</a>
    <a class="btn btn-neutro btn-mini" href="/ranksistem/pages/financeiro-importar.php"><?php echo icone('mais', 16); ?> Importar planilha</a>
</div>

<section class="grid grid-stats">
    <?php
    card_stat(array('titulo' => 'Patrimonio total', 'valor' => '<span class="abs">R$ ' . number_format($patrimonio, 2, ',', '.') . '</span>', 'sub' => 'saldo atual informado', 'cor' => '#22c55e', 'icone' => 'carteira', 'drill' => 'd_patrimonio'));
    card_stat(array('titulo' => 'Resultado', 'valor' => '<span class="abs">' . ($resultado >= 0 ? '+' : '-') . 'R$ ' . number_format(abs($resultado), 2, ',', '.') . '</span>', 'sub' => 'sobre o valor base', 'cor' => $resultado >= 0 ? '#22c55e' : '#f87171', 'icone' => 'grafico', 'drill' => 'd_resultado'));
    card_stat(array('titulo' => 'Variacao consolidada', 'valor' => ($variacao >= 0 ? '+' : '') . number_format($variacao, 2, ',', '.') . '%', 'sub' => 'saldo atual vs base', 'cor' => '#38bdf8', 'icone' => 'grafico', 'delta' => $variacao, 'drill' => 'd_variacao'));
    card_stat(array('titulo' => 'Taxa de acerto', 'valor' => number_format($winRate, 1, ',', '.') . '%', 'sub' => 'win rate', 'cor' => '#38bdf8', 'icone' => 'alvo', 'drill' => 'd_acerto'));
    card_stat(array('titulo' => 'Profit factor', 'valor' => number_format($pf, 2, ',', '.'), 'sub' => 'ganhos / perdas', 'cor' => '#a78bfa', 'icone' => 'grafico', 'drill' => 'd_pf'));
    card_stat(array('titulo' => 'Expectativa', 'valor' => ($expect >= 0 ? '+' : '') . number_format($expect, 2, ',', '.') . ' pp', 'sub' => 'por operacao', 'cor' => '#fbbf24', 'icone' => 'raio', 'drill' => 'd_expectativa'));
    card_stat(array('titulo' => 'Drawdown maximo', 'valor' => number_format($drawdown, 2, ',', '.') . '%', 'sub' => 'maior queda', 'cor' => '#f87171', 'icone' => 'grafico', 'drill' => 'd_drawdown'));
    card_stat(array('titulo' => 'Execucao perfeita', 'valor' => number_format($execucao, 1, ',', '.') . '%', 'sub' => 'adesao total', 'cor' => '#2dd4bf', 'icone' => 'check', 'drill' => 'd_execucao'));
    card_stat(array('titulo' => 'Amostra', 'valor' => (int)$amostra . ' ops', 'sub' => 'operacoes documentadas', 'cor' => '#8ba0c4', 'icone' => 'livro', 'drill' => 'd_amostra'));
    card_stat(array('titulo' => 'Confianca da leitura', 'valor' => $confianca['rotulo'], 'sub' => 'suficiencia da amostra', 'cor' => $confianca['cor'], 'icone' => 'cerebro'));
    ?>
</section>

<section class="grid">
    <?php
    ob_start();
    echo '<div class="aviso-amostra" style="--cor:' . $confianca['cor'] . '">' . icone('cerebro', 18)
        . '<span><strong>' . e($confianca['nota']) . '</strong> Um resultado positivo observado nao garante que o metodo continue funcionando.</span></div>';
    echo '<div class="formula-item" style="margin-top:12px"><strong>Expectativa historica (E)</strong>'
        . '<span>E = (p_g x ganho medio) - (p_p x perda media), com a mesma unidade e ja descontados os custos da operacao.</span>'
        . '<code>E = (' . number_format($wins / max(1, $wins + $losses), 3, ',', '') . ' x ganho_medio) - (' . number_format($losses / max(1, $wins + $losses), 3, ',', '') . ' x perda_media)</code></div>';
    $conteudo = ob_get_clean();
    card_bloco('Amostra, confianca e expectativa', $conteudo, '#8ba0c4', 'cerebro');
    ?>
</section>

<?php if (!empty($panMovs)) { ?>
    <section class="grid grid-stats">
        <?php
        card_stat(array(
            'titulo' => 'Lancamentos importados',
            'valor' => number_format(count($panMovs), 0, ',', '.'),
            'sub' => number_format($panMovA, 0, ',', '.') . ' investimento / ' . number_format($panMovF, 0, ',', '.') . ' trading',
            'cor' => '#a78bfa',
            'icone' => 'livro',
            'drill' => 'd_lancamentos',
        ));
        card_stat(array(
            'titulo' => 'Periodo importado',
            'valor' => ($panAn['contadores']['min_data'] ? date('d/m/Y', strtotime($panAn['contadores']['min_data'])) : '-') . ' a ' . ($panAn['contadores']['max_data'] ? date('d/m/Y', strtotime($panAn['contadores']['max_data'])) : '-'),
            'sub' => (int)$panAn['contadores']['dias'] . ' dias com lancamento',
            'cor' => '#38bdf8',
            'icone' => 'grafico',
            'drill' => 'd_periodo',
        ));
        card_stat(array(
            'titulo' => 'Fechamentos importados',
            'valor' => number_format(count($panAn['eventos']), 0, ',', '.'),
            'sub' => (int)$panResA['n'] . ' investimento / ' . (int)$panResF['n'] . ' trading',
            'cor' => '#fbbf24',
            'icone' => 'check',
            'drill' => 'd_fechamentos',
        ));
        card_stat(array(
            'titulo' => 'Realizado (os dois topicos)',
            'valor' => ($panResA['liquido'] >= 0 ? '+' : '-') . '<span class="abs">R$ ' . number_format(abs($panResA['liquido']), 2, ',', '.') . '</span> <span class="muted">|</span> ' . ($panResF['liquido'] >= 0 ? '+' : '-') . '<span class="abs">R$ ' . number_format(abs($panResF['liquido']), 2, ',', '.') . '</span>',
            'sub' => 'investimento e trading em R$',
            'cor' => '#e879f9',
            'icone' => 'grafico',
            'drill' => 'd_realizado',
        ));
        card_stat(array(
            'titulo' => 'Fator lucro investimento',
            'valor' => $panResA['fator'] !== null ? number_format($panResA['fator'], 2, ',', '.') : 'sem perdas',
            'sub' => 'acerto ' . ($panResA['win_rate'] !== null ? number_format($panResA['win_rate'], 1, ',', '.') . '%' : '-'),
            'cor' => '#22c55e',
            'icone' => 'grafico',
            'drill' => 'd_fator_inv',
        ));
        card_stat(array(
            'titulo' => 'Fator lucro trading',
            'valor' => $panResF['fator'] !== null ? number_format($panResF['fator'], 2, ',', '.') : 'sem perdas',
            'sub' => 'acerto ' . ($panResF['win_rate'] !== null ? number_format($panResF['win_rate'], 1, ',', '.') . '%' : '-'),
            'cor' => '#a78bfa',
            'icone' => 'grafico',
            'drill' => 'd_fator_trad',
        ));
        card_stat(array(
            'titulo' => 'Posicoes abertas',
            'valor' => $panAbA + $panAbF,
            'sub' => $panAbA . ' investimento / ' . $panAbF . ' trading',
            'cor' => '#f472b6',
            'icone' => 'carteira',
            'drill' => 'd_abertas',
        ));
        ?>
    </section>

    <section class="grid grid-2">
        <?php
        ob_start();
        ?>
        <canvas id="chartPanorama" height="260"></canvas>
        <?php
        $conteudo = ob_get_clean();
        card_bloco('Panorama do historico importado (curvas acumuladas)', $conteudo, '#e879f9', 'grafico', 'R$ no eixo esquerdo · trading convertido em R$');
        ?>

        <?php
        ob_start();
        echo '<div class="formula-lista">';
        echo '<div class="formula-item" style="border-left:4px solid #22c55e"><strong>Investimento (acoes, BDR, FIIs)</strong>'
            . '<span>' . e($panLeituraA) . '</span></div>';
        echo '<div class="formula-item" style="border-left:4px solid #a78bfa"><strong>Trading (futuros e indices)</strong>'
            . '<span>' . e($panLeituraF) . '</span></div>';
        echo '<div class="formula-item" style="border-left:4px solid #fbbf24"><strong>Conversao de pontos em R$ (B3)</strong>'
            . '<span>WIN (mini indice) R$ 0,20/pt · WDO (mini dolar) R$ 10,00/pt · BIT (mini bitcoin) R$ 0,01/pt. Conversao: R$ = pontos x valor_do_ponto x 1 contrato (a planilha nao traz quantidade de contratos).</span></div>';
        echo '</div>';
        echo '<p style="margin-top:12px"><a class="btn btn-neutro btn-mini" href="/ranksistem/pages/financeiro-analise.php">Abrir a analise completa (funil) &rarr;</a></p>';
        $conteudo = ob_get_clean();
        card_bloco('Leitura automatica dos dois topicos', $conteudo, '#38bdf8', 'cerebro', 'mesmo motor da analise');
        ?>
    </section>
<?php } ?>

<section class="grid grid-2">
    <?php
    ob_start();
    echo '<div class="contas-lista">';
    foreach ($contas as $c) {
        $ind = fin_indice_conta($c['saldo_atual'], $c['saldo_inicial']);
        $deltaR = (float)$c['saldo_atual'] - (float)$c['saldo_inicial'];
        $cor = $c['tipo'] === 'trading' ? '#22c55e' : ($c['tipo'] === 'investimento' ? '#38bdf8' : '#fbbf24');
        echo '<div class="conta-item" style="--cor:' . $cor . '">'
            . '<div class="conta-info"><span class="conta-tipo">' . e(isset($rotuloTipo[$c['tipo']]) ? $rotuloTipo[$c['tipo']] : $c['tipo']) . '</span>'
            . '<strong>' . e($c['nome']) . '</strong>'
            . '<span class="conta-data">' . ($c['data_saldo'] ? 'saldo de ' . date('d/m/Y', strtotime($c['data_saldo'])) : 'saldo atual') . '</span></div>'
            . '<div class="conta-valores">'
            . '<span class="conta-saldo abs">R$ ' . number_format((float)$c['saldo_atual'], 2, ',', '.') . '</span>'
            . '<span class="conta-delta ' . ($deltaR >= 0 ? 'up' : 'down') . '"><span class="abs">' . ($deltaR >= 0 ? '+' : '-') . 'R$ ' . number_format(abs($deltaR), 2, ',', '.') . '</span> · ' . ($ind['variacao_pct'] >= 0 ? '+' : '') . number_format($ind['variacao_pct'], 2, ',', '.') . '%</span>'
            . '</div>'
            . '<div class="conta-indice"><span class="indice-num">' . number_format($ind['indice'], 1, ',', '.') . '</span>'
            . '<span class="indice-legenda">indice</span></div>'
            . '</div>';
    }
    if (empty($contas)) {
        echo '<p class="muted">Nenhuma conta cadastrada. Vá em Cadastros.</p>';
    }
    echo '</div>';
    $conteudo = ob_get_clean();
    card_bloco('Suas contas (saldo atual)', $conteudo, '#22c55e', 'carteira');
    ?>

    <?php
    ob_start();
    ?>
    <canvas id="chartEquity" height="220"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Evolucao do patrimonio (R$)', $conteudo, '#2dd4bf', 'grafico');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <canvas id="chartSetups" height="180"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Desempenho por setup', $conteudo, '#a78bfa', 'alvo');
    ?>

    <?php
    ob_start();
    ?>
    <canvas id="chartWinLoss" height="180"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Distribuicao de resultados', $conteudo, '#38bdf8', 'grafico');
    ?>
</section>

<section class="grid">
    <?php
    ob_start();
    if (empty($setupStats)) {
        echo '<p class="muted">Nenhum metodo cadastrado. Documente as regras antes de avaliar resultados.</p>';
    } else {
        echo '<div class="lab-grid">';
        foreach ($setupStats as $s) {
            $cor = $s['soma_retorno'] >= 0 ? '#22c55e' : '#f87171';
            $total = (int)$s['total'];
            if ($total >= 100) {
                $evi = array('Evidencia razoavel', '#22c55e');
            } elseif ($total >= 30) {
                $evi = array('Evidencia limitada', '#fbbf24');
            } else {
                $evi = array('Evidencia insuficiente', '#f87171');
            }

            $setupRow = null;
            foreach ($setups as $st) {
                if ((int)$st['id'] === (int)$s['id']) {
                    $setupRow = $st;
                    break;
                }
            }
            $versao = ($setupRow && isset($setupRow['versao'])) ? (int)$setupRow['versao'] : 1;
            $setupId = (int)$s['id'];

            echo '<div class="metodo-item" id="metodo-' . $setupId . '">'
                . '<div class="metodo-head"><strong>' . e($s['nome']) . '</strong>'
                . '<span class="metodo-versao">v' . $versao . '</span></div>'
                . '<div class="metodo-meta">'
                . '<span>' . $total . ' obs.</span>'
                . '<span>WR ' . number_format($s['win_rate'], 0) . '%</span>'
                . '<span>Adesao ' . number_format($s['media_adesao'], 1, ',', '.') . '/10</span>'
                . '<span style="color:' . $cor . '">' . ($s['soma_retorno'] >= 0 ? '+' : '') . number_format($s['soma_retorno'], 2, ',', '.') . ' pp</span>'
                . '</div>'
                . '<div class="meta-badges">'
                . badge($evi[0], $evi[1])
                . ($total < 30 ? badge('Amostra pequena', '#f87171') : '')
                . '</div>'
                . '<div class="meta-acoes">'
                . '<a class="link-mini" href="/ranksistem/pages/financeiro.php?q=' . urlencode($s['nome']) . '">ver operacoes &rarr;</a>';
            if ($setupRow) {
                echo '<a class="link-mini" href="#" onclick="var el=document.getElementById(\'regras-' . $setupId . '\'); if(el){el.hidden=!el.hidden;} return false;">editar regras</a>';
            }
            echo '</div>';
            if ($setupRow) {
                echo '<div id="regras-' . (int)$setupRow['id'] . '" hidden>'
                    . '<pre class="metodo-regras">' . e($setupRow['regras']) . '</pre>'
                    . '<form id="lab-' . (int)$setupRow['id'] . '" class="metodo-form" method="post" action="/ranksistem/controllers/financeiroController.php">'
                    . '<input type="hidden" name="acao" value="upd_setup">'
                    . '<input type="hidden" name="id" value="' . (int)$setupRow['id'] . '">'
                    . '<div class="campo"><label>Regras do metodo (ao salvar, uma nova versao e criada)</label>'
                    . '<textarea name="regras" rows="4">' . e($setupRow['regras']) . '</textarea></div>'
                    . '<button type="submit" class="btn btn-neutro btn-mini">Salvar como nova versao</button>'
                    . '</form></div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    $conteudo = ob_get_clean();
    card_bloco('Laboratorio de metodos', $conteudo, '#fbbf24', 'raio', 'planos versionados');
    ?>
</section>

<section class="grid">
    <?php
    ob_start();

    // Importados consolidados por operacao: pares entrada+saida (mesmo dia/ativo)
    // viram 1 linha "op"; o resto entra avulso (mesma regra da analise de importados).
    $historico = array();
    foreach ($ops as $o) {
        $historico[] = array('data' => $o['data'], 'ordem' => (int)$o['id'], 'tipo' => 'manual', 'dados' => $o);
    }
    foreach (imp_consolidar_operacoes(array_reverse($importadas)) as $cImp) {
        if ($cImp['op']) {
            $dImp = drill_op_resumo($cImp['a'], $cImp['b']);
            $historico[] = array(
                'data' => $dImp['data'],
                'ordem' => max((int)$cImp['a']['id'], (int)$cImp['b']['id']),
                'tipo' => 'importado_op',
                'dados' => array('d' => $dImp, 'a' => $cImp['a'], 'conta_nome' => $cImp['a']['conta_nome']),
            );
        } else {
            $historico[] = array('data' => $cImp['a']['data'], 'ordem' => (int)$cImp['a']['id'], 'tipo' => 'importado', 'dados' => $cImp['a']);
        }
    }
    usort($historico, function ($a, $b) {
        if ($a['data'] === $b['data']) {
            if ($a['tipo'] !== $b['tipo']) {
                return ($a['tipo'] === 'importado' || $a['tipo'] === 'importado_op') ? 1 : -1;
            }
            return $b['ordem'] - $a['ordem'];
        }
        return strcmp($b['data'], $a['data']);
    });

    $rows = array();
    foreach ($historico as $h) {
        if ($h['tipo'] === 'manual') {
            $o = $h['dados'];
            $corRes = $o['resultado'] === 'win' ? '#22c55e' : ($o['resultado'] === 'loss' ? '#f87171' : '#94a3b8');
            $rows[] = array(
                date('d/m/Y', strtotime($o['data'])),
                badge('Manual', '#38bdf8'),
                e($o['conta_nome']),
                e($o['setup_nome'] ? $o['setup_nome'] : '—'),
                strtoupper($o['direcao']),
                '—',
                '—',
                '—',
                badge(isset($rotuloResultado[$o['resultado']]) ? $rotuloResultado[$o['resultado']] : $o['resultado'], $corRes),
                '<a class="link-del" href="/ranksistem/controllers/financeiroController.php?acao=del_operacao&id=' . (int)$o['id'] . '" onclick="return confirm(\'Remover operacao?\')">' . icone('lixo', 16) . '</a>'
            );
        } elseif ($h['tipo'] === 'importado_op') {
            // operacao consolidada (par entrada+saida do mesmo dia/ativo): 1 linha com preco de entrada -> saida e resultado
            $hD = $h['dados'];
            $dOp = $hD['d'];
            $corOp = $dOp['resultado'] !== null && $dOp['resultado'] >= 0 ? ' style="color:var(--green)"' : ' style="color:var(--red)"';
            $rows[] = array(
                date('d/m/Y', strtotime($dOp['data'])),
                badge('Importado', '#a78bfa'),
                e($hD['conta_nome']),
                '<strong>' . e($dOp['cod']) . '</strong><br><span class="muted">' . e($dOp['nome']) . '</span>',
                e($dOp['rotulo']) . ' (zerou)',
                $dOp['fut'] ? '—' : number_format($dOp['qtd'], 2, ',', '.'),
                '<span class="abs">R$ ' . number_format($dOp['preco_a'], 2, ',', '.') . '</span> <span class="muted">&rarr;</span> <span class="abs">R$ ' . number_format($dOp['preco_b'], 2, ',', '.') . '</span>',
                $dOp['resultado'] === null ? '—' : '<span' . $corOp . '><span class="abs">R$ ' . ($dOp['resultado'] >= 0 ? '+' : '-') . number_format(abs($dOp['resultado']), 2, ',', '.') . '</span></span>',
                badge('Operacao', '#38bdf8'),
                '<a class="link-mini" href="/ranksistem/pages/financeiro-importar.php">ver lote</a>'
            );
        } else {
            $m = $h['dados'];
            $corTipo = $m['tipo'] === 'credito' ? '#22c55e' : '#f87171';
            $corClasse = $m['classe'] === 'indice' ? '#22c55e' : '#38bdf8';
            $rows[] = array(
                date('d/m/Y', strtotime($m['data'])),
                badge('Importado', '#a78bfa'),
                e($m['conta_nome']),
                '<strong>' . e($m['ativo_codigo']) . '</strong><br><span class="muted">' . e($m['ativo_nome']) . '</span>',
                badge($m['tipo'] === 'credito' ? 'Credito' : 'Debito', $corTipo),
                number_format((float)$m['quantidade'], 2, ',', '.'),
                '<span class="abs">R$ ' . number_format((float)$m['preco_unitario'], 2, ',', '.') . '</span>',
                '<span class="abs">R$ ' . number_format((float)$m['valor_total'], 2, ',', '.') . '</span>',
                badge(isset($rotuloClasse[$m['classe']]) ? $rotuloClasse[$m['classe']] : $m['classe'], $corClasse),
                '<a class="link-mini" href="/ranksistem/pages/financeiro-importar.php">ver lote</a>'
            );
        }
    }
    tabela(array('Data', 'Origem', 'Conta', 'Ativo / Setup', 'Operacao', 'Qtd', 'Preco', 'Total', 'Resultado / Classe', ''), $rows, 'Nenhuma operacao registrada ou importada.', array('data_table' => true, 'id' => 'tabelaOperacoes', 'placeholder' => 'Pesquisar por ativo, conta, data, origem...', 'busca' => $buscaInicial));
    $conteudo = ob_get_clean();
    card_bloco('Historico de operacoes (manual + importado)', $conteudo, '#a78bfa', 'grafico');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/financeiroController.php" class="form-grid">
        <input type="hidden" name="acao" value="add_operacao">
        <?php
        $optsContas = array();
        foreach ($contas as $c) {
            $optsContas[$c['id']] = $c['nome'];
        }
        form_campo(array('name' => 'conta_id', 'label' => 'Conta', 'type' => 'select', 'options' => $optsContas, 'required' => true));
        form_campo(array('name' => 'data', 'label' => 'Data', 'type' => 'date', 'value' => date('Y-m-d'), 'required' => true));
        $optsSetups = array(0 => 'Sem setup');
        foreach ($setups as $s) {
            $optsSetups[$s['id']] = $s['nome'];
        }
        form_campo(array('name' => 'setup_id', 'label' => 'Setup / Plano', 'type' => 'select', 'options' => $optsSetups));
        form_campo(array('name' => 'direcao', 'label' => 'Direcao', 'type' => 'select', 'options' => array('long' => 'Long (compra)', 'short' => 'Short (venda)')));
        form_campo(array('name' => 'resultado', 'label' => 'Resultado', 'type' => 'select', 'options' => array('win' => 'Win', 'loss' => 'Loss', 'breakeven' => 'Breakeven')));
        form_campo(array('name' => 'risco_pct', 'label' => 'Risco (% do capital)', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'value' => '1', 'required' => true));
        form_campo(array('name' => 'retorno_pct', 'label' => 'Retorno (% do capital)', 'type' => 'number', 'step' => '0.01', 'min' => 0, 'value' => '2', 'required' => true));
        form_campo(array('name' => 'adesao_plano', 'label' => 'Adesao ao plano (0-10)', 'type' => 'number', 'min' => 0, 'max' => 10, 'value' => '10', 'required' => true));
        form_campo(array('name' => 'notas', 'label' => 'Notas / aprendizado', 'type' => 'text', 'placeholder' => 'O que funcionou ou falhou'));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Registrar operacao</button>
        </div>
    </form>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Diario de operacoes (Trade Log)', $conteudo, '#22c55e', 'carteira');
    ?>

    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/financeiroController.php" class="form-grid">
        <input type="hidden" name="acao" value="add_movimento">
        <?php
        form_campo(array('name' => 'conta_id', 'label' => 'Conta', 'type' => 'select', 'options' => $optsContas, 'required' => true));
        form_campo(array('name' => 'data', 'label' => 'Data', 'type' => 'date', 'value' => date('Y-m-d'), 'required' => true));
        form_campo(array('name' => 'tipo', 'label' => 'Tipo', 'type' => 'select', 'options' => array('aporte' => 'Aporte (entrada)', 'retirada' => 'Retirada (saida)')));
        form_campo(array('name' => 'valor', 'label' => 'Valor (R$)', 'type' => 'text', 'value' => '0,00', 'required' => true));
        form_campo(array('name' => 'descricao', 'label' => 'Descricao', 'type' => 'text', 'placeholder' => 'Opcional'));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-neutro">Registrar movimento</button>
        </div>
    </form>
    <p class="muted">Aportes e retiradas ajustam o saldo atual. Para redefinir o saldo real, use Cadastros → Definir saldo atual.</p>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Movimentos (aporte / retirada)', $conteudo, '#38bdf8', 'carteira');
    ?>
</section>

<?php drill_modal(); ?>

<script>
    window.RANK_CHARTS = [{
            id: 'chartPanorama',
            privado: true,
            config: {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_map(function ($c) {
                                return date('d/m', strtotime($c['data']));
                            }, $panConjunta)); ?>,
                    datasets: [{
                            label: 'Investimento acumulado (R$)',
                            data: <?php echo json_encode(array_map(function ($c) {
                                        return $c['investimento'] === null ? null : round($c['investimento'], 2);
                                    }, $panConjunta)); ?>,
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34,197,94,.12)',
                            fill: true,
                            tension: .3,
                            borderWidth: 3,
                            pointRadius: 0,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Trading acumulado (R$)',
                            data: <?php echo json_encode(array_map(function ($c) {
                                        return $c['trading'] === null ? null : round($c['trading'], 2);
                                    }, $panConjunta)); ?>,
                            borderColor: '#a78bfa',
                            backgroundColor: 'rgba(167,139,250,.10)',
                            fill: true,
                            tension: .3,
                            borderWidth: 3,
                            pointRadius: 0,
                            borderDash: [7, 4],
                            yAxisID: 'y'
                        }
                    ]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            position: 'left',
                            title: {
                                display: true,
                                text: 'R$ (acumulado)'
                            }
                        }
                    }
                }
            }
        },
        {
            id: 'chartEquity',
            privado: true,
            config: {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_map(function ($p) {
                                return $p['data'] === 'inicio' ? 'inicio' : date('d/m', strtotime($p['data']));
                            }, $saldoSerie)); ?>,
                    datasets: [{
                        label: 'Patrimonio (R$)',
                        data: <?php echo json_encode(array_map(function ($p) {
                                    return $p['valor'];
                                }, $saldoSerie)); ?>,
                        borderColor: '#2dd4bf',
                        backgroundColor: 'rgba(45,212,191,.15)',
                        fill: true,
                        tension: .3,
                        borderWidth: 3,
                        pointRadius: 0
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            }
        },
        {
            id: 'chartSetups',
            config: {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function ($s) {
                                return $s['nome'];
                            }, $setupStats)); ?>,
                    datasets: [{
                        label: 'Retorno acumulado (pp)',
                        data: <?php echo json_encode(array_map(function ($s) {
                                    return $s['soma_retorno'];
                                }, $setupStats)); ?>,
                        backgroundColor: <?php echo json_encode(array_map(function ($s) {
                                                return $s['soma_retorno'] >= 0 ? '#22c55e' : '#f87171';
                                            }, $setupStats)); ?>,
                        borderRadius: 8
                    }]
                },
                options: {
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            }
        },
        {
            id: 'chartWinLoss',
            config: {
                type: 'doughnut',
                data: {
                    labels: ['Win', 'Loss', 'Breakeven'],
                    datasets: [{
                        data: [<?php echo $wins; ?>, <?php echo $losses; ?>, <?php echo $be; ?>],
                        backgroundColor: ['#22c55e', '#f87171', '#94a3b8'],
                        borderWidth: 0
                    }]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    },
                    cutout: '62%'
                }
            }
        }
    ];
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>