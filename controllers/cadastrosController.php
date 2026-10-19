<?php
// cadastrosController.php - materias, exercicios+niveis, setups, contas, pesos, planos experimentais
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

$acao = isset($_POST['acao']) ? $_POST['acao'] : (isset($_GET['acao']) ? $_GET['acao'] : '');
$voltar = '/ranksistem/pages/cadastros.php';

function cad_redir($url)
{
    header('Location: ' . $url);
    exit;
}

if ($acao === 'add_materia' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $cor = $_POST['cor'];
    $stmt = db()->prepare("INSERT INTO materias (nome, cor) VALUES (?, ?)");
    $stmt->bind_param('ss', $nome, $cor);
    $stmt->execute();
    $stmt->close();
    flash_set('ok', 'Materia adicionada.');
    cad_redir($voltar);
}

if ($acao === 'del_materia' && isset($_GET['id'])) {
    db_query("DELETE FROM materias WHERE id = " . (int)$_GET['id']);
    flash_set('ok', 'Materia removida.');
    cad_redir($voltar);
}

if ($acao === 'add_exercicio' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $grupo = trim($_POST['grupo']);
    $stmt = db()->prepare("INSERT INTO exercicios (nome, grupo) VALUES (?, ?)");
    $stmt->bind_param('ss', $nome, $grupo);
    $stmt->execute();
    $exId = db_insert_id();
    $stmt->close();

    $niveis_nome = isset($_POST['nivel_nome']) ? $_POST['nivel_nome'] : array();
    $niveis_fator = isset($_POST['nivel_fator']) ? $_POST['nivel_fator'] : array();
    $stmtN = db()->prepare("INSERT INTO exercicios_niveis (exercicio_id, nivel, nome, fator_dificuldade) VALUES (?, ?, ?, ?)");
    $nivel = 0;
    foreach ($niveis_nome as $i => $nn) {
        $nn = trim($nn);
        if ($nn === '') { continue; }
        $nivel++;
        $fator = isset($niveis_fator[$i]) ? (float)$niveis_fator[$i] : 1.00;
        $stmtN->bind_param('iisd', $exId, $nivel, $nn, $fator);
        $stmtN->execute();
    }
    $stmtN->close();
    flash_set('ok', 'Exercicio e niveis cadastrados.');
    cad_redir($voltar);
}

