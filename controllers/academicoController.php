<?php
// academicoController.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

$acao = isset($_POST['acao']) ? $_POST['acao'] : (isset($_GET['acao']) ? $_GET['acao'] : '');
$voltar = '/ranksistem/pages/academico.php';

if ($acao === 'add_modulo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $materia_id = (int)$_POST['materia_id'];
    $titulo = trim($_POST['titulo']);
    $ordem = isset($_POST['ordem']) ? (int)$_POST['ordem'] : 1;
    $data_inicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
    $data_fim = !empty($_POST['data_fim_prevista']) ? $_POST['data_fim_prevista'] : null;
    $horas_meta = isset($_POST['horas_meta']) ? (float)$_POST['horas_meta'] : 20;

    $stmt = db()->prepare("INSERT INTO modulos (materia_id, titulo, ordem, data_inicio, data_fim_prevista, horas_meta) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isissd', $materia_id, $titulo, $ordem, $data_inicio, $data_fim, $horas_meta);
    $stmt->execute();
    $stmt->close();
    flash_set('ok', 'Modulo criado.');
    header('Location: ' . $voltar);
    exit;
}

if ($acao === 'add_estudo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $modulo_id = (int)$_POST['modulo_id'];
    $moduloVazio = $modulo_id > 0 ? $modulo_id : null;
    $data = $_POST['data'];
    $minutos = (int)$_POST['minutos'];
    $resumo = trim($_POST['resumo']);
    $retencao = ($_POST['retencao'] === '' ? null : (int)$_POST['retencao']);

    $stmt = db()->prepare("INSERT INTO estudos (modulo_id, data, minutos, resumo, retencao) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('isisi', $moduloVazio, $data, $minutos, $resumo, $retencao);
    $stmt->execute();
    $stmt->close();
    flash_set('ok', 'Sessao de estudo registrada.');
    header('Location: ' . $voltar);
    exit;
}

if ($acao === 'toggle_modulo' && isset($_GET['id'])) {
    db_query("UPDATE modulos SET concluido = 1 - concluido WHERE id = " . (int)$_GET['id']);
    flash_set('ok', 'Status do modulo atualizado.');
    header('Location: ' . $voltar);
    exit;
}

if ($acao === 'del_modulo' && isset($_GET['id'])) {
    db_query("DELETE FROM modulos WHERE id = " . (int)$_GET['id']);
    flash_set('ok', 'Modulo removido.');
    header('Location: ' . $voltar);
    exit;
}

header('Location: ' . $voltar);
exit;
