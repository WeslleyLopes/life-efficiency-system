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

$wins = 0; $losses = 0; $be = 0;
foreach ($ops as $o) {
    if ($o['resultado'] === 'win') { $wins++; }
    elseif ($o['resultado'] === 'loss') { $losses++; }
    else { $be++; }
}

$rotuloTipo = array('trading' => 'Trading', 'investimento' => 'Investimentos', 'renda_trabalho' => 'Renda do Trabalho');
$rotuloResultado = array('win' => 'Win', 'loss' => 'Loss', 'breakeven' => 'Breakeven');
$rotuloClasse = array('acao' => 'Acao/BDR/FII', 'indice' => 'Indice/Futuro', 'outro' => 'Outro');

require_once __DIR__ . '/../includes/header.php';
?>

<div class="barra-acoes">
    <span class="muted">O saldo atual e definido em Cadastros. A planilha importada traz o historico por data, sem somar os lancamentos antigos de novo.</span>
    <a class="btn btn-neutro btn-mini" href="/ranksistem/pages/financeiro-importar.php"><?php echo icone('mais', 16); ?> Importar planilha</a>
</div>

<section class="grid grid-stats">
    <?php
    card_stat(array('titulo' => 'Patrimonio total', 'valor' => 'R$ ' . number_format($patrimonio, 2, ',', '.'), 'sub' => 'saldo atual informado', 'cor' => '#22c55e', 'icone' => 'carteira'));
    card_stat(array('titulo' => 'Resultado', 'valor' => ($resultado >= 0 ? '+' : '-') . 'R$ ' . number_format(abs($resultado), 2, ',', '.'), 'sub' => 'sobre o valor base', 'cor' => $resultado >= 0 ? '#22c55e' : '#f87171', 'icone' => 'grafico'));
    card_stat(array('titulo' => 'Variacao consolidada', 'valor' => ($variacao >= 0 ? '+' : '') . number_format($variacao, 2, ',', '.') . '%', 'sub' => 'saldo atual vs base', 'cor' => '#38bdf8', 'icone' => 'grafico', 'delta' => $variacao));
    card_stat(array('titulo' => 'Taxa de acerto', 'valor' => number_format($winRate, 1, ',', '.') . '%', 'sub' => 'win rate', 'cor' => '#38bdf8', 'icone' => 'alvo'));
    card_stat(array('titulo' => 'Profit factor', 'valor' => number_format($pf, 2, ',', '.'), 'sub' => 'ganhos / perdas', 'cor' => '#a78bfa', 'icone' => 'grafico'));
    card_stat(array('titulo' => 'Expectativa', 'valor' => ($expect >= 0 ? '+' : '') . number_format($expect, 2, ',', '.') . ' pp', 'sub' => 'por operacao', 'cor' => '#fbbf24', 'icone' => 'raio'));
    card_stat(array('titulo' => 'Drawdown maximo', 'valor' => number_format($drawdown, 2, ',', '.') . '%', 'sub' => 'maior queda', 'cor' => '#f87171', 'icone' => 'grafico'));
    card_stat(array('titulo' => 'Execucao perfeita', 'valor' => number_format($execucao, 1, ',', '.') . '%', 'sub' => 'adesao total', 'cor' => '#2dd4bf', 'icone' => 'check'));
    ?>
</section>

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
            . '<span class="conta-saldo">R$ ' . number_format((float)$c['saldo_atual'], 2, ',', '.') . '</span>'
            . '<span class="conta-delta ' . ($deltaR >= 0 ? 'up' : 'down') . '">' . ($deltaR >= 0 ? '+' : '-') . 'R$ ' . number_format(abs($deltaR), 2, ',', '.') . ' · ' . ($ind['variacao_pct'] >= 0 ? '+' : '') . number_format($ind['variacao_pct'], 2, ',', '.') . '%</span>'
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

