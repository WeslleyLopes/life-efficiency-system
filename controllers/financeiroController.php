<?php
// financeiroController.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

$acao = isset($_POST['acao']) ? $_POST['acao'] : (isset($_GET['acao']) ? $_GET['acao'] : '');
$voltar = '/ranksistem/pages/financeiro.php';

function fin_redir($url)
{
    header('Location: ' . $url);
    exit;
}

if ($acao === 'add_conta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $tipo = $_POST['tipo'];
    $saldoBase = fin_parse_valor(isset($_POST['saldo_inicial']) ? $_POST['saldo_inicial'] : '0');
    $saldoAtual = !empty($_POST['saldo_atual']) ? fin_parse_valor($_POST['saldo_atual']) : $saldoBase;
    $dataSaldo = !empty($_POST['data_saldo']) ? $_POST['data_saldo'] : date('Y-m-d');
    if ($nome === '' || $saldoBase <= 0) {
        flash_set('erro', 'Informe nome e um valor base maior que zero.');
        fin_redir($voltar);
    }
    $stmt = db()->prepare("INSERT INTO financeiro_contas (nome, tipo, saldo_inicial, saldo_atual, data_saldo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('ssdds', $nome, $tipo, $saldoBase, $saldoAtual, $dataSaldo);
    $stmt->execute();
    $stmt->close();
    flash_set('ok', 'Conta criada.');
    fin_redir($voltar);
}

if ($acao === 'add_movimento' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $conta_id = (int)$_POST['conta_id'];
    $data = $_POST['data'];
    $tipo = $_POST['tipo'];
    $valor = abs((float)$_POST['valor']);
    $descricao = trim($_POST['descricao']);
    $delta = $tipo === 'retirada' ? -$valor : $valor;

    $stmt = db()->prepare("INSERT INTO financeiro_movimentos (conta_id, data, tipo, valor, descricao) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issds', $conta_id, $data, $tipo, $valor, $descricao);
    $stmt->execute();
    $stmt->close();

    $up = db()->prepare("UPDATE financeiro_contas SET saldo_atual = saldo_atual + ? WHERE id = ?");
    $up->bind_param('di', $delta, $conta_id);
    $up->execute();
    $up->close();

    flash_set('ok', 'Movimento registrado.');
    fin_redir($voltar);
}

if ($acao === 'add_operacao' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $conta_id = (int)$_POST['conta_id'];
    $contaVazia = $conta_id > 0 ? $conta_id : null;
    $data = $_POST['data'];
    $setup_id = (int)$_POST['setup_id'];
    $direcao = $_POST['direcao'];
    $resultado = $_POST['resultado'];
    $risco = abs((float)$_POST['risco_pct']);
    $retorno = abs((float)$_POST['retorno_pct']);
    $adesao = (int)$_POST['adesao_plano'];
    $notas = trim($_POST['notas']);

    $retornoAssinado = $retorno;
    if ($resultado === 'loss') { $retornoAssinado = -$retorno; }
    if ($resultado === 'breakeven') { $retornoAssinado = 0; }

    $setupVazio = $setup_id > 0 ? $setup_id : null;

    $stmt = db()->prepare("INSERT INTO financeiro_operacoes
        (conta_id, data, setup_id, direcao, resultado, risco_pct, retorno_pct, adesao_plano, notas)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isisisdis', $contaVazia, $data, $setupVazio, $direcao, $resultado, $risco, $retornoAssinado, $adesao, $notas);
    $stmt->execute();
    $stmt->close();

    if ($conta_id > 0) {
        $up = db()->prepare("UPDATE financeiro_contas SET saldo_atual = saldo_atual * (1 + ? / 100) WHERE id = ?");
        $up->bind_param('di', $retornoAssinado, $conta_id);
        $up->execute();
        $up->close();
    }

    flash_set('ok', 'Operacao registrada. Continue seguindo o plano!');
    fin_redir($voltar);
}

if ($acao === 'del_operacao' && isset($_GET['id'])) {
    db_query("DELETE FROM financeiro_operacoes WHERE id = " . (int)$_GET['id']);
    flash_set('ok', 'Operacao removida.');
    fin_redir($voltar);
}

if ($acao === 'del_movimento' && isset($_GET['id'])) {
    db_query("DELETE FROM financeiro_movimentos WHERE id = " . (int)$_GET['id']);
    flash_set('ok', 'Movimento removido (saldo nao recalculado automaticamente).');
    fin_redir($voltar);
}

$paginaImport = '/ranksistem/pages/financeiro-importar.php';

if ($acao === 'importar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        flash_set('erro', 'Nenhum arquivo valido enviado.');
        fin_redir($paginaImport);
    }
    $nome = $_FILES['arquivo']['name'];
    $tmp = $_FILES['arquivo']['tmp_name'];
    $linhas = planilha_ler_linhas($tmp, $nome);
    if ($linhas === null || empty($linhas)) {
        flash_set('erro', 'Nao foi possivel ler a planilha. Use .xlsx ou CSV/TXT.');
        fin_redir($paginaImport);
    }
    $res = fin_importar_linhas($linhas, $nome, false);
    if ((int)$res['importadas'] === 0) {
        flash_set('erro', 'Nenhuma linha valida encontrada. Confira o formato das colunas.');
        fin_redir($paginaImport);
    }
    salvar_pilar_scores();
    flash_set('ok', $res['importadas'] . ' lancamentos importados. Saldo atual mantido (defina-o em Cadastros).');
    fin_redir($paginaImport);
}

if ($acao === 'del_importacao' && isset($_GET['id'])) {
    fin_remover_importacao((int)$_GET['id']);
    salvar_pilar_scores();
    flash_set('ok', 'Importacao desfeita. Os lancamentos foram removidos e o saldo atual nao mudou.');
    fin_redir($paginaImport);
}

fin_redir($voltar);
