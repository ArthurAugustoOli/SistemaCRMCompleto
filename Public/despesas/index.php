<?php
// Public/despesas/index.php

require_once '../../app/config/config.php';
require_once '../../app/models/Despesas.php';
require_once '../../app/models/Produto.php';
require_once '../../app/models/ProdutoVariacao.php';
require_once '../login/verificar_sessao.php'; // Ajuste o caminho conforme necessário


require_once '../../app/config/config.php'; // Ajuste o caminho para o seu config
require_once '../login/verificar_sessao.php'; // Ajuste o caminho conforme necessário

use App\Models\Despesas;

// Instancia os models
$despesaModel = new Despesas();
$produtoModel = new Produto($mysqli);
$variacaoModel = new ProdutoVariacao($mysqli);

// restringe acesso ao admin
if (!isset($_SESSION['login']) || $_SESSION['login'] !== 'Silvania') {
    header('Location: ../produtos/index.php');
    exit;
}

// Exemplo de chamada para atualizar o estoque (apenas para teste, retire depois)
// $produtoModel->adicionarEstoque(1, 10); // Acrescenta 10 unidades ao produto de ID 1

// Carrega os produtos para os formulários de compra
global $mysqli;
$result = $mysqli->query("SELECT id_produto, nome FROM produtos ORDER BY nome");
$produtosList = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
// Mês selecionado via GET ou padrão para o mês atual (formato YYYY-MM)
$selectedMonth = $_GET['month'] ?? date('Y-m');
// Gera data de início e fim do mês
list($year, $month) = explode('-', $selectedMonth);
$dataInicio = "$year-$month-01";
$dataFim    = date("Y-m-t", strtotime($dataInicio));

// Endpoint interno para obter variações via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'getVariacoes' && isset($_GET['produto'])) {
  $produtoId = intval($_GET['produto']);
  $variacoes = $despesaModel->getVariacoesByProduto($produtoId);
  header('Content-Type: application/json');
  echo json_encode($variacoes);
  exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'getVariacoes' && isset($_GET['id_produto'])) {
  header('Content-Type: application/json');
  echo json_encode($despesaModel->getVariacoesByProduto(intval($_GET['id_produto'])));
  exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'getCompra' && isset($_GET['id'])) {
  $compra = $despesaModel->getDespesaById(intval($_GET['id']));
  header('Content-Type: application/json');
  echo json_encode($compra);
  exit;
}

// Inicializa variáveis para mensagens e modos de edição
$msg = "";
$editModeCompra = false;
$editCompra = null;
$editModeGeral = false;
$editGeral = null;

// --- Processamento via POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

  // 1) Cadastro de Despesa Geral (sem produto)
  if ($_POST['action'] === 'create_despesa') {
    $categoria = $_POST['categoria'];
    $descricao = $_POST['descricao'];
    $valor = $_POST['valor'];
    $data_despesa = $_POST['data_despesa'];
    $status = $_POST['status'];

    try {
      $id_despesa = $despesaModel->createDespesa($categoria, $descricao, $valor, $data_despesa, $status);
      $msg = "Despesa cadastrada com sucesso!";
    } catch (Exception $e) {
      $msg = "Erro ao cadastrar despesa: " . $e->getMessage();
    }
  }

  // 2) Atualização de Despesa Geral
  elseif ($_POST['action'] === 'update_despesa') {
    $id = intval($_POST['id_despesa']);
    $categoria = $_POST['categoria'];
    $descricao = $_POST['descricao'];
    $valor = $_POST['valor'];
    $data_despesa = $_POST['data_despesa'];
    $status = $_POST['status'];

    try {
      $despesaModel->updateDespesa($id, $categoria, $descricao, $valor, $data_despesa, $status, []);
      $msg = "Despesa atualizada com sucesso!";
    } catch (Exception $e) {
      $msg = "Erro ao atualizar despesa: " . $e->getMessage();
    }
  }
  // 3) Cadastro de Compra de Produtos
  elseif ($_POST['action'] === 'create_compra') {
    // monta lista de itens a partir de $_POST['produtos']
    $produtos = [];
    foreach ($_POST['produtos'] as $item) {
        if (empty($item['id_produto'])) continue;
        $produtos[] = [
            'id_produto'     => (int)$item['id_produto'],
            'id_variacao'    => $item['id_variacao'] ?: null,
            'quantidade'     => (int)$item['quantidade'],
            'preco_unitario' => (float)$item['preco_unitario'],
        ];
    }
    try {
        $id_despesa = $despesaModel->createDespesa(
            'Compra de Produtos',
            $_POST['descricao'],
            (float)$_POST['valor'],
            $_POST['data_despesa'],
            $_POST['status'],
            $produtos
        );
        // atualize estoques para cada item
foreach ($produtos as $it) {
    if ($it['id_variacao']) {
        // não altera preco_custo de variação
        $variacaoModel->adicionarEstoqueVariacao($it['id_variacao'], $it['quantidade']);
    } else {
        // a) soma no estoque
        $produtoModel->adicionarEstoque($it['id_produto'], $it['quantidade']);
        
        // b) atualiza preco_custo para o preco_unitario desta compra
        $produtoModel->setPrecoCusto($it['id_produto'], $it['preco_unitario']);
    }
}
$msg = "Compra cadastrada com sucesso!";
    } catch (Exception $e) {
        $msg = "Erro ao cadastrar compra: " . $e->getMessage();
    }
}
  // 4) Atualização de Compra de Produtos
elseif ($_POST['action'] === 'update_compra') {
    $id           = intval($_POST['id_despesa']);
    $categoria    = "Compra de Produtos";
    $descricao    = $_POST['descricao'];
    $valor        = $_POST['valor'];
    $data_despesa = $_POST['data_despesa'];
    $status       = $_POST['status'];

    // 1) Reverter estoque do item antigo
    $antigo = $despesaModel->getDespesaById($id)['produtos'][0];
    if ($antigo['id_variacao']) {
        $variacaoModel->adicionarEstoqueVariacao($antigo['id_variacao'], -$antigo['quantidade']);
    } else {
        $produtoModel->adicionarEstoque($antigo['id_produto'], -$antigo['quantidade']);
    }

    // 2) Montar novo array com o item editado
    $produtos = [[
      'id_produto'     => (int)$_POST['id_produto'],
      'id_variacao'    => $_POST['id_variacao'] ?: null,
      'quantidade'     => (int)$_POST['quantidade'],
      'preco_unitario' => (float)$_POST['preco_unitario'],
    ]];

    try {
        // 3) Atualizar o registro no banco
        $despesaModel->updateDespesa(
            $id, $categoria, $descricao, $valor, $data_despesa, $status, $produtos
        );

        // 4) Reaplicar estoque do novo item
$novo = $produtos[0];
if ($novo['id_variacao']) {
    $variacaoModel->adicionarEstoqueVariacao($novo['id_variacao'], $novo['quantidade']);
} else {
    // 1) soma estoque
    $produtoModel->adicionarEstoque($novo['id_produto'], $novo['quantidade']);
    // 2) atualiza preco_custo
    $produtoModel->setPrecoCusto($novo['id_produto'], $novo['preco_unitario']);
}
$msg = "Compra atualizada com sucesso!";
    } catch (Exception $e) {
        // Em caso de erro, a transação do model já faz rollback,
        // mas você não reverteu o estoque antigo: considere capturar
        // a exceção e **remeter** o estoque antigo para não perder produto.
        $msg = "Erro ao atualizar compra: " . $e->getMessage();
    }
}

