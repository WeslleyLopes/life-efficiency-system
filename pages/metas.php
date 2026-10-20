<?php
require_once __DIR__ . '/../config.php';

$page_title = 'Metas e Ciclo';
$page_sub = 'Diagnosticar, priorizar, planejar, executar, avaliar e aprender - um ciclo por vez';
$active = 'metas';

$stats = metas_stats();
$ativas = metas_listar('ativa');
$concluidas = metas_listar('concluida');
$encerradas = metas_listar('abandonada');
$proximas = metas_proximas(4);

$diagnosticos = diag_listar(null, 60);
$abertos = array();
$historico = array();
foreach ($diagnosticos as $d) {
    if ($d['status'] === 'aberto' || $d['status'] === 'aceito') { $abertos[] = $d; }
    else { $historico[] = $d; }
}
$resumoDiag = diag_resumo();

$etapas = metas_etapas();
$etapaAtual = null;
$contagemEtapas = array();
foreach ($ativas as $m) {
    if (!isset($contagemEtapas[$m['etapa']])) { $contagemEtapas[$m['etapa']] = 0; }
    $contagemEtapas[$m['etapa']]++;
    if ($etapaAtual === null) { $etapaAtual = $m['etapa']; }
}
if ($etapaAtual === null) { $etapaAtual = 'diagnosticar'; }

$rotuloEtapas = array(
    'diagnosticar' => 'Identifique a diferenca entre o estado atual e o desejado.',
    'priorizar'    => 'Escolha algo relevante, viavel e compativel com o tempo.',
    'planejar'     => 'Defina acao, prazo e indicador de sucesso.',
    'executar'     => 'Registre o que foi realmente realizado.',
    'avaliar'      => 'Compare o resultado com a linha de base.',
    'aprender'     => 'Mantenha, ajuste ou abandone com base na evidencia.',
);
$coresEtapas = array(
    'diagnosticar' => '#f87171', 'priorizar' => '#fbbf24', 'planejar' => '#38bdf8',
    'executar' => '#22c55e', 'avaliar' => '#a78bfa', 'aprender' => '#2dd4bf',
);

$pilares = metas_pilar_labels();
$rotuloPrioridade = array(1 => 'Alta', 2 => 'Media', 3 => 'Baixa');
$rotuloEsforco = array('baixo' => 'Baixo', 'medio' => 'Medio', 'alto' => 'Alto');
$rotuloStatusDiag = array('aberto' => 'Aberto', 'aceito' => 'Aceito', 'resolvido' => 'Resolvido', 'ignorado' => 'Ignorado');
$rotuloConf = array('baixa' => 'Confianca baixa', 'media' => 'Confianca media', 'alta' => 'Confianca alta');
$corConf = array('baixa' => '#8ba0c4', 'media' => '#fbbf24', 'alta' => '#22c55e');

