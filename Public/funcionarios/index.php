<?php
// =================================================
// 1. INCLUSÕES, CONFIGURAÇÕES E INSTÂNCIAS
// =================================================
require_once '../../app/config/config.php';
require_once '../../app/models/Venda.php';
require_once '../../app/models/ItemVenda.php';
require_once '../../app/models/Produto.php';
require_once '../../app/models/ProdutoVariacao.php';
require_once '../../app/models/VendaProduto.php';
require_once '../../app/models/Cliente.php';
require_once '../../app/models/Funcionario.php';

use App\Models\Funcionario;

session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: /PROJETOERP/Public/login/index.php");
    exit;
}

$msg = "";
$msg_type = "";
$funcionarioModel = new Funcionario($mysqli);

// Função para salvar a foto do funcionário
function saveEmployeePhoto($id) {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $targetDirRelative = '/../assets/images/';
        $uploadDir = realpath(__DIR__ . $targetDirRelative);
        if (!$uploadDir) {
            $dirToCreate = __DIR__ . $targetDirRelative;
            if (!mkdir($dirToCreate, 0755, true)) {
                error_log("Erro: Não foi possível criar a pasta de upload: " . $dirToCreate);
            }
            $uploadDir = realpath($dirToCreate);
        }
        if (substr($uploadDir, -1) !== DIRECTORY_SEPARATOR) {
            $uploadDir .= DIRECTORY_SEPARATOR;
        }
        $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $fileName = "funcionario_{$id}." . $extension;
        $targetPath = $uploadDir . $fileName;
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $targetPath)) {
            error_log("Erro ao mover o arquivo para: " . $targetPath);
        } else {
            return '../assets/images/' . $fileName;
        }
    } else {
        if (isset($_FILES['foto'])) {
            error_log("Erro no upload: Código " . $_FILES['foto']['error']);
        }
    }
    return "";
}

// Função para obter a foto do funcionário
function getEmployeePhoto($id) {
    $dir = __DIR__ . '/../assets/images/';
    $pattern = $dir . "funcionario_{$id}.*";
    $files = glob($pattern);
    if (!empty($files)) {
        $file = basename($files[0]);
        return '../assets/images/' . $file;
    }
    return "";
}

// ==================================================
// ENDPOINTS AJAX
// ==================================================

// Endpoint para buscar funcionários por nome
if (isset($_GET['action']) && $_GET['action'] === 'searchEmployee') {
  $query = $_GET['query'] ?? '';

  if (trim($query) === '') {
      // Se a busca estiver vazia, retorna todos
      $employees = $funcionarioModel->getAll();
  } else {
      // Usa o método searchByName() do Model
      $employees = $funcionarioModel->searchByName($query);
  }

  header('Content-Type: application/json');
  echo json_encode(['employees' => $employees]);
  exit;
}

// Endpoint para obter comissão (do mês atual) e histórico de comissões  
if (isset($_GET['action']) && $_GET['action'] === 'getCommission' && isset($_GET['id_funcionario'])) {
    $id_funcionario = intval($_GET['id_funcionario']);
    // Atualiza a comissão com base no total vendido no mês vigente (regente)
    $currentCommission = $funcionarioModel->atualizarComissao($id_funcionario);
    $history           = $funcionarioModel->getHistoricoComissoes($id_funcionario);

    header('Content-Type: application/json');
    echo json_encode([
        'current' => $currentCommission,
        'history' => $history
    ]);
    exit;
}

// Endpoint para obter as vendas do funcionário (para gráficos)
if (isset($_GET['action']) && $_GET['action'] === 'getSales' && isset($_GET['id_funcionario'])) {
    $id_funcionario = intval($_GET['id_funcionario']);
    $sales = [];
    $sql = "SELECT * FROM vendas WHERE id_funcionario = $id_funcionario ORDER BY data DESC";
    $res = $mysqli->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $sales[] = $row;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['sales' => $sales]);
    exit;
}

// ==================================================
// PROCESSAMENTO DOS FORMULÁRIOS POST (CREATE/UPDATE/DELETE)
// ==================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Criação de funcionário
        if (isset($_POST['action']) && $_POST['action'] === 'create') {
            $dados = [
                'nome'           => $_POST['nome'],
                'cpf_cnpj'       => $_POST['cpf_cnpj'],
                'telefone'       => $_POST['telefone'],
                'email'          => $_POST['email'],
                'cep'            => $_POST['cep'],
                'logradouro'     => $_POST['logradouro'],
                'numero'         => $_POST['numero'],
                'complemento'    => $_POST['complemento'],
                'bairro'         => $_POST['bairro'],
                'cidade'         => $_POST['cidade'],
                'estado'         => $_POST['estado'],
                'data_admissao'  => $_POST['data_admissao'],
                'comissao_atual' => $_POST['comissao_atual'], // Geralmente 0.00 na criação
                'senha'          => $_POST['senha']
            ];
            $newId = $funcionarioModel->create($dados);
            saveEmployeePhoto($newId);
            $msg = "Funcionário criado com sucesso!";
            $msg_type = "success";
        }

        // Atualização de funcionário
        if (isset($_POST['action']) && $_POST['action'] === 'update') {
            $id = intval($_POST['id_funcionario']);
            $funcAtual = $funcionarioModel->getById($id);
            $senha = !empty($_POST['senha']) ? $_POST['senha'] : $funcAtual['senha'];
            $dados = [
                'nome'           => $_POST['nome'],
                'cpf_cnpj'       => $_POST['cpf_cnpj'],
                'telefone'       => $_POST['telefone'],
                'email'          => $_POST['email'],
                'cep'            => $_POST['cep'],
                'logradouro'     => $_POST['logradouro'],
                'numero'         => $_POST['numero'],
                'complemento'    => $_POST['complemento'],
                'bairro'         => $_POST['bairro'],
                'cidade'         => $_POST['cidade'],
                'estado'         => $_POST['estado'],
                'data_admissao'  => $_POST['data_admissao'],
                'comissao_atual' => $_POST['comissao_atual'],
                'senha'          => $senha
            ];
            if ($funcionarioModel->update($id, $dados)) {
                saveEmployeePhoto($id);
                $msg = "Funcionário atualizado com sucesso!";
                $msg_type = "success";
            } else {
                $msg = "Erro ao atualizar funcionário.";
                $msg_type = "danger";
            }
        }

        // Exclusão de funcionário
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $id = intval($_POST['id_funcionario']);
            if ($funcionarioModel->delete($id)) {
                $photo = glob(__DIR__ . "/../assets/images/funcionario_{$id}.*");
                if (!empty($photo)) {
                    unlink($photo[0]);
                }
                $msg = "Funcionário excluído com sucesso!";
                $msg_type = "success";
            } else {
                $msg = "Não é possível excluir este funcionário, pois há vendas associadas.";
                $msg_type = "warning";
            }
        }
    } catch (Exception $e) {
        $msg = "Erro: " . $e->getMessage();
        $msg_type = "danger";
    }
}

