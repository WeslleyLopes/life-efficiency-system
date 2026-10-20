<?php
// analise_importados_helper.php - analise estatistica dos lancamentos importados
//
// Convencoes dos dados importados (extrato de corretora):
//   tipo 'credito' = ENTRADA de ativo (aumenta a posicao)
//   tipo 'debito'  = SAIDA de ativo (diminui a posicao)
//   acoes/BDR/FII: quantidade e valor em R$
//   futuros: ativo_codigo 'Futuro', quantidade e valor 0, so preco -> resultado em pontos
//   Resultado realizado = (preco da saida - preco medio ponderado) x qtd x sentido da posicao
//   O preco medio (PMP) e recalculado a cada entrada; a saida zera a posicao quando consome tudo.

define('IMP_EPS', 0.000001);
define('IMP_TOL', 0.01);

/* ---------------------------------------------------------------- filtros */

function imp_filtros()
{
    $f = array('de' => '', 'ate' => '', 'classe' => '', 'corretora' => '', 'ativo' => '', 'contrato' => '');

    $de = isset($_GET['de']) ? trim((string)$_GET['de']) : '';
    $ate = isset($_GET['ate']) ? trim((string)$_GET['ate']) : '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) { $f['de'] = $de; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) { $f['ate'] = $ate; }

    $classe = isset($_GET['classe']) ? strtolower(trim((string)$_GET['classe'])) : '';
    if (in_array($classe, array('acao', 'indice', 'outro'), true)) { $f['classe'] = $classe; }

    $corretora = isset($_GET['corretora']) ? substr(trim((string)$_GET['corretora']), 0, 120) : '';
    if ($corretora !== '') { $f['corretora'] = $corretora; }

    $ativo = isset($_GET['ativo']) ? strtoupper(substr(trim((string)$_GET['ativo']), 0, 40)) : '';
    if ($ativo !== '') { $f['ativo'] = $ativo; }

    $contrato = isset($_GET['contrato']) ? strtoupper(substr(trim((string)$_GET['contrato']), 0, 40)) : '';
    if ($contrato !== '') { $f['contrato'] = $contrato; }

    return $f;
}

function imp_where($f, $alias = 'm')
{
    $p = array();
    if ($f['de'] !== '') { $p[] = $alias . ".data >= '" . db_escape($f['de']) . "'"; }
    if ($f['ate'] !== '') { $p[] = $alias . ".data <= '" . db_escape($f['ate']) . "'"; }
    if ($f['classe'] !== '') { $p[] = $alias . ".classe = '" . db_escape($f['classe']) . "'"; }
    if ($f['corretora'] !== '') { $p[] = $alias . ".corretora = '" . db_escape($f['corretora']) . "'"; }
    if ($f['ativo'] !== '') { $p[] = $alias . ".ativo_codigo = '" . db_escape($f['ativo']) . "'"; }
    if ($f['contrato'] !== '') { $p[] = $alias . ".ativo_nome = '" . db_escape($f['contrato']) . "'"; }
    return $p ? implode(' AND ', $p) : '1=1';
}

function imp_movimentos($f)
{
    $where = imp_where($f);
    return db_all("SELECT m.* FROM financeiro_ativos_movimentos m WHERE $where ORDER BY m.data ASC, m.id ASC");
}

// Consolida em "operacao" pares que zeram a posicao no dia.
// A ordem do arquivo e cronologica (a maioria dos grupos com 4+ lancamentos intercala
// compra/venda): futuros usam pilha FIFO por dia+contrato+corretora (1 contrato por
// lancamento) — cada saida fecha a entrada mais antiga em aberto, e vice-versa.
// Acoes continuam com par consecutivo da mesma quantidade (lote fechado).
// Uso visual apenas: o motor de preco medio continua linha a linha.
function imp_consolidar_operacoes($movs)
{
    $movs = array_values($movs);
    usort($movs, function ($x, $y) {
        if ($x['data'] === $y['data']) { return (int)$x['id'] - (int)$y['id']; }
        return strcmp((string)$x['data'], (string)$y['data']);
    });
    $out = array();
    $pilhas = array();
    $n = count($movs);
    for ($i = 0; $i < $n; $i++) {
        $a = $movs[$i];
        if (!imp_e_futuro($a)) {
            $b = null;
            if ($i + 1 < $n) {
                $c = $movs[$i + 1];
                $mesmoCodigo = (string)$a['ativo_codigo'] === (string)$c['ativo_codigo'];
                $mesmaQtd = (float)$a['quantidade'] > 0 && abs((float)$a['quantidade'] - (float)$c['quantidade']) < 0.000001;
                if (!imp_e_futuro($c)
                    && (string)$a['data'] === (string)$c['data']
                    && (string)$a['tipo'] !== (string)$c['tipo']
                    && (string)$a['corretora'] === (string)$c['corretora']
                    && $mesmoCodigo && $mesmaQtd) {
                    $b = $c;
                }
            }
            if ($b !== null) {
                $out[] = array('op' => true, 'a' => $a, 'b' => $b);
                $i++;
            } else {
                $out[] = array('op' => false, 'a' => $a);
            }
            continue;
        }
        // futuros: FIFO — a abertura ocupa sua posicao na lista; o fechamento
        // substitui a linha da abertura pelo par (mesma ordem de exibicao de antes)
        $chave = $a['data'] . '|' . $a['ativo_nome'] . '|' . $a['corretora'];
        if (!isset($pilhas[$chave])) {
            $pilhas[$chave] = array('long' => array(), 'short' => array());
        }
        if ($a['tipo'] === 'credito') {
            if (count($pilhas[$chave]['short']) > 0) {
                $aberta = array_shift($pilhas[$chave]['short']); // venda aberta fechada por esta compra (short)
                $out[$aberta['idx']] = array('op' => true, 'a' => $aberta['m'], 'b' => $a);
            } else {
                $pilhas[$chave]['long'][] = array('m' => $a, 'idx' => count($out));
                $out[] = array('op' => false, 'a' => $a);
            }
        } else {
            if (count($pilhas[$chave]['long']) > 0) {
                $aberta = array_shift($pilhas[$chave]['long']); // compra aberta fechada por esta venda (long)
                $out[$aberta['idx']] = array('op' => true, 'a' => $aberta['m'], 'b' => $a);
            } else {
                $pilhas[$chave]['short'][] = array('m' => $a, 'idx' => count($out));
                $out[] = array('op' => false, 'a' => $a);
            }
        }
    }
    return $out;
}

