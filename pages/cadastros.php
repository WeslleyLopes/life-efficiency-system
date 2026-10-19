<?php
require_once __DIR__ . '/../config.php';

$page_title = 'Cadastros';
$page_sub = 'Itens expansiveis: materias, exercicios, setups, contas e pesos';
$active = 'cadastros';

$materias = ac_materias(false);
$exercicios = fis_exercicios();
$setups = db_all("SELECT * FROM financeiro_setups ORDER BY nome");
$regrasAtivo = db_all("SELECT * FROM financeiro_regras_ativo ORDER BY classe, padrao");
$contas = fin_contas(false);
$pesos = score_pesos();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/cadastrosController.php" class="form-grid">
        <input type="hidden" name="acao" value="add_materia">
        <?php
        form_campo(array('name' => 'nome', 'label' => 'Nova materia', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Filosofia, Neurociencia'));
        form_campo(array('name' => 'cor', 'label' => 'Cor', 'type' => 'text', 'value' => '#a78bfa', 'placeholder' => 'Ex: #22c55e'));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Adicionar materia</button>
        </div>
    </form>
    <div class="chips-lista">
        <?php foreach ($materias as $m): ?>
            <span class="chip" style="--cor:<?php echo e($m['cor']); ?>">
                <?php echo e($m['nome']); ?>
                <a href="/ranksistem/controllers/cadastrosController.php?acao=del_materia&id=<?php echo (int)$m['id']; ?>" onclick="return confirm('Remover materia e seus modulos?')">×</a>
            </span>
        <?php endforeach; ?>
    </div>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Materias de estudo', $conteudo, '#a78bfa', 'livro');
    ?>

    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/cadastrosController.php" class="form-grid">
        <input type="hidden" name="acao" value="add_setup">
        <?php
        form_campo(array('name' => 'nome', 'label' => 'Nome do setup', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Reversao na abertura'));
        form_campo(array('name' => 'descricao', 'label' => 'Descricao', 'type' => 'text'));
        form_campo(array('name' => 'regras', 'label' => 'Regras (uma por linha)', 'type' => 'textarea'));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Adicionar setup</button>
        </div>
    </form>
    <div class="lista-simples">
        <?php foreach ($setups as $s): ?>
            <div class="item-simples"><strong><?php echo e($s['nome']); ?></strong><span class="muted"><?php echo e($s['descricao']); ?></span></div>
        <?php endforeach; ?>
    </div>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Setups de trading', $conteudo, '#22c55e', 'carteira');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/cadastrosController.php" class="form-grid">
        <input type="hidden" name="acao" value="add_conta">
        <?php
        form_campo(array('name' => 'nome', 'label' => 'Nome da conta', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Conta Trading B'));
        form_campo(array('name' => 'tipo', 'label' => 'Tipo', 'type' => 'select', 'options' => array('trading' => 'Trading', 'investimento' => 'Investimento', 'renda_trabalho' => 'Renda do Trabalho')));
        form_campo(array('name' => 'saldo_inicial', 'label' => 'Valor base (referencia)', 'type' => 'text', 'placeholder' => 'Ex: 10.000,00', 'autocomplete' => 'off', 'required' => true));
        form_campo(array('name' => 'saldo_atual', 'label' => 'Saldo atual (R$)', 'type' => 'text', 'placeholder' => 'Deixe vazio para usar o valor base', 'autocomplete' => 'off'));
        form_campo(array('name' => 'data_saldo', 'label' => 'Data de referencia do saldo', 'type' => 'date', 'value' => date('Y-m-d'), 'required' => true));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Adicionar conta</button>
        </div>
    </form>
    <div class="lista-simples">
        <?php foreach ($contas as $c): ?>
            <div class="item-simples">
                <strong><?php echo e($c['nome']); ?></strong>
                <span class="muted"><?php echo e($c['tipo']); ?> · R$ <?php echo number_format((float)$c['saldo_atual'], 2, ',', '.'); ?><?php echo $c['data_saldo'] ? ' · desde ' . date('d/m/Y', strtotime($c['data_saldo'])) : ''; ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Contas financeiras', $conteudo, '#38bdf8', 'carteira');
    ?>

    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/cadastrosController.php" class="form-grid form-pesos">
        <input type="hidden" name="acao" value="salvar_pesos">
        <?php
        form_campo(array('name' => 'peso_financeiro', 'label' => 'Financeiro (%)', 'type' => 'number', 'min' => 0, 'max' => 100, 'value' => $pesos['financeiro']));
        form_campo(array('name' => 'peso_academico', 'label' => 'Academico (%)', 'type' => 'number', 'min' => 0, 'max' => 100, 'value' => $pesos['academico']));
        form_campo(array('name' => 'peso_fisico', 'label' => 'Fisico (%)', 'type' => 'number', 'min' => 0, 'max' => 100, 'value' => $pesos['fisico']));
        form_campo(array('name' => 'peso_social', 'label' => 'Social (%)', 'type' => 'number', 'min' => 0, 'max' => 100, 'value' => $pesos['social']));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-neutro">Salvar pesos do score global</button>
        </div>
    </form>
    <p class="muted">Ajuste o peso de cada pilar para o calculo do Score Global de Eficiencia.</p>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Pesos do Score Global', $conteudo, '#fbbf24', 'alvo');
    ?>
</section>

<section class="grid">
    <?php
    ob_start();
    if (empty($contas)) {
        echo '<p class="muted">Cadastre uma conta para definir o saldo atual.</p>';
    } else {
        echo '<div class="saldo-lista">';
        foreach ($contas as $c) {
            echo '<form method="post" action="/ranksistem/controllers/cadastrosController.php" class="saldo-item">'
                . '<input type="hidden" name="acao" value="salvar_saldo">'
                . '<input type="hidden" name="conta_id" value="' . (int)$c['id'] . '">'
                . '<div class="saldo-conta"><strong>' . e($c['nome']) . '</strong>'
                . '<span class="muted">' . e($c['tipo']) . '</span></div>'
                . '<div class="campo"><label>Valor base (R$)</label>'
                . '<input type="text" name="saldo_inicial" inputmode="decimal" value="' . number_format((float)$c['saldo_inicial'], 2, ',', '.') . '" autocomplete="off" required></div>'
                . '<div class="campo"><label>Saldo atual (R$)</label>'
                . '<input type="text" name="saldo_atual" inputmode="decimal" value="' . number_format((float)$c['saldo_atual'], 2, ',', '.') . '" autocomplete="off" required></div>'
                . '<div class="campo"><label>Data de referencia</label>'
                . '<input type="date" name="data_saldo" value="' . ($c['data_saldo'] ? e($c['data_saldo']) : date('Y-m-d')) . '" required></div>'
                . '<button type="submit" class="btn btn-primario btn-mini">Salvar saldo</button>'
                . '</form>';
        }
        echo '</div>';
    }
    echo '<p class="muted">O valor base e a referencia inicial usada no indice e na variacao. Informe o saldo real de hoje; a importacao da planilha usa as datas dos lancamentos para reconstruir o historico, sem somar os valores antigos de novo.</p>';
    $conteudo = ob_get_clean();
    card_bloco('Definir saldo atual (ancora)', $conteudo, '#22c55e', 'carteira');
    ?>
</section>

<section class="grid">
    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/cadastrosController.php" id="formExercicio" class="form-grid">
        <input type="hidden" name="acao" value="add_exercicio">
        <?php
        form_campo(array('name' => 'nome', 'label' => 'Exercicio', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: Barra fixa'));
        form_campo(array('name' => 'grupo', 'label' => 'Grupo muscular', 'type' => 'text', 'value' => 'Geral'));
        ?>
        <div class="campo campo-full">
            <label>Escada de niveis (progressao)</label>
            <div id="linhasNivel" class="linhas-treino"></div>
            <button type="button" class="btn btn-neutro btn-mini" id="addLinhaNivel"><?php echo icone('mais', 16); ?> Adicionar nivel</button>
        </div>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Cadastrar exercicio</button>
        </div>
    </form>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Novo exercicio de calistenia', $conteudo, '#fb923c', 'halter');
    ?>

    <?php
    ob_start();
    foreach ($exercicios as $ex) {
        $niveis = fis_niveis($ex['id']);
        echo '<div class="exercicio-lista-item"><div class="exercicio-lista-head"><strong>' . e($ex['nome']) . '</strong><span class="muted">' . e($ex['grupo']) . '</span></div>';
        echo '<div class="niveis-tags">';
        foreach ($niveis as $n) {
            echo '<span class="nivel-tag">N' . (int)$n['nivel'] . ' · ' . e($n['nome']) . ' <em>x' . number_format((float)$n['fator_dificuldade'], 2, ',', '.') . '</em></span>';
        }
        echo '</div>';
        echo '<form method="post" action="/ranksistem/controllers/cadastrosController.php" class="nivel-form">'
            . '<input type="hidden" name="acao" value="add_nivel"><input type="hidden" name="exercicio_id" value="' . (int)$ex['id'] . '">'
            . '<input type="text" name="nome" placeholder="Proximo nivel" required>'
            . '<input type="number" name="fator_dificuldade" step="0.05" min="0.1" value="1.20" title="Fator de dificuldade">'
            . '<button type="submit" class="btn btn-mini btn-neutro">+ Nivel</button></form>';
        echo '</div>';
    }
    $conteudo = ob_get_clean();
    card_bloco('Exercicios cadastrados e suas progressoes', $conteudo, '#a78bfa', 'alvo');
    ?>
</section>

<section class="grid grid-2">
    <?php
    ob_start();
    ?>
    <form method="post" action="/ranksistem/controllers/cadastrosController.php" class="form-grid">
        <input type="hidden" name="acao" value="add_regra">
        <?php
        form_campo(array('name' => 'padrao', 'label' => 'Padrao (prefixo do ticker)', 'type' => 'text', 'required' => true, 'placeholder' => 'Ex: IND, WIN, WDO ou ^'));
        form_campo(array('name' => 'classe', 'label' => 'Classificar como', 'type' => 'select', 'options' => array('indice' => 'Indice/Futuro (conta Trading)', 'acao' => 'Acao/BDR/FII (conta Investimento)')));
        form_campo(array('name' => 'descricao', 'label' => 'Descricao', 'type' => 'text', 'placeholder' => 'Opcional'));
        ?>
        <div class="campo campo-full">
            <button type="submit" class="btn btn-primario">Adicionar regra</button>
        </div>
    </form>
    <p class="muted">Na importacao, o ticker e comparado com o inicio de cada padrao (ex: WDOZ25 comeca com WDO). Use <strong>@</strong> no inicio para uma expressao regular.</p>
    <?php
    $conteudo = ob_get_clean();
    card_bloco('Nova regra de classificacao', $conteudo, '#f472b6', 'link');
    ?>

    <?php
    ob_start();
    $rows = array();
    foreach ($regrasAtivo as $r) {
        $rows[] = array(
            '<strong>' . e($r['padrao']) . '</strong>',
            badge($r['classe'] === 'indice' ? 'Indice -> Trading' : 'Acao -> Investimento', $r['classe'] === 'indice' ? '#22c55e' : '#38bdf8'),
            e($r['descricao']),
            '<a class="link-del" href="/ranksistem/controllers/cadastrosController.php?acao=del_regra&id=' . (int)$r['id'] . '" onclick="return confirm(\'Remover regra?\')">' . icone('lixo', 16) . '</a>'
        );
    }
    tabela(array('Padrao', 'Destino', 'Descricao', ''), $rows);
    $conteudo = ob_get_clean();
    card_bloco('Regras de classificacao de ativos', $conteudo, '#22c55e', 'alvo');
    ?>
</section>

<script>
window.RANK_EXERCICIOS = <?php echo json_encode(array()); ?>;
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
