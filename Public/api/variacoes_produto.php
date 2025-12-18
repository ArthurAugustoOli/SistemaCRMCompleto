<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/models/ProdutoVariacao.php';

header('Content-Type: application/json; charset=utf-8');

if (! isset($_GET['id_produto'])) {
    echo json_encode([]);
    exit;
}

$model = new ProdutoVariacao($mysqli);
$vars  = $model->getAllByProduto((int)$_GET['id_produto']);
echo json_encode($vars);