// Resumo de uma operacao consolidada (par entrada+saida do mesmo dia/ativo).
// Devolve cod/rotulo/precos/resultado (futuro: pontos x valor do ponto B3; acao: (saida-entrada) x qtd).
function drill_op_resumo($a, $b)
{
    $fut = imp_e_futuro($a);
    $cod = $fut ? $a['ativo_nome'] : $a['ativo_codigo'];
    $resultado = null;
    if ($fut) {
        $vp = imp_valor_ponto($cod);
        $pt = ($a['tipo'] === 'credito')
            ? ((float)$b['preco_unitario'] - (float)$a['preco_unitario'])
            : ((float)$a['preco_unitario'] - (float)$b['preco_unitario']);
        $resultado = ($vp === null) ? null : $pt * (float)$vp;
    } else {
        $pe = null;
        $ps = null;
        foreach (array($a, $b) as $r) {
            if ($r['tipo'] === 'credito') {
                $pe = (float)$r['preco_unitario'];
            } else {
                $ps = (float)$r['preco_unitario'];
            }
        }
        if ($pe !== null && $ps !== null) {
            $resultado = ($ps - $pe) * (float)$a['quantidade'];
        }
    }
    return array(
        'fut' => $fut, 'cod' => $cod, 'nome' => $a['ativo_nome'], 'corretora' => $a['corretora'],
        'data' => $b['data'], 'rotulo' => $a['operacao'] . ' + ' . $b['operacao'],
        'preco_a' => (float)$a['preco_unitario'], 'preco_b' => (float)$b['preco_unitario'],
        'qtd' => $fut ? 1 : (float)$a['quantidade'], 'resultado' => $resultado,
    );
}

function imp_opcoes()
{
    $corretoras = db_all("SELECT DISTINCT corretora FROM financeiro_ativos_movimentos WHERE corretora <> '' ORDER BY corretora");
    $ativos = db_all("SELECT DISTINCT ativo_codigo FROM financeiro_ativos_movimentos WHERE ativo_codigo <> 'Futuro' AND valor_total > 0 ORDER BY ativo_codigo");
    $contratos = db_all("SELECT ativo_nome FROM financeiro_ativos_movimentos WHERE ativo_nome <> '' AND quantidade = 0 GROUP BY ativo_nome ORDER BY MAX(data) DESC");
    $classes = db_all("SELECT DISTINCT classe FROM financeiro_ativos_movimentos WHERE classe <> '' ORDER BY classe");

    $out = array('corretoras' => array(), 'ativos' => array(), 'contratos' => array(), 'classes' => array());
    foreach ($corretoras as $r) { $out['corretoras'][] = $r['corretora']; }
    foreach ($ativos as $r) { $out['ativos'][] = $r['ativo_codigo']; }
    foreach ($contratos as $r) { $out['contratos'][] = $r['ativo_nome']; }
    foreach ($classes as $r) { $out['classes'][] = $r['classe']; }
    return $out;
}

/* --------------------------------------------------------- estatisticas */

// $valores: array numerico. Retorna n, soma, media, mediana, desvio, min, max, p25, p75.
function imp_estatisticas($valores)
{
    $n = count($valores);
    $r = array('n' => $n, 'soma' => 0.0, 'media' => null, 'mediana' => null, 'desvio' => null,
        'min' => null, 'max' => null, 'p25' => null, 'p75' => null);
    if ($n === 0) { return $r; }

    sort($valores);
    $soma = array_sum($valores);
    $r['soma'] = $soma;
    $r['media'] = $soma / $n;
    $r['min'] = $valores[0];
    $r['max'] = $valores[$n - 1];

    $meio = intdiv($n, 2);
    $r['mediana'] = ($n % 2) ? $valores[$meio] : ($valores[$meio - 1] + $valores[$meio]) / 2;

    if ($n > 1) {
        $somaQuad = 0.0;
        foreach ($valores as $v) { $d = $v - $r['media']; $somaQuad += $d * $d; }
        $r['desvio'] = sqrt($somaQuad / ($n - 1));
    }

    $r['p25'] = imp_percentil($valores, 25);
    $r['p75'] = imp_percentil($valores, 75);
    return $r;
}

