<?php
require_once __DIR__ . '/../config.php';

$page_title = 'Importar Planilha';
$page_sub = 'Acoes vao para Investimento · indices vao para Trading · historico por data (o saldo atual e definido em Cadastros)';
$active = 'financeiro';

$importacoes = fin_importacoes_lista(20);
$movimentos = fin_movimentos_importados(2000);
$carteira = fin_carteira_resumo();
$contas = fin_contas();

$rotuloClasse = array('acao' => 'Acao/BDR/FII', 'indice' => 'Indice/Futuro', 'outro' => 'Outro');

require_once __DIR__ . '/../includes/header.php';
?>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/financeiroController.php" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="acao" value="importar">
        <div class="campo campo-full upload-area">
            <label for="arquivo">Arquivo da planilha (.xlsx, .csv, .txt)</label>
            <input id="arquivo" type="file" name="arquivo" accept=".xlsx,.xlsm,.csv,.txt,.tsv" required>
            <p class="muted">Colunas esperadas: Tipo · Data · Operacao · Ativo · Corretora · Quantidade · Preco unitario · Total.</p>
        </div>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Importar planilha</button>
        </div>
    </form>
    <div class="regra-auto">
        <div class="regra-linha"><span class="regra-tag" style="--cor:#38bdf8">Acoes / BDR / FII / ETF</span> <?php echo icone('link', 16); ?> conta <strong>Investimento</strong></div>
        <div class="regra-linha"><span class="regra-tag" style="--cor:#22c55e">Indices / Futuros (IND, WIN, WDO...)</span> <?php echo icone('link', 16); ?> conta <strong>Trading</strong></div>
        <p class="muted">Regras editaveis em Cadastros. O saldo atual de cada conta e informado em Cadastros; os lancamentos importados usam as datas para reconstruir o historico, sem somar valores antigos de novo.</p>
        <p><a class="btn btn-neutro btn-mini" href="/ranksistem/pages/financeiro-analise.php"><?php echo icone('grafico', 16); ?> Analisar historico importado</a></p>
    </div>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Upload da planilha', $conteudo, '#22c55e', 'carteira');
    ?>

    <?php
    ob_start();
    if (empty($contas)) {
        echo '<p class="muted">Nenhuma conta cadastrada.</p>';
    } else {
        echo '<div class="contas-lista">';
        foreach ($contas as $c) {
            $ind = fin_indice_conta($c['saldo_atual'], $c['saldo_inicial']);
            $deltaR = (float)$c['saldo_atual'] - (float)$c['saldo_inicial'];
            $cor = $c['tipo'] === 'trading' ? '#22c55e' : ($c['tipo'] === 'investimento' ? '#38bdf8' : '#fbbf24');
            echo '<div class="conta-item" style="--cor:' . $cor . '">'
                . '<div class="conta-info"><span class="conta-tipo">' . e($c['tipo']) . '</span><strong>' . e($c['nome']) . '</strong>'
                . '<span class="conta-data">' . ($c['data_saldo'] ? 'saldo de ' . date('d/m/Y', strtotime($c['data_saldo'])) : 'saldo atual') . '</span></div>'
                . '<div class="conta-valores">'
                . '<span class="conta-saldo abs">R$ ' . number_format((float)$c['saldo_atual'], 2, ',', '.') . '</span>'
                . '<span class="conta-delta ' . ($deltaR >= 0 ? 'up' : 'down') . '"><span class="abs">' . ($deltaR >= 0 ? '+' : '-') . 'R$ ' . number_format(abs($deltaR), 2, ',', '.') . '</span> · ' . ($ind['variacao_pct'] >= 0 ? '+' : '') . number_format($ind['variacao_pct'], 2, ',', '.') . '%</span>'
                . '</div>'
                . '<div class="conta-indice"><span class="indice-num">' . number_format($ind['indice'], 1, ',', '.') . '</span><span class="indice-legenda">indice</span></div>'
                . '</div>';
        }
        echo '</div>';
    }
    $conteudo = ob_get_clean();
    card_bloco('Saldos atuais por conta (R$)', $conteudo, '#22c55e', 'carteira');
    ?>
</section>