if ($acao === 'add_nivel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $exercicio_id = (int)$_POST['exercicio_id'];
    $nome = trim($_POST['nome']);
    $fator = (float)$_POST['fator_dificuldade'];
    $row = db_one("SELECT COALESCE(MAX(nivel),0) AS n FROM exercicios_niveis WHERE exercicio_id = $exercicio_id");
    $nivel = (int)$row['n'] + 1;
    $stmt = db()->prepare("INSERT INTO exercicios_niveis (exercicio_id, nivel, nome, fator_dificuldade) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('iisd', $exercicio_id, $nivel, $nome, $fator);
    $stmt->execute();
    $stmt->close();
    flash_set('ok', 'Nivel de progressao adicionado.');
    cad_redir($voltar);
}

if ($acao === 'add_setup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $regras = trim($_POST['regras']);
    $stmt = db()->prepare("INSERT INTO financeiro_setups (nome, descricao, regras) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $nome, $descricao, $regras);
    $stmt->execute();
    $stmt->close();
    flash_set('ok', 'Setup adicionado.');
    cad_redir($voltar);
}

if ($acao === 'add_regra' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $padrao = trim($_POST['padrao']);
    $classe = $_POST['classe'];
    $descricao = trim($_POST['descricao']);
    if ($padrao !== '') {
        $stmt = db()->prepare("INSERT INTO financeiro_regras_ativo (padrao, classe, descricao) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $padrao, $classe, $descricao);
        $stmt->execute();
        $stmt->close();
        flash_set('ok', 'Regra de classificacao adicionada.');
    }
    cad_redir($voltar);
}

if ($acao === 'del_regra' && isset($_GET['id'])) {
    db_query("DELETE FROM financeiro_regras_ativo WHERE id = " . (int)$_GET['id']);
    flash_set('ok', 'Regra removida.');
    cad_redir($voltar);
}

if ($acao === 'add_conta' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $tipo = $_POST['tipo'];
    $saldoBase = fin_parse_valor(isset($_POST['saldo_inicial']) ? $_POST['saldo_inicial'] : '0');
    $saldoAtual = !empty($_POST['saldo_atual']) ? fin_parse_valor($_POST['saldo_atual']) : $saldoBase;
    $dataSaldo = !empty($_POST['data_saldo']) ? $_POST['data_saldo'] : date('Y-m-d');
    $stmt = db()->prepare("INSERT INTO financeiro_contas (nome, tipo, saldo_inicial, saldo_atual, data_saldo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('ssdds', $nome, $tipo, $saldoBase, $saldoAtual, $dataSaldo);
    $stmt->execute();
    $stmt->close();
    flash_set('ok', 'Conta adicionada.');
    cad_redir($voltar);
}

if ($acao === 'salvar_saldo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['conta_id'];
    $saldo = fin_parse_valor(isset($_POST['saldo_atual']) ? $_POST['saldo_atual'] : '0');
    $data = !empty($_POST['data_saldo']) ? $_POST['data_saldo'] : date('Y-m-d');
    if (isset($_POST['saldo_inicial']) && $_POST['saldo_inicial'] !== '') {
        $base = fin_parse_valor($_POST['saldo_inicial']);
        $stmt = db()->prepare("UPDATE financeiro_contas SET saldo_inicial = ?, saldo_atual = ?, data_saldo = ? WHERE id = ?");
        $stmt->bind_param('ddsi', $base, $saldo, $data, $id);
    } else {
        $stmt = db()->prepare("UPDATE financeiro_contas SET saldo_atual = ?, data_saldo = ? WHERE id = ?");
        $stmt->bind_param('dsi', $saldo, $data, $id);
    }
    $stmt->execute();
    $stmt->close();
    salvar_pilar_scores();
    flash_set('ok', 'Valores da conta atualizados.');
    cad_redir($voltar);
}

if ($acao === 'salvar_pesos' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pf = (int)$_POST['peso_financeiro'];
    $pa = (int)$_POST['peso_academico'];
    $pfi = (int)$_POST['peso_fisico'];
    $ps = (int)$_POST['peso_social'];
    $row = db_one("SELECT id FROM config_pesos_pilares ORDER BY id LIMIT 1");
    if ($row) {
        db_query("UPDATE config_pesos_pilares SET peso_financeiro=$pf, peso_academico=$pa, peso_fisico=$pfi, peso_social=$ps WHERE id=" . (int)$row['id']);
    } else {
        db_query("INSERT INTO config_pesos_pilares (peso_financeiro,peso_academico,peso_fisico,peso_social) VALUES ($pf,$pa,$pfi,$ps)");
    }
    flash_set('ok', 'Pesos do score global atualizados.');
    cad_redir($voltar);
}

if ($acao === 'add_plano' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pilar = $_POST['pilar'];
    $nome = trim($_POST['nome']);
    $hipotese = trim($_POST['hipotese']);
    $inicio = $_POST['data_inicio'];
    $fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
    $stmt = db()->prepare("INSERT INTO planos_experimentais (pilar, nome, hipotese, data_inicio, data_fim) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sssss', $pilar, $nome, $hipotese, $inicio, $fim);
    $stmt->execute();
    $stmt->close();
    flash_set('ok', 'Plano experimental criado.');
    cad_redir('/ranksistem/pages/correlacoes.php');
}

if ($acao === 'upd_plano' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $nota = (int)$_POST['resultado_nota'];
    $status = $_POST['status'];
    $obs = trim($_POST['observacoes']);
    db_query("UPDATE planos_experimentais SET resultado_nota=$nota, status='" . db_escape($status) . "', observacoes='" . db_escape($obs) . "' WHERE id=$id");
    flash_set('ok', 'Plano atualizado.');
    cad_redir('/ranksistem/pages/correlacoes.php');
}

cad_redir($voltar);