// 5) Exclusão de Despesa
elseif ($_POST['action'] === 'delete_despesa') {
    $id = intval($_POST['id_despesa']);
    try {
        $despesaModel->deleteDespesa($id);
        $msg = "Despesa excluída com sucesso!";
    } catch (Exception $e) {
        $msg = "Erro ao excluir despesa: " . $e->getMessage();
    }
}
// 6) Exclusão de Compra
elseif ($_POST['action'] === 'delete_compra') {
    // 1) Ler o parâmetro correto (id_compra) em vez de id_despesa
    $id = intval($_POST['id_compra']);

    try {
        // 2) Chamar o método certo no model de Compra
        $compraModel->deleteCompra($id);
        $msg = "Compra excluída com sucesso!";
    } catch (Exception $e) {
        $msg = "Erro ao excluir compra: " . $e->getMessage();
    }
}
}

// --- Listagem ---
$despesas = $despesaModel->getAllDespesas();
$comprasProdutos = $despesaModel->getAllComprasProdutos();

// Carrega os produtos para os formulários de compra
$result = $mysqli->query("SELECT id_produto, nome FROM produtos ORDER BY nome");
$produtosList = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Define a página ativa para o menu
$activePage = isset($_GET['view']) ? $_GET['view'] : 'despesas';


// --- Contadores para o dashboard (apenas mês/ano atual) ---
// Novo cálculo via model:
$totalDespesas       = $despesaModel->getTotalDespesas($dataInicio, $dataFim);
$valorDespesasGerais = $despesaModel->getSumDespesas($dataInicio, $dataFim);

$totalCompras   = count($comprasProdutos);
$valorTotal     = 0;
$pendentes      = 0;

// identifica mês e ano correntes no formato "YYYY-MM"
$mesAtual = $selectedMonth;

foreach ($despesas as $d) {
    // só processa se for do mês/ano atuais
    if (date('Y-m', strtotime($d['data_despesa'])) === $mesAtual) {

        // só contas gerais (não são compras de produto)
        if (stripos($d['categoria'], 'Compra de') === false) {
            // soma o valor dessa despesa
            $valorTotal          += floatval($d['valor']);
            $valorDespesasGerais += floatval($d['valor']);
            $totalDespesas++;
            
            // conta status pendente
            if ($d['status'] === 'pendente') {
                $pendentes++;
            }
        }

    }
}

$limite_por_pagina = 20;
$total_registros = $despesaModel->getTotalDespesas($dataInicio, $dataFim);
$total_paginas = ceil($total_registros / $limite_por_pagina);
$pagina_atual = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Caso a página atual seja maior do que o total de páginas, redirecione ou defina como 1.
if ($pagina_atual > $total_paginas) {
    $pagina_atual = $total_paginas;
}

$offset = ($pagina_atual - 1) * $limite_por_pagina;
$despesas        = $despesaModel->getDespesasPaginadas($offset, $limite_por_pagina, $dataInicio, $dataFim);

if ($pagina_atual > $total_paginas) {
  header("Location: index.php?page=" . $total_paginas);
  exit;
}

// Para a aba de compras, defina o limite e recupere o total e os registros paginados:
$limite_por_pagina_compras = 10;
$total_registros_compras = $despesaModel->getTotalCompras($dataInicio, $dataFim);
$total_paginas_compras = ceil($total_registros_compras / $limite_por_pagina_compras);
$pagina_atual_compras = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Se a página atual for maior que o total, ajuste-a
if ($pagina_atual_compras > $total_paginas_compras) {
    $pagina_atual_compras = $total_paginas_compras;
}

$offset_compras = ($pagina_atual_compras - 1) * $limite_por_pagina_compras;

// Recupere apenas os registros da página atual
$comprasProdutos         = $despesaModel->getComprasPaginadas($offset_compras, $limite_por_pagina_compras, $dataInicio, $dataFim);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestão Financeira</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome para ícones -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Animate.css para animações -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

  <!-- Google Fonts - Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome para ícones -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Animate.css para animações -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

  <!-- Google Fonts - Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <!-- Google Fonts - Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Animate.css -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <!-- AOS - Animate On Scroll -->
  <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Font Awesome para ícones -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <!-- Animate.css para animações -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

  <!-- Google Fonts - Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/inputmask/5.0.8/inputmask.min.js" integrity="sha512-9vR8A1YX0kY5UGeK95Iv+ZVOWXpVrsBfCPXJXq2/VvL8q8W2acKgCcF6MbeTxhrw5AcqZa16+f/HkYs9L2S/6g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- CSS -->
