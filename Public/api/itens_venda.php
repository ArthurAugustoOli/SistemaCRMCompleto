<?php
require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/models/ItemVenda.php';

header('Content-Type: application/json; charset=utf-8');

if (! isset($_GET['getByVenda'])) {
    echo json_encode(['error'=>'missing parameter']);
    exit;
}

$id_venda = (int) $_GET['getByVenda'];
$model    = new ItemVenda($mysqli);
$itens    = $model->getByVenda($id_venda);

echo json_encode($itens);
