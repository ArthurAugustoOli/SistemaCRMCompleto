<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/models/Etiqueta.php';
header('Content-Type: application/json; charset=utf-8');

$model = new \App\Models\Etiqueta($mysqli);

if (!isset($_GET['id_produto'])) {
    echo json_encode([]);
    exit;
}

$id = (int) $_GET['id_produto'];
echo json_encode($model->getVariacoesByProduto($id));