<style>
    :root {
      /* Cores do tema claro */
      --primary: #5a5af3;
      --primary-dark: #4646e6;
      --primary-light: #7b7bf5;
      --secondary: #22c55e;
      --dark: #1e293b;
      --light: #f8fafc;
      --gray-light: #f1f5f9;
      --gray: #e2e8f0;
      --text-dark: #334155;
      --text-light: #94a3b8;
      --danger: #ef4444;
      --warning: #f59e0b;
      --success: #10b981;
      --border-radius: 0.5rem;
      
      /* Cores de fundo */
      --bg-main: #f8fafc;
      --bg-card: #ffffff;
      --bg-sidebar: #5a5af3;
      --bg-input: #f1f5f9;
      --bg-hover: #f1f5f9;
      --bg-active: rgba(90, 90, 243, 0.1);
      
      /* Cores de borda */
      --border-color: #e2e8f0;
      
      /* Cores de texto */
      --text-primary: #334155;
      --text-secondary: #94a3b8;
      --text-sidebar: rgba(255, 255, 255, 0.8);
      
      /* Sombras */
      --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.05);
      --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.05);
    }
    
  

    /* Estilos Gerais */
    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--bg-main);
      color: var(--text-primary);
      transition: all 0.3s ease;
      margin: 0;
      padding: 0;
      overflow-x: hidden;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* Dark mode styles */
    body.dark-mode {
      --bg-main: #000000;
      --bg-sidebar: #333333;
      --bg-card: #1e1e1e;
      --text-primary: #e0e0e0;
      --text-secondary: #aaaaaa;
      --text-sidebar: #ffffff;
      --border-color: #444444;
      
      background-color: #121212;
      color: #f8f9fa;
      .sidebar{
        background-color: var(--bg-sidebar);
      }
    }
    
    body.dark-mode .card,
    body.dark-mode .modal-content,
    body.dark-mode .form-control,
    body.dark-mode .form-select,
    body.dark-mode .table,
    body.dark-mode .list-group-item,
    body.dark-mode .mobile-nav {
      background-color: #1e1e1e;
      color: #f8f9fa;
      border-color: #333;
    }
    
    body.dark-mode .modal-header,
    body.dark-mode .modal-footer {
      border-color: #333;
    }
    
    body.dark-mode .btn-close {
      filter: invert(1) grayscale(100%) brightness(200%);
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
    
    body.dark-mode .sidebar{
      background-color: var(--bg-sidebar);
    }

    body.dark-mode .breadcrumb-item{
      color: #ffffff;
    }

    body.dark-mode th{
      background-color: #2d2d2d;
      color: #ffffff;
    }

    body.dark-mode td{
      background-color: #1c1c1c;
      color: #ffffff;
    }

    body.dark-mode .btn-action{
      background-color: #2d2d2d;
    }

/* padrão: escondido em desktop */
.mobile-nav {
  display: none !important;
}

/* só exibe em tablet/mobile */
@media (max-width: 991.98px) {
  .mobile-nav {
    display: flex !important;
  }
}

    /* Main Content */
    .main-content {
      margin-left: 260px;
      transition: all 0.3s ease;
      flex: 1;
      padding-bottom: 80px; /* Espaço para o mobile nav */
    }

    .main-content.active {
      margin-left: 0;
    }

    /* Top Navbar */
    .top-navbar {
      background-color: var(--bg-card);
      padding: 15px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: var(--shadow-sm);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .search-bar {
      flex: 1;
      max-width: 400px;
      margin: 0 20px;
    }

    .search-bar input {
      width: 100%;
      padding: 10px 15px;
      border: none;
      border-radius: var(--border-radius);
      background-color: var(--bg-input);
      color: var(--text-primary);
    }

    /* Breadcrumb */
    .breadcrumb-nav {
      padding: 10px 20px;
      background-color: var(--bg-card);
      border-bottom: 1px solid var(--border-color);
    }

    .breadcrumb {
      margin: 0;
    }

    .breadcrumb-item a {
      color: var(--text-secondary);
      text-decoration: none;
    }

    .breadcrumb-item.active {
      color: var(--text-primary);
    }

    /* Page Content */
    .page-content {
      padding: 20px;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .page-title {
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0;
      display: flex;
      align-items: center;
    }

    .page-title i {
      margin-right: 10px;
      color: var(--primary);
    }

    /* Stats Cards */
    .stats-row {
      margin-bottom: 25px;
    }

    .stats-card {
      background-color: var(--bg-card);
      border-radius: var(--border-radius);
      padding: 20px;
      box-shadow: var(--shadow-sm);
      display: flex;
      align-items: center;
      margin-bottom: 15px;
      transition: all 0.3s ease;
      border-left: 4px solid transparent;
    }

    .stats-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-md);
    }

    .stats-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-right: 15px;
      color: white;
    }

    .stats-icon.purple {
      background-color: var(--primary);
    }

    .stats-icon.green {
      background-color: var(--success);
    }

    .stats-icon.orange {
      background-color: var(--warning);
    }

    .stats-icon.red {
      background-color: var(--danger);
    }

    .stats-info {
      flex: 1;
    }

    .stats-value {
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0;
      line-height: 1.2;
    }

    .stats-label {
      color: var(--text-secondary);
      margin: 0;
      font-size: 0.875rem;
    }

    /* Content Card */
    .content-card {
      background-color: var(--bg-card);
      border-radius: var(--border-radius);
      box-shadow: var(--shadow-sm);
      margin-bottom: 25px;
      overflow: hidden;
    }

    .content-card-header {
      padding: 15px 20px;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .content-card-title {
      margin: 0;
      font-weight: 600;
      display: flex;
      align-items: center;
    }

    .content-card-title i {
      margin-right: 10px;
      color: var(--primary);
    }

    /* Table */
    .table {
      margin-bottom: 0;
      color: var(--text-primary);
    }

    .table th {
      font-weight: 600;
      background-color: var(--bg-hover);
      border-bottom-width: 1px;
      padding: 12px 15px;
      white-space: nowrap;
    }

    .table td {
      padding: 12px 15px;
      vertical-align: middle;
      border-color: var(--border-color);
    }

    .table tr:hover {
      background-color: var(--bg-hover);
    }

    .badge-status {
      padding: 5px 10px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 500;
      display: inline-block;
    }

    .badge-success {
      background-color: rgba(16, 185, 129, 0.1);
      color: var(--success);
    }

    .badge-warning {
      background-color: rgba(245, 158, 11, 0.1);
      color: var(--warning);
    }

    /* Action Buttons */
    .action-buttons {
      display: flex;
      gap: 8px;
    }

    .btn-action {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
      background-color: var(--bg-hover);
      color: var(--text-primary);
    }

    .btn-action-view:hover {
      background-color: rgba(99, 102, 241, 0.1);
      color: var(--primary);
    }

    .btn-action-edit:hover {
      background-color: rgba(245, 158, 11, 0.1);
      color: var(--warning);
    }

    .btn-action-delete:hover {
      background-color: rgba(239, 68, 68, 0.1);
      color: var(--danger);
    }

    /* Buttons */
    .btn {
      padding: 8px 16px;
      border-radius: var(--border-radius);
      font-weight: 500;
      transition: all 0.2s ease;
    }

    .btn-primary {
      background-color: var(--primary);
      border-color: var(--primary);
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
      border-color: var(--primary-dark);
    }

    .btn-outline-primary {
      color: var(--primary);
      border-color: var(--primary);
    }

    .btn-outline-primary:hover {
      background-color: var(--primary);
      color: white;
    }

    /* Pagination */
    .pagination {
      margin-bottom: 0;
    }

    .page-link {
      color: var(--primary);
      border-color: var(--border-color);
      background-color: var(--bg-card);
    }

    .page-item.active .page-link {
      background-color: var(--primary);
      border-color: var(--primary);
    }

    /* Modals */
    .modal-content {
      background-color: var(--bg-card);
      border: none;
      border-radius: var(--border-radius);
    }

    .modal-header {
      border-bottom-color: var(--border-color);
      padding: 15px 20px;
    }

    .modal-footer {
      border-top-color: var(--border-color);
      padding: 15px 20px;
    }

    .modal-title {
      font-weight: 600;
      display: flex;
      align-items: center;
    }

    .modal-title i {
      margin-right: 10px;
      color: var(--primary);
    }

    /* Form Controls */
    .form-label {
      font-weight: 500;
      color: var(--text-primary);
      margin-bottom: 8px;
    }

    .form-control, .form-select {
      padding: 10px 15px;
      border-radius: var(--border-radius);
      border: 1px solid var(--border-color);
      background-color: var(--bg-input);
      color: var(--text-primary);
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 0.25rem rgba(90, 90, 243, 0.25);
    }

    .input-group-text {
      background-color: var(--bg-hover);
      border-color: var(--border-color);
      color: var(--text-secondary);
    }

    /* Detail Items */
    .detail-item {
      margin-bottom: 15px;
      display: flex;
      border-bottom: 1px solid var(--border-color);
      padding-bottom: 10px;
    }

    .detail-label {
      font-weight: 600;
      width: 120px;
      color: var(--text-secondary);
    }

    .detail-value {
      flex: 1;
      color: var(--text-primary);
    }

    /* Alerts */
    .alert {
      border-radius: var(--border-radius);
      border: none;
    }

    .alert-success {
      background-color: rgba(16, 185, 129, 0.1);
      color: var(--success);
    }

  /* Mobile Bottom Navigation */
.mobile-nav {
  display: none;
  position: fixed;
  bottom: 0;
  left: 0;
  width: 100%;
  background-color: var(--bg-card);
  box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
  z-index: 1000;
  padding: 10px 0;
  justify-content: space-around;
}

.mobile-nav-item {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-secondary);
  text-decoration: none;
  padding: 8px 0;
  transition: all 0.2s ease;
}

.mobile-nav-item i {
  font-size: 1.5rem;
}

.mobile-nav-item.active {
  color: var(--primary);
}

.mobile-nav-item:hover {
  color: var(--primary);
}

@media (max-width: 991.98px) {
  .mobile-nav {
    display: flex;
  }
}


    /* Responsive Styles */
    @media (max-width: 991.98px) {
      .main-content {
        margin-left: 0;
      }
      
      .search-bar {
        display: none;
      }
      
      .mobile-nav {
        display: flex;
      }
      
      .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
      }
      
      .page-header .btn {
        align-self: flex-start;
      }
    }

    @media (max-width: 767.98px) {
      .stats-card {
        margin-bottom: 15px;
      }
      
      .action-buttons {
        flex-wrap: wrap;
      }
      
      .table {
        min-width: 650px;
      }
      
      .modal-dialog {
        margin: 0.5rem;
      }
    }

    @media (max-width: 575.98px) {
      .top-navbar {
        padding: 10px 15px;
      }
      
      .page-content {
        padding: 15px;
      }
      
      .stats-value {
        font-size: 1.25rem;
      }

      
      .mobile-nav-item i {
        margin-bottom: 0;
        font-size: 1.5rem;
      }
      
      .mobile-nav {
        padding: 15px 0;
      }
    }
</style>

</head>

<body>