function imp_percentil($ordenados, $p)
{
    $n = count($ordenados);
    if ($n === 0) { return null; }
    if ($n === 1) { return $ordenados[0]; }
    $idx = ($p / 100) * ($n - 1);
    $baixo = (int)floor($idx);
    $alto = (int)ceil($idx);
    if ($baixo === $alto) { return $ordenados[$baixo]; }
    $frac = $idx - $baixo;
    return $ordenados[$baixo] + ($ordenados[$alto] - $ordenados[$baixo]) * $frac;
}

/* --------------------------------------------- motor de preco medio (PMP) */

function imp_e_futuro($m)
{
    return (float)$m['quantidade'] <= 0 && (float)$m['preco_unitario'] > 0;
}

// Valor em R$ de 1 ponto do contrato (minicontratos B3, por contrato).
// WIN (mini indice): R$ 0,20/pt | WDO (mini dolar): R$ 10,00/pt | BIT (mini bitcoin): R$ 0,01/pt
// Contratos cheios: IND R$ 1,00/pt | DOL R$ 50,00/pt
function imp_valor_ponto($contrato)
{
    $c = strtoupper(trim((string)$contrato));
    if (strpos($c, 'WIN') === 0) { return 0.20; }
    if (strpos($c, 'WDO') === 0) { return 10.00; }
    if (strpos($c, 'BIT') === 0) { return 0.01; }
    if (strpos($c, 'IND') === 0) { return 1.00; }
    if (strpos($c, 'DOL') === 0) { return 50.00; }
    return null;
}

// Legenda curta do valor do ponto para exibicao.
function imp_rotulo_valor_ponto($contrato)
{
    $vp = imp_valor_ponto($contrato);
    if ($vp === null) { return 'valor do ponto desconhecido'; }
    return 'R$ ' . number_format($vp, 2, ',', '.') . '/pt';
}

