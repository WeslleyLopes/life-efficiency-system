<?php
// importacao_helper.php - importacao de planilhas para o financeiro

require_once __DIR__ . '/xlsx_helper.php';

function fin_parse_valor($s)
{
    $s = trim((string)$s);
    if ($s === '') { return 0.0; }
    $neg = (strpos($s, '-') !== false && strpos($s, '(') === false && preg_match('/^\-/', trim($s)));
    if (strpos($s, '(') !== false) { $neg = true; }
    $s = str_replace(array('R$', 'r$', "\xC2\xA0", ' '), '', $s);
    $s = preg_replace('/[^0-9,.\-]/', '', $s);
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (strpos($s, ',') !== false) {
        $s = str_replace(',', '.', $s);
    }
    $v = (float)$s;
    return $neg ? -abs($v) : $v;
}

function fin_parse_qtd($s)
{
    $s = preg_replace('/[^0-9,.\-]/', '', trim((string)$s));
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (strpos($s, ',') !== false) {
        $s = str_replace(',', '.', $s);
    }
    return (float)$s;
}

function fin_parse_data($s)
{
    $s = trim((string)$s);
    if ($s === '') { return null; }
    // serial do Excel
    if (is_numeric($s) && (int)$s > 20000 && (int)$s < 80000) {
        return xlsx_serial_para_data($s);
    }
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $s, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    if (preg_match('#^(\d{4})-(\d{2})-(\d{2})#', $s, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    $ts = strtotime(str_replace('/', '-', $s));
    return $ts ? date('Y-m-d', $ts) : null;
}

function fin_normaliza_tipo($s)
{
    $s = strtolower(trim((string)$s));
    $s = str_replace(array('é', 'í', 'ó', 'á', 'â', 'ã', 'ç'), array('e', 'i', 'o', 'a', 'a', 'a', 'c'), $s);
    if (strpos($s, 'cred') !== false || strpos($s, 'entrada') !== false) { return 'credito'; }
    if (strpos($s, 'deb') !== false || strpos($s, 'saida') !== false) { return 'debito'; }
    return null;
}

// Classifica o ativo: 'indice' (vai para Trading) ou 'acao' (vai para Investimento)
function fin_classificar_ativo($codigo)
{
    $codigo = strtoupper(trim((string)$codigo));
    if ($codigo === '') { return 'outro'; }
    if (strpos($codigo, '^') === 0) { return 'indice'; }

    $regras = db_all("SELECT padrao, classe FROM financeiro_regras_ativo ORDER BY id");
    foreach ($regras as $r) {
        $p = trim($r['padrao']);
        if ($p === '') { continue; }
        if (strpos($p, '@') === 0) {
            if (@preg_match('/' . substr($p, 1) . '/i', $codigo)) { return $r['classe']; }
        } else {
            if (strpos($codigo, strtoupper($p)) === 0) { return $r['classe']; }
        }
    }
    return 'acao';
}

function fin_conta_por_tipo($tipo)
{
    $row = db_one("SELECT id, nome FROM financeiro_contas WHERE tipo = '" . db_escape($tipo) . "' AND ativo = 1 ORDER BY id LIMIT 1");
    return $row;
}

// Extrai os campos de uma linha da planilha (formato: Tipo | Data | Operacao | Ativo | Corretora | Qtd | Preco | Total)
function fin_extrair_linha($linha)
{
    $tipoIdx = null;
    foreach ($linha as $i => $cel) {
        if (fin_normaliza_tipo($cel) !== null) {
            $tipoIdx = $i;
            break;
        }
    }
    if ($tipoIdx === null) { return null; }

    $get = function ($i) use ($linha) {
        return isset($linha[$i]) ? trim((string)$linha[$i]) : '';
    };

    $tipo = fin_normaliza_tipo($get($tipoIdx));
    $data = fin_parse_data($get($tipoIdx + 1));
    $operacao = $get($tipoIdx + 2);
    $ativoRaw = $get($tipoIdx + 3);
    $corretora = $get($tipoIdx + 4);
    $quantidade = fin_parse_qtd($get($tipoIdx + 5));
    $preco = abs(fin_parse_valor($get($tipoIdx + 6)));
    $total = abs(fin_parse_valor($get($tipoIdx + 7)));

    if ($total <= 0 && $preco > 0 && $quantidade > 0) {
        $total = $preco * $quantidade;
    }

    if ($data === null || $ativoRaw === '') {
        return null;
    }

    $codigo = $ativoRaw;
    $nome = null;
    if (strpos($ativoRaw, ' - ') !== false) {
        $partes = explode(' - ', $ativoRaw, 2);
        $codigo = trim($partes[0]);
        $nome = trim($partes[1]);
    }

    return array(
        'data' => $data,
        'tipo' => $tipo,
        'operacao' => $operacao,
        'ativo_codigo' => $codigo,
        'ativo_nome' => $nome,
        'corretora' => $corretora,
        'quantidade' => $quantidade,
        'preco_unitario' => $preco,
        'valor_total' => $total,
    );
}

// Importa as linhas: grava movimentos e classifica a conta.
// O saldo atual e definido pelo usuario (Cadastros); os lancamentos historicos NAO sao somados de novo.
function fin_importar_linhas($linhas, $nome_arquivo, $so_preview = false)
{
    $validas = array();
    $totalLinhas = 0;
    foreach ($linhas as $linha) {
        $totalLinhas++;
        $dados = fin_extrair_linha($linha);
        if ($dados === null) { continue; }
        $dados['classe'] = fin_classificar_ativo($dados['ativo_codigo']);
        $tipoConta = $dados['classe'] === 'indice' ? 'trading' : 'investimento';
        $conta = fin_conta_por_tipo($tipoConta);
        $dados['conta_id'] = $conta ? (int)$conta['id'] : null;
        $dados['conta_nome'] = $conta ? $conta['nome'] : '(sem conta)';
        $dados['tipo_conta'] = $tipoConta;
        $validas[] = $dados;
    }

    $creditoTotal = 0.0;
    $debitoTotal = 0.0;
    foreach ($validas as $d) {
        if ($d['tipo'] === 'credito') { $creditoTotal += $d['valor_total']; }
        else { $debitoTotal += $d['valor_total']; }
    }

    if ($so_preview) {
        return array('total_linhas' => $totalLinhas, 'importadas' => count($validas), 'linhas' => $validas,
            'credito_total' => $creditoTotal, 'debito_total' => $debitoTotal, 'importacao_id' => null);
    }

    // Cria o lote
    $stmt = db()->prepare("INSERT INTO financeiro_importacoes (arquivo, total_linhas, importadas, credito_total, debito_total) VALUES (?, ?, ?, ?, ?)");
    $importadas = count($validas);
    $stmt->bind_param('siidd', $nome_arquivo, $totalLinhas, $importadas, $creditoTotal, $debitoTotal);
    $stmt->execute();
    $importacaoId = db_insert_id();
    $stmt->close();

    $stmtM = db()->prepare("INSERT INTO financeiro_ativos_movimentos
        (importacao_id, data, tipo, operacao, ativo_codigo, ativo_nome, corretora, classe, quantidade, preco_unitario, valor_total, conta_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($validas as $d) {
        $stmtM->bind_param('isssssssdddi',
            $importacaoId, $d['data'], $d['tipo'], $d['operacao'], $d['ativo_codigo'], $d['ativo_nome'],
            $d['corretora'], $d['classe'], $d['quantidade'], $d['preco_unitario'], $d['valor_total'], $d['conta_id']);
        $stmtM->execute();
    }
    $stmtM->close();

    return array('total_linhas' => $totalLinhas, 'importadas' => $importadas, 'linhas' => $validas,
        'credito_total' => $creditoTotal, 'debito_total' => $debitoTotal, 'importacao_id' => $importacaoId);
}

function fin_importacoes_lista($limite = 20)
{
    return db_all("SELECT * FROM financeiro_importacoes ORDER BY id DESC LIMIT " . (int)$limite);
}

function fin_movimentos_importados($limite = 200)
{
    return db_all("SELECT m.*, c.nome AS conta_nome, i.arquivo
        FROM financeiro_ativos_movimentos m
        LEFT JOIN financeiro_contas c ON c.id = m.conta_id
        LEFT JOIN financeiro_importacoes i ON i.id = m.importacao_id
        ORDER BY m.data DESC, m.id DESC LIMIT " . (int)$limite);
}

// Carteira: posicao por ativo
function fin_carteira_resumo()
{
    return db_all("SELECT ativo_codigo, MAX(ativo_nome) AS ativo_nome, MAX(classe) AS classe,
            SUM(CASE WHEN tipo='credito' THEN quantidade ELSE -quantidade END) AS posicao,
            SUM(CASE WHEN tipo='credito' THEN valor_total ELSE -valor_total END) AS financeiro,
            COUNT(*) AS movimentos
        FROM financeiro_ativos_movimentos
        GROUP BY ativo_codigo
        HAVING movimentos > 0
        ORDER BY financeiro DESC");
}

// Desfaz uma importacao (apaga o lote e seus lancamentos; nao altera o saldo informado)
function fin_remover_importacao($id)
{
    $id = (int)$id;
    db_query("DELETE FROM financeiro_importacoes WHERE id = $id");
}
