<?php
// Public/includes/notificacoes/criar.php

// 1) Verifica sessão
require_once __DIR__ . '/../../login/verificar_sessao.php';

// 2) Conexão com o banco
require_once __DIR__ . '/../../../app/config/config.php'; // deve criar $mysqli

// 3) Modelo
require_once __DIR__ . '/../../../app/models/Notificacao.php';

header('Content-Type: application/json; charset=utf-8');

// 4) Dados do usuário
$criadorId = $_SESSION['id_usuario'] ?? null;
if (! $criadorId) {
    echo json_encode(['success' => false, 'message' => 'Sessão inválida']);
    exit;
}

// 5) Recebe e valida input
$titulo    = trim($_POST['titulo']    ?? '');
$mensagem  = trim($_POST['mensagem']  ?? '');
$tipo      = $_POST['tipo']           ?? 'info';
$expiraEm  = $_POST['expira_em']      ?? null;
$paraTodos = isset($_POST['para_todos']) && $_POST['para_todos'] === '1' ? 1 : 0;

$erros = [];
if ($titulo === '') {
    $erros[] = 'Título é obrigatório.';
}
if ($mensagem === '') {
    $erros[] = 'Mensagem é obrigatória.';
}
$tiposValidos = ['info','success','warning','danger'];
if (! in_array($tipo, $tiposValidos, true)) {
    $erros[] = 'Tipo inválido.';
}
if ($expiraEm) {
    $dt = date_create($expiraEm);
    if (! $dt) {
        $erros[] = 'Data de expiração inválida.';
    } else {
        $expiraEm = date_format($dt, 'Y-m-d H:i:s');
    }
}

if ($erros) {
    echo json_encode([
        'success' => false,
        'message' => implode(' ', $erros)
    ]);
    exit;
}

// 6) Tenta criar
try {
    $model = new Notificacao($mysqli);
    $novoId = $model->criar($titulo, $mensagem, $tipo, (int)$criadorId, $expiraEm, $paraTodos);

    echo json_encode([
        'success' => true,
        'id'      => $novoId
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao salvar: ' . $e->getMessage()
    ]);
}
