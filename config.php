<?php
// config.php - Rank Sistem
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', 'Rank Sistem');
define('APP_ROOT', __DIR__);

require_once APP_ROOT . '/includes/conexao.php';
require_once APP_ROOT . '/includes/components/components.php';
require_once APP_ROOT . '/includes/helpers/financeiro_helper.php';
require_once APP_ROOT . '/includes/helpers/importacao_helper.php';
require_once APP_ROOT . '/includes/helpers/academico_helper.php';
require_once APP_ROOT . '/includes/helpers/fisico_helper.php';
require_once APP_ROOT . '/includes/helpers/social_helper.php';
require_once APP_ROOT . '/includes/helpers/scoring_helper.php';
require_once APP_ROOT . '/includes/helpers/correlacao_helper.php';