<!-- Sidebar -->
<?php include_once '../../frontend/includes/sidebar.php'?>

  <!-- Main Content -->
  <div class="main-content">
    <!-- Top Navbar -->
    <div class="top-navbar">
      <div class="d-flex align-items-center">
      <?php require_once __DIR__ . '/../includes/notificacoes.php'; ?>
        <button class="dark-mode-toggle d-none me-3">
          <i class="fas fa-moon"></i>
        </button>
      </div>
    </div>

    <!-- Dark Mode -->
    <?php include_once '../../frontend/includes/darkmode.php'?>

    <!-- Breadcrumb -->
    <nav class="breadcrumb-nav" aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i></a></li>
        <?php if ($activePage == 'despesas'): ?>
          <li class="breadcrumb-item active" aria-current="page">Despesas Gerais</li>
        <?php elseif ($activePage == 'compras'): ?>
          <li class="breadcrumb-item active" aria-current="page">Compras de Produtos</li>
        <?php else: ?>
          <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
        <?php endif; ?>
      </ol>
    </nav>

    <!-- Page Content -->
    <div class="page-content">
      <!-- Alert Messages -->
      <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeInDown" role="alert">
          <i class="fas fa-check-circle me-2"></i>
          <?php echo htmlspecialchars($msg); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <!-- Despesas Gerais -->
      <?php if ($activePage == 'despesas'): ?>
        <div class="page-header">
          <h1 class="page-title"><i class="fas fa-file-invoice-dollar"></i> Despesas Gerais</h1>

          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novaDespesaModal">
            <i class="fas fa-plus me-2"></i> Nova Despesa
          </button>
        </div>

        <!-- Stats Cards -->
        <div class="row stats-row">
          <div class="col-md-3 col-sm-6">
            <div class="stats-card animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
              <div class="stats-icon green">
                <i class="fas fa-dollar-sign"></i>
              </div>
              <div class="stats-info">
                <h3 class="stats-value">R$ <?php echo number_format($valorTotal, 2, ',', '.'); ?></h3>
                <p class="stats-label">Total Despesas (mês)</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Lista de Despesas -->
        <div class="content-card animate__animated animate__fadeIn" style="animation-delay: 0.4s;">
          <div class="content-card-header">
            <h5 class="content-card-title"><i class="fas fa-list"></i> Lista de Despesas</h5>
            <div>
            </div>
          </div>
          <form method="get" class="row g-2 align-items-end mb-3">
  <input type="hidden" name="view" value="<?= $activePage ?>">
  <div class="col-auto">
    <label for="filter-month" class="form-label">Mês</label>
    <input type="month"
           id="filter-month"
           name="month"
           class="form-control"
           value="<?= htmlspecialchars($selectedMonth) ?>">
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-primary">Filtrar</button>
  </div>
</form>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Categoria</th>
                  <th>Descrição</th>
                  <th>Valor</th>
                  <th>Data</th>
                  <th>Status</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($despesas)): ?>
                  <?php foreach ($despesas as $d): ?>
                    <?php if (stripos($d['categoria'], 'compra de') === false): ?>
                      <tr>
                        <td>#<?php echo htmlspecialchars($d['id_despesa']); ?></td>
                        <td><?php echo htmlspecialchars($d['categoria']); ?></td>
                        <td><?php echo htmlspecialchars($d['descricao']); ?></td>
                        <td>R$ <?php echo number_format($d['valor'], 2, ',', '.'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($d['data_despesa'])); ?></td>
                        <td>
                          <?php if ($d['status'] === 'paga'): ?>
                            <span class="badge-status badge-success">Finalizada</span>
                          <?php else: ?>
                            <span class="badge-status badge-warning">Pendente</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <div class="action-buttons">
                            <button type="button" class="btn-action btn-action-view" data-bs-toggle="modal"
                              data-bs-target="#viewDespesaModal" data-id="<?php echo htmlspecialchars($d['id_despesa']); ?>"
                              data-categoria="<?php echo htmlspecialchars($d['categoria']); ?>"
                              data-descricao="<?php echo htmlspecialchars($d['descricao']); ?>"
                              data-valor="<?php echo htmlspecialchars($d['valor']); ?>"
                              data-data="<?php echo htmlspecialchars($d['data_despesa']); ?>"
                              data-status="<?php echo htmlspecialchars($d['status']); ?>">
                              <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn-action btn-action-edit" data-bs-toggle="modal"
                              data-bs-target="#editDespesaModal" data-id="<?php echo htmlspecialchars($d['id_despesa']); ?>"
                              data-categoria="<?php echo htmlspecialchars($d['categoria']); ?>"
                              data-descricao="<?php echo htmlspecialchars($d['descricao']); ?>"
                              data-valor="<?php echo htmlspecialchars($d['valor']); ?>"
                              data-data="<?php echo htmlspecialchars($d['data_despesa']); ?>"
                              data-status="<?php echo htmlspecialchars($d['status']); ?>">
                              <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn-action btn-action-delete" data-bs-toggle="modal"
                              data-bs-target="#deleteDespesaModal" data-id="<?php echo htmlspecialchars($d['id_despesa']); ?>"
                              data-categoria="<?php echo htmlspecialchars($d['categoria']); ?>">
                              <i class="fas fa-trash-alt"></i>
                            </button>
                          </div>
                        </td>
                      </tr>
                    <?php endif; ?>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center">Nenhuma despesa geral cadastrada.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="d-flex justify-content-center p-3">
          <nav aria-label="Page navigation">
  <ul class="pagination">
    <!-- Botão "Anterior" -->
    <?php if ($pagina_atual > 1): ?>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $pagina_atual - 1 ?>">Anterior</a>
      </li>
    <?php else: ?>
      <li class="page-item disabled">
        <span class="page-link">Anterior</span>
      </li>
    <?php endif; ?>

    <!-- Botões de Páginas -->
    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
      <li class="page-item <?= $i == $pagina_atual ? 'active' : '' ?>">
      <a class="page-link" href="?view=despesas&page=<?= $i ?>&month=<?= $selectedMonth ?>">
  <?= $i ?>
</a>
      </li>
    <?php endfor; ?>

    <!-- Botão "Próxima" -->
    <?php if ($pagina_atual < $total_paginas): ?>
      <li class="page-item">
        <a class="page-link" href="?page=<?= $pagina_atual + 1 ?>">Próxima</a>
      </li>
    <?php else: ?>
      <li class="page-item disabled">
        <span class="page-link">Próxima</span>
      </li>
    <?php endif; ?>
  </ul>
</nav>

          </div>
        </div>
      <?php endif; ?>

      <!-- Compras de Produtos -->
      <?php if ($activePage == 'compras'): ?>
        <div class="page-header">
          <h1 class="page-title"><i class="fas fa-shopping-bag"></i> Compras de Produtos</h1>

          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#novaCompraModal">
            <i class="fas fa-plus me-2"></i> Nova Compra
          </button>
        </div>

        <!-- Lista de Compras -->
        <div class="content-card animate__animated animate__fadeIn">
          <div class="content-card-header">
            <h5 class="content-card-title"><i class="fas fa-list"></i> Lista de Compras</h5>
            <div>
            </div>
          </div>
          <form method="get" class="row g-2 align-items-end mb-3">
  <input type="hidden" name="view" value="<?= $activePage ?>">
  <div class="col-auto">
    <label for="filter-month" class="form-label">Mês</label>
    <input type="month"
           id="filter-month"
           name="month"
           class="form-control"
           value="<?= htmlspecialchars($selectedMonth) ?>">
  </div>
  <div class="col-auto">
    <button type="submit" class="btn btn-primary">Filtrar</button>
  </div>