// Percorre os movimentos na ordem cronologica, mantem o preco medio por ativo/contrato
// e registra um "evento de fechamento" cada vez que uma saida consome posicao aberta.
function imp_analisar($movs)
{
    $pos = array();
    $eventos = array();
    $ct = array(
        'n_ent' => 0, 'n_sai' => 0, 'vol_ent' => 0.0, 'vol_sai' => 0.0,
        'n_acao' => 0, 'n_futuro' => 0, 'n_sem_preco' => 0, 'sem_preco' => array(),
        'min_data' => null, 'max_data' => null, 'dias' => 0,
        'ativos' => array(), 'contratos' => array(), 'corretoras' => array(),
    );
    $dias = array();

    foreach ($movs as $m) {
        $qtd = (float)$m['quantidade'];
        $preco = (float)$m['preco_unitario'];
        $valor = (float)$m['valor_total'];

        // Sem preco nao da para calcular preco medio: fica de fora do calculo (avisado na pagina)
        if ($preco <= 0) {
            $ct['n_sem_preco']++;
            if (count($ct['sem_preco']) < 30) {
                $ct['sem_preco'][] = array('id' => isset($m['id']) ? (int)$m['id'] : 0, 'data' => (string)$m['data'],
                    'ativo' => (string)$m['ativo_codigo'], 'operacao' => (string)$m['operacao'], 'qtd' => $qtd);
            }
            continue;
        }

        $futuro = imp_e_futuro($m);

        $qtdEfetiva = $futuro ? 1.0 : $qtd;
        $chave = $futuro ? 'FUT|' . $m['ativo_nome'] : 'ACO|' . $m['ativo_codigo'];
        $dirMov = ($m['tipo'] === 'credito') ? 1 : -1;

        $ct[$dirMov > 0 ? 'n_ent' : 'n_sai']++;
        if ($valor > 0) {
            if ($dirMov > 0) { $ct['vol_ent'] += $valor; } else { $ct['vol_sai'] += $valor; }
        }
        if ($futuro) { $ct['n_futuro']++; } else { $ct['n_acao']++; }
        $dias[$m['data']] = 1;
        if ($ct['min_data'] === null || $m['data'] < $ct['min_data']) { $ct['min_data'] = $m['data']; }
        if ($ct['max_data'] === null || $m['data'] > $ct['max_data']) { $ct['max_data'] = $m['data']; }
        if ($futuro) { $ct['contratos'][$m['ativo_nome']] = 1; }
        else { $ct['ativos'][$m['ativo_codigo']] = 1; }
        if (trim((string)$m['corretora']) !== '') { $ct['corretoras'][trim((string)$m['corretora'])] = 1; }

        if (!isset($pos[$chave])) {
            $pos[$chave] = array(
                'chave' => $chave,
                'kind' => $futuro ? 'futuro' : 'acao',
                'codigo' => (string)$m['ativo_codigo'],
                'nome' => (string)$m['ativo_nome'],
                'classe' => (string)$m['classe'],
                'rotulo' => $futuro ? (string)$m['ativo_nome'] : (string)$m['ativo_codigo'],
                'qtd' => 0.0, 'pmp' => 0.0, 'dir' => 0,
                'n_ent' => 0, 'n_sai' => 0, 'vol_ent' => 0.0, 'vol_sai' => 0.0,
                'realizado' => 0.0, 'realizado_reais' => 0.0, 'n_eventos' => 0,
                'n_ganhos' => 0, 'n_perdas' => 0, 'n_be' => 0,
                'ganhos' => 0.0, 'perdas' => 0.0,
                'pontos_ganhos' => 0.0, 'pontos_perdas' => 0.0,
                'primeira' => $m['data'], 'ultima' => $m['data'],
                'datas' => array(),
            );
        }

        $pos[$chave]['ultima'] = $m['data'];
        if ($dirMov > 0) { $pos[$chave]['n_ent']++; } else { $pos[$chave]['n_sai']++; }
        if ($valor > 0) {
            if ($dirMov > 0) { $pos[$chave]['vol_ent'] += $valor; } else { $pos[$chave]['vol_sai'] += $valor; }
        }

        // 1) fecha parte da posicao quando o movimento e oposto ao sentido aberto
        $fechado = 0.0;
        if ($pos[$chave]['dir'] !== 0 && $pos[$chave]['dir'] !== $dirMov && $pos[$chave]['qtd'] > IMP_EPS) {
            $fechado = min($pos[$chave]['qtd'], $qtdEfetiva);
            $pmp = $pos[$chave]['pmp'];
            $realizado = ($preco - $pmp) * $fechado * $pos[$chave]['dir'];
            $custo = $pmp * $fechado;
            $retorno = (abs($custo) > IMP_EPS) ? ($realizado / abs($custo) * 100) : 0.0;

            // Data em que a parte fechada tinha entrado (FIFO de datas): base do day trade x swing
            $aberta = null;
            foreach ($pos[$chave]['datas'] as $dtF => $qF) { $aberta = $dtF; break; }
            if ($aberta === null) { $aberta = $m['data']; }
            $sobraF = $fechado;
            foreach ($pos[$chave]['datas'] as $dtF => $qF) {
                $tiraF = min((float)$qF, $sobraF);
                $pos[$chave]['datas'][$dtF] = (float)$qF - $tiraF;
                $sobraF -= $tiraF;
                if ($pos[$chave]['datas'][$dtF] <= IMP_EPS) { unset($pos[$chave]['datas'][$dtF]); }
                if ($sobraF <= IMP_EPS) { break; }
            }

            $eventos[] = array(
                'data' => $m['data'],
                'aberta' => $aberta,
                'ordem' => isset($m['id']) ? (int)$m['id'] : 0,
                'chave' => $chave,
                'rotulo' => $pos[$chave]['rotulo'],
                'kind' => $pos[$chave]['kind'],
                'operacao' => (string)$m['operacao'],
                'classe' => $pos[$chave]['classe'],
                'corretora' => (string)$m['corretora'],
                'qtd' => $fechado,
                'preco' => $preco,
                'pmp' => $pmp,
                'dir' => $pos[$chave]['dir'],
                'realizado' => $realizado,
                'realizado_reais' => $futuro ? ($realizado * (float)imp_valor_ponto($m['ativo_nome'])) : null,
                'valor_ponto' => $futuro ? imp_valor_ponto($m['ativo_nome']) : null,
                'custo' => $custo,
                'retorno' => $retorno,
                'parcial' => ($qtdEfetiva > $fechado + IMP_EPS),
            );

            $pos[$chave]['realizado'] += $realizado;
            $pos[$chave]['realizado_reais'] += $futuro ? ($realizado * (float)imp_valor_ponto($m['ativo_nome'])) : $realizado;
            $pos[$chave]['n_eventos']++;
            if ($realizado > IMP_TOL) {
                $pos[$chave]['n_ganhos']++;
                $pos[$chave]['ganhos'] += $realizado;
            } elseif ($realizado < -IMP_TOL) {
                $pos[$chave]['n_perdas']++;
                $pos[$chave]['perdas'] += $realizado;
            } else {
                $pos[$chave]['n_be']++;
            }
            if ($futuro) {
                if ($realizado >= 0) { $pos[$chave]['pontos_ganhos'] += $realizado; }
                else { $pos[$chave]['pontos_perdas'] += $realizado; }
            }

            $pos[$chave]['qtd'] -= $fechado;
            if ($pos[$chave]['qtd'] <= IMP_EPS) {
                $pos[$chave]['qtd'] = 0.0;
                $pos[$chave]['pmp'] = 0.0;
                $pos[$chave]['dir'] = 0;
                $pos[$chave]['datas'] = array();
            }
        }

        // 2) abre / aumenta / inverte com o que sobrou
        $restante = $qtdEfetiva - $fechado;
        if ($restante > IMP_EPS) {
            if ($pos[$chave]['dir'] === 0 || $pos[$chave]['dir'] !== $dirMov) {
                $pos[$chave]['dir'] = $dirMov;
                $pos[$chave]['qtd'] = $restante;
                $pos[$chave]['pmp'] = $preco;
                $pos[$chave]['datas'] = array($m['data'] => $restante);
            } else {
                $novoTotal = $pos[$chave]['qtd'] + $restante;
                $pos[$chave]['pmp'] = ($pos[$chave]['pmp'] * $pos[$chave]['qtd'] + $preco * $restante) / $novoTotal;
                $pos[$chave]['qtd'] = $novoTotal;
                if (!isset($pos[$chave]['datas'][$m['data']])) { $pos[$chave]['datas'][$m['data']] = 0.0; }
                $pos[$chave]['datas'][$m['data']] += $restante;
            }
        }
    }

    $ct['dias'] = count($dias);
    $ct['n_ativos'] = count($ct['ativos']);
    $ct['n_contratos'] = count($ct['contratos']);
    $ct['n_corretoras'] = count($ct['corretoras']);
    unset($ct['ativos'], $ct['contratos'], $ct['corretoras']);

    return array('eventos' => $eventos, 'posicoes' => $pos, 'contadores' => $ct);
}

