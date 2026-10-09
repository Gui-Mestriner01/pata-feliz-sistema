<?php
/** Raiz da instalação: manda para a página inicial pública. */
require_once __DIR__ . '/config/config.php';
header('Location: ' . BASE_URL . '/publico/index.php');
