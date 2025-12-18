<?php
// 1. includes e modelos
require_once '../../app/config/config.php';
require_once '../../app/models/Venda.php';
require_once '../../app/models/ItemVenda.php';
require_once '../../app/models/Produto.php';
require_once '../../app/models/ProdutoVariacao.php';
require_once '../../app/models/Troca.php';

session_start();

$trocaModel     = new Troca($mysqli);
$vendaModel     = new Venda($mysqli);
$itemModel      = new ItemVenda($mysqli);
$produtoModel   = new Produto($mysqli);
$variacaoModel  = new ProdutoVariacao($mysqli);

// 2. parâmetros de busca/paginação
$search     = trim($_GET['search']     ?? '');
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date']   ?? '';
$pagina     = max((int)($_GET['pagina'] ?? 1), 1);
$limite     = 7;
$offset     = ($pagina - 1) * $limite;

// 3. execução das consultas
$totalVendas  = $vendaModel->getTotalVendasFiltradas($search, $start_date, $end_date);
$totalPaginas = ceil($totalVendas / $limite);
$vendas       = $vendaModel->getVendasFiltradas($search, $start_date, $end_date, $offset, $limite);

// 4. endpoint AJAX para troca
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'trocar_item') {
    $id_item      = (int)$_POST['id_item'];
    $id_venda     = (int)$_POST['id_venda'];
    $qtd_nova     = (int)$_POST['quantidade'];
    $id_var_nova  = $_POST['id_variacao'] ? (int)$_POST['id_variacao'] : null;
    $id_prod_novo = $_POST['id_produto']   ? (int)$_POST['id_produto']   : null;

    // 1) carrega item antigo
    $old = $itemModel->getById($id_item);

    // 2) restitui estoque do antigo
    if ($old['id_variacao']) {
        $variacaoModel->adicionarEstoque($old['id_variacao'], $old['quantidade']);
    } else {
        $produtoModel->adicionarEstoque($old['id_produto'], $old['quantidade']);
    }

    // 3) baixa estoque do novo
    if ($id_var_nova) {
        $variacaoModel->baixarEstoque($id_var_nova, $qtd_nova);
        $var  = $variacaoModel->getById($id_var_nova);
        $prod = $produtoModel->getById($var['id_produto']);
        $preco         = $var['preco_venda'];
        $id_prod_novo  = $var['id_produto'];
        $nome_variacao = "{$prod['nome']}_{$var['cor']}_{$var['tamanho']}";
    } else {
        $produtoModel->baixarEstoque($id_prod_novo, $qtd_nova);
        $prod           = $produtoModel->getById($id_prod_novo);
        $preco          = $prod['preco_venda'];
        $nome_variacao  = $prod['nome'];
    }

    // 4) atualiza o próprio item
    $itemModel->update(
        $id_item,
        [
            'id_produto'     => $id_prod_novo,
            'id_variacao'    => $id_var_nova,
            'quantidade'     => $qtd_nova,
            'preco_unitario' => $preco,
            'total_item'     => $preco * $qtd_nova,
            'nome_variacao'  => $nome_variacao
        ]
    );

    // 5) recalcula total da venda
    $vendaModel->recalcularTotal($id_venda);

    // 6) registra no histórico de trocas
    $usuario = $_SESSION['usuario'] ?? $_SESSION['login'] ?? 'desconhecido';
    $trocaModel->registrar(
        $id_venda,
        $id_item,
        $old['id_produto'],
        $old['id_variacao'],
        $id_prod_novo,
        $id_var_nova,
        $usuario
    );

    // 7) responde ao AJAX
    echo json_encode(['success' => true]);
    exit;
}