/* ------------------------------------------------ resumo de um conjunto de eventos */

// $eventos: eventos ja filtrados por kind. Retorna as metricas de gain x loss.
function imp_resumo_eventos($eventos)
{
    $r = array(
        'n' => 0, 'n_ganhos' => 0, 'n_perdas' => 0, 'n_be' => 0,
        'ganhos' => 0.0, 'perdas' => 0.0, 'liquido' => 0.0,
        'liquido_reais' => null,
        'fator' => null, 'win_rate' => null,
        'media_ganho' => null, 'media_perda' => null, 'payoff' => null, 'expectativa' => null,
        'maior_ganho' => null, 'maior_perda' => null,
        'media_retorno' => null, 'mediana_retorno' => null, 'desvio_retorno' => null,
        'melhor_seq' => 0, 'pior_seq' => 0,
        'media_qtd' => null,
    );
    if (empty($eventos)) { return $r; }

    $retornos = array();
    $seqGanho = 0; $seqPerda = 0;
    $somaReais = 0.0; $temReais = false;
    foreach ($eventos as $e) {
        $r['n']++;
        $val = (float)$e['realizado'];
        if (isset($e['realizado_reais']) && $e['realizado_reais'] !== null) {
            $somaReais += (float)$e['realizado_reais'];
            $temReais = true;
        }
        if ($val > IMP_TOL) {
            $r['n_ganhos']++;
            $r['ganhos'] += $val;
            $seqGanho++; $seqPerda = 0;
            if ($r['maior_ganho'] === null || $val > $r['maior_ganho']['realizado']) { $r['maior_ganho'] = $e; }
        } elseif ($val < -IMP_TOL) {
            $r['n_perdas']++;
            $r['perdas'] += $val;
            $seqPerda++; $seqGanho = 0;
            if ($r['maior_perda'] === null || $val < $r['maior_perda']['realizado']) { $r['maior_perda'] = $e; }
        } else {
            $r['n_be']++;
            $seqGanho = 0; $seqPerda = 0;
        }
        $retornos[] = (float)$e['retorno'];
        if ($seqGanho > $r['melhor_seq']) { $r['melhor_seq'] = $seqGanho; }
        if ($seqPerda > $r['pior_seq']) { $r['pior_seq'] = $seqPerda; }
    }

    $r['liquido'] = $r['ganhos'] + $r['perdas'];
    if ($temReais) { $r['liquido_reais'] = $somaReais; }
    if ($r['perdas'] < 0) {
        $r['fator'] = $r['ganhos'] / abs($r['perdas']);
    } elseif ($r['ganhos'] > 0) {
        $r['fator'] = null; // sem perdas: fator indefinido (mostrar 'sem perdas')
    }

    $decididos = $r['n_ganhos'] + $r['n_perdas'];
    if ($decididos > 0) {
        $r['win_rate'] = $r['n_ganhos'] / $decididos * 100;
    }
    if ($r['n_ganhos'] > 0) { $r['media_ganho'] = $r['ganhos'] / $r['n_ganhos']; }
    if ($r['n_perdas'] > 0) { $r['media_perda'] = abs($r['perdas']) / $r['n_perdas']; }
    if ($r['media_ganho'] !== null && $r['media_perda'] !== null && $r['media_perda'] > 0) {
        $r['payoff'] = $r['media_ganho'] / $r['media_perda'];
    }
    if ($r['win_rate'] !== null && $r['media_ganho'] !== null && $r['media_perda'] !== null) {
        $p = $r['win_rate'] / 100;
        $r['expectativa'] = $p * $r['media_ganho'] - (1 - $p) * $r['media_perda'];
    }

    $est = imp_estatisticas($retornos);
    $r['media_retorno'] = $est['media'];
    $r['mediana_retorno'] = $est['mediana'];
    $r['desvio_retorno'] = $est['desvio'];

    $qtds = array();
    foreach ($eventos as $e) { $qtds[] = (float)$e['qtd']; }
    $r['media_qtd'] = array_sum($qtds) / count($qtds);

    return $r;
}

/* --------------------------------------------------------------- series */

// Serie diaria do resultado realizado com acumulado e drawdown do proprio curva.
function imp_serie_realizada($eventos)
{
    $porDia = array();
    foreach ($eventos as $e) {
        if (!isset($porDia[$e['data']])) { $porDia[$e['data']] = 0.0; }
        $porDia[$e['data']] += (float)$e['realizado'];
    }
    ksort($porDia);

    $linhas = array();
    $acum = 0.0; $pico = 0.0; $maxDd = 0.0; $dataDd = null;
    foreach ($porDia as $data => $valor) {
        $acum += $valor;
        if ($acum > $pico) { $pico = $acum; }
        $dd = $acum - $pico;
        if ($dd < $maxDd) { $maxDd = $dd; $dataDd = $data; }
        $linhas[] = array('data' => $data, 'valor' => $valor, 'acumulado' => $acum, 'drawdown' => $dd);
    }

    return array('linhas' => $linhas, 'drawdown' => $maxDd, 'data_drawdown' => $dataDd, 'pico' => $pico);
}

