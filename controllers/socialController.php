<?php
// socialController.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

$acao = isset($_POST['acao']) ? $_POST['acao'] : (isset($_GET['acao']) ? $_GET['acao'] : '');
$voltar = '/ranksistem/pages/social.php';

if ($acao === 'add_registro' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data'];
    $tipo = $_POST['tipo'];
    $descricao = trim($_POST['descricao']);
    $duracao = (int)$_POST['duracao_min'];
    $qualidade = ($_POST['qualidade'] === '' ? null : (int)$_POST['qualidade']);

    $stmt = db()->prepare("INSERT INTO social_registros (data, tipo, descricao, duracao_min, qualidade) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('sssii', $data, $tipo, $descricao, $duracao, $qualidade);
    $stmt->execute();
    $stmt->close();
    flash_set('ok', 'Momento social registrado.');
    header('Location: ' . $voltar);
    exit;
}

if ($acao === 'add_checkin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $semana = $_POST['semana_inicio'];
    $recarga = (int)$_POST['recarga_mental'];
    $obs = trim($_POST['observacoes']);

    $stmt = db()->prepare("INSERT INTO social_checkins (semana_inicio, recarga_mental, observacoes)
        VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE recarga_mental = VALUES(recarga_mental), observacoes = VALUES(observacoes)");
    $stmt->bind_param('sis', $semana, $recarga, $obs);
    $stmt->execute();
    $stmt->close();
    flash_set('ok', 'Recarga mental da semana registrada.');
    header('Location: ' . $voltar);
    exit;
}

if ($acao === 'del_registro' && isset($_GET['id'])) {
    db_query("DELETE FROM social_registros WHERE id = " . (int)$_GET['id']);
    flash_set('ok', 'Registro removido.');
    header('Location: ' . $voltar);
    exit;
}

header('Location: ' . $voltar);
exit;
