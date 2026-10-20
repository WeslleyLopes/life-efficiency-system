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
    $data = $_POST['data'];
    $setup_id = (int)$_POST['setup_id'];
    $direcao = $_POST['direcao'];
    $resultado = $_POST['resultado'];
    if (!in_array($resultado, array('win', 'loss', 'breakeven'), true)) { $resultado = 'breakeven'; }
    $risco = abs((float)$_POST['risco_pct']);
    $retorno = abs((float)$_POST['retorno_pct']);
    $adesao = (int)$_POST['adesao_plano'];
    $notas = trim($_POST['notas']);

    $retornoAssinado = $retorno;
    if ($resultado === 'loss') { $retornoAssinado = -$retorno; }
    if ($resultado === 'breakeven') { $retornoAssinado = 0; }

    // bind_param com null corrompe os campos seguintes; usa 0 e converte no SQL
    $stmt = db()->prepare("INSERT INTO financeiro_operacoes
        (conta_id, data, setup_id, direcao, resultado, risco_pct, retorno_pct, adesao_plano, notas)
        VALUES (?, ?, IF(? > 0, ?, NULL), ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isiissddis', $conta_id, $data, $setup_id, $setup_id, $direcao, $resultado, $risco, $retornoAssinado, $adesao, $notas);
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

// Exclui um lancamento importado (uma linha da tabela de lancamentos) e ajusta os totais do lote.
// voltar=analise + aba/filtros devolve o usuario para a analise no mesmo recorte.
if ($acao === 'del_lancamento' && isset($_GET['id'])) {
    $idLanc = (int)$_GET['id'];
    $voltarLanc = $paginaImport;
    if (isset($_GET['voltar']) && $_GET['voltar'] === 'analise') {
        $abaLanc = isset($_GET['aba']) && in_array($_GET['aba'], array('geral', 'investimento', 'trading'), true) ? $_GET['aba'] : 'geral';
        $qLanc = array('aba' => $abaLanc);
        foreach (array('de', 'ate', 'classe', 'corretora', 'ativo', 'contrato') as $kL) {
            if (isset($_GET[$kL]) && $_GET[$kL] !== '') { $qLanc[$kL] = (string)$_GET[$kL]; }
        }
        $voltarLanc = '/ranksistem/pages/financeiro-analise.php?' . http_build_query($qLanc);
    }
    $mv = db_one("SELECT id, importacao_id, tipo, valor_total FROM financeiro_ativos_movimentos WHERE id = $idLanc");
    if (!$mv) {
        flash_set('erro', 'Lancamento nao encontrado (pode ja ter sido excluido).');
        fin_redir($voltarLanc);
    }
    db_query("DELETE FROM financeiro_ativos_movimentos WHERE id = $idLanc");
    $impId = (int)$mv['importacao_id'];
    if ($impId > 0) {
        $campo = $mv['tipo'] === 'credito' ? 'credito_total' : 'debito_total';
        $val = sprintf('%.2F', (float)$mv['valor_total']);
        db_query("UPDATE financeiro_importacoes SET importadas = GREATEST(importadas - 1, 0), "
            . $campo . " = GREATEST(" . $campo . " - " . $val . ", 0) WHERE id = $impId");
    }
    salvar_pilar_scores();
    flash_set('ok', 'Lancamento excluido. Carteira e analise recalculadas; o saldo atual nao muda.');
    fin_redir($voltarLanc);
}

// Laboratorio de metodos: alterar as regras de um setup cria uma nova versao.
// Misturar resultados de versoes diferentes de uma regra torna a comparacao enganosa.
if ($acao === 'upd_setup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $regras = trim(isset($_POST['regras']) ? $_POST['regras'] : '');
    $atual = db_one("SELECT * FROM financeiro_setups WHERE id = $id");
    if (!$atual) {
        flash_set('erro', 'Metodo nao encontrado.');
        fin_redir($voltar);
    }
    if ($regras === trim((string)$atual['regras'])) {
        flash_set('ok', 'Nenhuma alteracao nas regras: a versao atual foi mantida.');
        fin_redir($voltar);
    }
    $temVersao = !empty(db_all("SHOW COLUMNS FROM financeiro_setups LIKE 'versao'"));
    if ($temVersao) {
        $stmt = db()->prepare("UPDATE financeiro_setups SET regras = ?, versao = versao + 1, atualizado_em = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param('si', $regras, $id);
    } else {
        $stmt = db()->prepare("UPDATE financeiro_setups SET regras = ? WHERE id = ?");
        $stmt->bind_param('si', $regras, $id);
    }
    $stmt->execute();
    $stmt->close();
    $novaVersao = isset($atual['versao']) ? (int)$atual['versao'] + 1 : 1;
    flash_set('ok', 'Regras atualizadas. Nova versao registrada: v' . $novaVersao
        . ($temVersao ? '' : ' (rode database/install.php para ativar o versionamento)'));
    fin_redir($voltar . '#metodo-' . $id);
}

fin_redir($voltar);