<section class="grid">
    <?php
    ob_start();
    if (empty($carteira)) {
        echo '<p class="muted">Nenhum ativo importado ainda.</p>';
    } else {
        echo '<div class="ativos-grid">';
        foreach ($carteira as $a) {
            $cor = $a['classe'] === 'indice' ? '#22c55e' : '#38bdf8';
            $pos = (float)$a['posicao'];
            echo '<div class="ativo-card">'
                . '<div class="ativo-topo"><strong>' . e($a['ativo_codigo']) . '</strong>'
                . '<span class="ativo-pos" style="color:' . ($pos >= 0 ? '#22c55e' : '#f87171') . '">' . number_format($pos, 2, ',', '.') . '</span></div>'
                . '<span class="ativo-nome" title="' . e($a['ativo_nome']) . '">' . e($a['ativo_nome']) . '</span>'
                . '<div class="ativo-meta">' . badge($rotuloClasse[$a['classe']], $cor)
                . '<span class="abs">R$ ' . number_format((float)$a['financeiro'], 2, ',', '.') . '</span>'
                . '<span>' . (int)$a['movimentos'] . ' mov.</span></div>'
                . '</div>';
        }
        echo '</div>';
    }
    $conteudo = ob_get_clean();
    card_bloco('Carteira por ativo (' . count($carteira) . ' ativos)', $conteudo, '#a78bfa', 'carteira');
    ?>
</section>

<section class="grid">
    <?php
    ob_start();
    if (empty($importacoes)) {
        echo '<p class="muted">Nenhuma importacao realizada.</p>';
    } else {
        echo '<div class="tabela-wrap"><table class="tabela"><thead><tr>'
            . '<th>Arquivo</th><th>Data</th><th>Linhas</th><th>Importadas</th><th>Creditos</th><th>Debitos</th><th></th>'
            . '</tr></thead><tbody>';
        foreach ($importacoes as $im) {
            echo '<tr>'
                . '<td>' . e($im['arquivo']) . '</td>'
                . '<td>' . date('d/m/Y H:i', strtotime($im['criado_em'])) . '</td>'
                . '<td>' . (int)$im['total_linhas'] . '</td>'
                . '<td>' . (int)$im['importadas'] . '</td>'
                . '<td style="color:#86efac"><span class="abs">R$ ' . number_format((float)$im['credito_total'], 2, ',', '.') . '</span></td>'
                . '<td style="color:#fca5a5"><span class="abs">R$ ' . number_format((float)$im['debito_total'], 2, ',', '.') . '</span></td>'
                . '<td><a class="link-mini danger" href="/ranksistem/controllers/financeiroController.php?acao=del_importacao&id=' . (int)$im['id'] . '" onclick="return confirm(\'Desfazer esta importacao? Os lancamentos serao removidos e o saldo atual nao muda.\')">Desfazer</a></td>'
                . '</tr>';
        }
        echo '</tbody></table></div>';
    }
    $conteudo = ob_get_clean();
    card_bloco('Historico de importacoes', $conteudo, '#fbbf24', 'grafico');
    ?>
</section>

<section class="grid">
    <?php
    ob_start();
    $rows = array();
    foreach ($movimentos as $m) {
        $corTipo = $m['tipo'] === 'credito' ? '#22c55e' : '#f87171';
        $corClasse = $m['classe'] === 'indice' ? '#22c55e' : '#38bdf8';
        $rows[] = array(
            date('d/m/Y', strtotime($m['data'])),
            badge($m['tipo'] === 'credito' ? 'Credito' : 'Debito', $corTipo),
            '<strong>' . e($m['ativo_codigo']) . '</strong><br><span class="muted">' . e($m['ativo_nome']) . '</span>',
            badge($rotuloClasse[$m['classe']], $corClasse),
            e($m['conta_nome']),
            number_format((float)$m['quantidade'], 2, ',', '.'),
            '<span class="abs">R$ ' . number_format((float)$m['preco_unitario'], 2, ',', '.') . '</span>',
            '<span class="abs">R$ ' . number_format((float)$m['valor_total'], 2, ',', '.') . '</span>',
            e($m['corretora']),
            '<a class="link-mini danger" href="/ranksistem/controllers/financeiroController.php?acao=del_lancamento&id=' . (int)$m['id'] . '" onclick="return confirm(\'Excluir este lancamento? A carteira e a analise serao recalculadas (o saldo atual nao muda).\')">Excluir</a>'
        );
    }
    tabela(array('Data', 'Tipo', 'Ativo', 'Classe', 'Conta', 'Qtd', 'Preco', 'Total', 'Corretora', 'Acao'), $rows, 'Nenhum lancamento importado.', array('data_table' => true, 'id' => 'tabelaLancamentos', 'placeholder' => 'Pesquisar por ativo, corretora, conta...', 'busca' => isset($_GET['q']) ? trim((string)$_GET['q']) : ''));
    $conteudo = ob_get_clean();
    card_bloco('Lancamentos importados', $conteudo, '#2dd4bf', 'grafico');
    ?>
</section>

<p><a class="link-mini" href="/ranksistem/pages/financeiro.php">&larr; Voltar ao Financeiro</a></p>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
