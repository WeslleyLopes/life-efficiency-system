<?php
require_once __DIR__ . '/../config.php';

$page_title = 'Analise do Historico';
$page_sub = 'Abas: Geral · Investimento · Trading — mesmos filtros para as tres';
$active = 'financeiro';

$f = imp_filtros();

$abaOpcoes = array('geral', 'investimento', 'trading');
$abaAtual = isset($_GET['aba']) && in_array($_GET['aba'], $abaOpcoes, true) ? $_GET['aba'] : 'geral';
$hidG = $abaAtual === 'geral' ? '' : ' hidden';
$hidI = $abaAtual === 'investimento' ? '' : ' hidden';
$hidT = $abaAtual === 'trading' ? '' : ' hidden';

if (!function_exists('aba_link')) {
    function aba_link($aba)
    {
        global $f;
        $q = array();
        foreach ($f as $k => $v) {
            if ($v !== '' && $v !== null) {
                $q[$k] = $v;
            }
        }
        $q['aba'] = $aba;
        return '/ranksistem/pages/financeiro-analise.php?' . http_build_query($q);
    }
}

$movs = imp_movimentos($f);
$opcoes = imp_opcoes();
$an = imp_analisar($movs);
$eventos = $an['eventos'];
$pos = $an['posicoes'];
$ct = $an['contadores'];

$movsA = array();
$movsF = array();
foreach ($movs as $m) {
    if (imp_e_futuro($m)) {
        $movsF[] = $m;
    } else {
        $movsA[] = $m;
    }
}

$evA = array();
$evF = array();
foreach ($eventos as $e) {
    if ($e['kind'] === 'acao') {
        $evA[] = $e;
    } else {
        $evF[] = $e;
    }
}

$resA = imp_resumo_eventos($evA);
$resF = imp_resumo_reais($evF);
$splitF = imp_split_dia_swing($evF);
$resDiaF = imp_resumo_reais($splitF['dia']);
$resSwingF = imp_resumo_reais($splitF['swing']);
$anomA = imp_anomalias($evA);
$anomF = imp_anomalias($evF);
$serieA = imp_serie_realizada($evA);
$serieF = imp_serie_realizada_reais($evF);
$conjunta = imp_linha_conjunta($serieA, $serieF);
$extremos = imp_extremos($evA, 5);
$leituraA = imp_leitura_resumo($resA, 'acao');
$leituraF = imp_leitura_resumo($resF, 'futuro');

$volA = array('n' => 0, 'ent' => 0, 'said' => 0, 'volEnt' => 0.0, 'volSai' => 0.0);
foreach ($movsA as $m) {
    $volA['n']++;
    $valor = (float)$m['valor_total'];
    if ($m['tipo'] === 'credito') {
        $volA['ent']++;
        $volA['volEnt'] += $valor;
    } else {
        $volA['said']++;
        $volA['volSai'] += $valor;
    }
}
$volF = array('n' => count($movsF));

$valores = array();
foreach ($movsA as $m) {
    if ((float)$m['valor_total'] > 0) {
        $valores[] = (float)$m['valor_total'];
    }
}
$estValores = imp_estatisticas($valores);
$volumeTotal = $volA['volEnt'] + $volA['volSai'];
$ticketMedio = ($estValores['n'] > 0) ? $volumeTotal / $estValores['n'] : 0;
$liquidoSemA = imp_liquido_sem($evA, $anomA);

$mesesA = imp_agrupar($movsA, $evA, 'mes');
$semanaA = imp_agrupar($movsA, $evA, 'semana');
$mesesF = imp_agrupar_reais($movsF, $evF, 'mes');
$semanaF = imp_agrupar_reais($movsF, $evF, 'semana');
$dist = imp_distribuicao($movsA);
$linhasAtivos = imp_linhas_ativos($pos, 'acao');
$linhasContratos = imp_linhas_ativos($pos, 'futuro');
$topReal = imp_top_realizado($pos, 'acao', 10);

$abertasA = array();
$abertasF = array();
foreach (imp_posicoes_abertas($pos) as $pa) {
    if ($pa['kind'] === 'futuro') {
        $abertasF[] = $pa;
    } else {
        $abertasA[] = $pa;
    }
}

$acumA = array();
$acc = 0.0;
foreach ($evA as $e) {
    $acc += (float)$e['realizado'];
    $acumA[] = $acc;
}
$acumF = array();
$acc = 0.0;
foreach ($evF as $e) {
    $acc += (isset($e['realizado_reais']) && $e['realizado_reais'] !== null) ? (float)$e['realizado_reais'] : 0.0;
    $acumF[] = $acc;
}

$rotuloClasse = array('acao' => 'Acao/BDR/FII', 'indice' => 'Indice/Futuro', 'outro' => 'Outro');

if (!function_exists('fa')) {
    function fa($v, $casas = 2, $sinal = false)
    {
        if ($v === null) {
            return '-';
        }
        $n = ($sinal && $v >= 0 ? '+' : '') . number_format((float)$v, $casas, ',', '.');
        return '<span class="abs">R$ ' . $n . '</span>';
    }
}
if (!function_exists('fp')) {
    function fp($v, $casas = 2)
    {
        if ($v === null) {
            return '-';
        }
        $n = number_format((float)$v, $casas, ',', '.');
        return '<span class="abs">R$ ' . $n . '</span>';
    }
}
if (!function_exists('fcor')) {
    function fcor($v)
    {
        if ($v === null) {
            return '';
        }
        return $v >= 0 ? ' style="color:var(--green)"' : ' style="color:var(--red)"';
    }
}
if (!function_exists('est_mini')) {
    function est_mini($rot, $valor, $nota = '', $cor = '')
    {
        echo '<div class="stat-mini">'
            . '<span class="rot">' . e($rot) . '</span>'
            . '<span class="val"' . ($cor !== '' ? ' style="color:' . e($cor) . '"' : '') . '>' . $valor . '</span>'
            . ($nota !== '' ? '<span class="not">' . $nota . '</span>' : '')
            . '</div>';
    }
}
if (!function_exists('funil_etapa')) {
    function funil_etapa($titulo, $descricao, $cor, $num = null)
    {
        echo '<div class="funil-etapa" style="--cor:' . e($cor) . '">'
            . ($num !== null ? '<span class="funil-num">' . (int)$num . '</span>' : '')
            . '<div class="funil-info"><strong>' . e($titulo) . '</strong><span>' . e($descricao) . '</span></div>'
            . '</div>';
    }
}

/* --------------- detalhamento (drill-down) da origem dos dados dos KPIs ---------------
   Cards com data-drill chamam ?drill=<chave> nesta mesma pagina e recebem JSON com
   titulo, nota (como o numero foi calculado), colunas e linhas da base. */

if (!function_exists('drill_row_evA')) {
    function drill_row_evA($e, $acum)
    {
        $r = (float)$e['realizado'];
        return array(
            date('d/m/Y', strtotime($e['data'])),
            e($e['rotulo']),
            e($e['operacao']),
            $e['dir'] > 0 ? 'Long (vendeu)' : 'Short (comprou)',
            number_format($e['qtd'], 0, ',', '.'),
            fa($e['preco']),
            fa($e['pmp']),
            fa($e['custo']),
            '<span' . fcor($r) . '>' . fa($r, 2, true) . '</span>',
            '<span' . fcor($r) . '>' . number_format($e['retorno'], 1, ',', '.') . '%</span>',
            fa($acum, 2, true) . ($e['parcial'] ? ' (parcial)' : ''),
            isset($e['aberta']) ? date('d/m/Y', strtotime($e['aberta'])) : '-',
        );
    }
}
if (!function_exists('drill_row_evF')) {
    function drill_row_evF($e, $acum)
    {
        $r = (isset($e['realizado_reais']) && $e['realizado_reais'] !== null) ? (float)$e['realizado_reais'] : 0.0;
        $pontos = (float)$e['realizado'];
        return array(
            date('d/m/Y', strtotime($e['data'])),
            e($e['rotulo']),
            e($e['operacao']),
            $e['dir'] > 0 ? 'Long (vendeu)' : 'Short (comprou)',
            number_format($e['qtd'], 0, ',', '.'),
            number_format($e['preco'], 2, ',', '.'),
            number_format($e['pmp'], 2, ',', '.'),
            '<span' . fcor($pontos) . '>' . number_format($pontos, 2, ',', '.') . '</span>',
            '<span' . fcor($r) . '>' . fp($r, 2) . '</span>',
            number_format($e['retorno'], 2, ',', '.') . '%',
            fp($acum, 2) . ($e['parcial'] ? ' (parcial)' : ''),
            isset($e['aberta']) ? date('d/m/Y', strtotime($e['aberta'])) : '-',
            imp_e_daytrade($e) ? 'Day trade' : 'Swing',
        );
    }
}
if (!function_exists('drill_row_mov')) {
    function drill_row_mov($m)
    {
        $fut = imp_e_futuro($m);
        $chave = $fut ? $m['ativo_nome'] : $m['ativo_codigo'];
        return array(
            date('d/m/Y', strtotime($m['data'])),
            $fut ? 'Trading' : 'Investimento',
            e($chave),
            e($m['ativo_nome']),
            e($m['operacao']),
            ($m['tipo'] === 'credito') ? 'Entrada' : 'Saida',
            $fut ? '-' : number_format((float)$m['quantidade'], 0, ',', '.'),
            fa((float)$m['preco_unitario']),
            fa((float)$m['valor_total']),
            e($m['corretora']),
        );
    }
}
if (!function_exists('drill_row_lanc')) {
    // linha de lancamento importado com botao Excluir (volta para a analise com os filtros atuais)
    function drill_row_lanc($m, $aba, $filtros)
    {
        $fut = imp_e_futuro($m);
        $q = array('acao' => 'del_lancamento', 'id' => (int)$m['id'], 'voltar' => 'analise', 'aba' => $aba);
        foreach ($filtros as $k => $v) {
            if ($v !== '' && $v !== null) {
                $q[$k] = $v;
            }
        }
        $link = '/ranksistem/controllers/financeiroController.php?' . http_build_query($q);
        return array(
            date('d/m/Y', strtotime($m['data'])),
            $fut ? 'Trading' : 'Investimento',
            e($fut ? $m['ativo_nome'] : $m['ativo_codigo']),
            e($m['ativo_nome']),
            e($m['operacao']),
            ($m['tipo'] === 'credito') ? 'Entrada' : 'Saida',
            $fut ? '-' : number_format((float)$m['quantidade'], 0, ',', '.'),
            fa((float)$m['preco_unitario']),
            fa((float)$m['valor_total']),
            e($m['corretora']),
            '<a class="link-mini danger" href="' . e($link) . '" onclick="return confirm(\'Excluir este lancamento? A carteira e a analise serao recalculadas (o saldo atual nao muda; a exclusao nao tem desfazer).\')">Excluir</a>',
        );
    }
}
// drill_op_resumo vive no analise_importados_helper.php (compartilhado com o dashboard)
if (!function_exists('drill_row_op')) {
    function drill_row_op($d)
    {
        return array(
            date('d/m/Y', strtotime($d['data'])),
            $d['fut'] ? 'Trading' : 'Investimento',
            e($d['cod']),
            e($d['nome']),
            e($d['rotulo']) . ' (zerou)',
            'Operacao (2 lanc.)',
            $d['fut'] ? '-' : number_format($d['qtd'], 0, ',', '.'),
            fa($d['preco_a']) . ' &rarr; ' . fa($d['preco_b']),
            $d['resultado'] === null ? '-' : '<span' . fcor($d['resultado']) . '>' . fa($d['resultado'], 2, true) . '</span>',
            e($d['corretora']),
        );
    }
}
if (!function_exists('drill_row_fech')) {
    function drill_row_fech($e, $painel, $acum)
    {
        $fut = ($painel === 'Trading');
        $r = $fut ? (float)$e['realizado_reais'] : (float)$e['realizado'];
        return array(
            date('d/m/Y', strtotime($e['data'])),
            $painel,
            e($e['rotulo']),
            number_format($e['qtd'], 0, ',', '.'),
            $fut ? number_format($e['preco'], 2, ',', '.') : fa($e['preco']),
            $fut ? number_format($e['pmp'], 2, ',', '.') : fa($e['pmp']),
            '<span' . fcor($r) . '>' . ($fut ? fp($r, 2) : fa($r, 2, true)) . '</span>',
            isset($e['aberta']) ? date('d/m/Y', strtotime($e['aberta'])) : '-',
            imp_e_daytrade($e) ? 'Day trade' : 'Swing',
            $fut ? fp($acum, 2) : fa($acum, 2, true),
        );
    }
}
if (!function_exists('drill_row_aberta')) {
    function drill_row_aberta($pa, $painel)
    {
        global $rotuloClasse;
        $fut = ($painel === 'Trading');
        $r = $fut ? (isset($pa['realizado_reais']) ? (float)$pa['realizado_reais'] : 0.0) : (float)$pa['realizado'];
        $classe = isset($rotuloClasse[$pa['classe']]) ? $rotuloClasse[$pa['classe']] : $pa['classe'];
        return array(
            $painel,
            e($pa['rotulo']),
            e($fut ? 'Futuro' : $classe),
            $pa['dir'] > 0 ? 'Comprado' : 'Vendido',
            number_format($pa['qtd'], 0, ',', '.') . ($fut ? ' contrato(s)' : ''),
            fa($pa['pmp']),
            date('d/m/Y', strtotime($pa['primeira'])),
            date('d/m/Y', strtotime($pa['ultima'])),
            '<span' . fcor($r) . '>' . ($fut ? fp($r, 2) : fa($r, 2, true)) . '</span>',
        );
    }
}

