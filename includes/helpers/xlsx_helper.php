<?php
// xlsx_helper.php - leitor de planilhas .xlsx usando ZipArchive + DOM (sem dependencias externas)

define('XLSX_NS', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

function xlsx_col_para_indice($letras)
{
    $letras = strtoupper($letras);
    $num = 0;
    for ($i = 0; $i < strlen($letras); $i++) {
        $num = $num * 26 + (ord($letras[$i]) - 64);
    }
    return $num - 1;
}

// Converte serial de data do Excel para Y-m-d
function xlsx_serial_para_data($serial)
{
    $dias = (int)$serial;
    if ($dias <= 0) { return null; }
    $ts = ($dias - 25569) * 86400;
    return gmdate('Y-m-d', $ts);
}

function xlsx_carregar_xml($conteudo)
{
    if ($conteudo === false || $conteudo === '') { return null; }
    $doc = new DOMDocument();
    $uso = libxml_use_internal_errors(true);
    $ok = $doc->loadXML($conteudo);
    libxml_clear_errors();
    libxml_use_internal_errors($uso);
    return $ok ? $doc : null;
}

// Le um arquivo .xlsx e retorna array de linhas (cada linha = array sequencial de strings)
function xlsx_ler_linhas($caminho)
{
    if (!class_exists('ZipArchive')) {
        return null;
    }
    $zip = new ZipArchive();
    if ($zip->open($caminho) !== true) {
        return null;
    }

    // ---------- Strings compartilhadas ----------
    $shared = array();
    $ssDoc = xlsx_carregar_xml($zip->getFromName('xl/sharedStrings.xml'));
    if ($ssDoc) {
        $xp = new DOMXPath($ssDoc);
        $xp->registerNamespace('d', XLSX_NS);
        foreach ($xp->query('//d:si') as $si) {
            $texto = '';
            foreach ($xp->query('.//d:t', $si) as $t) {
                $texto .= $t->textContent;
            }
            $shared[] = $texto;
        }
    }

    // ---------- Primeira worksheet ----------
    $sheetNome = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nome = $zip->getNameIndex($i);
        if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $nome)) {
            $sheetNome = $nome;
            break;
        }
    }
    if ($sheetNome === null) {
        $zip->close();
        return null;
    }

    $sheetDoc = xlsx_carregar_xml($zip->getFromName($sheetNome));
    $zip->close();
    if (!$sheetDoc) {
        return null;
    }

    $xp = new DOMXPath($sheetDoc);
    $xp->registerNamespace('d', XLSX_NS);

    $linhas = array();
    foreach ($xp->query('//d:sheetData/d:row') as $row) {
        $celulas = array();
        foreach ($xp->query('d:c', $row) as $c) {
            $ref = $c->getAttribute('r');
            $letras = preg_replace('/[0-9]+/', '', $ref);
            $col = $letras !== '' ? xlsx_col_para_indice($letras) : count($celulas);
            $tipo = $c->getAttribute('t');
            $valor = '';

            if ($tipo === 's') {
                $vn = $xp->query('d:v', $c);
                $idx = $vn->length > 0 ? (int)$vn->item(0)->textContent : -1;
                $valor = isset($shared[$idx]) ? $shared[$idx] : '';
            } elseif ($tipo === 'inlineStr') {
                foreach ($xp->query('.//d:t', $c) as $t) {
                    $valor .= $t->textContent;
                }
            } else {
                $vn = $xp->query('d:v', $c);
                $valor = $vn->length > 0 ? $vn->item(0)->textContent : '';
            }
            $celulas[$col] = $valor;
        }
        if (empty($celulas)) {
            continue;
        }
        $max = max(array_keys($celulas));
        $linha = array();
        for ($i = 0; $i <= $max; $i++) {
            $linha[] = isset($celulas[$i]) ? $celulas[$i] : '';
        }
        $linhas[] = $linha;
    }

    return $linhas;
}

// Le arquivo texto delimitado (CSV/TXT/TSV), detectando o delimitador
function texto_ler_linhas($caminho)
{
    $conteudo = file_get_contents($caminho);
    if ($conteudo === false) {
        return null;
    }
    $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);

    $linhasCruas = preg_split('/\r\n|\r|\n/', $conteudo);
    $delim = "\t";
    foreach ($linhasCruas as $lc) {
        if (trim($lc) === '') { continue; }
        $opcoes = array("\t" => substr_count($lc, "\t"), ';' => substr_count($lc, ';'), ',' => substr_count($lc, ','));
        arsort($opcoes);
        $delim = key($opcoes);
        if ($opcoes[$delim] === 0) { $delim = ';'; }
        break;
    }

    $linhas = array();
    foreach ($linhasCruas as $lc) {
        if (trim($lc) === '') { continue; }
        $linhas[] = array_map('trim', explode($delim, $lc));
    }
    return $linhas;
}

// Le qualquer formato suportado
function planilha_ler_linhas($caminho, $nome_arquivo)
{
    $ext = strtolower(pathinfo($nome_arquivo, PATHINFO_EXTENSION));
    if ($ext === 'xlsx' || $ext === 'xlsm') {
        $linhas = xlsx_ler_linhas($caminho);
        if ($linhas !== null) {
            return $linhas;
        }
    }
    return texto_ler_linhas($caminho);
}