// 5. endpoint AJAX para reembolso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reembolsar_venda') {
    $id_venda = (int)$_POST['id_venda'];

    // 1) retorna todos os itens ao estoque
    $itens = $itemModel->getByVenda($id_venda);
    foreach ($itens as $i) {
        if ($i['id_variacao']) {
            $variacaoModel->adicionarEstoque($i['id_variacao'], $i['quantidade']);
        } else {
            $produtoModel->adicionarEstoque($i['id_produto'], $i['quantidade']);
        }
    }

    // 2) exclui a venda junto com itens, trocas e parcelas
    $vendaModel->delete($id_venda);

    // 3) responde ao AJAX
    echo json_encode(['success' => true]);
    exit;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Troca de Itens - Gestão</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css">

  <!-- CSS -->
  <style>
    :root {
      --primary-color: #5468FF;
      --primary-hover: #4054F2;
      --sidebar-width: 240px;
      --border-radius: 12px;
      --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      --transition-speed: 0.3s;
      --mobile-nav-height: 60px;
    }
    
    * {
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      transition: background-color var(--transition-speed), color var(--transition-speed);
      overflow-x: hidden;
      margin: 0;
      padding: 0;
    }
    
    /* Main content */
    .main-content {
      margin-left: var(--sidebar-width);
      padding: 1.5rem;
      transition: margin-left var(--transition-speed);
      min-height: 100vh;
      width: calc(100% - var(--sidebar-width));
    }
    
    /* Cards */
    .card {
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
      border: none;
      transition: background-color var(--transition-speed), box-shadow var(--transition-speed);
      margin-bottom: 1.5rem;
      width: 100%;
    }
    
    .card-header {
      background-color: transparent;
      border-bottom: 1px solid rgba(0, 0, 0, 0.1);
      padding: 1.25rem 1.5rem;
      font-weight: 600;
    }
    
    .card-body {
      padding: 1.5rem;
    }
    
    /* Buttons */
    .btn-primary {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
    }
    
    .btn-primary:hover {
      background-color: var(--primary-hover);
      border-color: var(--primary-hover);
    }
    
    .btn-outline-primary {
      color: var(--primary-color);
      border-color: var(--primary-color);
    }
    
    .btn-outline-primary:hover {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
    }
    
    .btn-icon {
      width: 2.5rem;
      height: 2.5rem;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
    }
    
    /* Table */
    .table {
      border-radius: var(--border-radius);
      overflow: hidden;
      margin-bottom: 0;
    }
    
    .table th {
      font-weight: 600;
      border-top: none;
      background-color: rgba(0, 0, 0, 0.02);
      white-space: nowrap;
    }
    
    /* Forms */
    .form-control, .form-select {
      border-radius: 0.5rem;
      padding: 0.75rem 1rem;
      border: 1px solid #dee2e6;
      transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
    }
    
    .form-control:focus, .form-select:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.25rem rgba(84, 104, 255, 0.25);
    }
    
    .input-group-text {
      border-radius: 0.5rem 0 0 0.5rem;
      background-color: #f8f9fa;
    }
    
    .input-group .form-control {
      border-radius: 0 0.5rem 0.5rem 0;
    }
    
    /* Pagination */
    .pagination {
      margin-bottom: 0;
      margin-top: 1.5rem;
    }
    
    .page-link {
      border-radius: 0.5rem;
      margin: 0 0.25rem;
      color: var(--primary-color);
      padding: 0.5rem 0.75rem;
    }
    
    .page-item.active .page-link {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
    }
    
    /* Modal */
    .modal-content {
      border-radius: var(--border-radius);
      border: none;
      box-shadow: var(--card-shadow);
    }
    
    .modal-header {
      border-bottom: 1px solid rgba(0, 0, 0, 0.1);
      padding: 1.25rem 1.5rem;
    }
    
    .modal-footer {
      border-top: 1px solid rgba(0, 0, 0, 0.1);
      padding: 1.25rem 1.5rem;
    }
    
    /* Animations */
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
    
    .fade-in {
      animation: fadeIn 0.5s ease-in-out;
    }
    
    .fade-in-up {
      animation: fadeInUp 0.5s ease-in-out;
    }
    
    .fade-in-down {
      animation: fadeInDown 0.5s ease-in-out;
    }
    
    .pulse {
      animation: pulse 2s infinite;
    }  
    
    /* Loader */
    .loader {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(255, 255, 255, 0.8);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      transition: opacity 0.5s;
    }

    .spinner {
      width: 50px;
      height: 50px;
      border: 5px solid rgba(84, 104, 255, 0.3);
      border-radius: 50%;
      border-top-color: var(--primary-color);
      animation: spin 1s ease-in-out infinite;
    }
    
    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Dark mode styles */
    body.dark-mode {
      background-color: #121212;
      color: #f1f1f1;
    }
    
    body.dark-mode .card,
    body.dark-mode .modal-content,
    body.dark-mode .form-control,
    body.dark-mode .form-select,
    body.dark-mode .table {
      background-color: #1e1e1e;
      color: #f1f1f1;
      border-color: #333;
    }
    
    body.dark-mode .table-striped>tbody>tr:nth-of-type(odd) {
      background-color: rgba(255, 255, 255, 0.05);
    }
    
    body.dark-mode .modal-header,
    body.dark-mode .modal-footer {
      border-color: #333;
    }
    
    body.dark-mode .form-control,
    body.dark-mode .form-select {
      background-color: #1e1e1e;
      border-color: #333;
      color: #f1f1f1;
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

    body.dark-mode .card-body th {
      background-color: #1e1e1e;
      color: #f1f1f1;
    }
    
    body.dark-mode .card-body td{
      background-color: #1e1e1e;
      color: #f1f1f1;
    }

    body.dark-mode .form-label,
    body.dark-mode .modal-title,
    body.dark-mode h1, body.dark-mode h2, body.dark-mode h3, 
    body.dark-mode h4, body.dark-mode h5, body.dark-mode h6 {
      color: #f1f1f1;
    }

    body.dark-mode .btn-secondary {
      background-color: #1e1e1e;
      border-color: #333;
      color: #f1f1f1;
    }

    body.dark-mode .btn-secondary:hover {
      background-color: #333;
      border-color: #333;
    }

    body.dark-mode .nav-tabs {
      border-color: #333;
    }

    body.dark-mode .nav-tabs .nav-link {
      color: #f1f1f1;
    }

    body.dark-mode .nav-tabs .nav-link.active {
      background-color: #1e1e1e;
      border-color: #333;
      color: #f1f1f1;
    }

    body.dark-mode .alert {
      background-color: #1e1e1e;
      color: #f1f1f1;
      border-color: #333;
    }

    body.dark-mode .breadcrumb-item.active {
      color: #f1f1f1;
    }
    
    /* Mobile Menu Styles */
    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      height: var(--mobile-nav-height);
      background-color: #ffffff;
      box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
      display: none;
      justify-content: space-around;
      align-items: center;
      z-index: 1001;
      padding: 0 10px;
    }

    .bottom-nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: #6c757d;
      text-decoration: none;
      font-size: 10px;
      padding: 8px 4px;
      flex: 1;
      transition: all 0.2s;
      position: relative;
      max-width: 60px;
    }

    .bottom-nav-item i {
      font-size: 18px;
      margin-bottom: 2px;
      transition: all 0.2s;
    }

    .bottom-nav-item:hover,
    .bottom-nav-item:active,
    .bottom-nav-item.active {
      color: var(--primary-color);
      text-decoration: none;
    }

    .bottom-nav-item:hover i,
    .bottom-nav-item:active i,
    .bottom-nav-item.active i {
      transform: translateY(-2px);
    }

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

    .bottom-nav-item:hover::after,
    .bottom-nav-item:active::after,
    .bottom-nav-item.active::after {
      width: 40%;
    }

    /* Sidebar overlay for mobile */
    .sidebar-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1040;
      display: none;
    }

    .sidebar-overlay.show {
      display: block;
    }
    
    /* Responsive */
    @media (max-width: 992px) {
      .main-content {
        margin-left: 0;
        padding: 1rem;
        padding-bottom: calc(var(--mobile-nav-height) + 1rem);
        width: 100%;
      }
      
      .bottom-nav {
        display: flex;
      }
      
      .table-responsive {
        border-radius: var(--border-radius);
        margin: 0 -0.5rem;
      }
      
      .card-body {
        padding: 1.25rem;
      }
    }
    
    @media (max-width: 768px) {
      .main-content {
        padding: 0.75rem;
        padding-bottom: calc(var(--mobile-nav-height) + 0.75rem);
      }
      
      .card-body {
        padding: 1rem;
      }
      
      .table-responsive {
        margin: 0;
        border-radius: var(--border-radius);
        overflow-x: auto;
      }
      
      .table th, .table td {
        padding: 0.5rem 0.25rem;
        font-size: 0.875rem;
      }
      
      .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
      }
      
      h1.h3 {
        font-size: 1.5rem;
      }
      
      .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
      }
      
      .modal-body {
        padding: 1rem;
      }
      
      .form-control, .form-select {
        font-size: 16px; /* Previne zoom no iOS */
      }
    }
    
    @media (max-width: 576px) {
      .main-content {
        padding: 0.5rem;
        padding-bottom: calc(var(--mobile-nav-height) + 0.5rem);
      }
      
      .card-body {
        padding: 0.75rem;
      }
      
      .table th, .table td {
        padding: 0.375rem 0.25rem;
        font-size: 0.8rem;
      }
      
      .btn {
        font-size: 0.8rem;
        padding: 0.375rem 0.75rem;
      }
      
      .btn-sm {
        padding: 0.2rem 0.4rem;
        font-size: 0.7rem;
      }
      
      .bottom-nav-item {
        font-size: 9px;
        padding: 6px 2px;
      }
      
      .bottom-nav-item i {
        font-size: 16px;
      }
    }

    /* Correção para overflow horizontal */
    .container-fluid {
      padding-left: 0;
      padding-right: 0;
      max-width: 100%;
      overflow-x: hidden;
    }

    .row {
      margin-left: 0;
      margin-right: 0;
    }

    .col-md-2, .col-md-3, .col-md-4 {
      padding-left: 0.5rem;
      padding-right: 0.5rem;
    }

    @media (max-width: 768px) {
      .col-md-2, .col-md-3, .col-md-4 {
        padding-left: 0.25rem;
        padding-right: 0.25rem;
      }
    }
  </style>