if (!function_exists('drill_payload')) {
    function drill_payload($chave)
    {
        global $movs, $movsA, $evA, $evF, $resA, $resF, $anomA, $anomF,
            $abertasA, $abertasF, $linhasAtivos, $linhasContratos, $ct, $estValores,
            $volA, $volumeTotal, $ticketMedio, $acumA, $acumF, $rotuloClasse,
            $splitF, $resDiaF, $resSwingF, $liquidoSemA, $mesesA, $mesesF, $f;

        $p = array('titulo' => '', 'nota' => '', 'cols' => array(), 'rows' => array());
        $limMov = 1500;

        switch ($chave) {
            case 'g_periodo':
                $mapa = array();
                foreach ($mesesA as $m) {
                    $mapa[$m['chave']] = array(
                        'n' => (int)$m['n'],
                        'ent' => (float)$m['entradas'],
                        'sai' => (float)$m['saidas'],
                        'rA' => (float)$m['realizado'],
                        'rF' => 0.0,
                        'fc' => (int)$m['fechamentos']
                    );
                }
                foreach ($mesesF as $m) {
                    if (!isset($mapa[$m['chave']])) {
                        $mapa[$m['chave']] = array('n' => 0, 'ent' => 0.0, 'sai' => 0.0, 'rA' => 0.0, 'rF' => 0.0, 'fc' => 0);
                    }
                    $mapa[$m['chave']]['n'] += (int)$m['n'];
                    $mapa[$m['chave']]['ent'] += (float)$m['entradas'];
                    $mapa[$m['chave']]['sai'] += (float)$m['saidas'];
                    $mapa[$m['chave']]['rF'] = (float)$m['realizado'];
                    $mapa[$m['chave']]['fc'] += (int)$m['fechamentos'];
                }
                ksort($mapa);
                $p['titulo'] = 'Origem: periodo analisado';
                $p['cols'] = array('Mes', 'Lancamentos', 'Entradas (R$)', 'Saidas (R$)', 'Realizado invest. (R$)', 'Realizado trading (R$)', 'Fechamentos');
                foreach ($mapa as $k => $d) {
                    $p['rows'][] = array(
                        imp_rotulo_mes($k),
                        $d['n'],
                        '<span class="abs">R$ ' . number_format($d['ent'], 2, ',', '.') . '</span>',
                        '<span class="abs">R$ ' . number_format($d['sai'], 2, ',', '.') . '</span>',
                        '<span' . fcor($d['rA']) . '>' . fa($d['rA'], 2, true) . '</span>',
                        '<span' . fcor($d['rF']) . '>' . fp($d['rF'], 2) . '</span>',
                        $d['fc'],
                    );
                }
                $p['nota'] = 'Recorte de <b>' . ($ct['min_data'] ? date('d/m/Y', strtotime($ct['min_data'])) : '-') . '</b> a <b>'
                    . ($ct['max_data'] ? date('d/m/Y', strtotime($ct['max_data'])) : '-') . '</b> com <b>' . (int)$ct['dias']
                    . '</b> dias com lancamento. Cada linha e um mes dos filtros atuais somando os dois topicos.';
                break;

            case 'g_lancamentos':
                $p['titulo'] = 'Origem: lancamentos no periodo';
                $consL = imp_consolidar_operacoes($movs);
                $nOpsL = 0;
                foreach ($consL as $cL) {
                    if ($cL['op']) { $nOpsL++; }
                    if (count($p['rows']) >= $limMov) { continue; }
                    $p['rows'][] = $cL['op'] ? drill_row_op(drill_op_resumo($cL['a'], $cL['b'])) : drill_row_mov($cL['a']);
                }
                $p['cols'] = array('Data', 'Painel', 'Ativo / Contrato', 'Nome', 'Operacao', 'Tipo', 'Qtd', 'Preco', 'Total', 'Corretora');
                $p['nota'] = '<b>' . count($movs) . '</b> lancamentos no recorte em <b>' . count($consL) . '</b> linhas: <b>' . $nOpsL
                    . '</b> operacao(oes) consolidadas (entrada + saida do mesmo dia, mesmo ativo e mesma quantidade, zerando a posicao)'
                    . ' + <b>' . (count($consL) - $nOpsL) . '</b> lancamentos avulsos'
                    . (count($consL) > $limMov ? ' (mostrando as ' . $limMov . ' primeiras linhas)' : '')
                    . '. Nas linhas "Operacao (2 lanc.)" a coluna Preco mostra o 1º &rarr; o 2º lancamento do par e a coluna Total o resultado dele; '
                    . 'credito = entrada de ativo, debito = saida.';
                break;

            case 'g_volume':
            case 'g_media':
                $nComValor = 0;
                foreach ($movs as $m) {
                    if ((float)$m['valor_total'] <= 0) {
                        continue;
                    }
                    $nComValor++;
                    if (count($p['rows']) >= $limMov) {
                        continue;
                    }
                    $p['rows'][] = array(
                        date('d/m/Y', strtotime($m['data'])),
                        imp_e_futuro($m) ? 'Trading' : 'Investimento',
                        e(imp_e_futuro($m) ? $m['ativo_nome'] : $m['ativo_codigo']),
                        e($m['operacao']),
                        ($m['tipo'] === 'credito') ? 'Entrada' : 'Saida',
                        fa((float)$m['preco_unitario']),
                        fa((float)$m['valor_total']),
                    );
                }
                $p['cols'] = array('Data', 'Painel', 'Ativo / Contrato', 'Operacao', 'Tipo', 'Preco', 'Total');
                if ($chave === 'g_media') {
                    $p['titulo'] = 'Origem: media dos valores';
                    $p['nota'] = 'Media <b>' . fa($estValores['media']) . '</b> · mediana ' . fa($estValores['mediana'])
                        . ' · desvio ' . fa($estValores['desvio']) . ' · min ' . fa($estValores['min']) . ' · max ' . fa($estValores['max'])
                        . ' · p25 ' . fa($estValores['p25']) . ' · p75 ' . fa($estValores['p75'])
                        . ' em <b>' . (int)$estValores['n'] . '</b> lancamentos com valor em R$. As linhas abaixo sao a base do calculo.';
                } else {
                    $p['titulo'] = 'Origem: volume negociado (R$)';
                    $p['nota'] = 'Volume total <b>' . fa($volumeTotal) . '</b> (entradas ' . fa($volA['volEnt'])
                        . ' + saidas ' . fa($volA['volSai']) . ') em <b>' . (int)$estValores['n'] . '</b> lancamentos com valor; ticket medio '
                        . fa($ticketMedio) . '. So entram lancamentos com total em R$ (futuros nao tem valor na planilha).';
                }
                break;

            case 'g_ativos':
                $p['titulo'] = 'Origem: ativos negociados';
                $p['cols'] = array('Painel', 'Ativo / Contrato', 'Classe / tipo', 'Entradas', 'Saidas', 'Volume (R$)', 'Realizado (R$)', 'Acerto', 'Fechamentos', 'Posicao final');
                foreach ($linhasAtivos as $l) {
                    $p['rows'][] = array(
                        'Investimento',
                        e($l['rotulo']),
                        e(isset($rotuloClasse[$l['classe']]) ? $rotuloClasse[$l['classe']] : $l['classe']),
                        (int)$l['n_ent'],
                        (int)$l['n_sai'],
                        '<span class="abs">R$ ' . number_format($l['volume'], 2, ',', '.') . '</span>',
                        '<span' . fcor($l['realizado']) . '>' . fa($l['realizado'], 2, true) . '</span>',
                        $l['win_rate'] !== null ? number_format($l['win_rate'], 0, ',', '.') . '%' : '-',
                        (int)$l['n_eventos'],
                        ($l['qtd'] > 0 ? number_format($l['qtd'], 0, ',', '.') . ($l['dir'] > 0 ? ' (comprado)' : ' (vendido)') : 'zera'),
                    );
                }
                foreach ($linhasContratos as $l) {
                    $ganhos = 0.0;
                    $perdas = 0.0;
                    $realR = 0.0;
                    foreach ($evF as $e) {
                        if ($e['rotulo'] !== $l['rotulo']) {
                            continue;
                        }
                        $rr = (isset($e['realizado_reais']) && $e['realizado_reais'] !== null) ? (float)$e['realizado_reais'] : 0.0;
                        $realR += $rr;
                        if ($rr > IMP_TOL) {
                            $ganhos += $rr;
                        } elseif ($rr < -IMP_TOL) {
                            $perdas += $rr;
                        }
                    }
                    $p['rows'][] = array(
                        'Trading',
                        e($l['rotulo']),
                        'Futuro',
                        (int)$l['n_ent'],
                        (int)$l['n_sai'],
                        '-',
                        '<span' . fcor($realR) . '>' . fp($realR, 2) . '</span>',
                        $l['win_rate'] !== null ? number_format($l['win_rate'], 0, ',', '.') . '%' : '-',
                        (int)$l['n_eventos'],
                        ($l['qtd'] > 0 ? number_format($l['qtd'], 0, ',', '.') . ($l['dir'] > 0 ? ' (aberto)' : ' (vendido)') : 'zera'),
                    );
                }
                $p['nota'] = '<b>' . count($linhasAtivos) . '</b> ativos de investimento e <b>' . count($linhasContratos)
                    . '</b> contratos de trading no recorte, ordenados por volume financeiro. Volume de futuros fica em "-" porque a planilha nao traz valor em R$.';
                break;

            case 'g_fechamentos':
            case 'g_realizado':
                $itens = array();
                foreach ($evA as $i => $e) {
                    $itens[] = array('data' => $e['data'], 'ordem' => $e['ordem'], 'row' => drill_row_fech($e, 'Investimento', $acumA[$i]));
                }
                foreach ($evF as $i => $e) {
                    $itens[] = array('data' => $e['data'], 'ordem' => $e['ordem'], 'row' => drill_row_fech($e, 'Trading', $acumF[$i]));
                }
                usort($itens, function ($a, $b) {
                    if ($a['data'] === $b['data']) {
                        return $a['ordem'] - $b['ordem'];
                    }
                    return ($a['data'] < $b['data']) ? -1 : 1;
                });
                foreach ($itens as $it) {
                    $p['rows'][] = $it['row'];
                }
                $p['cols'] = array('Data', 'Painel', 'Ativo / Contrato', 'Qtd / contratos', 'Preco da saida', 'Preco medio', 'Realizado (R$)', 'Entrada em', 'Tipo', 'Acumulado (R$)');
                if ($chave === 'g_realizado') {
                    $p['titulo'] = 'Origem: realizado (os dois topicos)';
                    $p['nota'] = 'Investimento <b>' . fa($resA['liquido'], 2, true) . '</b> · Trading <b>' . fp($resF['liquido'], 2)
                        . '</b>. A coluna "Realizado (R$)" e a origem exata dos dois numeros; trading ja vem convertido pela tabela B3.';
                } else {
                    $p['titulo'] = 'Origem: fechamentos';
                    $p['nota'] = '<b>' . count($evA) . '</b> fechamentos de investimento + <b>' . count($evF) . '</b> de trading. '
                        . 'Cada saida e comparada com o preco medio da pilha de entradas; "Entrada em" mostra quando a parte fechada tinha entrado.';
                }
                break;

            case 'g_abertas':
                foreach ($abertasA as $pa) {
                    $p['rows'][] = drill_row_aberta($pa, 'Investimento');
                }
                foreach ($abertasF as $pa) {
                    $p['rows'][] = drill_row_aberta($pa, 'Trading');
                }
                $p['titulo'] = 'Origem: posicoes abertas';
                $p['cols'] = array('Painel', 'Ativo / Contrato', 'Classe / tipo', 'Sentido', 'Quantidade', 'Preco medio', 'Primeira', 'Ultima', 'Realizado ate agora (R$)');
                $p['nota'] = '<b>' . count($abertasA) . '</b> posicoes de investimento + <b>' . count($abertasF)
                    . '</b> de trading que nao zeraram no fim do recorte. Preco medio da pilha de entradas, sem cotacao de mercado.';
                break;

            case 'i_realizado':
            case 'i_fator':
            case 'i_acerto':
            case 'i_expectativa':
            case 'i_maior_ganho':
            case 'i_maior_perda':
                $itens = array();
                foreach ($evA as $i => $e) {
                    $itens[] = array('k' => (float)$e['realizado'], 'row' => drill_row_evA($e, $acumA[$i]));
                }
                if ($chave === 'i_fator' || $chave === 'i_maior_ganho') {
                    usort($itens, function ($a, $b) {
                        return ($a['k'] < $b['k']) ? 1 : -1;
                    });
                } elseif ($chave === 'i_maior_perda') {
                    usort($itens, function ($a, $b) {
                        return ($a['k'] > $b['k']) ? 1 : -1;
                    });
                }
                if ($chave === 'i_maior_ganho' || $chave === 'i_maior_perda') {
                    $itens = array_slice($itens, 0, 100);
                }
                foreach ($itens as $it) {
                    $p['rows'][] = $it['row'];
                }
                $p['cols'] = array('Data', 'Ativo', 'Operacao', 'Sentido', 'Qtd fechada', 'Preco da saida', 'Preco medio', 'Custo', 'Realizado (R$)', 'Retorno', 'Acumulado (R$)', 'Entrada em');
                if ($chave === 'i_realizado') {
                    $p['titulo'] = 'Origem: resultados realizados (investimento)';
                    $p['nota'] = 'Liquido <b>' . fa($resA['liquido'], 2, true) . '</b> · sem anomalias <b>' . fa($liquidoSemA, 2, true)
                        . '</b> (' . count($anomA) . ' sinalizado(s) com variacao acima de 50% do preco medio). '
                        . 'Realizado = (preco da saida - preco medio) x qtd fechada, linha a linha.';
                } elseif ($chave === 'i_fator') {
                    $p['titulo'] = 'Origem: fator lucro (investimento)';
                    $p['nota'] = 'Ganhos <b>' . fa($resA['ganhos']) . '</b> / perdas <b>' . fa(abs($resA['perdas'])) . '</b> = fator <b>'
                        . ($resA['fator'] !== null ? number_format($resA['fator'], 2, ',', '.') : 'sem perdas') . '</b>. '
                        . 'Linhas ordenadas do maior ganho para a maior perda.';
                } elseif ($chave === 'i_acerto') {
                    $p['titulo'] = 'Origem: taxa de acerto (investimento)';
                    $p['nota'] = '<b>' . (int)$resA['n_ganhos'] . '</b> ganhos · <b>' . (int)$resA['n_perdas'] . '</b> perdas · <b>'
                        . (int)$resA['n_be'] . '</b> empates => taxa de acerto <b>'
                        . ($resA['win_rate'] !== null ? number_format($resA['win_rate'], 1, ',', '.') . '%' : '-')
                        . '</b> (ganhos / (ganhos + perdas)). Empate tecnico ate 1 centavo.';
                } elseif ($chave === 'i_expectativa') {
                    $p['titulo'] = 'Origem: expectativa por fechamento (investimento)';
                    $p['nota'] = 'Media de ganho <b>' . fa($resA['media_ganho']) . '</b> · media de perda <b>' . fa(abs($resA['media_perda'])) . '</b>'
                        . ' · payoff ' . ($resA['payoff'] !== null ? number_format($resA['payoff'], 2, ',', '.') : '-')
                        . ' · probabilidade de ganho ' . ($resA['win_rate'] !== null ? number_format($resA['win_rate'], 1, ',', '.') . '%' : '-')
                        . ' => expectativa <b>' . fa($resA['expectativa'], 2, true) . '</b> por fechamento.';
                } elseif ($chave === 'i_maior_ganho') {
                    $p['titulo'] = 'Origem: maior ganho (investimento)';
                    $p['nota'] = 'Os <b>' . count($p['rows']) . '</b> maiores ganhos de <b>' . (int)$resA['n']
                        . '</b> fechamentos (ate 100 linhas). O card mostra a primeira linha.';
                } else {
                    $p['titulo'] = 'Origem: maior perda (investimento)';
                    $p['nota'] = 'As <b>' . count($p['rows']) . '</b> maiores perdas de <b>' . (int)$resA['n']
                        . '</b> fechamentos (ate 100 linhas). O card mostra a primeira linha.';
                }
                break;

            case 'i_volume':
                foreach ($movsA as $m) {
                    if ((float)$m['valor_total'] <= 0) {
                        continue;
                    }
                    if (count($p['rows']) >= $limMov) {
                        break;
                    }
                    $p['rows'][] = array(
                        date('d/m/Y', strtotime($m['data'])),
                        e($m['ativo_codigo']),
                        e($m['operacao']),
                        ($m['tipo'] === 'credito') ? 'Entrada' : 'Saida',
                        fa((float)$m['preco_unitario']),
                        fa((float)$m['valor_total']),
                    );
                }
                $p['titulo'] = 'Origem: volume negociado (investimento)';
                $p['cols'] = array('Data', 'Ativo', 'Operacao', 'Tipo', 'Preco', 'Total');
                $p['nota'] = 'Volume total <b>' . fa($volumeTotal) . '</b> (entradas ' . fa($volA['volEnt']) . ' + saidas ' . fa($volA['volSai'])
                    . ') em <b>' . (int)$estValores['n'] . '</b> lancamentos de investimento com valor em R$; ticket medio ' . fa($ticketMedio) . '.';
                break;

            case 'i_abertas':
                foreach ($abertasA as $pa) {
                    $p['rows'][] = drill_row_aberta($pa, 'Investimento');
                }
                $p['titulo'] = 'Origem: posicoes abertas (investimento)';
                $p['cols'] = array('Painel', 'Ativo / Contrato', 'Classe / tipo', 'Sentido', 'Quantidade', 'Preco medio', 'Primeira', 'Ultima', 'Realizado ate agora (R$)');
                $p['nota'] = '<b>' . count($abertasA) . '</b> posicoes de investimento que nao zeraram no fim do recorte, com preco medio da pilha.';
                break;

            case 't_realizado':
            case 't_fator':
            case 't_acerto':
            case 't_expectativa':
            case 't_maior_ganho':
            case 't_maior_perda':
                $itens = array();
                foreach ($evF as $i => $e) {
                    $itens[] = array(
                        'k' => (isset($e['realizado_reais']) && $e['realizado_reais'] !== null) ? (float)$e['realizado_reais'] : 0.0,
                        'row' => drill_row_evF($e, $acumF[$i])
                    );
                }
                if ($chave === 't_fator' || $chave === 't_maior_ganho') {
                    usort($itens, function ($a, $b) {
                        return ($a['k'] < $b['k']) ? 1 : -1;
                    });
                } elseif ($chave === 't_maior_perda') {
                    usort($itens, function ($a, $b) {
                        return ($a['k'] > $b['k']) ? 1 : -1;
                    });
                }
                if ($chave === 't_maior_ganho' || $chave === 't_maior_perda') {
                    $itens = array_slice($itens, 0, 100);
                }
                foreach ($itens as $it) {
                    $p['rows'][] = $it['row'];
                }
                $p['cols'] = array('Data', 'Contrato', 'Operacao', 'Sentido', 'Contratos', 'Preco da saida', 'Preco medio', 'Pontos', 'Realizado (R$)', 'Retorno', 'Acumulado (R$)', 'Entrada em', 'Tipo');
                if ($chave === 't_realizado') {
                    $p['titulo'] = 'Origem: resultado realizado (trading)';
                    $p['nota'] = 'Liquido <b>' . fp($resF['liquido'], 2) . '</b> · day trades <b>' . fp($resDiaF['liquido'], 2) . '</b> ('
                        . (int)$resDiaF['n'] . ') · swing <b>' . fp($resSwingF['liquido'], 2) . '</b> (' . (int)$resSwingF['n']
                        . '). 1 contrato por lancamento, pontos convertidos em R$ pela tabela B3 (WIN 0,20 · WDO 10,00 · BIT 0,01 por ponto).';
                } elseif ($chave === 't_fator') {
                    $p['titulo'] = 'Origem: fator lucro (trading)';
                    $p['nota'] = 'Ganhos <b>' . fp(abs($resF['ganhos']), 2) . '</b> / perdas <b>' . fp(abs($resF['perdas']), 2) . '</b> = fator <b>'
                        . ($resF['fator'] !== null ? number_format($resF['fator'], 2, ',', '.') : 'sem perdas')
                        . '</b>. Linhas ordenadas do maior ganho para a maior perda.';
                } elseif ($chave === 't_acerto') {
                    $p['titulo'] = 'Origem: taxa de acerto (trading)';
                    $p['nota'] = '<b>' . (int)$resF['n_ganhos'] . '</b> ganhos · <b>' . (int)$resF['n_perdas'] . '</b> perdas · <b>'
                        . (int)$resF['n_be'] . '</b> empates => taxa de acerto <b>'
                        . ($resF['win_rate'] !== null ? number_format($resF['win_rate'], 1, ',', '.') . '%' : '-')
                        . '</b> (ganhos / (ganhos + perdas)). Empate tecnico ate 0,01 ponto.';
                } elseif ($chave === 't_expectativa') {
                    $p['titulo'] = 'Origem: expectativa por fechamento (trading)';
                    $p['nota'] = 'Media de ganho <b>' . fp($resF['media_ganho'], 2) . '</b> · media de perda <b>' . fp(abs($resF['media_perda']), 2) . '</b>'
                        . ' · payoff ' . ($resF['payoff'] !== null ? number_format($resF['payoff'], 2, ',', '.') : '-')
                        . ' · probabilidade de ganho ' . ($resF['win_rate'] !== null ? number_format($resF['win_rate'], 1, ',', '.') . '%' : '-')
                        . ' => expectativa <b>' . fp($resF['expectativa'], 2) . '</b> por fechamento.';
                } elseif ($chave === 't_maior_ganho') {
                    $p['titulo'] = 'Origem: maior ganho (trading)';
                    $p['nota'] = 'Os <b>' . count($p['rows']) . '</b> maiores ganhos de <b>' . (int)$resF['n']
                        . '</b> fechamentos (ate 100 linhas). O card mostra a primeira linha.';
                } else {
                    $p['titulo'] = 'Origem: maior perda (trading)';
                    $p['nota'] = 'As <b>' . count($p['rows']) . '</b> maiores perdas de <b>' . (int)$resF['n']
                        . '</b> fechamentos (ate 100 linhas). O card mostra a primeira linha.';
                }
                break;

            case 't_dia':
            case 't_swing':
                $lista = ($chave === 't_dia') ? $splitF['dia'] : $splitF['swing'];
                $acc = 0.0;
                foreach ($lista as $e) {
                    $acc += (isset($e['realizado_reais']) && $e['realizado_reais'] !== null) ? (float)$e['realizado_reais'] : 0.0;
                    $p['rows'][] = drill_row_evF($e, $acc);
                }
                $p['cols'] = array('Data', 'Contrato', 'Operacao', 'Sentido', 'Contratos', 'Preco da saida', 'Preco medio', 'Pontos', 'Realizado (R$)', 'Retorno', 'Acumulado (R$)', 'Entrada em', 'Tipo');
                if ($chave === 't_dia') {
                    $p['titulo'] = 'Origem: day trades (entrada e saida no mesmo dia)';
                    $p['nota'] = 'Fechamentos em que a parte fechada tinha entrado no <b>mesmo dia</b> ("Entrada em" = data): <b>'
                        . (int)$resDiaF['n'] . '</b> fechamentos somam <b>' . fp($resDiaF['liquido'], 2)
                        . '</b>. Nao carrega posicao de dias anteriores.';
                } else {
                    $p['titulo'] = 'Origem: swing (posicao carregada de dias anteriores)';
                    $p['nota'] = 'Fechamentos que consumiram parte de uma posicao aberta em <b>dias anteriores</b> (coluna "Entrada em" anterior a data): <b>'
                        . (int)$resSwingF['n'] . '</b> fechamentos somam <b>' . fp($resSwingF['liquido'], 2)
                        . '</b>. E a maior parcela do resultado realizado do painel.';
                }
                break;

            case 't_contratos':
                $p['titulo'] = 'Origem: contratos negociados';
                $p['cols'] = array('Contrato', 'Compras', 'Vendas', 'Fechamentos', 'Ganhos (R$)', 'Perdas (R$)', 'Liquido (R$)', 'Fator lucro', 'Acerto', 'Posicao final');
                foreach ($linhasContratos as $l) {
                    $ganhos = 0.0;
                    $perdas = 0.0;
                    $realR = 0.0;
                    foreach ($evF as $e) {
                        if ($e['rotulo'] !== $l['rotulo']) {
                            continue;
                        }
                        $rr = (isset($e['realizado_reais']) && $e['realizado_reais'] !== null) ? (float)$e['realizado_reais'] : 0.0;
                        $realR += $rr;
                        if ($rr > IMP_TOL) {
                            $ganhos += $rr;
                        } elseif ($rr < -IMP_TOL) {
                            $perdas += $rr;
                        }
                    }
                    $ft = ($perdas < 0) ? $ganhos / abs($perdas) : null;
                    $p['rows'][] = array(
                        e($l['rotulo']),
                        (int)$l['n_ent'],
                        (int)$l['n_sai'],
                        (int)$l['n_eventos'],
                        '<span style="color:var(--green)">' . fp($ganhos, 2) . '</span>',
                        '<span style="color:var(--red)">' . fp($perdas, 2) . '</span>',
                        '<span' . fcor($realR) . '>' . fp($realR, 2) . '</span>',
                        $ft !== null ? number_format($ft, 2, ',', '.') : 'sem perdas',
                        $l['win_rate'] !== null ? number_format($l['win_rate'], 0, ',', '.') . '%' : '-',
                        $l['qtd'] > 0 ? number_format($l['qtd'], 0, ',', '.') . ($l['dir'] > 0 ? ' (aberto)' : ' (vendido)') : 'zera',
                    );
                }
                $p['nota'] = '<b>' . count($linhasContratos) . '</b> contratos negociados no recorte. Ganhos e perdas somam os fechamentos em R$ '
                    . '(1 contrato por lancamento, tabela B3); "Posicao final" mostra contrato aberto no fim do recorte.';
                break;

            case 't_abertas':
                foreach ($abertasF as $pa) {
                    $p['rows'][] = drill_row_aberta($pa, 'Trading');
                }
                $p['titulo'] = 'Origem: posicoes abertas (trading)';
                $p['cols'] = array('Painel', 'Ativo / Contrato', 'Classe / tipo', 'Sentido', 'Quantidade', 'Preco medio', 'Primeira', 'Ultima', 'Realizado ate agora (R$)');
                $p['nota'] = '<b>' . count($abertasF) . '</b> posicoes de trading que nao zeraram no fim do recorte, com preco medio da pilha.';
                break;

            default:
                // detalhe por ativo (investimento) ou contrato (trading): lista os lancamentos
                // da planilha importada com botao de excluir em cada linha
                $preAto = 'lanc_ativo_';
                $preCon = 'lanc_contrato_';
                if (strpos($chave, $preCon) === 0) {
                    $ehCon = true;
                    $cod = substr($chave, strlen($preCon));
                } elseif (strpos($chave, $preAto) === 0) {
                    $ehCon = false;
                    $cod = substr($chave, strlen($preAto));
                } else {
                    return null;
                }
                if ($cod === '' || !preg_match('/^[A-Za-z0-9._\^\-]+$/', $cod)) {
                    return null;
                }
                $abaL = $ehCon ? 'trading' : 'investimento';
                $nL = 0;
                $entL = 0.0;
                $saiL = 0.0;
                foreach ($movs as $m) {
                    $igual = $ehCon
                        ? (imp_e_futuro($m) && (string)$m['ativo_nome'] === $cod)
                        : (!imp_e_futuro($m) && (string)$m['ativo_codigo'] === $cod);
                    if (!$igual) {
                        continue;
                    }
                    $nL++;
                    $vL = (float)$m['valor_total'];
                    if ($m['tipo'] === 'credito') {
                        $entL += $vL;
                    } else {
                        $saiL += $vL;
                    }
                    if (count($p['rows']) >= $limMov) {
                        continue;
                    }
                    $p['rows'][] = drill_row_lanc($m, $abaL, $f);
                }
                $p['titulo'] = 'Origem: lancamentos de ' . $cod;
                $p['cols'] = array('Data', 'Painel', 'Ativo / Contrato', 'Nome', 'Operacao', 'Tipo', 'Qtd', 'Preco', 'Total', 'Corretora', 'Acao');
                $p['nota'] = '<b>' . $nL . '</b> lancamentos de <b>' . e($cod) . '</b> no recorte (entradas ' . fa($entL) . ' · saidas ' . fa($saiL) . ')'
                    . (count($p['rows']) < $nL ? ' — mostrando os ' . $limMov . ' primeiros' : '')
                    . '. "Excluir" apaga a linha da planilha importada e recalcula carteira e analise; o saldo atual nao muda.';
                break;
        }
        return $p;
    }
}