if (!function_exists('meta_render_badges')) {
    function meta_render_badges($m, $rotuloPrioridade, $rotuloEsforco)
    {
    $cor = metas_cor($m['pilar']);
    echo '<div class="meta-badges">';
    echo badge(ucfirst($m['etapa']), $cor);
    echo '<span class="meta-prioridade p' . (int)$m['prioridade'] . '">Prioridade ' . $rotuloPrioridade[(int)$m['prioridade']] . '</span>';
    echo badge('Esforco ' . $rotuloEsforco[$m['esforco']], '#8ba0c4');
    if (!empty($m['prazo'])) {
        $vencida = $m['status'] === 'ativa' && $m['prazo'] < date('Y-m-d');
        echo badge('Prazo ' . date('d/m/Y', strtotime($m['prazo'])), $vencida ? '#f87171' : '#38bdf8');
    }
    echo '</div>';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="grid grid-stats">
    <?php
    card_stat(array('titulo' => 'Metas ativas', 'valor' => (int)$stats['ativa'], 'sub' => 'em andamento', 'cor' => '#38bdf8', 'icone' => 'alvo'));
    card_stat(array('titulo' => 'Metas concluidas', 'valor' => number_format($stats['pct'], 0) . '%', 'sub' => 'realizadas / planejadas', 'cor' => '#22c55e', 'icone' => 'check'));
    card_stat(array('titulo' => 'Prazos vencidos', 'valor' => (int)$stats['vencidas'], 'sub' => 'ativas alem do prazo', 'cor' => $stats['vencidas'] > 0 ? '#f87171' : '#94a3b8', 'icone' => 'raio'));
    card_stat(array('titulo' => 'Diagnosticos abertos', 'valor' => count($abertos), 'sub' => 'alertas para revisar', 'cor' => '#fbbf24', 'icone' => 'cerebro'));
    ?>
</section>

<section class="grid">
    <?php
    ob_start();
    echo '<div class="ciclo">';
    $num = 0;
    foreach ($etapas as $chave => $rotulo) {
        $num++;
        $cor = $coresEtapas[$chave];
        $ativo = ($chave === $etapaAtual);
        $qtd = isset($contagemEtapas[$chave]) ? (int)$contagemEtapas[$chave] : 0;
        echo '<div class="ciclo-etapa' . ($ativo ? ' ativo' : '') . '" style="--cor:' . $cor . '">'
            . '<span class="ciclo-num">Etapa ' . $num . ($qtd > 0 ? ' · ' . $qtd . ' meta(s)' : '') . '</span>'
            . '<span class="ciclo-nome">' . e($rotulo) . '</span>'
            . '<span class="ciclo-desc">' . e($rotuloEtapas[$chave]) . '</span>'
            . '</div>';
    }
    echo '</div>';
    echo '<p class="muted">Toda meta atravessa as seis etapas. Avancar sem concluir a etapa anterior esconde a evidencia que faltou.</p>';
    $conteudo = ob_get_clean();
    card_bloco('Ciclo operacional', $conteudo, '#e879f9', 'chama');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/metasController.php" class="form-grid">
        <input type="hidden" name="acao" value="add_meta">
        <?php
        form_campo(array('name' => 'titulo', 'label' => 'Titulo da melhoria', 'type' => 'text', 'placeholder' => 'Ex.: registrar adesao em toda operacao', 'required' => true));
        form_campo(array('name' => 'pilar', 'label' => 'Pilar', 'type' => 'select', 'options' => $pilares, 'value' => 'cross'));
        form_campo(array('name' => 'descricao', 'label' => 'Problema observado / hipotese', 'type' => 'textarea', 'placeholder' => 'O que esta acontecendo e o que voce acredita que melhorar'));
        form_campo(array('name' => 'prioridade', 'label' => 'Prioridade', 'type' => 'select', 'options' => array(1 => 'Alta', 2 => 'Media', 3 => 'Baixa'), 'value' => 2));
        form_campo(array('name' => 'esforco', 'label' => 'Esforco estimado', 'type' => 'select', 'options' => $rotuloEsforco, 'value' => 'medio'));
        form_campo(array('name' => 'prazo', 'label' => 'Prazo', 'type' => 'date', 'value' => date('Y-m-d', strtotime('+14 days'))));
        form_campo(array('name' => 'indicador', 'label' => 'Indicador de sucesso', 'type' => 'text', 'placeholder' => 'Ex.: adesao media >= 9'));
        form_campo(array('name' => 'baseline', 'label' => 'Linha de base (hoje)', 'type' => 'number', 'step' => '0.1', 'placeholder' => 'valor atual'));
        form_campo(array('name' => 'alvo', 'label' => 'Meta (valor)', 'type' => 'number', 'step' => '0.1', 'placeholder' => 'valor desejado'));
        form_campo(array('name' => 'unidade', 'label' => 'Unidade', 'type' => 'text', 'placeholder' => 'ex.: pontos, %, dias'));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Criar meta</button>
        </div>
    </form>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Nova meta de melhoria', $conteudo, '#38bdf8', 'mais');
    ?>

    <?php
    ob_start();
    if (empty($abertos)) {
        echo '<p class="muted">Nenhum alerta aberto. O motor de diagnostico roda ao abrir a Visao Geral e registra aqui desvios de meta, padroes identificados, dados insuficientes e melhorias sustentadas.</p>';
    } else {
        echo '<div class="diag-lista">';
        foreach ($abertos as $d) {
            $info = diag_tipo_info($d['tipo']);
            echo '<div class="diag-item" style="--cor:' . $info[1] . '">'
                . '<span class="diag-ico">' . icone($info[2], 18) . '</span>'
                . '<div>'
                . '<span class="diag-titulo">' . e($info[0]) . ' · ' . e($d['metrica']) . '</span>'
                . '<p>' . e($d['mensagem']) . '</p>'
                . '<div class="meta-info">'
                . '<span>Comparacao: ' . e($d['comparacao']) . '</span>'
                . '<span>Periodo: ' . e($d['periodo']) . '</span>'
                . '</div>'
                . '<div class="diag-acoes">'
                . '<span class="diag-tag" style="--cor:' . $corConf[$d['confianca']] . '">' . e($rotuloConf[$d['confianca']]) . '</span>'
                . '<span class="diag-tag" style="--cor:' . $info[1] . '">' . e($rotuloStatusDiag[$d['status']]) . '</span>';
            if (!empty($d['registros_ref'])) {
                $ref = '/ranksistem/' . ltrim($d['registros_ref'], '/');
                echo '<a class="link-mini" href="' . e($ref) . '">ver registros</a>';
            }
            echo '<a class="link-mini" href="/ranksistem/controllers/metasController.php?acao=diag_status&id=' . (int)$d['id'] . '&status=aceito">aceitar</a>'
                . '<a class="link-mini" href="/ranksistem/controllers/metasController.php?acao=diag_status&id=' . (int)$d['id'] . '&status=resolvido">resolver</a>'
                . '<a class="link-mini danger" href="/ranksistem/controllers/metasController.php?acao=diag_status&id=' . (int)$d['id'] . '&status=ignorado">ignorar</a>'
                . '</div></div></div>';
        }
        echo '</div>';
    }
    $conteudo = ob_get_clean();
    card_bloco('Motor de diagnostico e recomendacoes', $conteudo, '#fbbf24', 'cerebro', count($abertos) . ' aberto(s)');
    ?>
</section>

<section class="grid">
    <?php
    ob_start();
    if (empty($ativas)) {
        echo '<p class="muted">Nenhuma meta ativa. Comece pelo diagnostico acima ou transforme um alerta aberto em meta.</p>';
    } else {
        foreach ($ativas as $m) {
            $cor = metas_cor($m['pilar']);
            echo '<div class="meta-item" style="--cor:' . $cor . '">'
                . '<div class="meta-head">'
                . '<div><span class="meta-titulo">' . e($m['titulo']) . '</span>'
                . '<div class="muted">' . e($pilares[$m['pilar']]) . '</div></div>'
                . '</div>';
            if (!empty($m['descricao'])) { echo '<p class="meta-desc">' . e($m['descricao']) . '</p>'; }
            meta_render_badges($m, $rotuloPrioridade, $rotuloEsforco);
            echo '<div class="meta-info">';
            if (!empty($m['indicador'])) { echo '<span>Indicador: ' . e($m['indicador']) . '</span>'; }
            if ($m['baseline'] !== null) { echo '<span>Base: ' . number_format((float)$m['baseline'], 1, ',', '.') . '</span>'; }
            if ($m['alvo'] !== null) {
                echo '<span>Alvo: ' . number_format((float)$m['alvo'], 1, ',', '.') . e($m['unidade'] !== '' ? ' ' . $m['unidade'] : '') . '</span>';
            }
            echo '</div>';
            if ($m['baseline'] !== null && $m['alvo'] !== null) {
                $delta = (float)$m['alvo'] - (float)$m['baseline'];
                echo '<div class="meta-info"><span>Falta ' . number_format($delta, 1, ',', '.') . ' ate a meta</span></div>';
            }
            echo '<div class="meta-acoes">'
                . '<a class="btn btn-primario btn-mini" href="/ranksistem/controllers/metasController.php?acao=avancar_etapa&id=' . (int)$m['id'] . '">Avancar etapa →</a>'
                . '<a class="btn btn-neutro btn-mini" href="/ranksistem/controllers/metasController.php?acao=concluir&id=' . (int)$m['id'] . '">Concluir</a>'
                . '<a class="btn btn-neutro btn-mini" href="/ranksistem/controllers/metasController.php?acao=abandonar&id=' . (int)$m['id'] . '">Abandonar</a>'
                . '<a class="link-mini danger" href="/ranksistem/controllers/metasController.php?acao=del_meta&id=' . (int)$m['id'] . '" onclick="return confirm(\'Remover esta meta?\')">remover</a>'
                . '</div>'
                . '</div>';
        }
    }
    $conteudo = ob_get_clean();
    card_bloco('Metas ativas', $conteudo, '#e879f9', 'alvo', count($ativas) . ' em andamento');
    ?>
</section>

<?php if (!empty($concluidas) || !empty($encerradas)): ?>
<section class="grid grid-2">
    <?php
    ob_start();
    if (empty($concluidas)) {
        echo '<p class="muted">Nenhuma meta concluida ainda.</p>';
    } else {
        foreach ($concluidas as $m) {
            echo '<div class="meta-item" style="--cor:#22c55e">'
                . '<span class="meta-titulo">' . e($m['titulo']) . '</span>'
                . '<div class="muted">' . e($pilares[$m['pilar']]) . ' · concluida</div>';
            if (!empty($m['resultado'])) { echo '<p class="meta-desc">' . e($m['resultado']) . '</p>'; }
            echo '<div class="meta-acoes"><a class="btn btn-neutro btn-mini" href="/ranksistem/controllers/metasController.php?acao=reativar&id=' . (int)$m['id'] . '">Reabrir</a>'
                . '<a class="link-mini danger" href="/ranksistem/controllers/metasController.php?acao=del_meta&id=' . (int)$m['id'] . '" onclick="return confirm(\'Remover esta meta?\')">remover</a></div>'
                . '</div>';
        }
    }
    $conteudo = ob_get_clean();
    card_bloco('Concluidas', $conteudo, '#22c55e', 'check');
    ?>

    <?php
    ob_start();
    if (empty($encerradas)) {
        echo '<p class="muted">Nenhuma meta abandonada. Abandonar tambem e uma decisao valida quando a evidencia nao justifica o esforco.</p>';
    } else {
        foreach ($encerradas as $m) {
            echo '<div class="meta-item" style="--cor:#64748b; opacity:.8">'
                . '<span class="meta-titulo">' . e($m['titulo']) . '</span>'
                . '<div class="muted">' . e($pilares[$m['pilar']]) . ' · abandonada</div>'
                . '<div class="meta-acoes"><a class="btn btn-neutro btn-mini" href="/ranksistem/controllers/metasController.php?acao=reativar&id=' . (int)$m['id'] . '">Reabrir</a>'
                . '<a class="link-mini danger" href="/ranksistem/controllers/metasController.php?acao=del_meta&id=' . (int)$m['id'] . '" onclick="return confirm(\'Remover esta meta?\')">remover</a></div>'
                . '</div>';
        }
    }
    $conteudo = ob_get_clean();
    card_bloco('Abandonadas', $conteudo, '#64748b', 'lixo');
    ?>
</section>
<?php endif; ?>

<?php if (!empty($historico)): ?>
<section class="grid">
    <?php
    ob_start();
    $rows = array();
    foreach ($historico as $d) {
        $info = diag_tipo_info($d['tipo']);
        $rows[] = array(
            date('d/m/Y', strtotime($d['atualizado_em'])),
            badge($info[0], $info[1]),
            e($d['metrica']),
            e($d['mensagem']),
            badge($rotuloStatusDiag[$d['status']], $d['status'] === 'resolvido' ? '#22c55e' : '#64748b'),
        );
    }
    tabela(array('Data', 'Tipo', 'Metrica', 'Diagnostico', 'Status'), $rows, 'Nenhum alerta encerrado ainda.', array('data_table' => true, 'id' => 'tabelaDiag', 'placeholder' => 'Pesquisar por metrica ou texto...'));
    $conteudo = ob_get_clean();
    card_bloco('Historico de recomendacoes', $conteudo, '#8ba0c4', 'livro');
    ?>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