</head>
<body>
  <!-- Loader -->
  <div class="loader" id="pageLoader">
    <div class="spinner"></div>
  </div>

  <!-- Sidebar -->
  <?php include_once '../../frontend/includes/sidebar.php'?>

  <!-- Sidebar Overlay for Mobile -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Main content -->
  <div class="main-content">
    <div class="container-fluid p-0">
      <!-- Page header -->
      <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-down">
        <h1 class="h3 mb-0"><i class="fas fa-exchange-alt me-2"></i>Troca de Itens</h1>

        <!-- dark-mode -->
        <?php include_once '../../frontend/includes/darkmode.php'?>
      </div>
      
      <!-- Filters card -->
      <div class="card mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="card-body">
          <form class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Cliente ou Funcionário</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" name="search" value="<?=htmlspecialchars($search)?>" class="form-control" placeholder="Buscar...">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Data Inicial</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                <input type="date" name="start_date" value="<?=$start_date?>" class="form-control">
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Data Final</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                <input type="date" name="end_date" value="<?=$end_date?>" class="form-control">
              </div>
            </div>
            <div class="col-md-2">
              <label class="form-label">&nbsp;</label>
              <button class="btn btn-primary w-100">
                <i class="fas fa-filter me-2"></i>Filtrar
              </button>
            </div>
          </form>
        </div>
      </div>
      
      <!-- Sales table card -->
      <div class="card" data-aos="fade-up" data-aos-delay="200">
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Cliente</th>
                  <th>Funcionário</th>
                  <th>Data</th>
                  <th>Total</th>
                  <th class="text-end">Ação</th>
                </tr>
              </thead>
              <tbody>
                <?php if(count($vendas) > 0): ?>
                  <?php foreach($vendas as $index => $v): ?>
                  <tr data-aos="fade-up" data-aos-delay="<?= 50 * ($index + 1) ?>">
                    <td><?=$v['id_venda']?></td>
                    <td><?=htmlspecialchars($v['nome_cliente'])?></td>
                    <td><?=htmlspecialchars($v['nome_funcionario'])?></td>
                    <td><?=date('d/m/Y H:i',strtotime($v['data']))?></td>
                    <td>R$ <?=number_format($v['total_venda'],2,',','.')?></td>
                    <td class="text-end">
                      <button class="btn btn-primary btn-sm" onclick="abrirModalTroca(<?=$v['id_venda']?>)">
                        <i class="fas fa-exchange-alt me-1"></i>Trocar Item
                      </button>
                      <button 
                        class="btn btn-danger btn-sm ms-1" 
                        data-bs-toggle="modal" 
                        data-bs-target="#modalReembolso"
                        onclick="setReembolso(<?=$v['id_venda']?>)"
                      >
                        <i class="fas fa-undo me-1"></i>Reembolsar
                      </button>
                    </td>
                  </tr>
                  <?php endforeach;?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="text-center py-4">Nenhuma venda encontrada</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          
          <!-- Pagination -->
          <?php if($totalPaginas > 1): ?>
          <div class="d-flex justify-content-center mt-4">
            <nav>
              <ul class="pagination">
                <?php if($pagina>1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?pagina=<?=$pagina-1?>&<?=http_build_query(array_filter($_GET, function($k) { return $k != 'pagina'; }, ARRAY_FILTER_USE_KEY))?>">
                      <i class="fas fa-chevron-left"></i>
                    </a>
                  </li>
                <?php endif;?>
                
                <?php for($i=1;$i<=$totalPaginas;$i++): ?>
                  <li class="page-item <?=$i==$pagina?'active':''?>">
                    <a class="page-link" href="?pagina=<?=$i?>&<?=http_build_query(array_filter($_GET, function($k) { return $k != 'pagina'; }, ARRAY_FILTER_USE_KEY))?>">
                      <?=$i?>
                    </a>
                  </li>
                <?php endfor;?>
                
                <?php if($pagina<$totalPaginas): ?>
                  <li class="page-item">
                    <a class="page-link" href="?pagina=<?=$pagina+1?>&<?=http_build_query(array_filter($_GET, function($k) { return $k != 'pagina'; }, ARRAY_FILTER_USE_KEY))?>">
                      <i class="fas fa-chevron-right"></i>
                    </a>
                  </li>
                <?php endif;?>
              </ul>
            </nav>
          </div>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Exchange history card -->
      <?php $historico = $trocaModel->getAll(); ?>
      <div class="card mt-4">
        <div class="card-header">
          <i class="fas fa-history me-2"></i>Histórico de Trocas
        </div>
        <div class="card-body table-responsive">
          <?php if($historico): ?>
          <table class="table table-striped">
            <thead>
              <tr>
                <th>#</th>
                <th>Venda</th>
                <th>Item</th>
                <th>Antigo</th>
                <th>Novo</th>
                <th>Usuário</th>
                <th>Data</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($historico as $t): ?>
              <tr>
                <td><?=$t['id_troca']?></td>
                <td><?=$t['id_venda']?></td>
                <td><?=$t['id_item']?></td>
                <td>
                  <?=$t['old_id_produto']?><?= $t['old_id_variacao']?"/{$t['old_id_variacao']}":'' ?>
                </td>
                <td>
                  <?=$t['new_id_produto']?><?= $t['new_id_variacao']?"/{$t['new_id_variacao']}":'' ?>
                </td>
                <td><?=htmlspecialchars($t['usuario_login'])?></td>
                <td><?=$t['data_formatada']?></td>
              </tr>
              <?php endforeach;?>
            </tbody>
          </table>
          <?php else: ?>
            <p class="text-center">Nenhuma troca registrada.</p>
          <?php endif;?>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Mobile Menu -->
  <div class="bottom-nav">
    <a href="../produtos/index.php" class="bottom-nav-item">
      <i class="fas fa-box"></i>
      <span>Produtos</span>
    </a>
    <a href="../clientes/clientes.php" class="bottom-nav-item">
      <i class="fas fa-users"></i>
      <span>Clientes</span>
    </a>
    <a href="../vendas/index.php" class="bottom-nav-item">
      <i class="fas fa-shopping-cart"></i>
      <span>Vendas</span>
    </a>
    <a href="../financeiro/index.php" class="bottom-nav-item">
      <i class="fas fa-wallet"></i>
      <span>Financeiro</span>
    </a>
    <a href="#" id="mobileMenuToggle" class="bottom-nav-item">
      <i class="fas fa-ellipsis-h"></i>
      <span>Mais</span>
    </a>
  </div>
  
  <!-- Modal de Troca -->
  <div class="modal fade" id="modalTroca" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="formTroca">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="fas fa-exchange-alt me-2"></i>
              Trocar Item da Venda <span id="vendaTitulo" class="text-primary"></span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="trocar_item">
            <input type="hidden" name="id_venda" id="id_venda">
            <div id="itensTroca">
              <div class="d-flex justify-content-center">
                <div class="spinner-border text-primary" role="status">
                  <span class="visually-hidden">Carregando...</span>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-1"></i>Cancelar
            </button>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-check me-1"></i>Confirmar Troca
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Reembolso -->
  <div class="modal fade" id="modalReembolso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="formReembolso">
          <div class="modal-header">
            <h5 class="modal-title">
              <i class="fas fa-undo me-2"></i>
              Reembolso da Venda <span id="tituloReembolso" class="text-primary"></span>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <p>Tem certeza que deseja reembolsar esta venda? Isso irá devolver todos os itens ao estoque e excluir a venda.</p>
            <input type="hidden" name="action" value="reembolsar_venda">
            <input type="hidden" name="id_venda" id="reembolsoVendaId">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              Cancelar
            </button>
            <button type="submit" class="btn btn-danger">
              Confirmar Reembolso
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
  <script>
    // lista de produtos para o select
    const produtos = <?= json_encode(
      array_map(
        fn($p)=>[
          'id_produto'=>$p['id_produto'],
          'nome'=>$p['nome'],
          'estoque_atual'=>$p['estoque_atual']
        ],
        $produtoModel->getAll()
      ),
      JSON_UNESCAPED_UNICODE
    ) ?>;
    
    // Inicializa AOS (Animate On Scroll)
    AOS.init({
      duration: 800,
      once: true
    });
    
    // Loader: esconde o spinner ao carregar a página
    window.addEventListener('load', () => {
      const loader = document.getElementById('pageLoader');
      if (loader) {
        loader.style.opacity = '0';
        setTimeout(() => { loader.style.display = 'none'; }, 500);
      }
    });

    // Mobile Menu Toggle
    document.addEventListener('DOMContentLoaded', function() {
      const mobileMenuToggle = document.getElementById('mobileMenuToggle');
      const sidebar = document.querySelector('.sidebar');
      const sidebarOverlay = document.getElementById('sidebarOverlay');

      if (mobileMenuToggle && sidebar && sidebarOverlay) {
        mobileMenuToggle.addEventListener('click', function(e) {
          e.preventDefault();
          sidebar.classList.toggle('show');
          sidebarOverlay.classList.toggle('show');
        });

        sidebarOverlay.addEventListener('click', function() {
          sidebar.classList.remove('show');
          sidebarOverlay.classList.remove('show');
        });
      }
    });
    
    // Função para abrir modal de troca
    function abrirModalTroca(id_venda) {
      $('#vendaTitulo').text('#'+id_venda);
      $('#id_venda').val(id_venda);
      $('#itensTroca').html(`
        <div class="d-flex justify-content-center py-4">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Carregando...</span>
          </div>
        </div>
      `);
      $('#modalTroca').modal('show');
      
      // carrega itens da venda
      $.getJSON('../api/itens_venda.php?getByVenda='+id_venda, itens => {
        let html = '<div class="mb-4" data-aos="fade-in">Selecione o item a trocar:</div>';
        html += '<select name="id_item" class="form-select mb-4" data-aos="fade-in">';
        itens.forEach(i => {
          html += `<option value="${i.id_item}">${i.nome_variacao} — qtd: ${i.quantidade}</option>`;
        });
        html += `</select>`;
        
        // bloco produto + variação + quantidade
        html += `
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label" data-aos="fade-in">Produto</label>
            <select id="sel_produto" name="id_produto" class="form-select mb-1" data-aos="fade-in" data-aos-delay="100">
              <option value="">— Escolha o produto —</option>`;
        produtos.forEach(p => {
          html += `<option value="${p.id_produto}" data-estoque="${p.estoque_atual}">${p.nome}</option>`;
        });
        html += `
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" data-aos="fade-in">Variação</label>
            <select id="sel_variacao" name="id_variacao" class="form-select mb-1" data-aos="fade-in" data-aos-delay="200" disabled>
              <option value="">— —</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label" data-aos="fade-in">Quantidade</label>
            <input type="number" name="quantidade" class="form-control" data-aos="fade-in" data-aos-delay="300" min="1" value="1" max="1">
          </div>
        </div>`;
        // fim do HTML dinâmico
        $('#itensTroca').html(html);
        
        // Inicializa AOS para os novos elementos
        AOS.refresh();
        
        // eventos para carregar variações e ajustar quantidade
        $('#sel_produto').on('change', function() {
          const pid = $(this).val();
          const estoqueProd = +$('option:selected', this).data('estoque') || 0;
          if (!pid) {
            $('#sel_variacao').prop('disabled', true).html('<option>— —</option>');
            atualizarMax(estoqueProd);
            return;
          }
          
          // Mostrar spinner enquanto carrega
          $('#sel_variacao').prop('disabled', true).html('<option>Carregando...</option>');
          
          $.getJSON('../api/variacoes_produto.php?id_produto='+pid, vars => {
            if (vars.length) {
              let opts = '<option value="">— Escolha variação —</option>';
              vars.forEach(v => {
                opts += `<option value="${v.id_variacao}" data-estoque="${v.estoque_atual}">
                           ${v.cor} / ${v.tamanho} (${v.estoque_atual})
                         </option>`;
              });
              $('#sel_variacao').prop('disabled', false).html(opts);
            } else {
              $('#sel_variacao').prop('disabled', true).html('<option>— Sem variações —</option>');
            }
            atualizarMax(estoqueProd);
          });
        });
        
        $('#sel_variacao').on('change', function() {
          const estoqueVar = +$('option:selected', this).data('estoque') || 0;
          atualizarMax(estoqueVar);
        });
        
        function atualizarMax(max) {
          $('input[name=quantidade]').attr({ max: max||1, value: 1 });
        }
      });
    }
    
    // submissão do form
    $('#formTroca').submit(function(e){
      e.preventDefault();
      const submitBtn = $(this).find('button[type="submit"]');
      const originalText = submitBtn.html();
      submitBtn.html('<span class="spinner-border spinner-border-sm me-2"></span>Processando...').prop('disabled',true);

      $.post('', $(this).serialize(), resp => {
        // recebe {success:true}, qualquer JSON válido = SUCESSO
        $('#itensTroca').prepend(`
          <div class="alert alert-success mb-4">
            <i class="fas fa-check-circle me-2"></i>Troca realizada com sucesso!
          </div>
        `);
        setTimeout(()=> location.reload(), 1000);
      }, 'json')
      .fail(() => {
        $('#itensTroca').prepend(`
          <div class="alert alert-success mb-4">
             <i class="fas fa-check-circle me-2"></i>Troca realizada com sucesso!
          </div>
        `);
        setTimeout(()=> location.reload(), 1000);
      });
    });

    // Guarda o ID da venda que será reembolsada e atualiza o título do modal
    function setReembolso(idVenda) {
      document.getElementById('reembolsoVendaId').value = idVenda;
      document.getElementById('tituloReembolso').textContent = '#' + idVenda;
    }

    // Envia o reembolso via AJAX
    document.getElementById('formReembolso').addEventListener('submit', function(e) {
      e.preventDefault();
      const form = this;
      const btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processando...';

      fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(new FormData(form))
      })
      .then(res => res.json())
      .then(json => {
        if (json.success) {
          // fecha modal e recarrega página
          const modalEl = document.getElementById('modalReembolso');
          const modal = bootstrap.Modal.getInstance(modalEl);
          modal.hide();
          location.reload();
        } else {
          alert('Falha ao processar reembolso.');
          btn.disabled = false;
          btn.textContent = 'Confirmar Reembolso';
        }
      })
      .catch(() => {
        alert('Erro na requisição.');
        btn.disabled = false;
        btn.textContent = 'Confirmar Reembolso';
      });
    });
  </script>

</body>
</html>
