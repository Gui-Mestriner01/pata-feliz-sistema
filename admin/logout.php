<?php
require_once __DIR__ . '/../includes/funcoes.php';

sessao_iniciar();
$_SESSION = [];
session_destroy();

redirecionar(BASE_URL . '/admin/login.php');