try {
    $funcionarios = $funcionarioModel->getAll();
} catch (Exception $e) {
    $funcionarios = [];
    $msg = "Erro ao carregar funcionários: " . $e->getMessage();
    $msg_type = "danger";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Funcionários | Sistema de Gestão</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons & Font Awesome -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/inputmask/5.0.8/inputmask.min.js" integrity="sha512-9vR8A1YX0kY5UGeK95Iv+ZVOWXpVrsBfCPXJXq2/VvL8q8W2acKgCcF6MbeTxhrw5AcqZa16+f/HkYs9L2S/6g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <style>
/* CSS aprimorado para o sistema de gerenciamento de funcionários */
:root {
  --primary-color: #4e54e9;
  --sidebar-bg: #4e54e9;
  --sidebar-width: 240px;
  --card-border-radius: 12px;
  --header-height: 120px; /* Aumentado para acomodar o header com duas linhas */
  --mobile-nav-height: 60px;
  --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  --card-shadow-hover: 0 8px 20px rgba(0, 0, 0, 0.12);
  --transition-speed: 0.3s;
}

body {
  font-family: 'Poppins', sans-serif;
  background-color: #f8f9fa;
  margin: 0;
  padding: 0;
  overflow-x: hidden;
}

/* ===== LAYOUT PRINCIPAL CORRIGIDO ===== */
.main-wrapper {
  display: flex;
  min-height: 100vh;
}

.content-wrapper {
  flex: 1;
  margin-left: var(--sidebar-width);
  padding-top: var(--header-height);
  transition: all var(--transition-speed);
}

.main-content {
  padding: 20px;
  min-height: calc(100vh - var(--header-height));
  background-color: #f8f9fa;
}

/* ===== MELHORIAS NO HEADER ===== */
.top-header {
  position: fixed;
  top: 0;
  right: 0;
  left: var(--sidebar-width);
  height: var(--header-height);
  z-index: 999;
  background-color: #ffffff;
  box-shadow: 0 2px 5px rgba(0,0,0,0.05);
  transition: all var(--transition-speed);
  padding: 0 1.5rem;
}

.search-container {
  width: 300px;
}

.search-container input {
  height: 40px;
  background-color: #f3f4f6 !important;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.search-container input:focus {
  box-shadow: none;
  border-color: var(--primary-color);
}

.breadcrumb-nav {
  border-top: 1px solid rgba(0,0,0,0.05);
}

.breadcrumb-item + .breadcrumb-item::before {
  color: #9ca3af;
}

.breadcrumb-item.active {
  color: #6b7280;
}

/* ===== MELHORIAS NOS CARDS ===== */
.card {
  border-radius: var(--card-border-radius);
  border: none;
  box-shadow: var(--card-shadow);
  overflow: hidden;
  transition: all var(--transition-speed);
  margin-bottom: 1.5rem;
}

.card-employee {
  border-radius: var(--card-border-radius);
  border: none;
  box-shadow: var(--card-shadow);
  transition: transform var(--transition-speed), box-shadow var(--transition-speed);
  cursor: pointer;
  overflow: hidden;
  height: 100%;
  display: flex;
  flex-direction: column;
  background-color: #fff;
}

.card-employee:hover {
  transform: translateY(-5px);
  box-shadow: var(--card-shadow-hover);
}

/* MELHORIAS NAS IMAGENS DOS CARDS */
.card-employee .card-img-top {
  width: 100%;
  height: 220px;
  object-fit: cover;
  background-color: #f8f9fa;
  transition: all var(--transition-speed);
  border-bottom: 1px solid rgba(0,0,0,0.05);
}

.card-employee:hover .card-img-top {
  transform: scale(1.03);
}

.card-employee .card-body {
  padding: 1.25rem;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.card-employee .card-title {
  font-weight: 600;
  margin-bottom: 0.5rem;
  font-size: 1.1rem;
}

.card-employee .card-text {
  color: #6c757d;
  margin-bottom: 1rem;
  font-size: 0.95rem;
}

.card-employee .card-body .d-flex {
  margin-top: auto;
  justify-content: flex-end;
}

.card-employee .btn {
  padding: 0.4rem 0.75rem;
  border-radius: 6px;
  font-size: 0.85rem;
  transition: all 0.2s;
}

.card-employee .btn:hover {
  transform: translateY(-2px);
}

.card-employee .btn i {
  font-size: 0.9rem;
}

/* Melhoria no placeholder de foto */
.employee-photo-placeholder {
  width: 100%;
  height: 220px;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #f1f3f5;
  color: #adb5bd;
  transition: all var(--transition-speed);
  border-bottom: 1px solid rgba(0,0,0,0.05);
}

.employee-photo-placeholder i {
  font-size: 3.5rem;
  opacity: 0.7;
}

/* ===== MELHORIAS NOS BOTÕES ===== */
.btn {
  border-radius: 8px;
  padding: 0.5rem 1rem;
  font-weight: 500;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.btn-primary {
  background-color: var(--primary-color);
  border-color: var(--primary-color);
}

.btn-primary:hover {
  background-color: #3a40c0;
  border-color: #3a40c0;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(78, 84, 233, 0.2);
}

.btn i {
  font-size: 0.9rem;
}

/* ===== MELHORIAS NOS MODAIS ===== */
.modal-header {
  border-bottom: none;
  padding: 1.5rem 1.5rem 0.75rem;
}

.modal-footer {
  border-top: none;
  padding: 0.75rem 1.5rem 1.5rem;
}

.modal-content {
  border-radius: var(--card-border-radius);
  border: none;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  overflow: hidden;
}

.modal-title {
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.modal-title i {
  color: var(--primary-color);
}

/* ===== MELHORIAS NOS FORMULÁRIOS ===== */
.form-label {
  font-weight: 500;
  margin-bottom: 0.5rem;
  font-size: 0.9rem;
  color: #555;
}

.form-control {
  border-radius: 8px;
  padding: 0.65rem 1rem;
  border: 1px solid #ced4da;
  transition: all 0.2s;
  font-size: 0.95rem;
}

.form-control:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 0.25rem rgba(78, 84, 233, 0.15);
}

/* ===== MELHORIAS NAS ABAS ===== */
.nav-tabs {
  border-bottom: 1px solid #dee2e6;
  margin-bottom: 1rem;
}

.nav-tabs .nav-link {
  border: none;
  color: #6c757d;
  padding: 0.75rem 1rem;
  font-weight: 500;
  transition: all 0.2s;
  position: relative;
}

.nav-tabs .nav-link:hover {
  color: var(--primary-color);
}

.nav-tabs .nav-link.active {
  color: var(--primary-color);
  background-color: transparent;
  border-bottom: 2px solid var(--primary-color);
}

/* ===== MELHORIAS NOS ALERTAS ===== */
.alert {
  border-radius: var(--card-border-radius);
  border: none;
  padding: 1rem 1.25rem;
  margin-bottom: 1.5rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

/* ===== RESPONSIVIDADE ===== */
@media (max-width: 1200px) {
  .card-employee .card-img-top,
  .employee-photo-placeholder {
    height: 200px;
  }
}

@media (max-width: 992px) {
  .content-wrapper {
    margin-left: 70px; /* Sidebar colapsada */
  }
  
  .top-header {
    left: 70px;
  }
  
  .card-employee .card-img-top,
  .employee-photo-placeholder {
    height: 180px;
  }
}

@media (max-width: 768px) {
  .content-wrapper {
    margin-left: 0;
    padding-bottom: var(--mobile-nav-height);
  }
  
  .top-header {
    left: 0;
    padding: 0 1rem;
  }
  
  .search-container {
    width: 200px;
  }
  
  .breadcrumb-nav .btn {
    font-size: 0.85rem;
    padding: 0.375rem 0.75rem;
  }
  
  .breadcrumb-nav .btn i {
    margin-right: 0.25rem !important;
  }
  
  .card-employee .card-img-top,
  .employee-photo-placeholder {
    height: 200px;
  }
  
  .card-employee .card-body {
    padding: 1rem;
  }
}

@media (max-width: 576px) {
  .search-container {
    width: 150px;
  }
  
  .breadcrumb-nav .btn span {
    display: none;
  }
  
  .breadcrumb-nav .btn i {
    margin-right: 0 !important;
  }
  
  .card-employee .card-img-top,
  .employee-photo-placeholder {
    height: 180px;
  }
}

/* ===== ANIMAÇÕES ===== */
.animate-fade-in {
  opacity: 0;
  transform: translateY(15px);
  animation: fadeIn 0.5s ease forwards;
}

@keyframes fadeIn {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Dark Mode */
body.dark-mode {
    background-color: #121212;
    color: #f1f1f1;
}

body.dark-mode .top-header,
body.dark-mode .card-body,
body.dark-mode .main-content {
    background-color: #1e1e1e;
    color: #f1f1f1;
    border-color: #333;
}

@media (max-width: 768px) {
  .mobile-bottom-nav,
  .bottom-nav {
    display: flex;
  }
}

.mobile-nav-item,
.bottom-nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #6c757d;
  text-decoration: none;
  font-size: 10px;
  padding: 8px 0;
  flex: 1;
  transition: all 0.2s;
  position: relative;
}

.mobile-nav-item i,
.bottom-nav-item i {
  font-size: 20px;
  margin-bottom: 4px;
  transition: all 0.2s;
}

.mobile-nav-item:hover,
.mobile-nav-item:active,
.mobile-nav-item.active,
.bottom-nav-item:hover,
.bottom-nav-item:active,
.bottom-nav-item.active {
  color: var(--primary-color);
}

.mobile-nav-item:hover i,
.mobile-nav-item:active i,
.mobile-nav-item.active i,
.bottom-nav-item:hover i,
.bottom-nav-item:active i,
.bottom-nav-item.active i {
  transform: translateY(-2px);
}

.mobile-nav-item::after,
.bottom-nav-item::after {
  content: "";
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 3px;
  background-color: var(--primary-color);
  transition: width 0.2s;
  border-radius: 3px 3px 0 0;
}

.mobile-nav-item:hover::after,
.mobile-nav-item:active::after,
.mobile-nav-item.active::after,
.bottom-nav-item:hover::after,
.bottom-nav-item:active::after,
.bottom-nav-item.active::after {
  width: 40%;
}

body.dark-mode .bottom-nav {
    background-color: #1e1e1e;
    border-top: 1px solid #333;
}

body.dark-mode .bottom-nav-item {
  color: #adb5bd;
}

body.dark-mode .bottom-nav-item.active {
  color: var(--primary-color);
}

/* ===== CORREÇÕES PARA MODAIS EM MOBILE ===== */
@media (max-width: 768px) {
  /* Garantir que os modais apareçam acima de tudo em mobile */
  .modal {
    z-index: 1060 !important;
  }
  
  .modal-backdrop {
    z-index: 1055 !important;
  }
  
  /* Ajustar o posicionamento dos modais em mobile */
  .modal-dialog {
    margin: 10px;
    max-height: calc(100vh - 20px);
    display: flex;
    align-items: center;
  }
  
  .modal-content {
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    border-radius: 12px;
  }
  
  /* Ajustar o modal de criação/edição para mobile */
  .modal-lg {
    max-width: calc(100vw - 20px);
  }
  
  /* Melhorar a visualização do modal body em mobile */
  .modal-body {
    padding: 1rem;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
  }
  
  /* Ajustar formulários dentro dos modais em mobile */
  .modal-body .row {
    margin-left: 0;
    margin-right: 0;
  }
  
  .modal-body .col-md-6 {
    padding-left: 0;
    padding-right: 0;
    margin-bottom: 1rem;
  }
  
  /* Garantir que os campos de formulário sejam legíveis em mobile */
  .modal-body .form-control {
    font-size: 16px; /* Previne zoom automático no iOS */
  }
  
  /* Ajustar botões do modal footer em mobile */
  .modal-footer {
    padding: 0.75rem 1rem;
    flex-direction: column-reverse;
    gap: 0.5rem;
  }
  
  .modal-footer .btn {
    width: 100%;
    margin: 0;
  }
  
  /* Ajustar modal de informações (3 abas) para mobile */
  .modal-xl {
    max-width: calc(100vw - 20px);
  }
  
  /* Melhorar as abas em mobile */
  .nav-tabs {
    flex-wrap: nowrap;
    overflow-x: auto;
    border-bottom: 1px solid #dee2e6;
  }
  
  .nav-tabs .nav-link {
    white-space: nowrap;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
  }
  
  /* Ajustar gráficos em mobile */
  .tab-content .row .col-md-6 {
    margin-bottom: 1rem;
  }
  
  /* Garantir que o modal não seja afetado pela navegação mobile */
  body.modal-open {
    padding-bottom: 0 !important;
  }
}

/* Correções específicas para telas muito pequenas */
@media (max-width: 576px) {
  .modal-dialog {
    margin: 5px;
    max-height: calc(100vh - 10px);
  }
  
  .modal-content {
    max-height: calc(100vh - 20px);
  }
  
  .modal-body {
    padding: 0.75rem;
    max-height: calc(100vh - 160px);
  }
  
  .modal-header {
    padding: 1rem 0.75rem 0.5rem;
  }
  
  .modal-footer {
    padding: 0.5rem 0.75rem;
  }
  
  /* Ajustar título do modal em telas pequenas */
  .modal-title {
    font-size: 1.1rem;
  }
  
  /* Melhorar campos de formulário em telas pequenas */
  .modal-body .form-label {
    font-size: 0.9rem;
    margin-bottom: 0.25rem;
  }
  
  .modal-body .form-control {
    padding: 0.5rem 0.75rem;
  }
  
  /* Ajustar seções do formulário */
  .modal-body h6 {
    font-size: 1rem;
    margin-bottom: 0.75rem;
    margin-top: 1rem;
  }
  
  .modal-body h6:first-child {
    margin-top: 0;
  }
}

/* Garantir que o backdrop não interfira com a navegação mobile */
.modal-backdrop {
  background-color: rgba(0, 0, 0, 0.5);
}

/* Melhorar a experiência de scroll em modais mobile */
@media (max-width: 768px) {
  .modal-body {
    -webkit-overflow-scrolling: touch;
  }
  
  /* Prevenir problemas de viewport em iOS */
  .modal {
    -webkit-transform: translate3d(0, 0, 0);
    transform: translate3d(0, 0, 0);
  }
}

/* Correção para evitar conflitos com a navegação mobile */
@media (max-width: 768px) {
  .modal.show {
    padding-right: 0 !important;
  }
  
  body.modal-open {
    overflow: hidden;
    padding-right: 0 !important;
  }
}
  </style>
</head>

<body>
  <div class="main-wrapper">
    <!-- Sidebar -->
    <?php include_once '../../frontend/includes/sidebar.php'?>
    
    <div class="content-wrapper">
      <!-- Top Header -->
      <header class="top-header">
        <div class="d-flex flex-column w-100">
          <!-- Linha superior com busca e ícones -->
          <div class="d-flex justify-content-between align-items-center py-2">
            <!-- Área de busca -->
            <div class="search-container d-flex flex-row gap-3">
              <input 
                type="text" 
                class="form-control" 
                placeholder="Buscar funcionário..." 
                id="searchEmployeeInput"
              >

              <div class="user-area">
                <?php require_once __DIR__ . '/../includes/notificacoes.php'; ?>
              </div>
            </div>

            <!-- Área de ações do usuário -->
            <div class="d-flex align-items-center gap-3">
              <div>
                <?php include_once '../../frontend/includes/darkmode.php'?>
              </div>
            </div>
          </div>

          <!-- Breadcrumb navigation -->
          <div class="breadcrumb-nav py-2 d-flex justify-content-between align-items-center">
            <nav style="--bs-breadcrumb-divider: '/';" aria-label="breadcrumb">
              <!-- Breadcrumb (Desktop) -->
              <ol class="breadcrumb mb-0 gap-3">
                <li class="breadcrumb-item"><a href="#"><i class="bi bi-house-door"></i> Home</a></li>
                <li> / </li>
                <li aria-current="page">Relatórios</li>
              </ol>
            </nav>
            <!-- Botão Novo Funcionário alinhado à direita -->
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCreate">
              <i class="fas fa-plus me-2"></i> 
              <span>Novo Funcionário</span>
            </button>
          </div>
        </div>
      </header>

      <!-- Main Content -->
      <main class="main-content">
        <!-- Mensagens -->
        <?php if ($msg): ?>
          <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
          </div>
        <?php endif; ?>

        <!-- Listagem de Funcionários (Cards) -->
        <div class="container-fluid">
          <div class="row" id="employeeCardsContainer">
            <?php if (count($funcionarios) > 0): ?>
              <?php foreach ($funcionarios as $index => $func): ?>
                <?php $fotoPath = getEmployeePhoto($func['id_funcionario']); ?>
                <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                  <div 
                    class="card card-employee h-100 animate-fade-in" 
                    style="animation-delay: <?= (0.2 + $index * 0.05); ?>s"
                    data-id="<?= $func['id_funcionario']; ?>"
                    data-nome="<?= htmlspecialchars($func['nome']); ?>"
                    data-cpf="<?= $func['cpf_cnpj']; ?>"
                    data-telefone="<?= $func['telefone']; ?>"
                    data-email="<?= $func['email']; ?>"
                    data-cep="<?= $func['cep']; ?>"
                    data-logradouro="<?= htmlspecialchars($func['logradouro']); ?>"
                    data-numero="<?= $func['numero']; ?>"
                    data-complemento="<?= $func['complemento']; ?>"
                    data-bairro="<?= $func['bairro']; ?>"
                    data-cidade="<?= $func['cidade']; ?>"
                    data-estado="<?= $func['estado']; ?>"
                    data-dataadmissao="<?= $func['data_admissao']; ?>"
                    data-comissao="<?= $func['comissao_atual']; ?>"
                  >
                    <?php if ($fotoPath): ?>
                      <img src="<?= $fotoPath; ?>" alt="<?= htmlspecialchars($func['nome']); ?>" class="card-img-top">
                    <?php else: ?>
                      <div class="employee-photo-placeholder">
                        <i class="bi bi-person" style="font-size:3rem;"></i>
                      </div>
                    <?php endif; ?>
                    <div class="card-body">
                      <h5 class="card-title"><?= htmlspecialchars($func['nome']); ?></h5>
                      <p class="card-text">
                        <strong>Comissão:</strong> R$ <?= number_format($func['comissao_atual'], 2, ',', '.'); ?>
                      </p>
                      <div class="d-flex justify-content-end">
                        <button 
                          class="btn btn-sm btn-outline-primary me-2 btn-edit" 
                          data-bs-toggle="modal" 
                          data-bs-target="#modalEdit"
                          data-id="<?= $func['id_funcionario']; ?>"
                          data-nome="<?= htmlspecialchars($func['nome']); ?>"
                          data-cpf="<?= $func['cpf_cnpj']; ?>"
                          data-telefone="<?= $func['telefone']; ?>"
                          data-email="<?= $func['email']; ?>"
                          data-cep="<?= $func['cep']; ?>"
                          data-logradouro="<?= htmlspecialchars($func['logradouro']); ?>"
                          data-numero="<?= $func['numero']; ?>"
                          data-complemento="<?= $func['complemento']; ?>"
                          data-bairro="<?= $func['bairro']; ?>"
                          data-cidade="<?= $func['cidade']; ?>"
                          data-estado="<?= $func['estado']; ?>"
                          data-dataadmissao="<?= $func['data_admissao']; ?>"
                          data-comissao="<?= $func['comissao_atual']; ?>"
                        >
                          <i class="fas fa-edit"></i>
                        </button>
                        <!-- Botão para visualizar comissão/histórico -->
                        <button 
                          class="btn btn-sm btn-outline-info me-2 btn-commission" 
                          data-id="<?= $func['id_funcionario']; ?>"
                          data-nome="<?= htmlspecialchars($func['nome']); ?>"
                        >
                          <i class="fas fa-percentage"></i>
                        </button>
                        <button 
                          class="btn btn-sm btn-outline-danger btn-delete" 
                          data-bs-toggle="modal" 
                          data-bs-target="#modalDelete"
                          data-id="<?= $func['id_funcionario']; ?>"
                          data-nome="<?= htmlspecialchars($func['nome']); ?>"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="col-12">
                <div class="alert alert-info">
                  Nenhum funcionário cadastrado. Clique em "Novo Funcionário" para adicionar.
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </main>
    </div>
  </div>

<!-- Menu mobile -->
<div class="bottom-nav d-block d-md-none d-flex justify-content-center align-items-center">
  <a href="index.php" class="bottom-nav-item <?php echo in_array($action, ['list','create','edit','variacoes'])?'active':''; ?>">
    <i class="fas fa-box"></i>
    <span>Produtos</span>
  </a>
  <a href="../clientes/clientes.php" class="bottom-nav-item <?php echo ($action=='clientes')?'active':''; ?>">
    <i class="fas fa-users"></i>
    <span>Clientes</span>
  </a>
  <a href="../vendas/index.php" class="bottom-nav-item <?php echo ($action=='vendas')?'active':''; ?>">
    <i class="fas fa-shopping-cart"></i>
    <span>Vendas</span>
  </a>
  <a href="../financeiro/index.php" class="bottom-nav-item <?php echo ($action=='financeiro')?'active':''; ?>">
    <i class="fas fa-wallet"></i>
    <span>Financeiro</span>
  </a>
  <a href="#" id="desktopSidebarToggle" class="bottom-nav-item">
     <i class="fas fa-ellipsis-h"></i>
     <span>Mais</span>
</a>
</div>

<style>
  /* Mobile Menu */
  .mobile-bottom-nav,
  .bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    height: var(--bottom-nav-height);
    background-color: #ffffff;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
    display: flex;
    justify-content: space-around;
    align-items: center;
    z-index: 1001;
    padding: 0 10px;
  }

  .mobile-nav-item,
  .bottom-nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    text-decoration: none;
    font-size: 10px;
    padding: 8px 0;
    flex: 1;
    transition: all 0.2s;
    position: relative;
  }

  .mobile-nav-item i,
  .bottom-nav-item i {
    font-size: 20px;
    margin-bottom: 4px;
    transition: all 0.2s;
  }

  .mobile-nav-item:hover,
  .mobile-nav-item:active,
  .mobile-nav-item.active,
  .bottom-nav-item:hover,
  .bottom-nav-item:active,
  .bottom-nav-item.active {
    color: var(--primary-color);
  }

  .mobile-nav-item:hover i,
  .mobile-nav-item:active i,
  .mobile-nav-item.active i,
  .bottom-nav-item:hover i,
  .bottom-nav-item:active i,
  .bottom-nav-item.active i {
    transform: translateY(-2px);
  }

  .mobile-nav-item::after,
  .bottom-nav-item::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 3px;
    background-color: var(--primary-color);
    transition: width 0.2s;
    border-radius: 3px 3px 0 0;
  }

  .mobile-nav-item:hover::after,
  .mobile-nav-item:active::after,
  .mobile-nav-item.active::after,
  .bottom-nav-item:hover::after,
  .bottom-nav-item:active::after,
  .bottom-nav-item.active::after {
    width: 40%;
  }

  body.dark-mode .bottom-nav {
      background-color: #1e1e1e;
      border-top: 1px solid #333;
  }
  
  body.dark-mode .bottom-nav-item {
    color: #adb5bd;
  }
  
  body.dark-mode .bottom-nav-item.active {
    color: var(--primary-color);
  }
</style>

<script>
  // Mobile Menu 
  const overlay = document.querySelector('.drawer-overlay');
  document.getElementById('desktopSidebarToggle')
    .addEventListener('click', e => {
      e.preventDefault();
      sidebar.classList.toggle('show');
      overlay.classList.toggle('show');
    });

  // fechar ao clicar no overlay
  overlay.addEventListener('click', () => {
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
  });
</script>

  <!-- MODAL: Informações do Funcionário (com 3 abas) -->
  <div class="modal fade" id="modalInfo" tabindex="-1" aria-labelledby="modalInfoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalInfoLabel">Informações de <span id="infoEmployeeName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <!-- Nav tabs (3 páginas) -->
          <ul class="nav nav-tabs" id="infoTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button 
                class="nav-link active" 
                id="tab-detalhes-tab" 
                data-bs-toggle="tab" 
                data-bs-target="#tab-detalhes" 
                type="button" 
                role="tab" 
                aria-controls="tab-detalhes" 
                aria-selected="true"
              >
                Detalhes
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button 
                class="nav-link" 
                id="tab-graficos-tab" 
                data-bs-toggle="tab" 
                data-bs-target="#tab-graficos" 
                type="button" 
                role="tab" 
                aria-controls="tab-graficos" 
                aria-selected="false"
              >
                Gráficos
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button 
                class="nav-link" 
                id="tab-comissao-tab" 
                data-bs-toggle="tab" 
                data-bs-target="#tab-comissao" 
                type="button" 
                role="tab" 
                aria-controls="tab-comissao" 
                aria-selected="false"
              >
                Comissão
              </button>
            </li>
          </ul>

          <!-- Tab content -->
          <div class="tab-content" id="infoTabsContent">
            <!-- TAB 1: DETALHES -->
            <div class="tab-pane fade show active" id="tab-detalhes" role="tabpanel" aria-labelledby="tab-detalhes-tab">
              <div id="employeeDetails" class="mt-3"></div>
            </div>

            <!-- TAB 2: GRÁFICOS (e lista de vendas) -->
            <div class="tab-pane fade" id="tab-graficos" role="tabpanel" aria-labelledby="tab-graficos-tab">
              <div class="row mt-3">
                <div class="col-md-6">
                  <canvas id="monthlyChart"></canvas>
                </div>
                <div class="col-md-6">
                  <canvas id="weeklyChart"></canvas>
                </div>
              </div>
              <h5 class="mt-4">Lista de Vendas</h5>
              <div id="salesList" style="max-height:300px; overflow-y:auto;"></div>
            </div>

            <!-- TAB 3: COMISSÃO (último mês + histórico) -->
            <div class="tab-pane fade" id="tab-comissao" role="tabpanel" aria-labelledby="tab-comissao-tab">
              <div class="mt-3">
                <p>
                  <strong>Comissão do último mês (5%):</strong> R$ <span id="currentCommission"></span>
                </p>
                <h6>Histórico de Comissões</h6>
                <div class="table-responsive">
                  <table class="table table-bordered" id="commissionHistoryTable">
                    <thead>
                      <tr>
                        <th>Mês</th>
                        <th>Total em Vendas (R$)</th>
                        <th>Comissão (R$)</th>
                        <th>Data Registro</th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- Preenchido via JS -->
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

          </div> <!-- /tab-content -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: Criação de Funcionário -->
  <div class="modal fade" id="modalCreate" tabindex="-1" aria-labelledby="modalCreateLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <form method="POST" action="index.php" class="modal-content" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCreateLabel"><i class="fas fa-plus"></i> Novo Funcionário</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
        <div class="row">
            <!-- Coluna Esquerda -->
            <div class="col-md-6">
              <h6 class="mb-3">Dados Pessoais</h6>
              <div class="mb-3">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">CPF/CNPJ</label>
                <input type="text" name="cpf_cnpj" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Foto</label>
                <input type="file" name="foto" class="form-control" accept="image/*">
              </div>
            </div>
            <!-- Coluna Direita -->
            <div class="col-md-6">
              <h6 class="mb-3">Endereço</h6>
              <div class="mb-3">
                <label class="form-label">CEP</label>
                <input type="text" name="cep" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Logradouro</label>
                <input type="text" name="logradouro" class="form-control">
              </div>
              <div class="row mb-3">
                <div class="col">
                  <label class="form-label">Número</label>
                  <input type="text" name="numero" class="form-control">
                </div>
                <div class="col">
                  <label class="form-label">Complemento</label>
                  <input type="text" name="complemento" class="form-control">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Bairro</label>
                <input type="text" name="bairro" class="form-control">
              </div>
              <div class="row mb-3">
                <div class="col">
                  <label class="form-label">Cidade</label>
                  <input type="text" name="cidade" class="form-control">
                </div>
                <div class="col">
                  <label class="form-label">Estado</label>
                  <input type="text" name="estado" class="form-control" maxlength="2">
                </div>
              </div>
            </div>
          </div>
          <div class="row mt-3">
            <!-- Dados Profissionais -->
            <div class="col-md-6">
              <h6 class="mb-3">Dados Profissionais</h6>
              <div class="mb-3">
                <label class="form-label">Data de Admissão</label>
                <input type="date" name="data_admissao" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Comissão Atual</label>
                <input type="number" step="0.01" name="comissao_atual" class="form-control" value="0.00" required>
              </div>
            </div>
            <!-- Acesso ao Sistema -->
            <div class="col-md-6">
              <h6 class="mb-3">Acesso ao Sistema</h6>
              <div class="mb-3">
                <label class="form-label">Senha</label>
                <input type="password" name="senha" class="form-control" required>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <input type="hidden" name="action" value="create">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: Edição de Funcionário -->
  <div class="modal fade" id="modalEdit" tabindex="-1" aria-labelledby="modalEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <form method="POST" action="index.php" class="modal-content" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="modalEditLabel"><i class="fas fa-edit"></i> Editar Funcionário</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id_funcionario" id="edit_id">
          <div class="row">
            <!-- Coluna Esquerda -->
            <div class="col-md-6">
              <h6 class="mb-3">Dados Pessoais</h6>
              <div class="mb-3">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" id="edit_nome" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">CPF/CNPJ</label>
                <input type="text" name="cpf_cnpj" id="edit_cpf" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" id="edit_telefone" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" id="edit_email" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Foto</label>
                <input type="file" name="foto" class="form-control" accept="image/*">
                <div id="currentFoto" class="mt-2"></div>
              </div>
            </div>
            <!-- Coluna Direita -->
            <div class="col-md-6">
              <h6 class="mb-3">Endereço</h6>
              <div class="mb-3">
                <label class="form-label">CEP</label>
                <input type="text" name="cep" id="edit_cep" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Logradouro</label>
                <input type="text" name="logradouro" id="edit_logradouro" class="form-control">
              </div>
              <div class="row mb-3">
                <div class="col">
                  <label class="form-label">Número</label>
                  <input type="text" name="numero" id="edit_numero" class="form-control">
                </div>
                <div class="col">
                  <label class="form-label">Complemento</label>
                  <input type="text" name="complemento" id="edit_complemento" class="form-control">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Bairro</label>
                <input type="text" name="bairro" id="edit_bairro" class="form-control">
              </div>
              <div class="row mb-3">
                <div class="col">
                  <label class="form-label">Cidade</label>
                  <input type="text" name="cidade" id="edit_cidade" class="form-control">
                </div>
                <div class="col">
                  <label class="form-label">Estado</label>
                  <input type="text" name="estado" id="edit_estado" class="form-control" maxlength="2">
                </div>
              </div>
            </div>
          </div>
          <div class="row mt-3">
            <!-- Dados Profissionais -->
            <div class="col-md-6">
              <h6 class="mb-3">Dados Profissionais</h6>
              <div class="mb-3">
                <label class="form-label">Data de Admissão</label>
                <input type="date" name="data_admissao" id="edit_data_admissao" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Comissão Atual</label>
                <input type="number" step="0.01" name="comissao_atual" id="edit_comissao" class="form-control" required>
              </div>
            </div>
            <!-- Acesso ao Sistema -->
            <div class="col-md-6">
              <h6 class="mb-3">Acesso ao Sistema</h6>
              <div class="mb-3">
                <label class="form-label">Senha</label>
                <input type="password" name="senha" id="edit_senha" class="form-control" placeholder="Deixe em branco para manter a senha atual">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <input type="hidden" name="action" value="update">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Atualizar</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL: Exclusão de Funcionário -->
  <div class="modal fade" id="modalDelete" tabindex="-1" aria-labelledby="modalDeleteLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form method="POST" action="index.php" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDeleteLabel"><i class="fas fa-trash"></i> Excluir Funcionário</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p>Tem certeza que deseja excluir o funcionário <strong id="delete_nome"></strong>?</p>
          <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Esta ação não pode ser desfeita.</p>
        </div>
        <div class="modal-footer">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id_funcionario" id="delete_id">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger">Excluir</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Bootstrap & Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <script>
        function maskCPF(value) {
        // Remove tudo que não é dígito
        let digits = value.replace(/\D/g, '');
        // Limita a 11 dígitos
        digits = digits.substring(0, 11);
        let masked = '';
        if (digits.length > 0) masked = digits.substring(0, 3);
        if (digits.length >= 4) masked += '.' + digits.substring(3, 6);
        if (digits.length >= 7) masked += '.' + digits.substring(6, 9);
        if (digits.length >= 10) masked += '-' + digits.substring(9, 11);
        return masked;
      }

      function maskTelefone(value) {
  // Remove caracteres não numéricos
  let digits = value.replace(/\D/g, '');
  // Limita a no máximo 11 dígitos
  digits = digits.substring(0, 11);
  let masked = '';
  if (digits.length <= 10) {
    // Para números fixos (10 dígitos): (XX) XXXX-XXXX
    masked = '(' + digits.substring(0, 2) + ') ' + digits.substring(2, 6);
    if (digits.length > 6) {
      masked += '-' + digits.substring(6, 10);
    }
  } else {
    // Para celular (11 dígitos): (XX) X XXXX-XXXX
    masked = '(' + digits.substring(0, 2) + ') ' + digits.substring(2, 3) + ' ' + digits.substring(3, 7);
    if (digits.length > 7) {
      masked += '-' + digits.substring(7, 11);
    }
  }
  return masked;
}

      // Aplica máscara aos campos de CPF/CNPJ e Telefone
      const cpfInputs = document.querySelectorAll('input[name="cpf_cnpj"]');
cpfInputs.forEach(input => {
  input.addEventListener('input', function() {
    this.value = maskCPF(this.value);
  });
});


const telefoneInputs = document.querySelectorAll('input[name="telefone"]');
telefoneInputs.forEach(input => {
  input.addEventListener('input', function() {
    this.value = maskTelefone(this.value);
  });
});
    document.addEventListener('DOMContentLoaded', function() {
      // ============ MODAL DELETE ============
      const modalDelete = document.getElementById('modalDelete');
      if (modalDelete) {
        modalDelete.addEventListener('show.bs.modal', function(event) {
          const button = event.relatedTarget;
          document.getElementById('delete_id').value = button.getAttribute('data-id');
          document.getElementById('delete_nome').innerText = button.getAttribute('data-nome');
        });
      }

      // ============ MODAL EDIT ============
      const modalEdit = document.getElementById('modalEdit');
      if (modalEdit) {
        modalEdit.addEventListener('show.bs.modal', function(event) {
          const button = event.relatedTarget;
          document.getElementById('edit_id').value = button.getAttribute('data-id');
          document.getElementById('edit_nome').value = button.getAttribute('data-nome');
          document.getElementById('edit_cpf').value = button.getAttribute('data-cpf');
          document.getElementById('edit_telefone').value = button.getAttribute('data-telefone');
          document.getElementById('edit_email').value = button.getAttribute('data-email');
          document.getElementById('edit_cep').value = button.getAttribute('data-cep');
          document.getElementById('edit_logradouro').value = button.getAttribute('data-logradouro');
          document.getElementById('edit_numero').value = button.getAttribute('data-numero');
          document.getElementById('edit_complemento').value = button.getAttribute('data-complemento');
          document.getElementById('edit_bairro').value = button.getAttribute('data-bairro');
          document.getElementById('edit_cidade').value = button.getAttribute('data-cidade');
          document.getElementById('edit_estado').value = button.getAttribute('data-estado');
          document.getElementById('edit_data_admissao').value = button.getAttribute('data-dataadmissao');
          document.getElementById('edit_comissao').value = button.getAttribute('data-comissao');
          document.getElementById('edit_senha').value = '';

          // Carrega a foto atual, se houver
          const id = button.getAttribute('data-id');
          const currentFotoElem = document.getElementById('currentFoto');
          if (currentFotoElem) {
            const img = new Image();
            img.onload = function() {
              currentFotoElem.innerHTML = `<div class="alert alert-info"><small>Foto atual:<br><img src="${this.src}" alt="Foto atual" style="max-height:100px; max-width:100%;"></small></div>`;
            };
            img.onerror = function() {
              currentFotoElem.innerHTML = `<div class="alert alert-secondary"><small>Sem foto cadastrada</small></div>`;
            };
            img.src = `../assets/images/funcionario_${id}.png`;
          }
        });
      }

      // ============ FUNÇÃO: ABRIR MODAL INFO (3 ABAS) ============
      async function openEmployeeModal(card, showCommissionTab = false) {
        const id = card.getAttribute('data-id');
        const nome = card.getAttribute('data-nome');
        const cpf = card.getAttribute('data-cpf');
        const telefone = card.getAttribute('data-telefone');
        const email = card.getAttribute('data-email');
        const cep = card.getAttribute('data-cep');
        const logradouro = card.getAttribute('data-logradouro');
        const numero = card.getAttribute('data-numero');
        const complemento = card.getAttribute('data-complemento');
        const bairro = card.getAttribute('data-bairro');
        const cidade = card.getAttribute('data-cidade');
        const estado = card.getAttribute('data-estado');
        const dataAdm = card.getAttribute('data-dataadmissao');
        const comissao = card.getAttribute('data-comissao');

        // Atualiza o título do modal
        document.getElementById('infoEmployeeName').innerText = nome;

        // Preenche a aba de Detalhes
        const detailsHTML = `
          <div class="row">
            <div class="col-md-6">
              <h6>Dados Pessoais</h6>
              <p><strong>Nome:</strong> ${nome}</p>
              <p><strong>CPF/CNPJ:</strong> ${cpf}</p>
              <p><strong>Telefone:</strong> ${telefone}</p>
              <p><strong>Email:</strong> ${email}</p>
            </div>
            <div class="col-md-6">
              <h6>Endereço</h6>
              <p><strong>CEP:</strong> ${cep}</p>
              <p><strong>Logradouro:</strong> ${logradouro}, ${numero} ${complemento}</p>
              <p><strong>Bairro:</strong> ${bairro}</p>
              <p><strong>Cidade/Estado:</strong> ${cidade} - ${estado}</p>
            </div>
          </div>
          <div class="row mt-3">
            <div class="col-md-6">
              <h6>Dados Profissionais</h6>
              <p><strong>Data de Admissão:</strong> ${dataAdm}</p>
              <p><strong>Comissão Atual:</strong> R$ ${parseFloat(comissao).toFixed(2).replace('.',',')}</p>
            </div>
          </div>
        `;
        document.getElementById('employeeDetails').innerHTML = detailsHTML;

        // Reseta gráficos e lista de vendas (aba Gráficos)
        const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
        if (window.monthlyChartInstance) { window.monthlyChartInstance.destroy(); }
        window.monthlyChartInstance = null;
        const ctxWeekly = document.getElementById('weeklyChart').getContext('2d');
        if (window.weeklyChartInstance) { window.weeklyChartInstance.destroy(); }
        window.weeklyChartInstance = null;
        document.getElementById('salesList').innerHTML = '';

        // Reseta aba de Comissão
        document.getElementById('currentCommission').innerText = '...';
        const histBody = document.querySelector('#commissionHistoryTable tbody');
        histBody.innerHTML = '<tr><td colspan="4" class="text-center">Carregando...</td></tr>';

        // Abre o modal
        const modalInfo = new bootstrap.Modal(document.getElementById('modalInfo'));
        modalInfo.show();

        // Se não for para abrir diretamente a aba de Comissão, força a aba Detalhes
        if (!showCommissionTab) {
          const tabDetalhes = document.getElementById('tab-detalhes-tab');
          new bootstrap.Tab(tabDetalhes).show();
        }

        // Carrega as vendas (para a aba Gráficos)
        fetch(`index.php?action=getSales&id_funcionario=${id}`)
          .then(r => r.json())
          .then(data => {
            const monthlyData = new Array(12).fill(0);
            const weeklyData = [];
            const weeklyLabel = [];
            const today = new Date();
            const oneWeekAgo = new Date();
            oneWeekAgo.setDate(today.getDate() - 6);
            data.sales.forEach(sale => {
              const d = new Date(sale.data);
              const m = d.getMonth();
              monthlyData[m] += parseFloat(sale.total_venda);
              if (d >= oneWeekAgo && d <= today) {
                const label = d.toLocaleDateString();
                const idx = weeklyLabel.indexOf(label);
                if (idx === -1) {
                  weeklyLabel.push(label);
                  weeklyData.push(parseFloat(sale.total_venda));
                } else {
                  weeklyData[idx] += parseFloat(sale.total_venda);
                }
              }
            });

            // Gráfico Mensal
            window.monthlyChartInstance = new Chart(ctxMonthly, {
              type: 'bar',
              data: {
                labels: ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'],
                datasets: [{
                  label: 'Faturamento (R$)',
                  data: monthlyData,
                  backgroundColor: 'rgba(78, 84, 233, 0.2)',
                  borderColor: 'rgba(78, 84, 233, 1)',
                  borderWidth: 2
                }]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
              }
            });

            // Gráfico Semanal
            const temp = weeklyLabel.map((lbl, i) => ({ lbl, val: weeklyData[i] }));
            temp.sort((a, b) => {
              const [dayA, monA, yrA] = a.lbl.split('/');
              const [dayB, monB, yrB] = b.lbl.split('/');
              return new Date(+yrA, monA - 1, +dayA) - new Date(+yrB, monB - 1, +dayB);
            });
            const sortedLabels = temp.map(t => t.lbl);
            const sortedData = temp.map(t => t.val);
            window.weeklyChartInstance = new Chart(ctxWeekly, {
              type: 'line',
              data: {
                labels: sortedLabels,
                datasets: [{
                  label: 'Faturamento Diário (R$)',
                  data: sortedData,
                  borderColor: 'rgba(255, 99, 132, 1)',
                  backgroundColor: 'rgba(255, 99, 132, 0.2)',
                  tension: 0.4,
                  fill: true
                }]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true } }
              }
            });

            // Monta a lista de vendas
            let salesHTML = '<ul class="list-group">';
            if (data.sales && data.sales.length > 0) {
              let totalVendas = 0;
              data.sales.forEach(sale => {
                const saleTotal = parseFloat(sale.total_venda);
                totalVendas += saleTotal;
                salesHTML += `<li class="list-group-item">
                  <strong>ID:</strong> ${sale.id_venda} –
                  <strong>Data:</strong> ${new Date(sale.data).toLocaleDateString()} –
                  <strong>Total:</strong> R$ ${saleTotal.toFixed(2).replace('.',',')} –
                  <strong>Status:</strong> ${sale.status || 'finalizada'}
                </li>`;
              });
              salesHTML += `</ul>
                <p class="mt-2"><strong>Total de Vendas:</strong> R$ ${totalVendas.toFixed(2).replace('.',',')}</p>`;
            } else {
              salesHTML += '<li class="list-group-item text-center">Nenhuma venda encontrada</li></ul>';
            }
            document.getElementById('salesList').innerHTML = salesHTML;
          })
          .catch(err => console.error("Erro ao obter dados de vendas:", err));

        // Carrega a aba de Comissão (se necessário)
        if (showCommissionTab) {
          const tabComissao = document.getElementById('tab-comissao-tab');
          new bootstrap.Tab(tabComissao).show();
          loadCommissionData(id);
        } else {
          const tabComissaoTrigger = document.getElementById('tab-comissao-tab');
          tabComissaoTrigger.addEventListener('shown.bs.tab', function onceComissaoTab() {
            loadCommissionData(id);
            tabComissaoTrigger.removeEventListener('shown.bs.tab', onceComissaoTab);
          }, { once: true });
        }
      }

      // Função para carregar os dados de comissão (aba Comissão)
      function loadCommissionData(id_funcionario) {
        fetch(`index.php?action=getCommission&id_funcionario=${id_funcionario}`)
          .then(r => r.json())
          .then(data => {
            document.getElementById('currentCommission').innerText = parseFloat(data.current).toFixed(2).replace('.',',');
            const tbody = document.querySelector('#commissionHistoryTable tbody');
            tbody.innerHTML = "";
            if (data.history && data.history.length > 0) {
              data.history.forEach(record => {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${record.mes}</td>
                                <td>R$ ${parseFloat(record.total_vendas).toFixed(2).replace('.',',')}</td>
                                <td>R$ ${parseFloat(record.comissao).toFixed(2).replace('.',',')}</td>
                                <td>${record.data_registro}</td>`;
                tbody.appendChild(tr);
              });
            } else {
              const tr = document.createElement('tr');
              tr.innerHTML = `<td colspan="4" class="text-center">Nenhum histórico encontrado</td>`;
              tbody.appendChild(tr);
            }
          })
          .catch(error => console.error('Erro ao obter comissão:', error));
      }

      // ============ FUNÇÃO DE PESQUISA POR NOME ============
      const searchEmployeeInput = document.getElementById('searchEmployeeInput');
      searchEmployeeInput.addEventListener('input', function() {
        const query = this.value.trim();
        fetch(`index.php?action=searchEmployee&query=${encodeURIComponent(query)}`)
          .then(r => r.json())
          .then(data => {
            // Atualiza o container dos cards de funcionários
            const container = document.getElementById('employeeCardsContainer');
            let html = "";
            if (data.employees && data.employees.length > 0) {
              data.employees.forEach((emp, idx) => {
                // Aqui, opcionalmente, você pode usar emp.foto ou a função getEmployeePhoto
                html += `
                  <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                    <div 
                      class="card card-employee h-100 animate-fade-in" 
                      style="animation-delay: ${0.2 + idx * 0.05}s"
                      data-id="${emp.id_funcionario}"
                      data-nome="${emp.nome}"
                      data-cpf="${emp.cpf_cnpj}"
                      data-telefone="${emp.telefone}"
                      data-email="${emp.email}"
                      data-cep="${emp.cep}"
                      data-logradouro="${emp.logradouro}"
                      data-numero="${emp.numero}"
                      data-complemento="${emp.complemento}"
                      data-bairro="${emp.bairro}"
                      data-cidade="${emp.cidade}"
                      data-estado="${emp.estado}"
                      data-dataadmissao="${emp.data_admissao}"
                      data-comissao="${emp.comissao_atual}"
                    >
                      <div class="employee-photo-placeholder">
                        <i class="bi bi-person" style="font-size:3rem;"></i>
                      </div>
                      <div class="card-body">
                        <h5 class="card-title">${emp.nome}</h5>
                        <p class="card-text">
                          <strong>Comissão:</strong> R$ ${parseFloat(emp.comissao_atual).toFixed(2).replace('.',',')}
                        </p>
                        <div class="d-flex justify-content-end">
                          <button class="btn btn-sm btn-outline-primary me-2 btn-edit" data-bs-toggle="modal" data-bs-target="#modalEdit" data-id="${emp.id_funcionario}" data-nome="${emp.nome}" data-cpf="${emp.cpf_cnpj}" data-telefone="${emp.telefone}" data-email="${emp.email}" data-cep="${emp.cep}" data-logradouro="${emp.logradouro}" data-numero="${emp.numero}" data-complemento="${emp.complemento}" data-bairro="${emp.bairro}" data-cidade="${emp.cidade}" data-estado="${emp.estado}" data-dataadmissao="${emp.data_admissao}" data-comissao="${emp.comissao_atual}">
                            <i class="fas fa-edit"></i>
                          </button>
                          <button class="btn btn-sm btn-outline-info me-2 btn-commission" data-id="${emp.id_funcionario}" data-nome="${emp.nome}">
                            <i class="fas fa-percentage"></i>
                          </button>
                          <button class="btn btn-sm btn-outline-danger btn-delete" data-bs-toggle="modal" data-bs-target="#modalDelete" data-id="${emp.id_funcionario}" data-nome="${emp.nome}">
                            <i class="fas fa-trash"></i>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                `;
              });
            } else {
              html = `<div class="col-12">
                        <div class="alert alert-info">
                          Nenhum funcionário encontrado.
                        </div>
                      </div>`;
            }
            container.innerHTML = html;

            // Reanexa os eventos para abertura dos modais nas novas cards
            document.querySelectorAll('.card-employee').forEach(card => {
              card.addEventListener('click', function(event) {
                if (event.target.closest('.btn')) return;
                openEmployeeModal(this, false);
              });
            });

            // Reanexa o evento para o botão de comissão
            document.querySelectorAll('.btn-commission').forEach(button => {
              button.addEventListener('click', function(e) {
                e.stopPropagation();
                openEmployeeModal(button.closest('.card-employee'), true);
              });
            });
          })
          .catch(error => console.error('Erro na pesquisa:', error));
      });

      // ============ ABRIR MODAL INFO (clicando no card ou botão de comissão) ============
      const employeeCards = document.querySelectorAll('.card-employee');
      employeeCards.forEach(card => {
        card.addEventListener('click', function(event) {
          if (event.target.closest('.btn')) return;
          openEmployeeModal(this, false);
        });
      });

      const btnCommissionList = document.querySelectorAll('.btn-commission');
      btnCommissionList.forEach(button => {
        button.addEventListener('click', function(e) {
          e.stopPropagation();
          openEmployeeModal(button.closest('.card-employee'), true);
        });
      });
    });

    // Função auxiliar para obter a foto do funcionário (opcional, se não estiver disponível no objeto)
    function getEmployeePhoto(id_funcionario) {
      const base = `../assets/images/funcionario_${id_funcionario}`;
      const pngPath = `${base}.png`;
      const jpgPath = `${base}.jpg`;

      // Faz uma requisição HEAD síncrona para ver se o PNG existe
      const xhr = new XMLHttpRequest();
      xhr.open('HEAD', pngPath, false);
      xhr.send();

      // Se não for 404, retorna o PNG; senão, retorna o JPG
      if (xhr.status !== 404) {
        return pngPath;
      }
      return jpgPath;
    }
</script>
</body>
</html>
