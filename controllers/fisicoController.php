<?php
// fisicoController.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

$acao = isset($_POST['acao']) ? $_POST['acao'] : (isset($_GET['acao']) ? $_GET['acao'] : '');
$voltar = '/ranksistem/pages/fisico.php';

function fis_redir($url)
{
    header('Location: ' . $url);
    exit;
}

if ($acao === 'add_treino' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data'];
    $energia = ($_POST['energia_pre'] === '' ? null : (int)$_POST['energia_pre']);
    $sono = ($_POST['sono_horas'] === '' ? null : (float)$_POST['sono_horas']);
    $dor = ($_POST['dor_muscular'] === '' ? null : (int)$_POST['dor_muscular']);
    $obs = trim($_POST['observacoes']);

    $existente = db_one("SELECT id FROM treinos WHERE data = '" . db_escape($data) . "'");
    if ($existente) {
        db_query("DELETE FROM treino_series WHERE treino_id = " . (int)$existente['id']);
        db_query("DELETE FROM treinos WHERE id = " . (int)$existente['id']);
    }

    $stmt = db()->prepare("INSERT INTO treinos (data, energia_pre, sono_horas, dor_muscular, observacoes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sidis', $data, $energia, $sono, $dor, $obs);
    $stmt->execute();
    $treino_id = db_insert_id();
    $stmt->close();

    $ex = isset($_POST['exercicio_id']) ? $_POST['exercicio_id'] : array();
    $niveis = isset($_POST['nivel']) ? $_POST['nivel'] : array();
    $series = isset($_POST['series']) ? $_POST['series'] : array();
    $reps = isset($_POST['reps']) ? $_POST['reps'] : array();

    $stmtS = db()->prepare("INSERT INTO treino_series (treino_id, exercicio_id, nivel_usado, series, reps) VALUES (?, ?, ?, ?, ?)");
    foreach ($ex as $i => $exId) {
        if (empty($exId)) { continue; }
        $n = isset($niveis[$i]) ? (int)$niveis[$i] : 1;
        $s = isset($series[$i]) ? (int)$series[$i] : 0;
        $r = isset($reps[$i]) ? (int)$reps[$i] : 0;
        if ($s <= 0 || $r <= 0) { continue; }
        $stmtS->bind_param('iiiii', $treino_id, $exId, $n, $s, $r);
        $stmtS->execute();
    }
    $stmtS->close();

    flash_set('ok', 'Treino registrado.');
    fis_redir($voltar);
}

if ($acao === 'del_treino' && isset($_GET['id'])) {
    db_query("DELETE FROM treinos WHERE id = " . (int)$_GET['id']);
    flash_set('ok', 'Treino removido.');
    fis_redir($voltar);
}

fis_redir($voltar);