</form>

          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Produto</th>
                  <th>Foto</th>
                  <th>Quantidade</th>
                  <th>Preço Unit.</th>
                  <th>Valor Total</th>
                  <th>Data</th>
                  <th>Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($comprasProdutos)): ?>
                  <?php foreach ($comprasProdutos as $cp): ?>
                    <tr>
                      <td>#<?php echo htmlspecialchars($cp['id_despesa']); ?></td>
                      <td><?php echo htmlspecialchars($cp['produto_nome']); ?></td>
                      <td>
                        <?php if (!empty($cp['produto_foto'])): ?>
                          <img src="<?php echo htmlspecialchars($cp['produto_foto']); ?>" alt="Foto" class="img-thumbnail"
                            width="40">
                        <?php else: ?>
                          <i class="fas fa-image text-muted"></i>
                        <?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars($cp['quantidade']); ?></td>
                      <td>R$ <?php echo number_format($cp['preco_unitario'], 2, ',', '.'); ?></td>
                      <td>R$ <?php echo number_format($cp['despesa_valor'], 2, ',', '.'); ?></td>
                      <td><?php echo date('d/m/Y', strtotime($cp['data_despesa'])); ?></td>
                      <td>
                        <!-- Botão para visualizar a compra -->
  <button
            type="button"
            class="btn btn-sm btn-outline-secondary"
            data-bs-toggle="modal"
            data-bs-target="#viewDespesaModal"
            data-id="<?php echo htmlspecialchars($d['id_despesa']); ?>"
            data-categoria="<?php echo htmlspecialchars($d['categoria']); ?>"
            data-descricao="<?php echo htmlspecialchars($d['descricao']); ?>"
            data-valor="<?php echo htmlspecialchars($d['valor']); ?>"
            data-data="<?php echo htmlspecialchars($d['data_despesa']); ?>"
            data-status="<?php echo htmlspecialchars($d['status']); ?>"
          >
            <i class="fas fa-eye"></i>
          </button>
                        <!-- Botão para editar a compra -->
                      <button
            type="button"
            class="btn btn-sm btn-outline-primary"
            data-bs-toggle="modal"
            data-bs-target="#editDespesaModal"
            data-id="<?php echo htmlspecialchars($d['id_despesa']); ?>"
            data-categoria="<?php echo htmlspecialchars($d['categoria']); ?>"
            data-descricao="<?php echo htmlspecialchars($d['descricao']); ?>"
            data-valor="<?php echo htmlspecialchars($d['valor']); ?>"
            data-data="<?php echo htmlspecialchars($d['data_despesa']); ?>"
            data-status="<?php echo htmlspecialchars($d['status']); ?>"
          >
            <i class="fas fa-edit"></i>
          </button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="9" class="text-center">Nenhuma compra cadastrada.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="d-flex justify-content-center p-3">
          <nav aria-label="Page navigation">
    <ul class="pagination">
        <!-- Botão "Anterior" -->
        <?php if ($pagina_atual_compras > 1): ?>
            <li class="page-item">
                <a class="page-link" href="?view=compras&page=<?= $pagina_atual_compras - 1 ?>">Anterior</a>
            </li>
        <?php else: ?>
            <li class="page-item disabled">
                <span class="page-link">Anterior</span>
            </li>
        <?php endif; ?>

        <!-- Links para cada página -->
        <?php for ($i = 1; $i <= $total_paginas_compras; $i++): ?>
            <li class="page-item <?= $i == $pagina_atual_compras ? 'active' : '' ?>">
                <a class="page-link" href="?view=compras&page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>

        <!-- Botão "Próxima" -->
        <?php if ($pagina_atual_compras < $total_paginas_compras): ?>
            <li class="page-item">
                <a class="page-link" href="?view=compras&page=<?= $pagina_atual_compras + 1 ?>">Próxima</a>
            </li>
        <?php else: ?>
            <li class="page-item disabled">
                <span class="page-link">Próxima</span>
            </li>
        <?php endif; ?>
    </ul>
</nav>

          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Mobile Bottom Navigation -->
  <!-- Substituir o mobile navigation atual por este novo código -->
<!-- Mobile Bottom Navigation -->
<div class="mobile-nav">
  <a href="../produtos/index.php" class="mobile-nav-item <?php echo $activePage == 'produtos' ? 'active' : ''; ?>">
    <i class="fas fa-box"></i>
    <span>Produtos</span>
  </a>
  <a href="../clientes/clientes.php" class="mobile-nav-item <?php echo $activePage == 'clientes' ? 'active' : ''; ?>">
    <i class="fas fa-users"></i>
    <span>Clientes</span>
  </a>
  <a href="?view=despesas&page=1" class="mobile-nav-item <?php echo $activePage == 'despesas' ? 'active' : ''; ?>">
    <i class="fas fa-file-invoice-dollar"></i>
    <span>Despesas</span>
  </a>
  <a href="?view=compras&page=1" class="mobile-nav-item <?php echo $activePage == 'compras' ? 'active' : ''; ?>">
    <i class="fas fa-shopping-bag"></i>
    <span>Compras</span>
  </a>
  <a href="#" id="mobileMenuToggle" class="mobile-nav-item">
     <i class="fas fa-ellipsis-h"></i>
     <span>Mais</span>
   </a>