// Serie diaria ja em R$ (usa realizado_reais dos eventos de trading).
function imp_serie_realizada_reais($eventos)
{
    $porDia = array();
    foreach ($eventos as $e) {
        if (!isset($e['realizado_reais']) || $e['realizado_reais'] === null) { continue; }
        if (!isset($porDia[$e['data']])) { $porDia[$e['data']] = 0.0; }
        $porDia[$e['data']] += (float)$e['realizado_reais'];
    }
    ksort($porDia);

    $linhas = array();
    $acum = 0.0; $pico = 0.0; $maxDd = 0.0; $dataDd = null;
    foreach ($porDia as $data => $valor) {
        $acum += $valor;
        if ($acum > $pico) { $pico = $acum; }
        $dd = $acum - $pico;
        if ($dd < $maxDd) { $maxDd = $dd; $dataDd = $data; }
        $linhas[] = array('data' => $data, 'valor' => $valor, 'acumulado' => $acum, 'drawdown' => $dd);
    }

    return array('linhas' => $linhas, 'drawdown' => $maxDd, 'data_drawdown' => $dataDd, 'pico' => $pico);
}

// Agrupa movimentos e eventos ja em R$ por mes, dia ou dia da semana.
function imp_agrupar_reais($movs, $eventos, $campo)
{
    $linhas = array();

    foreach ($movs as $m) {
        $k = imp_chave_agrupamento($m['data'], $campo);
        if ($k === null) { continue; }
        if (!isset($linhas[$k])) { $linhas[$k] = imp_linha_agrup($k); }
        $linhas[$k]['n']++;
        $valor = (float)$m['valor_total'];
        if ($valor <= 0) { continue; }
        if ($m['tipo'] === 'credito') {
            $linhas[$k]['entradas'] += $valor;
        } else {
            $linhas[$k]['saidas'] += $valor;
        }
    }

    foreach ($eventos as $e) {
        if (!isset($e['realizado_reais']) || $e['realizado_reais'] === null) { continue; }
        $k = imp_chave_agrupamento($e['data'], $campo);
        if ($k === null) { continue; }
        if (!isset($linhas[$k])) { $linhas[$k] = imp_linha_agrup($k); }
        $linhas[$k]['realizado'] += (float)$e['realizado_reais'];
        $linhas[$k]['fechamentos']++;
    }

    ksort($linhas);

    $acum = 0.0;
    foreach ($linhas as $k => $l) {
        $acum += $l['realizado'];
        $linhas[$k]['acumulado'] = $acum;
        $linhas[$k]['volume'] = $l['entradas'] + $l['saidas'];
    }
    return $linhas;
}

// Resumo de eventos ja convertidos para R$ (realizado_reais vira realizado).
function imp_resumo_reais($eventos)
{
    $conv = array();
    foreach ($eventos as $e) {
        if (!isset($e['realizado_reais']) || $e['realizado_reais'] === null) { continue; }
        $e2 = $e;
        $e2['realizado'] = (float)$e['realizado_reais'];
        $e2['retorno'] = 0.0;
        $conv[] = $e2;
    }
    return imp_resumo_eventos($conv);
}

// Agrupa movimentos (volume) e eventos (realizado) por mes, dia ou dia da semana.
// Conta todo lancamento em 'n'; entradas/saidas so valem quando ha valor em R$.
function imp_agrupar($movs, $eventos, $campo)
{
    $linhas = array();

    foreach ($movs as $m) {
        $k = imp_chave_agrupamento($m['data'], $campo);
        if ($k === null) { continue; }
        if (!isset($linhas[$k])) { $linhas[$k] = imp_linha_agrup($k); }
        $linhas[$k]['n']++;
        $valor = (float)$m['valor_total'];
        if ($valor <= 0) { continue; }
        if ($m['tipo'] === 'credito') {
            $linhas[$k]['entradas'] += $valor;
        } else {
            $linhas[$k]['saidas'] += $valor;
        }
    }

    foreach ($eventos as $e) {
        $k = imp_chave_agrupamento($e['data'], $campo);
        if ($k === null) { continue; }
        if (!isset($linhas[$k])) { $linhas[$k] = imp_linha_agrup($k); }
        $linhas[$k]['realizado'] += (float)$e['realizado'];
        $linhas[$k]['fechamentos']++;
    }

    if ($campo === 'semana') {
        ksort($linhas);
    } else {
        ksort($linhas);
    }

    $acum = 0.0;
    foreach ($linhas as $k => $l) {
        $acum += $l['realizado'];
        $linhas[$k]['acumulado'] = $acum;
        $linhas[$k]['volume'] = $l['entradas'] + $l['saidas'];
    }
    return $linhas;
}

function imp_linha_agrup($k)
{
    return array('chave' => $k, 'n' => 0, 'entradas' => 0.0, 'saidas' => 0.0,
        'realizado' => 0.0, 'fechamentos' => 0, 'acumulado' => 0.0, 'volume' => 0.0);
}

function imp_chave_agrupamento($data, $campo)
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$data)) { return null; }
    if ($campo === 'mes') { return substr($data, 0, 7); }
    if ($campo === 'dia') { return $data; }
    if ($campo === 'semana') { return (string)(int)date('N', strtotime($data)); }
    return $data;
}

