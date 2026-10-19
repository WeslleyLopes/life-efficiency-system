<?php
// checkinController.php - check-in diario (energia, humor, estresse, sono)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /ranksistem/index.php');
    exit;
}

$data = isset($_POST['data']) ? $_POST['data'] : date('Y-m-d');
$energia = isset($_POST['energia']) ? (int)$_POST['energia'] : 5;
$humor = isset($_POST['humor']) ? (int)$_POST['humor'] : 5;
$estresse = isset($_POST['estresse']) ? (int)$_POST['estresse'] : 5;
$sono = isset($_POST['sono_horas']) ? (float)$_POST['sono_horas'] : 7.0;
$notas = isset($_POST['notas']) ? trim($_POST['notas']) : null;

$stmt = db()->prepare("INSERT INTO checkin_diario (data, energia, humor, estresse, sono_horas, notas)
    VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE energia = VALUES(energia), humor = VALUES(humor),
    estresse = VALUES(estresse), sono_horas = VALUES(sono_horas), notas = VALUES(notas)");
$stmt->bind_param('siiids', $data, $energia, $humor, $estresse, $sono, $notas);
$stmt->execute();
$stmt->close();

salvar_pilar_scores();
flash_set('ok', 'Check-in registrado.');
header('Location: /ranksistem/index.php');
exit;