</div>

  <!-- MODAIS PARA DESPESAS -->

  <!-- Modal Nova Despesa -->
  <div class="modal fade" id="novaDespesaModal" tabindex="-1" aria-labelledby="novaDespesaModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="novaDespesaModalLabel"><i class="fas fa-plus-circle me-2"></i> Nova Despesa Geral
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form action="index.php?page=1" method="post" class="row g-3">
            <div class="col-md-6">
              <label for="categoria" class="form-label">Categoria</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-tag"></i></span>
                <input type="text" class="form-control" id="categoria" name="categoria"
                  placeholder="Ex: Aluguel, Luz, Água..." required>
              </div>
            </div>

            <div class="col-md-6">
              <label for="valor" class="form-label">Valor</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                <input type="number" step="0.01" class="form-control" id="valor" name="valor" required>
              </div>
            </div>

            <div class="col-md-6">
              <label for="data_despesa" class="form-label">Data</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                <input type="date" class="form-control" id="data_despesa" name="data_despesa" required>
              </div>
            </div>

            <div class="col-md-6">
              <label for="status" class="form-label">Status</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                <select class="form-select" id="status" name="status">
                  <option value="paga">Paga</option>
                  <option value="pendente">Pendente</option>
                </select>
              </div>
            </div>

            <div class="col-12">
              <label for="descricao" class="form-label">Descrição</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                <textarea class="form-control" id="descricao" name="descricao" rows="3" required></textarea>
              </div>
            </div>

            <div class="col-12 mt-4 text-end">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" name="action" value="create_despesa" class="btn btn-primary">
                <i class="fas fa-save me-2"></i> Salvar Despesa
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Visualizar Despesa -->
  <div class="modal fade" id="viewDespesaModal" tabindex="-1" aria-labelledby="viewDespesaModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewDespesaModalLabel"><i class="fas fa-eye me-2"></i> Detalhes da Despesa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="detail-item">
            <div class="detail-label">ID</div>
            <div class="detail-value" id="view-despesa-id"></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Categoria</div>
            <div class="detail-value" id="view-despesa-categoria"></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Valor</div>
            <div class="detail-value" id="view-despesa-valor"></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Data</div>
            <div class="detail-value" id="view-despesa-data"></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Status</div>
            <div class="detail-value" id="view-despesa-status"></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Descrição</div>
            <div class="detail-value" id="view-despesa-descricao"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Editar Despesa -->
  <div class="modal fade" id="editDespesaModal" tabindex="-1" aria-labelledby="editDespesaModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editDespesaModalLabel"><i class="fas fa-edit me-2"></i> Editar Despesa Geral</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form action="index.php?page=1" method="post" class="row g-3">
            <input type="hidden" name="id_despesa" id="edit-despesa-id">

            <div class="col-md-6">
              <label for="edit-categoria" class="form-label">Categoria</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-tag"></i></span>
                <input type="text" class="form-control" id="edit-categoria" name="categoria" required>
              </div>
            </div>

            <div class="col-md-6">
              <label for="edit-valor" class="form-label">Valor</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                <input type="number" step="0.01" class="form-control" id="edit-valor" name="valor" required>
              </div>
            </div>

            <div class="col-md-6">
              <label for="edit-data_despesa" class="form-label">Data</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                <input type="date" class="form-control" id="edit-data_despesa" name="data_despesa" required>
              </div>
            </div>

            <div class="col-md-6">
              <label for="edit-status" class="form-label">Status</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                <select class="form-select" id="edit-status" name="status">
                  <option value="paga">Paga</option>
                  <option value="pendente">Pendente</option>
                </select>
              </div>
            </div>

            <div class="col-12">
              <label for="edit-descricao" class="form-label">Descrição</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                <textarea class="form-control" id="edit-descricao" name="descricao" rows="3" required></textarea>
              </div>
            </div>

            <div class="col-12 mt-4 text-end">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" name="action" value="update_despesa" class="btn btn-primary">
                <i class="fas fa-save me-2"></i> Atualizar Despesa
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Excluir Despesa -->
  <div class="modal fade" id="deleteDespesaModal" tabindex="-1" aria-labelledby="deleteDespesaModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteDespesaModalLabel"><i class="fas fa-trash-alt me-2"></i> Excluir Despesa
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Tem certeza que deseja excluir a despesa <strong id="delete-despesa-categoria"></strong>?</p>
          <p class="text-danger">Esta ação não pode ser desfeita.</p>

          <form action="index.php?page=1" method="post">
            <input type="hidden" name="id_despesa" id="delete-despesa-id">

            <div class="text-end mt-4">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" name="action" value="delete_despesa" class="btn btn-danger">
                <i class="fas fa-trash-alt me-2"></i> Excluir
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

 
<!-- Modal Nova Compra -->
<div class="modal fade" id="novaCompraModal" tabindex="-1" aria-labelledby="novaCompraModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> Nova Compra de Produtos</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="formNovaCompra" method="post" action="index.php?page=compras&page=1">
          <input type="hidden" name="categoria" value="Compra de Produtos">
          
          <!-- Tabela de itens -->
          <table class="table table-sm" id="tabelaItens">
            <thead>
              <tr>
                <th>Produto</th>
                <th>Variação</th>
                <th width="80">Qtd.</th>
                <th width="120">Preço Unit.</th>
                <th width="120">Total</th>
                <th width="40"><button type="button" class="btn btn-sm btn-success" id="btnAddLinha">
                  <i class="fas fa-plus"></i>
                </button></th>
              </tr>
            </thead>
            <tbody>
              <!-- linha-template clonada pelo JS -->
              <tr class="linha-item d-none" id="linhaTemplate">
  <td>
    <select disabled name="produtos[0][id_produto]" class="form-select produto-select" required>
      <option value="">Selecione...</option>
      <?php foreach($produtosList as $p): ?>
        <option value="<?= $p['id_produto'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
      <?php endforeach; ?>
    </select>
  </td>
  <td>
    <select disabled name="produtos[0][id_variacao]" class="form-select variacao-select">
      <option value="">—</option>
    </select>
  </td>
  <td>
    <input disabled type="number" name="produtos[0][quantidade]" class="form-control qtd-input" min="1" value="1" required>
  </td>
  <td>
    <input disabled type="number" step="0.01" name="produtos[0][preco_unitario]" class="form-control pu-input" required>
  </td>
  <td>
    <!-- aqui não precisa enviar ao servidor, só exibição -->
    <input disabled type="text" class="form-control total-item" readonly>
  </td>
  <td>
    <button disabled type="button" class="btn btn-sm btn-danger btn-remove-linha">
      <i class="fas fa-trash-alt"></i>
    </button>
  </td>
</tr>
            </tbody>
          </table>

          <!-- Total geral, data, status e descrição -->
          <div class="row g-3">
            <div class="col-md-4">
              <label>Valor Total</label>
              <input type="text" id="valorGeral" name="valor" class="form-control" readonly>
            </div>
            <div class="col-md-4">
              <label>Data</label>
              <input type="date" name="data_despesa" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-4">
              <label>Status</label>
              <select name="status" class="form-select" required>
                <option value="paga">Paga</option>
                <option value="pendente">Pendente</option>
              </select>
            </div>
            <div class="col-12">
              <label>Descrição</label>
              <textarea name="descricao" rows="2" class="form-control" required></textarea>
            </div>
          </div>

          <div class="mt-4 text-end">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" name="action" value="create_compra" class="btn btn-primary">
              <i class="fas fa-save me-2"></i> Salvar Compra
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>


<!-- jQuery e Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>