function imp_rotulo_semana($chave)
{
    $mapa = array(1 => 'Segunda', 2 => 'Terca', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sabado', 7 => 'Domingo');
    return isset($mapa[(int)$chave]) ? $mapa[(int)$chave] : (string)$chave;
}

function imp_rotulo_mes($chave)
{
    $mapa = array('01' => 'Jan', '02' => 'Fev', '03' => 'Mar', '04' => 'Abr', '05' => 'Mai', '06' => 'Jun',
        '07' => 'Jul', '08' => 'Ago', '09' => 'Set', '10' => 'Out', '11' => 'Nov', '12' => 'Dez');
    $ano = substr($chave, 0, 4);
    $mes = substr($chave, 5, 2);
    return (isset($mapa[$mes]) ? $mapa[$mes] : $mes) . '/' . $ano;
}

/* --------------------------------------------------------- distribuicao */

// Faixas de valor dos lancamentos com R$ (entradas e saidas separadas).
function imp_distribuicao($movs)
{
    $faixas = array(
        array('rotulo' => 'ate 100', 'min' => 0, 'max' => 100),
        array('rotulo' => '100 a 500', 'min' => 100, 'max' => 500),
        array('rotulo' => '500 a 1k', 'min' => 500, 'max' => 1000),
        array('rotulo' => '1k a 5k', 'min' => 1000, 'max' => 5000),
        array('rotulo' => '5k a 10k', 'min' => 5000, 'max' => 10000),
        array('rotulo' => '10k a 50k', 'min' => 10000, 'max' => 50000),
        array('rotulo' => '50k+', 'min' => 50000, 'max' => null),
    );
    foreach ($faixas as $i => $f) {
        $faixas[$i]['entradas'] = 0;
        $faixas[$i]['saidas'] = 0;
    }

    foreach ($movs as $m) {
        $v = (float)$m['valor_total'];
        if ($v <= 0) { continue; }
        foreach ($faixas as $i => $f) {
            if ($v >= $f['min'] && ($f['max'] === null || $v < $f['max'])) {
                if ($m['tipo'] === 'credito') { $faixas[$i]['entradas']++; }
                else { $faixas[$i]['saidas']++; }
                break;
            }
        }
    }
    return $faixas;
}

/* --------------------------------------------------- linhas de relatorio */

function imp_posicoes_abertas($pos)
{
    $out = array();
    foreach ($pos as $p) {
        if ($p['qtd'] > IMP_EPS) { $out[] = $p; }
    }
    usort($out, function ($a, $b) {
        $va = ($a['vol_ent'] + $a['vol_sai']);
        $vb = ($b['vol_ent'] + $b['vol_sai']);
        if (abs($va - $vb) < 0.0001) { return strcmp($a['rotulo'], $b['rotulo']); }
        return ($va > $vb) ? -1 : 1;
    });
    return $out;
}

function imp_linhas_ativos($pos, $kind)
{
    $out = array();
    foreach ($pos as $p) {
        if ($p['kind'] !== $kind) { continue; }
        $volume = $p['vol_ent'] + $p['vol_sai'];
        $decididos = $p['n_ganhos'] + $p['n_perdas'];
        $out[] = array(
            'rotulo' => $p['rotulo'],
            'nome' => $p['nome'],
            'classe' => $p['classe'],
            'n_ent' => $p['n_ent'],
            'n_sai' => $p['n_sai'],
            'vol_ent' => $p['vol_ent'],
            'vol_sai' => $p['vol_sai'],
            'volume' => $volume,
            'realizado' => $p['realizado'],
            'n_eventos' => $p['n_eventos'],
            'win_rate' => ($decididos > 0) ? ($p['n_ganhos'] / $decididos * 100) : null,
            'qtd' => $p['qtd'],
            'pmp' => $p['pmp'],
            'dir' => $p['dir'],
            'custo' => $p['pmp'] * $p['qtd'],
            'primeira' => $p['primeira'],
            'ultima' => $p['ultima'],
        );
    }
    usort($out, function ($a, $b) {
        if (abs($a['volume'] - $b['volume']) < 0.0001) { return strcmp($a['rotulo'], $b['rotulo']); }
        return ($a['volume'] > $b['volume']) ? -1 : 1;
    });
    return $out;
}

// Top N ativos por resultado realizado (ordena do melhor para o pior).
function imp_top_realizado($pos, $kind, $limite = 10)
{
    $out = array();
    foreach ($pos as $p) {
        if ($p['kind'] !== $kind) { continue; }
        if (abs($p['realizado']) < 0.0001) { continue; }
        $out[] = $p;
    }
    usort($out, function ($a, $b) {
        if (abs($a['realizado'] - $b['realizado']) < 0.0001) { return strcmp($a['rotulo'], $b['rotulo']); }
        return ($a['realizado'] > $b['realizado']) ? -1 : 1;
    });
    if (count($out) > $limite) {
        // mantem os melhores e os piores, cortando do meio
        $metade = (int)ceil($limite / 2);
        $melhores = array_slice($out, 0, $metade);
        $piores = array_slice($out, -$metade);
        $out = array_merge($melhores, $piores);
    }
    return $out;
}

// Amostra dos maiores ganhos e maiores perdas (eventos).
function imp_extremos($eventos, $limite = 5)
{
    $ganhos = array();
    $perdas = array();
    foreach ($eventos as $e) {
        if ($e['realizado'] > IMP_TOL) { $ganhos[] = $e; }
        elseif ($e['realizado'] < -IMP_TOL) { $perdas[] = $e; }
    }
    usort($ganhos, function ($a, $b) { return ($a['realizado'] > $b['realizado']) ? -1 : 1; });
    usort($perdas, function ($a, $b) { return ($a['realizado'] < $b['realizado']) ? -1 : 1; });
    return array(
        'ganhos' => array_slice($ganhos, 0, $limite),
        'perdas' => array_slice($perdas, 0, $limite),
    );
}

// Fechamentos com variacao extrema: costumam ser desdobramento, desdobramento inverso,
// dividendo com ajuste de preco ou erro de digitacao na planilha. Sinalizamos, nao apagamos.
function imp_anomalias($eventos, $limiar = 50.0)
{
    $out = array();
    foreach ($eventos as $e) {
        if (abs((float)$e['retorno']) >= $limiar) { $out[] = $e; }
    }
    usort($out, function ($a, $b) { return abs($b['retorno']) - abs($a['retorno']); });
    return $out;
}

// Um fechamento e day trade quando a parte fechada entrou no mesmo dia (sem carregar de dias anteriores).
function imp_e_daytrade($e)
{
    return empty($e['aberta']) || (string)$e['aberta'] >= (string)$e['data'];
}

// Separa eventos em day trades (entrada e saida no mesmo dia) e swing (entrada de dias anteriores).
function imp_split_dia_swing($eventos)
{
    $dia = array();
    $swing = array();
    foreach ($eventos as $e) {
        if (imp_e_daytrade($e)) { $dia[] = $e; }
        else { $swing[] = $e; }
    }
    return array('dia' => $dia, 'swing' => $swing);
}

// Liquido realizado excluindo um conjunto de eventos (para mostrar o impacto das anomalias).
function imp_liquido_sem($eventos, $excluir)
{
    if (empty($excluir)) {
        $soma = 0.0;
        foreach ($eventos as $e) { $soma += (float)$e['realizado']; }
        return $soma;
    }
    $chaves = array();
    foreach ($excluir as $e) {
        $chaves[$e['data'] . '|' . $e['ordem'] . '|' . $e['chave']] = 1;
    }
    $soma = 0.0;
    foreach ($eventos as $e) {
        $k = $e['data'] . '|' . $e['ordem'] . '|' . $e['chave'];
        if (!isset($chaves[$k])) { $soma += (float)$e['realizado']; }
    }
    return $soma;
}

// Junta as duas curvas acumuladas (investimento em R$ e trading em pontos) em um unico eixo de datas.
// Cada linha traz o ultimo valor conhecido ate a data (carry forward); null antes do primeiro registro.
function imp_linha_conjunta($serieA, $serieF)
{
    $mapA = array();
    $mapF = array();
    $datas = array();
    foreach ($serieA['linhas'] as $l) {
        $mapA[$l['data']] = (float)$l['acumulado'];
        $datas[$l['data']] = 1;
    }
    foreach ($serieF['linhas'] as $l) {
        $mapF[$l['data']] = (float)$l['acumulado'];
        $datas[$l['data']] = 1;
    }
    ksort($datas);

    $linhas = array();
    $a = null;
    $t = null;
    foreach (array_keys($datas) as $d) {
        if (isset($mapA[$d])) { $a = $mapA[$d]; }
        if (isset($mapF[$d])) { $t = $mapF[$d]; }
        $linhas[] = array('data' => $d, 'investimento' => $a, 'trading' => $t);
    }
    return $linhas;
}

// Leitura curta e automatica de um conjunto de metricas. $tipo: 'acao' ou 'futuro' (ambos em R$).
// Usada no dashboard do financeiro e na pagina de analise para os dois paineis conversarem.
function imp_leitura_resumo($res, $tipo = 'acao')
{
    if ($res['n'] === 0) {
        return 'Sem fechamentos no periodo para avaliar.';
    }
    $fatorTxt = ($res['fator'] !== null) ? number_format($res['fator'], 2, ',', '.') : 'sem perdas';
    $wr = ($res['win_rate'] !== null) ? number_format($res['win_rate'], 1, ',', '.') . '%' : '-';
    $payoff = ($res['payoff'] !== null) ? number_format($res['payoff'], 2, ',', '.') : '-';
    $exp = ($res['expectativa'] !== null)
        ? 'R$ ' . number_format(abs($res['expectativa']), 2, ',', '.') . ' de ' . ($res['expectativa'] >= 0 ? 'ganho' : 'perda') . ' por fechamento'
        : 'expectativa indisponivel';

    $liqTxt = 'R$ ' . number_format(abs($res['liquido']), 2, ',', '.') . ' ' . ($res['liquido'] >= 0 ? 'positivos' : 'negativos');
    if ($tipo === 'futuro' && isset($res['liquido_reais']) && $res['liquido_reais'] !== null) {
        $liqTxt .= ' (convertido pela tabela B3)';
    }

    $veredito = ($res['fator'] !== null && $res['fator'] >= 1)
        ? 'Os ganhos somam mais que as perdas (fator lucro acima de 1).'
        : 'As perdas somam mais que os ganhos (fator lucro abaixo de 1).';
    $amostra = ($res['n'] >= 30)
        ? 'Amostra com ' . (int)$res['n'] . ' fechamentos: leitura razoavel, ainda assim resultado observado nao garante resultado futuro.'
        : 'Amostra pequena (' . (int)$res['n'] . ' fechamentos): qualquer resultado pode ser sorte.';

    return 'Resultado ' . $liqTxt . ' em ' . (int)$res['n'] . ' fechamentos, fator lucro ' . $fatorTxt
        . ', taxa de acerto ' . $wr . ' e payoff ' . $payoff . ' (' . $exp . '). ' . $veredito . ' ' . $amostra;
}