if (isset($_GET['drill']) && $_GET['drill'] !== '') {
    require_once __DIR__ . '/../includes/auth_check.php';
    header('Content-Type: application/json; charset=utf-8');
    $pl = drill_payload((string)$_GET['drill']);
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
    <span class="muted">As abas separam o panorama geral, o painel de investimento e o painel de trading. Os filtros valem para as tres abas.</span>
    <a class="btn btn-neutro btn-mini" href="/ranksistem/pages/financeiro.php"><?php echo icone('carteira', 16); ?> Financeiro</a>
    <a class="btn btn-neutro btn-mini" href="/ranksistem/pages/financeiro-importar.php"><?php echo icone('mais', 16); ?> Importar</a>
</div>

<nav class="tabs-nav" aria-label="Abas da analise">
    <a class="tab-btn<?php echo $abaAtual === 'geral' ? ' ativo' : ''; ?>" data-aba="geral" href="<?php echo e(aba_link('geral')); ?>" style="--aba-cor:#38bdf8" aria-selected="<?php echo $abaAtual === 'geral' ? 'true' : 'false'; ?>"><?php echo icone('grafico', 16); ?> Geral</a>
    <a class="tab-btn<?php echo $abaAtual === 'investimento' ? ' ativo' : ''; ?>" data-aba="investimento" href="<?php echo e(aba_link('investimento')); ?>" style="--aba-cor:#22c55e" aria-selected="<?php echo $abaAtual === 'investimento' ? 'true' : 'false'; ?>"><?php echo icone('carteira', 16); ?> Investimento</a>
    <a class="tab-btn<?php echo $abaAtual === 'trading' ? ' ativo' : ''; ?>" data-aba="trading" href="<?php echo e(aba_link('trading')); ?>" style="--aba-cor:#a78bfa" aria-selected="<?php echo $abaAtual === 'trading' ? 'true' : 'false'; ?>"><?php echo icone('raio', 16); ?> Trading</a>
</nav>

<!-- ============================ ABA GERAL ============================ -->

<section class="grid" data-aba="geral" <?php echo $hidG; ?>>
    <?php funil_etapa('Geral', 'Panorama consolidado dos dois topicos: periodo, volume, lancamentos e a leitura lado a lado de investimento e trading.', '#38bdf8'); ?>
</section>

<section class="grid">
    <?php
    ob_start();
    $atual = array();
    foreach (array('de' => 'De', 'ate' => 'Ate', 'classe' => 'Classe', 'corretora' => 'Corretora', 'ativo' => 'Ativo', 'contrato' => 'Contrato') as $campo => $rot) {
        if ($f[$campo] !== '') {
            $atual[] = $rot . ' <strong>' . e($f[$campo]) . '</strong>';
        }
    }
    if ($atual) {
        echo '<div class="tabela-filtro">Filtros da investigacao: ' . implode(' · ', $atual)
            . ' <a class="link-mini" href="/ranksistem/pages/financeiro-analise.php?aba=' . e($abaAtual) . '">limpar todos</a></div>';
    }
    ?>
    <form method="get" action="/ranksistem/pages/financeiro-analise.php" class="filtros-form">
        <input type="hidden" name="aba" value="<?php echo e($abaAtual); ?>">
        <?php
        form_campo(array('name' => 'de', 'label' => 'De', 'type' => 'date', 'value' => $f['de']));
        form_campo(array('name' => 'ate', 'label' => 'Ate', 'type' => 'date', 'value' => $f['ate']));

        $opClasse = array('' => 'Todas');
        foreach ($opcoes['classes'] as $c) {
            $opClasse[$c] = isset($rotuloClasse[$c]) ? $rotuloClasse[$c] : $c;
        }
        form_campo(array('name' => 'classe', 'label' => 'Classe', 'type' => 'select', 'value' => $f['classe'], 'options' => $opClasse));

        $opCorr = array('' => 'Todas');
        foreach ($opcoes['corretoras'] as $c) {
            $opCorr[$c] = $c;
        }
        form_campo(array('name' => 'corretora', 'label' => 'Corretora', 'type' => 'select', 'value' => $f['corretora'], 'options' => $opCorr));

        $opAtivo = array('' => 'Todos');
        foreach ($opcoes['ativos'] as $c) {
            $opAtivo[$c] = $c;
        }
        form_campo(array('name' => 'ativo', 'label' => 'Ativo (investimento)', 'type' => 'select', 'value' => $f['ativo'], 'options' => $opAtivo));

        $opCon = array('' => 'Todos');
        foreach ($opcoes['contratos'] as $c) {
            $opCon[$c] = $c;
        }
        form_campo(array('name' => 'contrato', 'label' => 'Contrato (trading)', 'type' => 'select', 'value' => $f['contrato'], 'options' => $opCon));
        ?>
        <div class="campo">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primario">Aplicar filtros</button>
        </div>
        <div class="campo">
            <label>&nbsp;</label>
            <a class="btn btn-neutro" href="/ranksistem/pages/financeiro-analise.php?aba=<?php echo e($abaAtual); ?>">Limpar</a>
        </div>
    </form>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Filtros da analise', $conteudo, '#38bdf8', 'alvo', count($movs) . ' lancamentos no recorte');
    ?>
</section>

<?php
$verAvisoAnomA = ($abaAtual !== 'trading') && $anomA;
$verAvisoAnomF = ($abaAtual !== 'investimento') && $anomF;
if ($ct['n_sem_preco'] > 0 || $verAvisoAnomA || $verAvisoAnomF) { ?>
    <section class="grid">
        <?php
        ob_start();
        if ($ct['n_sem_preco'] > 0) {
            echo '<div class="aviso-amostra" style="--cor:#fbbf24">' . icone('cerebro', 18)
                . '<span><strong>' . (int)$ct['n_sem_preco'] . ' lancamento(s) sem preco nao entraram no calculo do preco medio.</strong> ';
            $linhas = array();
            foreach ($ct['sem_preco'] as $s) {
                $linhas[] = $s['data'] . ' ' . $s['ativo'] . ' (' . $s['operacao'] . ', qtd ' . number_format($s['qtd'], 0, ',', '.') . ')';
            }
            echo e(implode(' · ', $linhas)) . '</span></div>';
        }
        if ($verAvisoAnomA) {
            echo '<div class="aviso-amostra" style="--cor:#f87171; margin-top:10px">' . icone('cerebro', 18)
                . '<span><strong>' . count($anomA) . ' fechamento(s) de investimento com variacao acima de 50% do preco medio.</strong> '
                . 'Costumam ser desdobramento de acao, ajuste de preco ou erro na planilha. Conferir na origem: '
                . 'liquido com eles ' . fa($resA['liquido'], 2, true) . ', sem eles ' . fa($liquidoSemA, 2, true) . '.<br>';
            $lista = array();
            foreach (array_slice($anomA, 0, 8) as $e) {
                $lista[] = $e['data'] . ' ' . $e['rotulo'] . ' ' . number_format($e['retorno'], 0, ',', '.') . '%';
            }
            echo e(implode(' · ', $lista)) . (count($anomA) > 8 ? ' ...' : '') . '</span></div>';
        }
        if ($verAvisoAnomF) {
            $lista = array();
            foreach (array_slice($anomF, 0, 8) as $e) {
                $lista[] = $e['data'] . ' ' . $e['rotulo'] . ' ' . number_format($e['retorno'], 1, ',', '.') . '%';
            }
            echo '<div class="aviso-amostra" style="--cor:#f87171; margin-top:10px">' . icone('cerebro', 18)
                . '<span><strong>' . count($anomF) . ' fechamento(s) de trading com variacao acima de 50% do preco medio.</strong> '
                . e(implode(' · ', $lista)) . '</span></div>';
        }
        $conteudo = ob_get_clean();
        card_bloco('Avisos de qualidade dos dados', $conteudo, '#fbbf24', 'cerebro', 'nada foi apagado, so sinalizado');
        ?>
    </section>
<?php } ?>

<section class="grid grid-stats" data-aba="geral" <?php echo $hidG; ?>>
    <?php
    card_stat(array(
        'titulo' => 'Periodo analisado',
        'valor' => ($ct['min_data'] ? date('d/m/Y', strtotime($ct['min_data'])) : '-') . ' a ' . ($ct['max_data'] ? date('d/m/Y', strtotime($ct['max_data'])) : '-'),
        'sub' => $ct['dias'] . ' dias com lancamento',
        'cor' => '#38bdf8',
        'icone' => 'grafico',
        'drill' => 'g_periodo',
    ));
    card_stat(array(
        'titulo' => 'Lancamentos',
        'valor' => number_format(count($movs), 0, ',', '.'),
        'sub' => number_format($volA['n'], 0, ',', '.') . ' investimento / ' . number_format($volF['n'], 0, ',', '.') . ' trading',
        'cor' => '#a78bfa',
        'icone' => 'livro',
        'drill' => 'g_lancamentos',
    ));
    card_stat(array(
        'titulo' => 'Volume negociado (R$)',
        'valor' => fa($volumeTotal),
        'sub' => 'ticket medio R$ ' . number_format($ticketMedio, 2, ',', '.') . ' em ' . $estValores['n'] . ' lancamentos com valor',
        'cor' => '#22c55e',
        'icone' => 'carteira',
        'drill' => 'g_volume',
    ));
    card_stat(array(
        'titulo' => 'Media dos valores',
        'valor' => fa($estValores['media']),
        'sub' => 'mediana R$ ' . number_format((float)$estValores['mediana'], 2, ',', '.') . ' · desvio R$ ' . number_format((float)$estValores['desvio'], 2, ',', '.'),
        'cor' => '#2dd4bf',
        'icone' => 'grafico',
        'drill' => 'g_media',
    ));
    card_stat(array(
        'titulo' => 'Ativos negociados',
        'valor' => (int)$ct['n_ativos'],
        'sub' => (int)$ct['n_contratos'] . ' contratos de trading · ' . (int)$ct['n_corretoras'] . ' corretora(s)',
        'cor' => '#8ba0c4',
        'icone' => 'alvo',
        'drill' => 'g_ativos',
    ));
    card_stat(array(
        'titulo' => 'Fechamentos',
        'valor' => number_format(count($eventos), 0, ',', '.'),
        'sub' => (int)$resA['n'] . ' investimento / ' . (int)$resF['n'] . ' trading',
        'cor' => '#fbbf24',
        'icone' => 'check',
        'drill' => 'g_fechamentos',
    ));
    card_stat(array(
        'titulo' => 'Realizado (os dois topicos)',
        'valor' => fa($resA['liquido'], 2, true) . ' <span class="muted">|</span> ' . fp($resF['liquido'], 2),
        'sub' => 'investimento e trading em R$',
        'cor' => '#e879f9',
        'icone' => 'grafico',
        'drill' => 'g_realizado',
    ));
    card_stat(array(
        'titulo' => 'Posicoes abertas',
        'valor' => count($abertasA) + count($abertasF),
        'sub' => count($abertasA) . ' investimento / ' . count($abertasF) . ' trading',
        'cor' => '#f472b6',
        'icone' => 'carteira',
        'drill' => 'g_abertas',
    ));
    ?>
</section>

<section class="grid grid-2" data-aba="geral" <?php echo $hidG; ?>>
    <?php
    ob_start();
    ?>
    <canvas id="chartPanorama" height="260"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Panorama dos dois topicos (curvas acumuladas)', $conteudo, '#e879f9', 'grafico', 'R$ no eixo esquerdo · trading convertido em R$');
    ?>

    <?php
    ob_start();
    echo '<div class="formula-lista">';
    echo '<div class="formula-item" style="border-left:4px solid #22c55e"><strong>Investimento (acoes, BDR, FIIs)</strong>'
        . '<span>' . e($leituraA) . '</span></div>';
    echo '<div class="formula-item" style="border-left:4px solid #a78bfa"><strong>Trading (futuros e indices)</strong>'
        . '<span>' . e($leituraF) . '</span></div>';
    echo '<div class="formula-item" style="border-left:4px solid #fbbf24"><strong>Por que os dois numeros nao se somam</strong>'
        . '<span>Investimento e medido em reais pelo preco medio; trading e medido em pontos e convertido em R$ pela tabela B3 (WIN 0,20 · WDO 10,00 · BIT 0,01 por ponto).</span></div>';
    echo '</div>';
    echo '<p style="margin-top:12px"><a class="btn btn-neutro btn-mini" data-aba-ir="investimento" href="#indices">Ir para os indices dos paineis &rarr;</a> '
        . '<a class="btn btn-neutro btn-mini" href="#operacoes">Ir para as operacoes &rarr;</a></p>';
    $conteudo = ob_get_clean();
    card_bloco('Leitura automatica dos dois topicos', $conteudo, '#38bdf8', 'cerebro', 'mesmo motor dos paineis');
    ?>
</section>

<!-- ============================ ABA INVESTIMENTO ============================ -->

<section class="grid" id="indices" data-aba="investimento" <?php echo $hidI; ?>>
    <?php funil_etapa('Investimento', 'Acoes, BDR, FIIs e ETFs com seus proprios indices: realizado, fator lucro, taxa de acerto, medias e graficos.', '#22c55e'); ?>
</section>

<section class="grid" data-aba="investimento" <?php echo $hidI; ?>>
    <?php
    ob_start();
    echo '<p class="muted">' . e($leituraA) . '</p>';
    $conteudo = ob_get_clean();
    card_bloco(
        'Painel Investimento - acoes, BDR, FIIs e ETFs',
        $conteudo,
        '#22c55e',
        'carteira',
        badge((int)$volA['n'] . ' lancamentos', '#22c55e') . ' ' . badge((int)$resA['n'] . ' fechamentos', '#38bdf8')
    );
    ?>
</section>

<section class="grid grid-stats" data-aba="investimento" <?php echo $hidI; ?>>
    <?php
    card_stat(array(
        'titulo' => 'Resultados realizados (R$)',
        'valor' => fa($resA['liquido'], 2, true),
        'sub' => 'sem as anomalias: ' . fa($liquidoSemA, 2, true),
        'cor' => $resA['liquido'] >= 0 ? '#22c55e' : '#f87171',
        'icone' => 'grafico',
        'drill' => 'i_realizado',
    ));
    card_stat(array(
        'titulo' => 'Fator lucro (R$)',
        'valor' => $resA['fator'] !== null ? number_format($resA['fator'], 2, ',', '.') : 'sem perdas',
        'sub' => 'ganhos R$ ' . number_format($resA['ganhos'], 2, ',', '.') . ' / perdas R$ ' . number_format(abs($resA['perdas']), 2, ',', '.'),
        'cor' => ($resA['fator'] !== null && $resA['fator'] >= 1) ? '#22c55e' : '#f87171',
        'icone' => 'raio',
        'drill' => 'i_fator',
    ));
    card_stat(array(
        'titulo' => 'Taxa de acerto (R$)',
        'valor' => $resA['win_rate'] !== null ? number_format($resA['win_rate'], 1, ',', '.') . '%' : '-',
        'sub' => $resA['n_ganhos'] . ' ganhos · ' . $resA['n_perdas'] . ' perdas · ' . $resA['n_be'] . ' be',
        'cor' => '#38bdf8',
        'icone' => 'check',
        'drill' => 'i_acerto',
    ));
    card_stat(array(
        'titulo' => 'Expectativa por fechamento',
        'valor' => fa($resA['expectativa'], 2, true),
        'sub' => 'payoff ' . ($resA['payoff'] !== null ? number_format($resA['payoff'], 2, ',', '.') : '-'),
        'cor' => ($resA['expectativa'] !== null && $resA['expectativa'] >= 0) ? '#22c55e' : '#f87171',
        'icone' => 'alvo',
        'drill' => 'i_expectativa',
    ));
    card_stat(array(
        'titulo' => 'Maior ganho',
        'valor' => $resA['maior_ganho'] ? fa($resA['maior_ganho']['realizado'], 2, true) : '-',
        'sub' => $resA['maior_ganho'] ? $resA['maior_ganho']['rotulo'] . ' em ' . date('d/m/Y', strtotime($resA['maior_ganho']['data'])) : 'sem ganhos',
        'cor' => '#22c55e',
        'icone' => 'grafico',
        'drill' => 'i_maior_ganho',
    ));
    card_stat(array(
        'titulo' => 'Maior perda',
        'valor' => $resA['maior_perda'] ? fa($resA['maior_perda']['realizado'], 2, true) : '-',
        'sub' => $resA['maior_perda'] ? $resA['maior_perda']['rotulo'] . ' em ' . date('d/m/Y', strtotime($resA['maior_perda']['data'])) : 'sem perdas',
        'cor' => '#f87171',
        'icone' => 'grafico',
        'drill' => 'i_maior_perda',
    ));
    card_stat(array(
        'titulo' => 'Volume negociado (R$)',
        'valor' => fa($volumeTotal),
        'sub' => $volA['ent'] . ' entradas / ' . $volA['said'] . ' saidas',
        'cor' => '#2dd4bf',
        'icone' => 'carteira',
        'drill' => 'i_volume',
    ));
    card_stat(array(
        'titulo' => 'Posicoes abertas',
        'valor' => count($abertasA),
        'sub' => 'investimento com preco medio',
        'cor' => '#fbbf24',
        'icone' => 'alvo',
        'drill' => 'i_abertas',
    ));
    ?>
</section>

<section class="grid grid-2" data-aba="investimento" <?php echo $hidI; ?>>
    <?php
    ob_start();
    echo '<div class="stat-linha">';
    est_mini('Sem as anomalias', fa($liquidoSemA, 2, true), count($anomA) . ' fechamento(s) extremo(s) fora', $liquidoSemA >= 0 ? 'var(--green)' : 'var(--red)');
    est_mini('Fator lucro', $resA['fator'] !== null ? number_format($resA['fator'], 2, ',', '.') : 'sem perdas', 'ganhos dividido pelas perdas');
    est_mini('Payoff', $resA['payoff'] !== null ? number_format($resA['payoff'], 2, ',', '.') : '-', 'ganho medio / perda media');
    est_mini('Expectativa por fechamento', fa($resA['expectativa'], 2, true), 'prob x ganho medio - (1-prob) x perda media', $resA['expectativa'] >= 0 ? 'var(--green)' : 'var(--red)');
    est_mini('Media de ganho', fa($resA['media_ganho']), 'em ' . $resA['n_ganhos'] . ' ganhos', 'var(--green)');
    est_mini('Media de perda', fa(abs($resA['media_perda'])), 'em ' . $resA['n_perdas'] . ' perdas', 'var(--red)');
    est_mini('Retorno medio', $resA['media_retorno'] !== null ? number_format($resA['media_retorno'], 2, ',', '.') . '%' : '-', 'puxado pelos casos extremos');
    est_mini('Retorno mediana', $resA['mediana_retorno'] !== null ? number_format($resA['mediana_retorno'], 2, ',', '.') . '%' : '-', 'metade dos fechamentos fica abaixo disso');
    est_mini('Desvio do retorno', $resA['desvio_retorno'] !== null ? number_format($resA['desvio_retorno'], 2, ',', '.') . '%' : '-', 'dispersao dos retornos');
    est_mini('Melhor sequencia', $resA['melhor_seq'] . ' ganhos', 'consecutivos');
    est_mini('Pior sequencia', $resA['pior_seq'] . ' perdas', 'consecutivas');
    est_mini('Drawdown da curva', fa($serieA['drawdown'], 2, true), $serieA['data_drawdown'] ? 'ate ' . date('d/m/Y', strtotime($serieA['data_drawdown'])) : 'sem retração', 'var(--red)');
    est_mini('Pico acumulado', fa($serieA['pico']), 'melhor nivel da curva acumulada');
    est_mini('Quantidade media', number_format($resA['media_qtd'], 1, ',', '.') . ' papeis', 'por fechamento');
    est_mini('Maior lancamento', fa($estValores['max']), 'entrada ou saida mais cara');
    est_mini('Menor lancamento', fa($estValores['min']), 'menor valor registrado');
    est_mini('Percentil 25', fa($estValores['p25']), '25% dos lancamentos ficam abaixo');
    est_mini('Percentil 75', fa($estValores['p75']), '25% dos lancamentos ficam acima');
    echo '</div>';
    $conteudo = ob_get_clean();
    card_bloco('Estatisticas detalhadas - investimento (R$)', $conteudo, '#22c55e', 'grafico', (int)$resA['n'] . ' fechamentos');
    ?>

    <?php
    ob_start();
    echo '<div class="formula-lista">';
    ?>
    <div class="formula-item">
        <strong>Resultado realizado (R$)</strong>
        <span>Cada saida e comparada com o preco medio ponderado das entradas anteriores. A diferenca multiplicada pela quantidade fechada e o lucro ou prejuizo realizado.</span>
        <code>realizado = (preco_saida - preco_medio) * qtd_fechada * sentido</code>
    </div>
    <div class="formula-item">
        <strong>Fator lucro, payoff e expectativa</strong>
        <span>Ganhos e perdas sao separados por fechamento (tolerancia de 1 centavo define empate).</span>
        <code>fator_lucro = SUM(ganhos) / ABS(SUM(perdas))
            payoff = media_ganho / media_perda
            expectativa = p_ganho * media_ganho - (1 - p_ganho) * media_perda</code>
    </div>
    <div class="formula-item">
        <strong>Sem preco na planilha</strong>
        <span><?php
                if ($ct['n_sem_preco'] > 0) {
                    $linhas = array();
                    foreach ($ct['sem_preco'] as $s) {
                        $linhas[] = $s['data'] . ' ' . $s['ativo'] . ' qtd ' . number_format($s['qtd'], 0, ',', '.');
                    }
                    echo e(implode(' · ', $linhas));
                } else {
                    echo 'Todos os lancamentos de investimento tem preco registrado.';
                }
                ?> — fora do calculo de preco medio para nao distorcer a media.</span>
    </div>
    <div class="formula-item">
        <strong>Fechamentos que pedem conferencia</strong>
        <span><?php
                if ($anomA) {
                    $lista = array();
                    foreach (array_slice($anomA, 0, 6) as $e) {
                        $lista[] = $e['rotulo'] . ' em ' . date('d/m/Y', strtotime($e['data'])) . ' (' . number_format($e['retorno'], 1, ',', '.') . '%)';
                    }
                    echo e(implode(' · ', $lista));
                } else {
                    echo 'Nenhum fechamento com variacao extrema neste recorte.';
                }
                ?> — variacao acima de 50% do preco medio: costuma ser desdobramento ou erro de digitacao.</span>
    </div>
    <?php
    echo '</div>';
    $conteudo = ob_get_clean();
    card_bloco('Como o painel de investimento calcula', $conteudo, '#38bdf8', 'livro', 'explicabilidade');
    ?>
</section>

<section class="grid grid-3" data-aba="investimento" <?php echo $hidI; ?>>
    <?php
    ob_start();
    ?>
    <canvas id="chartAcum" height="230"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Resultado realizado acumulado (R$)', $conteudo, '#22c55e', 'grafico', count($serieA['linhas']) . ' dias');
    ?>

    <?php
    ob_start();
    ?>
    <canvas id="chartMes" height="230"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Resultado realizado por mes (R$)', $conteudo, '#38bdf8', 'grafico', count($mesesA) . ' meses');
    ?>

    <?php
    ob_start();
    ?>
    <canvas id="chartTopReal" height="230"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Melhores e piores ativos por resultado (R$)', $conteudo, '#fbbf24', 'alvo', count($topReal) . ' ativos');
    ?>
</section>

<section class="grid grid-3" data-aba="investimento" <?php echo $hidI; ?>>
    <?php
    ob_start();
    ?>
    <canvas id="chartVolumeMes" height="230"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Entradas e saidas por mes (R$)', $conteudo, '#2dd4bf', 'carteira', 'volume financeiro');
    ?>

    <?php
    ob_start();
    ?>
    <canvas id="chartDist" height="230"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Distribuicao dos valores (R$)', $conteudo, '#fb923c', 'grafico', 'lancamentos por faixa');
    ?>

    <?php
    ob_start();
    ?>
    <canvas id="chartSemana" height="230"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Resultado realizado por dia da semana (R$)', $conteudo, '#e879f9', 'grafico');
    ?>
</section>

<section class="grid" data-aba="trading" <?php echo $hidT; ?>>
    <?php funil_etapa('Trading', 'Futuros e indices com seus proprios indices: realizado, fator lucro, taxa de acerto, medias e graficos.', '#a78bfa'); ?>
</section>

<section class="grid" data-aba="trading" <?php echo $hidT; ?>>
    <?php
    ob_start();
    echo '<p class="muted">' . e($leituraF) . '</p>';
    echo '<div class="formula-lista" style="margin-top:10px">';
    echo '<div class="formula-item" style="border-left:4px solid #a78bfa"><strong>Trading convertido para R$ (minicontratos B3)</strong>'
        . '<span>WIN (mini indice) R$ 0,20 por ponto · WDO (mini dolar) R$ 10,00 por ponto · BIT (mini bitcoin) R$ 0,01 por ponto. '
        . 'Todos os numeros deste painel ja estao em R$: pontos x valor_do_ponto x 1 contrato por lancamento.</span></div>';
    echo '</div>';
    $conteudo = ob_get_clean();
    card_bloco(
        'Painel Trading - futuros e indices (R$)',
        $conteudo,
        '#a78bfa',
        'raio',
        badge((int)$volF['n'] . ' lancamentos', '#a78bfa') . ' ' . badge((int)$resF['n'] . ' fechamentos', '#38bdf8') . ' ' . badge('valores em R$', '#8ba0c4')
    );
    ?>
</section>

<section class="grid grid-stats" data-aba="trading" <?php echo $hidT; ?>>
    <?php
    card_stat(array(
        'titulo' => 'Resultado realizado (R$)',
        'valor' => fp($resF['liquido'], 2),
        'sub' => 'convertido pela tabela B3 (1 contrato por lancamento)',
        'cor' => $resF['liquido'] >= 0 ? '#22c55e' : '#f87171',
        'icone' => 'grafico',
        'drill' => 't_realizado',
    ));
    card_stat(array(
        'titulo' => 'Day trades (R$)',
        'valor' => fp($resDiaF['liquido'], 2),
        'sub' => (int)$resDiaF['n'] . ' fechamentos no mesmo dia',
        'cor' => $resDiaF['liquido'] >= 0 ? '#22c55e' : '#f87171',
        'icone' => 'check',
        'drill' => 't_dia',
    ));
    card_stat(array(
        'titulo' => 'Swing (R$)',
        'valor' => fp($resSwingF['liquido'], 2),
        'sub' => (int)$resSwingF['n'] . ' fechamentos de dias anteriores',
        'cor' => $resSwingF['liquido'] >= 0 ? '#22c55e' : '#f87171',
        'icone' => 'raio',
        'drill' => 't_swing',
    ));
    card_stat(array(
        'titulo' => 'Fator lucro (R$)',
        'valor' => $resF['fator'] !== null ? number_format($resF['fator'], 2, ',', '.') : 'sem perdas',
        'sub' => 'ganhos R$ ' . number_format(abs($resF['ganhos']), 2, ',', '.') . ' / perdas R$ ' . number_format(abs($resF['perdas']), 2, ',', '.'),
        'cor' => ($resF['fator'] !== null && $resF['fator'] >= 1) ? '#22c55e' : '#f87171',
        'icone' => 'raio',
        'drill' => 't_fator',
    ));
    card_stat(array(
        'titulo' => 'Taxa de acerto (R$)',
        'valor' => $resF['win_rate'] !== null ? number_format($resF['win_rate'], 1, ',', '.') . '%' : '-',
        'sub' => $resF['n_ganhos'] . ' ganhos · ' . $resF['n_perdas'] . ' perdas · ' . $resF['n_be'] . ' be',
        'cor' => '#38bdf8',
        'icone' => 'check',
        'drill' => 't_acerto',
    ));
    card_stat(array(
        'titulo' => 'Expectativa por fechamento',
        'valor' => fp($resF['expectativa'], 2),
        'sub' => 'payoff ' . ($resF['payoff'] !== null ? number_format($resF['payoff'], 2, ',', '.') : '-'),
        'cor' => ($resF['expectativa'] !== null && $resF['expectativa'] >= 0) ? '#22c55e' : '#f87171',
        'icone' => 'alvo',
        'drill' => 't_expectativa',
    ));
    card_stat(array(
        'titulo' => 'Maior ganho',
        'valor' => $resF['maior_ganho'] ? fp($resF['maior_ganho']['realizado'], 2) : '-',
        'sub' => $resF['maior_ganho'] ? $resF['maior_ganho']['rotulo'] . ' em ' . date('d/m/Y', strtotime($resF['maior_ganho']['data'])) : 'sem ganhos',
        'cor' => '#22c55e',
        'icone' => 'grafico',
        'drill' => 't_maior_ganho',
    ));
    card_stat(array(
        'titulo' => 'Maior perda',
        'valor' => $resF['maior_perda'] ? fp($resF['maior_perda']['realizado'], 2) : '-',
        'sub' => $resF['maior_perda'] ? $resF['maior_perda']['rotulo'] . ' em ' . date('d/m/Y', strtotime($resF['maior_perda']['data'])) : 'sem perdas',
        'cor' => '#f87171',
        'icone' => 'grafico',
        'drill' => 't_maior_perda',
    ));
    card_stat(array(
        'titulo' => 'Contratos negociados',
        'valor' => (int)$ct['n_contratos'],
        'sub' => number_format($volF['n'], 0, ',', '.') . ' lancamentos (1 contrato por lancamento)',
        'cor' => '#a78bfa',
        'icone' => 'alvo',
        'drill' => 't_contratos',
    ));
    card_stat(array(
        'titulo' => 'Posicoes abertas',
        'valor' => count($abertasF),
        'sub' => 'trading com preco medio',
        'cor' => '#fbbf24',
        'icone' => 'carteira',
        'drill' => 't_abertas',
    ));
    ?>
</section>

<section class="grid grid-2" data-aba="trading" <?php echo $hidT; ?>>
    <?php
    ob_start();
    echo '<div class="stat-linha">';
    est_mini('Ganhos (R$)', fp($resF['ganhos'], 2), 'soma dos ganhos', 'var(--green)');
    est_mini('Perdas (R$)', fp($resF['perdas'], 2), 'soma das perdas', 'var(--red)');
    est_mini('Media de ganho', fp($resF['media_ganho'], 2), 'R$ por ganho', 'var(--green)');
    est_mini('Media de perda', fp(abs($resF['media_perda']), 2), 'R$ por perda', 'var(--red)');
    est_mini('Payoff', $resF['payoff'] !== null ? number_format($resF['payoff'], 2, ',', '.') : '-', 'ganho medio / perda media');
    est_mini('Expectativa por fechamento', fp($resF['expectativa'], 2), 'em R$ por fechamento', $resF['expectativa'] >= 0 ? 'var(--green)' : 'var(--red)');
    est_mini('Retorno medio', $resF['media_retorno'] !== null ? number_format($resF['media_retorno'], 3, ',', '.') . '%' : '-', 'variacao percentual do preco do contrato');
    est_mini('Retorno mediana', $resF['mediana_retorno'] !== null ? number_format($resF['mediana_retorno'], 3, ',', '.') . '%' : '-', 'metade dos fechamentos abaixo disso');
    est_mini('Desvio do retorno', $resF['desvio_retorno'] !== null ? number_format($resF['desvio_retorno'], 3, ',', '.') . '%' : '-', 'dispersao dos retornos');
    est_mini('Melhor sequencia', $resF['melhor_seq'] . ' ganhos', 'consecutivos');
    est_mini('Pior sequencia', $resF['pior_seq'] . ' perdas', 'consecutivas');
    est_mini('Drawdown da curva', fp($serieF['drawdown'], 2), $serieF['data_drawdown'] ? 'ate ' . date('d/m/Y', strtotime($serieF['data_drawdown'])) : 'sem retração', 'var(--red)');
    est_mini('Pico acumulado', fp($serieF['pico'], 2), 'melhor nivel da curva em R$');
    est_mini('Contratos fechados', number_format($resF['n'], 0, ',', '.'), '1 contrato por lancamento (a planilha nao traz quantidade)');
    est_mini('Fechamentos por dia', number_format($ct['dias'] > 0 ? $resF['n'] / $ct['dias'] : 0, 2, ',', '.'), 'no periodo filtrado');
    echo '</div>';
    $conteudo = ob_get_clean();
    card_bloco('Estatisticas detalhadas - trading (R$)', $conteudo, '#a78bfa', 'grafico', (int)$resF['n'] . ' fechamentos · conversao B3 aplicada');
    ?>

    <?php
    ob_start();
    echo '<div class="formula-lista">';
    ?>
    <div class="formula-item">
        <strong>Resultado realizado (R$)</strong>
        <span>A planilha nao traz quantidade nos contratos, so o preco. Cada lancamento e tratado como 1 contrato e o resultado sai em pontos; depois convertido em R$ pela tabela B3.</span>
        <code>pontos = (preco_saida - preco_medio) * contratos * sentido
            R$ = pontos x valor_do_ponto</code>
    </div>
    <div class="formula-item">
        <strong>Conversao de pontos para R$ (minicontratos B3)</strong>
        <span>WIN (mini indice) cada ponto vale R$ 0,20 · WDO (mini dolar) R$ 10,00 · BIT (mini bitcoin) R$ 0,01. Contratos cheios: IND R$ 1,00 · DOL R$ 50,00. A conversao usa 1 contrato por lancamento.</span>
        <code>R$ = pontos x valor_do_ponto x contratos</code>
    </div>
    <div class="formula-item">
        <strong>Fator lucro e taxa de acerto</strong>
        <span>Mesma conta do painel de investimento, porem o resultado sai em pontos e e convertido em R$ (WIN 0,20 · WDO 10,00 · BIT 0,01 por ponto). Empate tecnico (ate 0,01 ponto) nao conta nem como ganho nem como perda.</span>
        <code>fator_lucro = SUM(pontos_ganhos) / ABS(SUM(pontos_perdas))</code>
    </div>
    <?php
    echo '</div>';
    $conteudo = ob_get_clean();
    card_bloco('Como o painel de trading calcula', $conteudo, '#38bdf8', 'livro', 'explicabilidade');
    ?>
</section>

<section class="grid grid-3" data-aba="trading" <?php echo $hidT; ?>>
    <?php
    ob_start();
    ?>
    <canvas id="chartAcumFut" height="230"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Resultado acumulado (R$)', $conteudo, '#a78bfa', 'grafico', count($serieF['linhas']) . ' dias');
    ?>

    <?php
    ob_start();
    ?>
    <canvas id="chartMesFut" height="230"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Resultado realizado por mes (R$)', $conteudo, '#38bdf8', 'grafico', count($mesesF) . ' meses');
    ?>

    <?php
    ob_start();
    ?>
    <canvas id="chartContratos" height="230"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Liquido por contrato (R$)', $conteudo, '#fbbf24', 'alvo', count($linhasContratos) . ' contratos');
    ?>
</section>

<!-- ============================ 3. OPERACOES ============================ -->

<section class="grid" id="operacoes">
    <?php funil_etapa('Operacoes', 'Detalhamento linha a linha: fechamentos, posicoes abertas, ativos, contratos e todos os lancamentos do recorte.', '#22c55e', 3); ?>
</section>

<section class="grid" data-aba="investimento" <?php echo $hidI; ?>>
    <?php
    ob_start();
    $rows = array();
    foreach ($linhasAtivos as $l) {
        $rows[] = array(
            '<span class="link-mini drill-gatilho" role="button" tabindex="0" data-drill="lanc_ativo_' . e($l['rotulo']) . '" title="Ver os lancamentos deste ativo e excluir linha a linha">' . e($l['rotulo']) . '</span>',
            e(isset($rotuloClasse[$l['classe']]) ? $rotuloClasse[$l['classe']] : $l['classe']),
            (int)$l['n_ent'],
            (int)$l['n_sai'],
            '<span class="abs">R$ ' . number_format($l['volume'], 2, ',', '.') . '</span>',
            '<span' . fcor($l['realizado']) . '>' . fa($l['realizado'], 2, true) . '</span>',
            $l['win_rate'] !== null ? number_format($l['win_rate'], 0, ',', '.') . '%' : '-',
            (int)$l['n_eventos'],
            ($l['qtd'] > 0 ? number_format($l['qtd'], 0, ',', '.') . ($l['dir'] > 0 ? ' (comprado)' : ' (vendido)') : 'zera'),
            $l['pmp'] > 0 ? fa($l['pmp']) : '-',
            $l['volume'] > 0 ? number_format($l['volume'] / max(0.0001, $volumeTotal) * 100, 1, ',', '.') . '%' : '-',
        );
    }
    tabela(
        array('Ativo', 'Classe', 'Entradas', 'Saidas', 'Volume (R$)', 'Realizado (R$)', 'Acerto', 'Fechamentos', 'Posicao final', 'Preco medio', '% do volume'),
        $rows,
        'Nenhum ativo de investimento no recorte.',
        array('data_table' => true, 'id' => 'tabelaAtivos', 'placeholder' => 'Pesquisar ativo, classe...', 'busca' => $f['ativo'])
    );
    $conteudo = ob_get_clean();
    card_bloco('Investimento por ativo', $conteudo, '#22c55e', 'alvo', count($rows) . ' ativos · clique no ativo para ver e excluir lancamentos');
    ?>
</section>

<section class="grid grid-2" data-aba="investimento" <?php echo $hidI; ?>>
    <?php
    ob_start();
    $rows = array();
    foreach ($evA as $i => $e) {
        $cor = $e['realizado'] > IMP_TOL ? '#22c55e' : ($e['realizado'] < -IMP_TOL ? '#f87171' : '#8ba0c4');
        $rows[] = array(
            date('d/m/Y', strtotime($e['data'])),
            '<a class="link-mini" href="/ranksistem/pages/financeiro-analise.php?ativo=' . urlencode($e['rotulo']) . '">' . e($e['rotulo']) . '</a>',
            e($e['operacao']),
            badge($e['dir'] > 0 ? 'Long (vendeu)' : 'Short (comprou)', $e['dir'] > 0 ? '#22c55e' : '#f87171'),
            number_format($e['qtd'], 0, ',', '.'),
            fa($e['preco']),
            fa($e['pmp']),
            fa($e['custo']),
            '<span style="color:' . $cor . '">' . fa($e['realizado'], 2, true) . '</span>',
            '<span style="color:' . $cor . '">' . number_format($e['retorno'], 1, ',', '.') . '%</span>',
            fa($acumA[$i], 2, true) . ($e['parcial'] ? ' <span class="muted">(parcial)</span>' : ''),
        );
    }
    tabela(
        array('Data', 'Ativo', 'Operacao', 'Sentido', 'Qtd fechada', 'Preco da saida', 'Preco medio', 'Custo', 'Resultado', 'Retorno', 'Acumulado'),
        $rows,
        'Nenhum fechamento de investimento no recorte.',
        array('data_table' => true, 'id' => 'tabelaFechA', 'placeholder' => 'Pesquisar por ativo, data, operacao...', 'busca' => $f['ativo'])
    );
    $conteudo = ob_get_clean();
    card_bloco('Operacoes de investimento fechadas (R$)', $conteudo, '#22c55e', 'grafico', (int)$resA['n'] . ' fechamentos · cada saida contra o preco medio');
    ?>

    <?php
    ob_start();
    $rows = array();
    foreach ($abertasA as $posAb) {
        $rows[] = array(
            e($posAb['rotulo']),
            e(isset($rotuloClasse[$posAb['classe']]) ? $rotuloClasse[$posAb['classe']] : $posAb['classe']),
            badge($posAb['dir'] > 0 ? 'Comprado' : 'Vendido', $posAb['dir'] > 0 ? '#22c55e' : '#f87171'),
            number_format($posAb['qtd'], 0, ',', '.'),
            fa($posAb['pmp']),
            fa($posAb['pmp'] * $posAb['qtd']),
            date('d/m/Y', strtotime($posAb['primeira'])),
            date('d/m/Y', strtotime($posAb['ultima'])),
            '<span' . fcor($posAb['realizado']) . '>' . fa($posAb['realizado'], 2, true) . '</span>',
        );
    }
    tabela(
        array('Ativo', 'Tipo', 'Sentido', 'Quantidade', 'Preco medio', 'Custo aportado', 'Primeira', 'Ultima', 'Realizado ate agora'),
        $rows,
        'Nenhuma posicao de investimento aberta no fim do recorte.',
        array('data_table' => true, 'id' => 'tabelaAbertasA', 'placeholder' => 'Pesquisar posicao...')
    );
    $conteudo = ob_get_clean();
    card_bloco('Posicoes abertas (investimento)', $conteudo, '#fbbf24', 'carteira', count($rows) . ' abertas · sem preco de mercado');
    ?>
</section>

<section class="grid grid-2" data-aba="investimento" <?php echo $hidI; ?>>
    <?php
    ob_start();
    echo '<div class="duo-colunas">';
    echo '<div><h4 class="muted">Maiores ganhos</h4><div class="formula-lista">';
    foreach ($extremos['ganhos'] as $e) {
        echo '<div class="formula-item"><strong>' . e($e['rotulo']) . ' · ' . date('d/m/Y', strtotime($e['data'])) . '</strong>'
            . '<span>saida a ' . fa($e['preco']) . ' vs preco medio ' . fa($e['pmp']) . ' em ' . number_format($e['qtd'], 0, ',', '.')
            . ' papeis = <b style="color:var(--green)">' . fa($e['realizado'], 2, true) . '</b> (' . number_format($e['retorno'], 1, ',', '.') . '%)</span></div>';
    }
    if (!$extremos['ganhos']) {
        echo '<p class="muted">Nenhum ganho registrado.</p>';
    }
    echo '</div></div>';
    echo '<div><h4 class="muted">Maiores perdas</h4><div class="formula-lista">';
    foreach ($extremos['perdas'] as $e) {
        echo '<div class="formula-item"><strong>' . e($e['rotulo']) . ' · ' . date('d/m/Y', strtotime($e['data'])) . '</strong>'
            . '<span>saida a ' . fa($e['preco']) . ' vs preco medio ' . fa($e['pmp']) . ' em ' . number_format($e['qtd'], 0, ',', '.')
            . ' papeis = <b style="color:var(--red)">' . fa($e['realizado'], 2, true) . '</b> (' . number_format($e['retorno'], 1, ',', '.') . '%)</span></div>';
    }
    if (!$extremos['perdas']) {
        echo '<p class="muted">Nenhuma perda registrada.</p>';
    }
    echo '</div></div>';
    echo '</div>';
    $conteudo = ob_get_clean();
    card_bloco('Extremos: maiores ganhos e maiores perdas (investimento)', $conteudo, '#f87171', 'raio', count($anomA) . ' sinalizado(s) para conferencia');
    ?>

    <?php
    ob_start();
    $rows = array();
    foreach ($mesesA as $m) {
        $rows[] = array(
            e(imp_rotulo_mes($m['chave'])),
            (int)$m['n'],
            '<span class="abs">R$ ' . number_format($m['entradas'], 2, ',', '.') . '</span>',
            '<span class="abs">R$ ' . number_format($m['saidas'], 2, ',', '.') . '</span>',
            '<span' . fcor($m['realizado']) . '>' . fa($m['realizado'], 2, true) . '</span>',
            (int)$m['fechamentos'],
            fa($m['acumulado'], 2, true),
        );
    }
    tabela(
        array('Mes', 'Lancamentos', 'Entradas (R$)', 'Saidas (R$)', 'Realizado (R$)', 'Fechamentos', 'Acumulado (R$)'),
        $rows,
        'Nenhum mes no recorte.',
        array('data_table' => true, 'id' => 'tabelaMesesA', 'placeholder' => 'Pesquisar mes...')
    );
    $conteudo = ob_get_clean();
    card_bloco('Investimento mes a mes', $conteudo, '#38bdf8', 'grafico', count($rows) . ' meses');
    ?>
</section>

<section class="grid" data-aba="investimento" <?php echo $hidI; ?>>
    <?php
    ob_start();
    $rows = array();
    foreach ($semanaA as $s) {
        $rows[] = array(
            e(imp_rotulo_semana($s['chave'])),
            (int)$s['n'],
            '<span class="abs">R$ ' . number_format($s['entradas'], 2, ',', '.') . '</span>',
            '<span class="abs">R$ ' . number_format($s['saidas'], 2, ',', '.') . '</span>',
            '<span' . fcor($s['realizado']) . '>' . fa($s['realizado'], 2, true) . '</span>',
            (int)$s['fechamentos'],
            fa($s['fechamentos'] > 0 ? $s['realizado'] / $s['fechamentos'] : 0, 2, true),
        );
    }
    tabela(
        array('Dia da semana', 'Lancamentos', 'Entradas (R$)', 'Saidas (R$)', 'Realizado (R$)', 'Fechamentos', 'Media por fechamento'),
        $rows,
        'Nenhum dia no recorte.',
        array('data_table' => true, 'id' => 'tabelaSemanaA', 'placeholder' => 'Pesquisar dia...')
    );
    $conteudo = ob_get_clean();
    card_bloco('Investimento por dia da semana', $conteudo, '#e879f9', 'grafico');
    ?>
</section>
<section class="grid" data-aba="trading" <?php echo $hidT; ?>>
    <?php
    ob_start();
    $rows = array();
    foreach ($linhasContratos as $l) {
        $ganhos = 0.0;
        $perdas = 0.0;
        $realR = 0.0;
        foreach ($evF as $e) {
            if ($e['rotulo'] !== $l['rotulo']) {
                continue;
            }
            $r = (isset($e['realizado_reais']) && $e['realizado_reais'] !== null) ? (float)$e['realizado_reais'] : 0.0;
            $realR += $r;
            if ($r > IMP_TOL) {
                $ganhos += $r;
            } elseif ($r < -IMP_TOL) {
                $perdas += $r;
            }
        }
        $ft = ($perdas < 0) ? $ganhos / abs($perdas) : null;
        $rows[] = array(
            '<span class="link-mini drill-gatilho" role="button" tabindex="0" data-drill="lanc_contrato_' . e($l['rotulo']) . '" title="Ver os lancamentos deste contrato e excluir linha a linha">' . e($l['rotulo']) . '</span>',
            (int)$l['n_ent'],
            (int)$l['n_sai'],
            (int)$l['n_eventos'],
            '<span style="color:var(--green)">' . fp($ganhos, 2) . '</span>',
            '<span style="color:var(--red)">' . fp($perdas, 2) . '</span>',
            '<span' . fcor($realR) . '>' . fp($realR, 2) . '</span>',
            $ft !== null ? number_format($ft, 2, ',', '.') : 'sem perdas',
            $l['win_rate'] !== null ? number_format($l['win_rate'], 0, ',', '.') . '%' : '-',
            $l['qtd'] > 0 ? number_format($l['qtd'], 0, ',', '.') . ($l['dir'] > 0 ? ' (aberto)' : ' (vendido)') : 'zera',
        );
    }
    tabela(
        array('Contrato', 'Compras', 'Vendas', 'Fechamentos', 'Ganhos (R$)', 'Perdas (R$)', 'Liquido (R$)', 'Fator lucro', 'Acerto', 'Posicao final'),
        $rows,
        'Nenhum contrato negociado no recorte.',
        array('data_table' => true, 'id' => 'tabelaContratos', 'placeholder' => 'Pesquisar contrato...', 'busca' => $f['contrato'])
    );
    $conteudo = ob_get_clean();
    card_bloco('Trading por contrato (R$)', $conteudo, '#a78bfa', 'alvo', count($rows) . ' contratos · clique no contrato para ver e excluir lancamentos');
    ?>
</section>
<section class="grid grid-2" data-aba="trading" <?php echo $hidT; ?>>
    <?php
    ob_start();
    $rows = array();
    foreach ($evF as $i => $e) {
        $r = (isset($e['realizado_reais']) && $e['realizado_reais'] !== null) ? (float)$e['realizado_reais'] : 0.0;
        $cor = $r > IMP_TOL ? '#22c55e' : ($r < -IMP_TOL ? '#f87171' : '#8ba0c4');
        $rows[] = array(
            date('d/m/Y', strtotime($e['data'])),
            '<a class="link-mini" href="/ranksistem/pages/financeiro-analise.php?contrato=' . urlencode($e['rotulo']) . '">' . e($e['rotulo']) . '</a>',
            e($e['operacao']),
            badge($e['dir'] > 0 ? 'Long (vendeu)' : 'Short (comprou)', $e['dir'] > 0 ? '#22c55e' : '#f87171'),
            number_format($e['qtd'], 0, ',', '.'),
            number_format($e['preco'], 2, ',', '.'),
            number_format($e['pmp'], 2, ',', '.'),
            '<span style="color:' . $cor . '">' . fp($r, 2) . '</span>',
            '<span style="color:' . $cor . '">' . number_format($e['retorno'], 2, ',', '.') . '%</span>',
            fp($acumF[$i], 2) . ($e['parcial'] ? ' <span class="muted">(parcial)</span>' : ''),
        );
    }
    tabela(
        array('Data', 'Contrato', 'Operacao', 'Sentido', 'Contratos', 'Preco da saida', 'Preco medio', 'Resultado (R$)', 'Retorno', 'Acumulado (R$)'),
        $rows,
        'Nenhum fechamento de trading no recorte.',
        array('data_table' => true, 'id' => 'tabelaFechF', 'placeholder' => 'Pesquisar contrato, data...', 'busca' => $f['contrato'])
    );
    $conteudo = ob_get_clean();
    card_bloco('Operacoes de trading fechadas (R$)', $conteudo, '#a78bfa', 'grafico', (int)$resF['n'] . ' fechamentos · 1 contrato por lancamento');
    ?>

    <?php
    ob_start();
    $rows = array();
    foreach ($abertasF as $posAb) {
        $rAb = isset($posAb['realizado_reais']) ? (float)$posAb['realizado_reais'] : 0.0;
        $rows[] = array(
            e($posAb['rotulo']),
            badge($posAb['dir'] > 0 ? 'Comprado' : 'Vendido', $posAb['dir'] > 0 ? '#22c55e' : '#f87171'),
            number_format($posAb['qtd'], 0, ',', '.') . ' contrato(s)',
            number_format($posAb['pmp'], 2, ',', '.'),
            date('d/m/Y', strtotime($posAb['primeira'])),
            date('d/m/Y', strtotime($posAb['ultima'])),
            '<span' . fcor($rAb) . '>' . fp($rAb, 2) . '</span>',
        );
    }
    tabela(
        array('Contrato', 'Sentido', 'Quantidade', 'Preco medio', 'Primeira', 'Ultima', 'Realizado ate agora (R$)'),
        $rows,
        'Nenhuma posicao de trading aberta no fim do recorte.',
        array('data_table' => true, 'id' => 'tabelaAbertasF', 'placeholder' => 'Pesquisar contrato...')
    );
    $conteudo = ob_get_clean();
    card_bloco('Posicoes abertas (trading)', $conteudo, '#fbbf24', 'carteira', count($rows) . ' abertas · 1 contrato por lancamento');
    ?>
</section>



<section class="grid">
    <?php
    ob_start();
    $mostrar = array_reverse($movs);
    if ($abaAtual === 'investimento') {
        $mostrar = array_values(array_filter($mostrar, function ($m) {
            return !imp_e_futuro($m);
        }));
    } elseif ($abaAtual === 'trading') {
        $mostrar = array_values(array_filter($mostrar, function ($m) {
            return imp_e_futuro($m);
        }));
    }
    $totalMovs = count($mostrar);
    $limite = 500;
    // consolidacao por operacao na ordem cronologica; exibicao continua da mais recente
    $listaCons = array_reverse(imp_consolidar_operacoes(array_reverse($mostrar)));
    $nOpsCons = 0;
    foreach ($listaCons as $cL) {
        if ($cL['op']) { $nOpsCons++; }
    }
    $rows = array();
    foreach (array_slice($listaCons, 0, $limite) as $cL) {
        if (!$cL['op']) {
            $m = $cL['a'];
            $futuro = imp_e_futuro($m);
            $chave = $futuro ? $m['ativo_nome'] : $m['ativo_codigo'];
            $ehEntrada = ($m['tipo'] === 'credito');
            $rows[] = array(
                date('d/m/Y', strtotime($m['data'])),
                badge($futuro ? 'Trading' : 'Investimento', $futuro ? '#a78bfa' : '#22c55e'),
                '<a class="link-mini" href="/ranksistem/pages/financeiro-analise.php?' . ($futuro ? 'contrato' : 'ativo') . '=' . urlencode($chave) . '">' . e($chave) . '</a>',
                e($m['ativo_nome']),
                e($m['operacao']),
                badge($ehEntrada ? 'Entrada' : 'Saida', $ehEntrada ? '#22c55e' : '#f87171'),
                $futuro ? '-' : number_format((float)$m['quantidade'], 0, ',', '.'),
                fa((float)$m['preco_unitario']),
                fa((float)$m['valor_total']),
                e($m['corretora']),
            );
            continue;
        }
        $d = drill_op_resumo($cL['a'], $cL['b']);
        $rows[] = array(
            date('d/m/Y', strtotime($d['data'])),
            badge($d['fut'] ? 'Trading' : 'Investimento', $d['fut'] ? '#a78bfa' : '#22c55e'),
            '<a class="link-mini" href="/ranksistem/pages/financeiro-analise.php?' . ($d['fut'] ? 'contrato' : 'ativo') . '=' . urlencode($d['cod']) . '">' . e($d['cod']) . '</a>',
            e($d['nome']),
            e($d['rotulo']) . ' (zerou)',
            badge('Operacao', '#38bdf8'),
            $d['fut'] ? '-' : number_format($d['qtd'], 0, ',', '.'),
            fa($d['preco_a']) . ' &rarr; ' . fa($d['preco_b']),
            $d['resultado'] === null ? '-' : '<span' . fcor($d['resultado']) . '>' . fa($d['resultado'], 2, true) . '</span>',
            e($d['corretora']),
        );
    }
    if ($nOpsCons > 0) {
        echo '<p class="muted"><strong>' . $nOpsCons . '</strong> operacao(oes) aparecem consolidadas em uma linha (entrada + saida do mesmo dia, mesmo ativo e mesma quantidade, zerando a posicao), com o resultado do par na coluna Total. O detalhe linha a linha continua na pagina Importar.</p>';
    }
    if (count($listaCons) > $limite) {
        echo '<p class="muted">Mostrando as ' . $limite . ' linhas mais recentes de ' . number_format(count($listaCons), 0, ',', '.')
            . ' (' . number_format($totalMovs, 0, ',', '.') . ' lancamentos no recorte). Use os filtros acima para reduzir (por periodo, ativo ou contrato).</p>';
    }
    tabela(
        array('Data', 'Painel', 'Ativo / Contrato', 'Nome', 'Operacao', 'Tipo', 'Qtd', 'Preco', 'Total', 'Corretora'),
        $rows,
        'Nenhum lancamento no recorte.',
        array('data_table' => true, 'id' => 'tabelaMovimentos', 'placeholder' => 'Pesquisar ativo, contrato, data, operacao...', 'busca' => $f['ativo'] !== '' ? $f['ativo'] : $f['contrato'])
    );
    $conteudo = ob_get_clean();
    card_bloco('Lancamentos no periodo' . ($abaAtual === 'geral' ? ' (investimento + trading)' : ($abaAtual === 'investimento' ? ' (investimento)' : ' (trading)')), $conteudo, '#2dd4bf', 'livro', number_format($totalMovs, 0, ',', '.') . ' no recorte');
    ?>
</section>

<section class="grid grid-2" data-aba="trading" <?php echo $hidT; ?>>
    <?php
    ob_start();
    $rows = array();
    foreach ($mesesF as $m) {
        $rows[] = array(
            e(imp_rotulo_mes($m['chave'])),
            (int)$m['n'],
            '<span' . fcor($m['realizado']) . '>' . fp($m['realizado'], 2) . '</span>',
            (int)$m['fechamentos'],
            fp($m['acumulado'], 2),
        );
    }
    tabela(
        array('Mes', 'Lancamentos', 'Realizado (R$)', 'Fechamentos', 'Acumulado (R$)'),
        $rows,
        'Nenhum mes no recorte.',
        array('data_table' => true, 'id' => 'tabelaMesesF', 'placeholder' => 'Pesquisar mes...')
    );
    $conteudo = ob_get_clean();
    card_bloco('Trading mes a mes', $conteudo, '#38bdf8', 'grafico', count($rows) . ' meses');
    ?>

    <?php
    ob_start();
    $rows = array();
    foreach ($semanaF as $s) {
        $rows[] = array(
            e(imp_rotulo_semana($s['chave'])),
            (int)$s['n'],
            '<span' . fcor($s['realizado']) . '>' . fp($s['realizado'], 2) . '</span>',
            (int)$s['fechamentos'],
            fp($s['fechamentos'] > 0 ? $s['realizado'] / $s['fechamentos'] : 0, 2),
        );
    }
    tabela(
        array('Dia da semana', 'Lancamentos', 'Realizado (R$)', 'Fechamentos', 'Media por fechamento'),
        $rows,
        'Nenhum dia no recorte.',
        array('data_table' => true, 'id' => 'tabelaSemanaF', 'placeholder' => 'Pesquisar dia...')
    );
    $conteudo = ob_get_clean();
    card_bloco('Trading por dia da semana', $conteudo, '#e879f9', 'grafico');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    echo '<div class="formula-lista">';
    ?>
    <div class="formula-item">
        <strong>Entrada e saida</strong>
        <span>Credito do extrato conta como entrada de ativo (aumenta a posicao) e debito como saida (diminui). E a mesma convencao da carteira: posicao = entradas - saidas.</span>
        <code>posicao = SUM(qtd onde tipo = credito) - SUM(qtd onde tipo = debito)</code>
    </div>
    <div class="formula-item">
        <strong>Investimento x Trading</strong>
        <span>Investimento e o que tem quantidade e valor em R$ (acoes, BDR, FII e ETF): resultado em reais pelo preco medio. Trading e o que vem so com preco (futuros): resultado em pontos convertidos em R$ pela tabela B3.</span>
    </div>
    <div class="formula-item">
        <strong>Preco medio (PMP)</strong>
        <span>O preco medio e recalculado a cada entrada; fechamento parcial mantem o preco medio do que sobrou; posicao invertida abre novo preco medio.</span>
        <code>preco_medio_novo = (preco_medio * qtd + preco_entrada * qtd_nova) / (qtd + qtd_nova)</code>
    </div>
    <div class="formula-item">
        <strong>Um unico motor</strong>
        <span>Dashboard do Financeiro e esta pagina usam a mesma funcao de analise: os numeros do panorama e dos paineis vem do mesmo calculo, por periodo completo (la) ou com filtros (aqui).</span>
    </div>
    <?php
    echo '</div>';
    $conteudo = ob_get_clean();
    card_bloco('Como estes numeros foram calculados', $conteudo, '#38bdf8', 'livro', 'explicabilidade');
    ?>

    <?php
    ob_start();
    echo '<div class="formula-lista">';
    ?>
    <div class="formula-item">
        <strong>O que nao entra na conta</strong>
        <span>Corretagem, emolumentos, taxas, dividendos e impostos nao estao nas colunas importadas: o realizado aqui e bruto de custos. Dividendos e proventos aparecem como lancamento de credito comum.</span>
    </div>
    <div class="formula-item">
        <strong>Sem preco de mercado</strong>
        <span>As posicoes abertas mostram quantidade e preco medio, nao valor de mercado: o sistema nao busca cotacao. Resultado nao realizado so existe quando a saida acontece.</span>
    </div>
    <div class="formula-item">
        <strong>Amostra e confianca</strong>
        <span>Com poucos fechamentos o fator lucro e a taxa de acerto mudam muito com um unico evento. Confira a quantidade de fechamentos antes de concluir qualquer coisa.</span>
        <code>ate 30 fechamentos: confianca baixa (resultado pode ser sorte)
            30 a 99: evidencia limitada
            100 ou mais: leitura razoavel</code>
    </div>
    <div class="formula-item">
        <strong>Comparar com o diario de operacoes</strong>
        <span>O historico manual (trade log) fica em Financeiro e traz setup, adesao e notas. Estes paineis cobrem so o que veio da planilha importada.</span>
    </div>
    <?php
    echo '</div>';
    $conteudo = ob_get_clean();
    card_bloco('Limites desta analise', $conteudo, '#fbbf24', 'cerebro', 'antes de concluir qualquer coisa');
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
                            }, $conjunta)); ?>,
                    datasets: [{
                            label: 'Investimento acumulado (R$)',
                            data: <?php echo json_encode(array_map(function ($c) {
                                        return $c['investimento'] === null ? null : round($c['investimento'], 2);
                                    }, $conjunta)); ?>,
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
                                    }, $conjunta)); ?>,
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
            id: 'chartAcum',
            privado: true,
            config: {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_map(function ($l) {
                                return date('d/m', strtotime($l['data']));
                            }, $serieA['linhas'])); ?>,
                    datasets: [{
                        label: 'Realizado acumulado (R$)',
                        data: <?php echo json_encode(array_map(function ($l) {
                                    return round($l['acumulado'], 2);
                                }, $serieA['linhas'])); ?>,
                        borderColor: '#22c55e',
                        backgroundColor: 'rgba(34,197,94,.15)',
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
            id: 'chartMes',
            privado: true,
            config: {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function ($m) {
                                return imp_rotulo_mes($m['chave']);
                            }, $mesesA)); ?>,
                    datasets: [{
                        label: 'Realizado (R$)',
                        data: <?php echo json_encode(array_map(function ($m) {
                                    return round($m['realizado'], 2);
                                }, $mesesA)); ?>,
                        backgroundColor: <?php echo json_encode(array_map(function ($m) {
                                                return $m['realizado'] >= 0 ? '#22c55e' : '#f87171';
                                            }, $mesesA)); ?>,
                        borderRadius: 6
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
            id: 'chartTopReal',
            privado: true,
            config: {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function ($p) {
                                return $p['rotulo'];
                            }, $topReal)); ?>,
                    datasets: [{
                        label: 'Realizado (R$)',
                        data: <?php echo json_encode(array_map(function ($p) {
                                    return round($p['realizado'], 2);
                                }, $topReal)); ?>,
                        backgroundColor: <?php echo json_encode(array_map(function ($p) {
                                                return $p['realizado'] >= 0 ? '#22c55e' : '#f87171';
                                            }, $topReal)); ?>,
                        borderRadius: 6
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
            id: 'chartVolumeMes',
            privado: true,
            config: {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function ($m) {
                                return imp_rotulo_mes($m['chave']);
                            }, $mesesA)); ?>,
                    datasets: [{
                            label: 'Entradas',
                            data: <?php echo json_encode(array_map(function ($m) {
                                        return round($m['entradas'], 2);
                                    }, $mesesA)); ?>,
                            backgroundColor: 'rgba(34,197,94,.85)',
                            borderRadius: 5
                        },
                        {
                            label: 'Saidas',
                            data: <?php echo json_encode(array_map(function ($m) {
                                        return round($m['saidas'], 2);
                                    }, $mesesA)); ?>,
                            backgroundColor: 'rgba(248,113,113,.85)',
                            borderRadius: 5
                        }
                    ]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            }
        },
        {
            id: 'chartDist',
            privado: true,
            config: {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function ($d) {
                                return $d['rotulo'];
                            }, $dist)); ?>,
                    datasets: [{
                            label: 'Entradas',
                            data: <?php echo json_encode(array_map(function ($d) {
                                        return (int)$d['entradas'];
                                    }, $dist)); ?>,
                            backgroundColor: 'rgba(34,197,94,.85)',
                            borderRadius: 5
                        },
                        {
                            label: 'Saidas',
                            data: <?php echo json_encode(array_map(function ($d) {
                                        return (int)$d['saidas'];
                                    }, $dist)); ?>,
                            backgroundColor: 'rgba(248,113,113,.85)',
                            borderRadius: 5
                        }
                    ]
                },
                options: {
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            }
        },
        {
            id: 'chartSemana',
            privado: true,
            config: {
                type: 'bar',
                data: {
                    labels: ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sab', 'Dom'],
                    datasets: [{
                        label: 'Realizado (R$)',
                        data: [<?php
                                $vals = array();
                                for ($d = 1; $d <= 7; $d++) {
                                    $achou = null;
                                    foreach ($semanaA as $s) {
                                        if ((int)$s['chave'] === $d) {
                                            $achou = $s;
                                            break;
                                        }
                                    }
                                    $vals[] = $achou ? round($achou['realizado'], 2) : 0;
                                }
                                echo implode(', ', $vals);
                                ?>],
                        backgroundColor: [<?php
                                            $cols = array();
                                            for ($d = 1; $d <= 7; $d++) {
                                                $achou = null;
                                                foreach ($semanaA as $s) {
                                                    if ((int)$s['chave'] === $d) {
                                                        $achou = $s;
                                                        break;
                                                    }
                                                }
                                                $cols[] = ($achou && $achou['realizado'] >= 0) ? '#22c55e' : '#f87171';
                                            }
                                            echo "'" . implode("', '", $cols) . "'";
                                            ?>],
                        borderRadius: 7
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
            id: 'chartAcumFut',
            config: {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_map(function ($l) {
                                return date('d/m', strtotime($l['data']));
                            }, $serieF['linhas'])); ?>,
                    datasets: [{
                        label: 'Acumulado (R$)',
                        data: <?php echo json_encode(array_map(function ($l) {
                                    return round($l['acumulado'], 2);
                                }, $serieF['linhas'])); ?>,
                        borderColor: '#a78bfa',
                        backgroundColor: 'rgba(167,139,250,.18)',
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
            id: 'chartMesFut',
            config: {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function ($m) {
                                return imp_rotulo_mes($m['chave']);
                            }, $mesesF)); ?>,
                    datasets: [{
                        label: 'Realizado (R$)',
                        data: <?php echo json_encode(array_map(function ($m) {
                                    return round($m['realizado'], 2);
                                }, $mesesF)); ?>,
                        backgroundColor: <?php echo json_encode(array_map(function ($m) {
                                                return $m['realizado'] >= 0 ? '#22c55e' : '#f87171';
                                            }, $mesesF)); ?>,
                        borderRadius: 6
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
            id: 'chartContratos',
            config: {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function ($p) {
                                return $p['rotulo'];
                            }, $linhasContratos)); ?>,
                    datasets: [{
                        label: 'Liquido (R$)',
                        data: <?php
                                $vals = array();
                                foreach ($linhasContratos as $p) {
                                    $s = 0.0;
                                    foreach ($evF as $e) {
                                        if ($e['rotulo'] !== $p['rotulo']) {
                                            continue;
                                        }
                                        $s += (isset($e['realizado_reais']) && $e['realizado_reais'] !== null) ? (float)$e['realizado_reais'] : 0.0;
                                    }
                                    $vals[] = round($s, 2);
                                }
                                echo json_encode($vals);
                                ?>,
                        backgroundColor: <?php
                                            $cols = array();
                                            foreach ($linhasContratos as $p) {
                                                $s = 0.0;
                                                foreach ($evF as $e) {
                                                    if ($e['rotulo'] !== $p['rotulo']) {
                                                        continue;
                                                    }
                                                    $s += (isset($e['realizado_reais']) && $e['realizado_reais'] !== null) ? (float)$e['realizado_reais'] : 0.0;
                                                }
                                                $cols[] = $s >= 0 ? '#22c55e' : '#f87171';
                                            }
                                            echo json_encode($cols);
                                            ?>,
                        borderRadius: 6
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
        }
    ];
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>