<!-- jQuery e Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>





  <!-- Modal Visualizar Compra -->
  <div class="modal fade" id="viewCompraModal" tabindex="-1" aria-labelledby="viewCompraModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewCompraModalLabel"><i class="fas fa-eye me-2"></i> Detalhes da Compra</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="detail-item">
            <div class="detail-label">ID</div>
            <div class="detail-value" id="view-compra-id"></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Produto</div>
            <div class="detail-value" id="view-compra-produto"></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Quantidade</div>
            <div class="detail-value" id="view-compra-quantidade"></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Preço Unitário</div>
            <div class="detail-value" id="view-compra-preco"></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Valor Total</div>
            <div class="detail-value" id="view-compra-valor"></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Data</div>
            <div class="detail-value" id="view-compra-data"></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Status</div>
            <div class="detail-value" id="view-compra-status"></div>
          </div>
          <div class="detail-item">
            <div class="detail-label">Descrição</div>
            <div class="detail-value" id="view-compra-descricao"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Editar Compra -->
  <div class="modal fade" id="editCompraModal" tabindex="-1" aria-labelledby="editCompraModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editCompraModalLabel"><i class="fas fa-edit me-2"></i> Editar Compra de Produto
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form action="index.php?page=compras&page=1" method="post" class="row g-3">
            <input type="hidden" name="id_despesa" id="edit-compra-id">
            <input type="hidden" name="categoria" value="Compra de Produtos">

            <div class="col-md-6">
              <label for="edit-id_produto" class="form-label">Produto</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-box"></i></span>
                <select class="form-select" id="edit-id_produto" name="id_produto" required>
                  <option value="">Selecione um produto</option>
                  <?php foreach ($produtosList as $p): ?>
                    <option value="<?php echo htmlspecialchars($p['id_produto']); ?>">
                      <?php echo htmlspecialchars($p['nome']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <!-- Alterado: select de variação com id "edit-id_variacao" -->
            <div class="col-md-6">
              <label for="edit-id_variacao" class="form-label">Variação</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-tags"></i></span>
                <select class="form-select" id="edit-id_variacao" name="id_variacao">
                  <option value="">Selecione uma variação (opcional)</option>
                </select>
              </div>
            </div>

            <div class="col-md-6">
              <label for="edit-quantidade" class="form-label">Quantidade</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-sort-numeric-up"></i></span>
                <input type="number" class="form-control" id="edit-quantidade" name="quantidade" required>
              </div>
            </div>

            <div class="col-md-6">
              <label for="edit-preco_unitario" class="form-label">Preço Unitário</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                <input type="number" step="0.01" class="form-control" id="edit-preco_unitario" name="preco_unitario"
                  required>
              </div>
            </div>

            <div class="col-md-6">
              <label for="edit-preco_venda" class="form-label">Preço de Custo</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-tags"></i></span>
                <input type="number" step="0.01" class="form-control" id="edit-preco_venda" name="preco_venda" required>
              </div>
            </div>


            <div class="col-md-6">
              <label for="edit-valor" class="form-label">Valor Total</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                <input type="number" step="0.01" class="form-control" id="edit-valor" name="valor" required>
              </div>
            </div>

            <div class="col-md-6">
              <label for="edit-data_despesa" class="form-label">Data</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                <input type="date" class="form-control" id="edit-data_despesa" name="data_despesa" required>
              </div>
            </div>

            <div class="col-md-6">
              <label for="edit-status" class="form-label">Status</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                <select class="form-select" id="edit-status" name="status">
                  <option value="paga">Paga</option>
                  <option value="pendente">Pendente</option>
                </select>
              </div>
            </div>

            <div class="col-12">
              <label for="edit-descricao" class="form-label">Descrição da Compra</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-align-left"></i></span>
                <textarea class="form-control" id="edit-descricao" name="descricao" rows="3" required></textarea>
              </div>
            </div>

            <div class="col-12 mt-4 text-end">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" name="action" value="update_compra" class="btn btn-primary">
                <i class="fas fa-save me-2"></i> Atualizar Compra
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Excluir Compra -->
  <div class="modal fade" id="deleteCompraModal" tabindex="-1" aria-labelledby="deleteCompraModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="deleteCompraModalLabel"><i class="fas fa-trash-alt me-2"></i> Excluir Compra</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Tem certeza que deseja excluir a compra do produto <strong id="delete-compra-produto"></strong>?</p>
          <p class="text-danger">Esta ação não pode ser desfeita.</p>

          <form action="index.php?page=compras&page=1" method="post">
            <input type="hidden" name="id_compra" id="delete-compra-id">
            <div class="text-end mt-4">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" name="action" value="delete_compra" class="btn btn-danger">
                <i class="fas fa-trash-alt me-2"></i> Excluir
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <script>
    // Inicializar tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Toggle sidebar
// dispara o clique no sidebarToggle quando o "Mais" for acionado
document.getElementById('mobileMenuToggle').addEventListener('click', function(e) {
  e.preventDefault();
  document.getElementById('sidebarToggle').click();
});

    

    // Mobile menu toggle
    document.getElementById('mobileMenuToggle').addEventListener('click', function () {
      document.querySelector('.sidebar').classList.toggle('show');
    });

    // Cálculo automático do valor total para nova compra e edição
    $('#quantidade, #preco_unitario').on('input', function () {
  var quantidade = parseFloat($('#quantidade').val().replace(',', '.')) || 0;
  var precoUnitario = parseFloat($('#preco_unitario').val().replace(',', '.')) || 0;
  console.log('Quantidade:', quantidade, 'Preço Unitário:', precoUnitario);
  var valorTotal = quantidade * precoUnitario;
  console.log('Valor Total calculado:', valorTotal);
  $('#valor').val(valorTotal.toFixed(2));
});
    $('#edit-quantidade, #edit-preco_unitario').on('input', function () {
      var quantidade = parseFloat($('#edit-quantidade').val()) || 0;
      var precoUnitario = parseFloat($('#edit-preco_unitario').val()) || 0;
      var valorTotal = quantidade * precoUnitario;
      $('#edit-valor').val(valorTotal.toFixed(2));
    });

    // Loader


    // Animação dos cards de estatísticas
    $('.stats-card').each(function (index) {
      var card = $(this);
      setTimeout(function () {
        card.addClass('animate__animated animate__fadeIn');
      }, index * 100);
    });

    // Modal de visualização de despesa
    $('#viewDespesaModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var id = button.data('id');
      var categoria = button.data('categoria');
      var descricao = button.data('descricao');
      var valor = button.data('valor');
      var data = button.data('data');
      var status = button.data('status');

      $('#view-despesa-id').text('#' + id);
      $('#view-despesa-categoria').text(categoria);
      $('#view-despesa-descricao').text(descricao);
      $('#view-despesa-valor').text('R$ ' + parseFloat(valor).toFixed(2).replace('.', ','));

      var dataFormatada = new Date(data);
      var dia = dataFormatada.getDate().toString().padStart(2, '0');
      var mes = (dataFormatada.getMonth() + 1).toString().padStart(2, '0');
      var ano = dataFormatada.getFullYear();
      $('#view-despesa-data').text(dia + '/' + mes + '/' + ano);

      var statusText = status === 'paga' ? 'Finalizada' : 'Pendente';
      var statusClass = status === 'paga' ? 'badge-success' : 'badge-warning';
      $('#view-despesa-status').html('<span class="badge-status ' + statusClass + '">' + statusText + '</span>');
    });

    // Modal de edição de despesa (geral)
    $('#editDespesaModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var id = button.data('id');
      var categoria = button.data('categoria');
      var descricao = button.data('descricao');
      var valor = button.data('valor');
      var data = button.data('data');
      var status = button.data('status');

      $('#edit-despesa-id').val(id);
      $('#edit-categoria').val(categoria);
      $('#edit-descricao').val(descricao);
      $('#edit-valor').val(valor);
      $('#edit-data_despesa').val(data);
      $('#edit-status').val(status);
    });

    // Modal de exclusão de despesa
    $('#deleteDespesaModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var id = button.data('id');
      var categoria = button.data('categoria');

      $('#delete-despesa-id').val(id);
      $('#delete-despesa-categoria').text(categoria);
    });

    // Modal de visualização de compra
    $('#viewCompraModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var id = button.data('id');
      var produto = button.data('produto');
      var quantidade = button.data('quantidade');
      var preco = button.data('preco');
      var valor = button.data('valor');
      var data = button.data('data');
      var status = button.data('status');
      var descricao = button.data('descricao');

      $('#view-compra-id').text('#' + id);
      $('#view-compra-produto').text(produto);
      $('#view-compra-quantidade').text(quantidade);
      $('#view-compra-preco').text('R$ ' + parseFloat(preco).toFixed(2).replace('.', ','));
      $('#view-compra-valor').text('R$ ' + parseFloat(valor).toFixed(2).replace('.', ','));
      $('#view-compra-descricao').text(descricao);

      var dataFormatada = new Date(data);
      var dia = dataFormatada.getDate().toString().padStart(2, '0');
      var mes = (dataFormatada.getMonth() + 1).toString().padStart(2, '0');
      var ano = dataFormatada.getFullYear();
      $('#view-compra-data').text(dia + '/' + mes + '/' + ano);

      var statusText = status === 'paga' ? 'Finalizada' : 'Pendente';
      var statusClass = status === 'paga' ? 'badge-success' : 'badge-warning';
      $('#view-compra-status').html('<span class="badge-status ' + statusClass + '">' + statusText + '</span>');
    });

    // Modal de edição de compra (para compras de produtos)
    $('#editCompraModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var idDespesa = button.data('id');

      // Chama o endpoint para obter os dados completos da compra
      $.ajax({
        url: 'index.php',
        type: 'GET',
        data: { action: 'getCompra', id: idDespesa },
        dataType: 'json',
        success: function (compra) {
          if (compra && compra.produtos && compra.produtos.length > 0) {
            var item = compra.produtos[0]; // Considerando que há apenas um item

            // Preenche os campos gerais (dados da tabela despesas)
            $('#edit-compra-id').val(compra.id_despesa);
            $('#edit-descricao').val(compra.descricao);
            $('#edit-data_despesa').val(compra.data_despesa); // Data da despesa
            $('#edit-status').val(compra.status);
            $('#edit-valor').val(compra.valor); // Valor total

            // Preenche os campos do item da compra (dados da tabela despesa_produtos)
            $('#edit-quantidade').val(item.quantidade);
            $('#edit-preco_unitario').val(item.preco_unitario);
            $('#edit-id_produto').val(item.id_produto);

            // Carrega as variações via AJAX (o código já está correto)
            $.ajax({
              url: 'index.php',
              type: 'GET',
              data: { action: 'getVariacoes', produto: item.id_produto },
              dataType: 'json',
              success: function (variacoes) {
                var select = $('#edit-id_variacao');
                select.empty().append('<option value="">Selecione uma variação (opcional)</option>');
                $.each(variacoes, function (index, variacao) {
                  select.append('<option value="' + variacao.id_variacao + '">' + variacao.descricao + '</option>');
                });
                select.val(item.id_variacao);
              },
              error: function (xhr, status, error) {
                console.error('Erro ao carregar variações: ' + error);
              }
            });
          }
        },
        error: function (xhr, status, error) {
          console.error('Erro ao obter dados da compra: ' + error);
        }
      });
    });


    // Modal de exclusão de compra
    $('#deleteCompraModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget);
      var id = button.data('id');
      var produto = button.data('produto');

      $('#delete-compra-id').val(id);
      $('#delete-compra-produto').text(produto);
    });



  </script>