<section class="grid grid-3">
    <?php
    ob_start();
    ?>
    <canvas id="chartSetups" height="220"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Desempenho por setup', $conteudo, '#a78bfa', 'alvo');
    ?>

    <?php
    ob_start();
    ?>
    <canvas id="chartWinLoss" height="220"></canvas>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Distribuicao de resultados', $conteudo, '#38bdf8', 'grafico');
    ?>

    <?php
    ob_start();
    if (empty($setupStats)) {
        echo '<p class="muted">Sem setups.</p>';
    } else {
        echo '<div class="setup-lista">';
        foreach ($setupStats as $s) {
            $cor = $s['soma_retorno'] >= 0 ? '#22c55e' : '#f87171';
            echo '<div class="setup-item">'
                . '<div class="setup-head"><strong>' . e($s['nome']) . '</strong>'
                . '<span class="setup-retorno" style="color:' . $cor . '">' . ($s['soma_retorno'] >= 0 ? '+' : '') . number_format($s['soma_retorno'], 2, ',', '.') . ' pp</span></div>'
                . '<div class="setup-meta"><span>' . (int)$s['total'] . ' ops</span>'
                . '<span>WR ' . number_format($s['win_rate'], 0) . '%</span>'
                . '<span>Adesao ' . number_format($s['media_adesao'], 1, ',', '.') . '</span></div>'
                . '</div>';
        }
        echo '</div>';
    }
    $conteudo = ob_get_clean();
    card_bloco('Ranking de setups', $conteudo, '#fbbf24', 'raio');
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
        foreach ($contas as $c) { $optsContas[$c['id']] = $c['nome']; }
        form_campo(array('name' => 'conta_id', 'label' => 'Conta', 'type' => 'select', 'options' => $optsContas, 'required' => true));
        form_campo(array('name' => 'data', 'label' => 'Data', 'type' => 'date', 'value' => date('Y-m-d'), 'required' => true));
        $optsSetups = array(0 => 'Sem setup');
        foreach ($setups as $s) { $optsSetups[$s['id']] = $s['nome']; }
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

<section class="grid">
    <?php
    ob_start();

    $historico = array();
    foreach ($ops as $o) {
        $historico[] = array('data' => $o['data'], 'ordem' => (int)$o['id'], 'tipo' => 'manual', 'dados' => $o);
    }
    foreach ($importadas as $m) {
        $historico[] = array('data' => $m['data'], 'ordem' => (int)$m['id'], 'tipo' => 'importado', 'dados' => $m);
    }
    usort($historico, function ($a, $b) {
        if ($a['data'] === $b['data']) {
            if ($a['tipo'] !== $b['tipo']) { return $a['tipo'] === 'importado' ? 1 : -1; }
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
                'R$ ' . number_format((float)$m['preco_unitario'], 2, ',', '.'),
                'R$ ' . number_format((float)$m['valor_total'], 2, ',', '.'),
                badge(isset($rotuloClasse[$m['classe']]) ? $rotuloClasse[$m['classe']] : $m['classe'], $corClasse),
                '<a class="link-mini" href="/ranksistem/pages/financeiro-importar.php">ver lote</a>'
            );
        }
    }
    tabela(array('Data', 'Origem', 'Conta', 'Ativo / Setup', 'Operacao', 'Qtd', 'Preco', 'Total', 'Resultado / Classe', ''), $rows, 'Nenhuma operacao registrada ou importada.', array('data_table' => true, 'id' => 'tabelaOperacoes', 'placeholder' => 'Pesquisar por ativo, conta, data, origem...'));
    $conteudo = ob_get_clean();
    card_bloco('Historico de operacoes (manual + importado)', $conteudo, '#a78bfa', 'grafico');
    ?>
</section>

<script>
window.RANK_CHARTS = [
    {
        id: 'chartEquity',
        config: {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function ($p) { return $p['data'] === 'inicio' ? 'inicio' : date('d/m', strtotime($p['data'])); }, $saldoSerie)); ?>,
                datasets: [{ label: 'Patrimonio (R$)', data: <?php echo json_encode(array_map(function ($p) { return $p['valor']; }, $saldoSerie)); ?>, borderColor: '#2dd4bf', backgroundColor: 'rgba(45,212,191,.15)', fill: true, tension: .3, borderWidth: 3, pointRadius: 0 }]
            },
            options: { plugins: { legend: { display: false } } }
        }
    },
    {
        id: 'chartSetups',
        config: {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function ($s) { return $s['nome']; }, $setupStats)); ?>,
                datasets: [{ label: 'Retorno acumulado (pp)', data: <?php echo json_encode(array_map(function ($s) { return $s['soma_retorno']; }, $setupStats)); ?>, backgroundColor: <?php echo json_encode(array_map(function ($s) { return $s['soma_retorno'] >= 0 ? '#22c55e' : '#f87171'; }, $setupStats)); ?>, borderRadius: 8 }]
            },
            options: { indexAxis: 'y', plugins: { legend: { display: false } } }
        }
    },
    {
        id: 'chartWinLoss',
        config: {
            type: 'doughnut',
            data: {
                labels: ['Win', 'Loss', 'Breakeven'],
                datasets: [{ data: [<?php echo $wins; ?>, <?php echo $losses; ?>, <?php echo $be; ?>], backgroundColor: ['#22c55e', '#f87171', '#94a3b8'], borderWidth: 0 }]
            },
            options: { plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
        }
    }
];
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