<!-- Adicionar o CSS do mobile navigation -->
<style>
/* Mobile Bottom Navigation Styles */
.mobile-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 60px;
  background-color: #ffffff;
  box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
  display: flex;
  justify-content: space-around;
  align-items: center;
  z-index: 1001;
  padding: 0 10px;
}

.mobile-nav-item {
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

.mobile-nav-item i {
  font-size: 20px;
  margin-bottom: 4px;
  transition: all 0.2s;
}

.mobile-nav-item:hover, 
.mobile-nav-item:active,
.mobile-nav-item.active {
  color: var(--primary);
}

.mobile-nav-item:hover i, 
.mobile-nav-item:active i,
.mobile-nav-item.active i {
  transform: translateY(-2px);
}

.mobile-nav-item::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 3px;
  background-color: var(--primary);
  transition: width 0.2s;
  border-radius: 3px 3px 0 0;
}

.mobile-nav-item:hover::after,
.mobile-nav-item:active::after,
.mobile-nav-item.active::after {
  width: 40%;
}

.toggle-sidebar {
  position: relative;
}

.toggle-sidebar::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 0;
  transform: translateY(-50%);
  height: 60%;
  width: 1px;
  background-color: rgba(0, 0, 0, 0.1);
}

</style>

<!-- Adicionar o JavaScript para o toggle do sidebar -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Toggle drawer when clicking the "Mais" button in the bottom navigation
  const maisButton = document.querySelector(".mobile-nav-item.toggle-sidebar");
  if (maisButton) {
    maisButton.addEventListener("click", function(e) {
      e.preventDefault();
      toggleDrawer();
    });
  }
});
</script>
<script>
$(function(){

  // — NOVA COMPRA — carregamento de variações
  $('#id_produto').on('change', function() {
    const produtoId = $(this).val();
    const sel = $('#id_variacao')
      .empty()
      .append('<option value="">Selecione uma variação (opcional)</option>');
    if (produtoId) {
      $.get('index.php', { action:'getVariacoes', produto:produtoId }, 'json')
       .done(data => data.forEach(v =>
         sel.append(`<option value="${v.id_variacao}">${v.descricao}</option>`)
       ))
       .fail(() => console.error('Erro ao carregar variações'));
    }
  });

  // — EDITAR COMPRA — pré-preenche todos os campos do modal
  $('#editCompraModal').on('show.bs.modal', function(e) {
    const id = $(e.relatedTarget).data('id');
    $.get('index.php', { action:'getCompra', id }, 'json')
     .done(compra => {
       const item = compra.produtos[0] || {};

       // Campos gerais
       $('#edit-compra-id').val(compra.id_despesa);
       $('#edit-descricao').val(compra.descricao);
       $('#edit-data_despesa').val(compra.data_despesa);
       $('#edit-status').val(compra.status);
       $('#edit-valor').val(compra.valor);

       // Campos do item
       $('#edit-quantidade').val(item.quantidade);
       $('#edit-preco_unitario').val(item.preco_unitario);
       $('#edit-preco_venda').val(item.preco_venda || '');
       $('#edit-id_produto').val(item.id_produto);

       // Carrega variações e seleciona a atual
       $.get('index.php', { action:'getVariacoes', produto:item.id_produto }, 'json')
        .done(vars => {
          const selVar = $('#edit-id_variacao')
            .empty()
            .append('<option value="">Selecione uma variação (opcional)</option>');
          vars.forEach(v =>
            selVar.append(`<option value="${v.id_variacao}">${v.descricao}</option>`)
          );
          selVar.val(item.id_variacao);
        });
     })
     .fail(() => console.error('Erro ao obter dados da compra'));
  });

  // — EDITAR COMPRA — se trocar produto dentro do modal
  $('#edit-id_produto').on('change', function() {
    const produtoId = $(this).val();
    const sel = $('#edit-id_variacao')
      .empty()
      .append('<option value="">Selecione uma variação (opcional)</option>');
    if (produtoId) {
      $.get('index.php', { action:'getVariacoes', produto:produtoId }, 'json')
       .done(data => data.forEach(v =>
         sel.append(`<option value="${v.id_variacao}">${v.descricao}</option>`)
       ))
       .fail(() => console.error('Erro ao carregar variações'));
    }
  });
    $('#edit-quantidade, #edit-preco_venda').on('input', function() {
    const q = parseFloat($('#edit-quantidade').val()) || 0;
    const custo = parseFloat($('#edit-preco_venda').val()) || 0;
    $('#edit-valor').val((q * custo).toFixed(2));
  });

});
</script>

<script>
$(function(){
  // Recalcula o total geral somando todos os totais de linha
  function recalcTotalGeral(){
    let soma = 0;
    $('#tabelaItens tbody tr').not('#linhaTemplate').each(function(){
      let v = parseFloat($(this).find('.total-item').val().replace(',','.'))||0;
      soma += v;
    });
    $('#valorGeral').val(soma.toFixed(2));
  }

  // Adiciona uma nova linha a partir do template
  $('#btnAddLinha').on('click', function(){
    const tbody = $('#tabelaItens tbody');
    const index = tbody.find('tr').not('#linhaTemplate').length;
    const nova = $('#linhaTemplate')
      .clone()
      .removeAttr('id')
      .removeClass('d-none');

    // atualiza os name e habilita campos
    nova.find('select.produto-select')
       .attr('name', `produtos[${index}][id_produto]`)
       .prop('disabled', false);
    nova.find('select.variacao-select')
       .attr('name', `produtos[${index}][id_variacao]`)
       .prop('disabled', false)
       .empty().append('<option value="">—</option>');
    nova.find('input.qtd-input')
       .attr('name', `produtos[${index}][quantidade]`)
       .prop('disabled', false);
    nova.find('input.pu-input')
       .attr('name', `produtos[${index}][preco_unitario]`)
       .prop('disabled', false);
    nova.find('input.total-item')
       .prop('disabled', false)
       .val('');
    nova.find('.btn-remove-linha')
       .prop('disabled', false);

    tbody.append(nova);
  });

  // Remove linha e recalcula
  $(document).on('click', '.btn-remove-linha', function(){
    $(this).closest('tr').remove();
    recalcTotalGeral();
  });

  // Carrega variações dinamicamente
  $(document).on('change', '.produto-select', function(){
    const linha = $(this).closest('tr');
    const pid   = $(this).val();
    const sel   = linha.find('.variacao-select').empty().append('<option value="">—</option>');
    if (!pid) return;
    $.get('index.php', {action:'getVariacoes', produto:pid}, 'json')
     .done(vars => {
       vars.forEach(v => sel.append(`<option value="${v.id_variacao}">${v.descricao}</option>`));
     });
  });

  // Calcula total da linha e geral
  $(document).on('input', '.qtd-input, .pu-input', function(){
    const linha = $(this).closest('tr');
    const qtd   = parseFloat(linha.find('.qtd-input').val())||0;
    const pu    = parseFloat(linha.find('.pu-input').val())||0;
    linha.find('.total-item').val((qtd*pu).toFixed(2));
    recalcTotalGeral();
  });
});
</script>

</body>
</html>
