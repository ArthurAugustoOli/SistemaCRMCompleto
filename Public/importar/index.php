<?php
// Public/importar/index.php

use PhpOffice\PhpSpreadsheet\IOFactory;

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../app/config/config.php';
require __DIR__ . '/../../app/models/Importar.php';

$msg     = '';
$msgType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
    try {
        $tmpFile  = $_FILES['arquivo']['tmp_name'];
        $importer = new Importar($mysqli);
        $importer->importar($tmpFile);

        $msg     = 'Importação concluída com sucesso!';
        $msgType = 'success';
    } catch (\Exception $e) {
        $msg     = 'Erro ao importar: ' . $e->getMessage();
        $msgType = 'danger';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Importar Produtos - Sistema de Gestão</title>
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
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      transition: background-color var(--transition-speed), color var(--transition-speed);
      overflow-x: hidden;
      padding-bottom: 60px; /* Espaço para o menu mobile */
    }

    /* Main content */
    .main-content {
      margin-left: var(--sidebar-width);
      padding: 1.5rem;
      transition: margin-left var(--transition-speed);
    }
    
    /* Cards */
    .card {
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
      border: none;
      transition: background-color var(--transition-speed), box-shadow var(--transition-speed), transform var(--transition-speed);
      margin-bottom: 1.5rem;
      height: 100%;
    }
    
    .card:hover {
      box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
    }
    
    .card-header {
      background-color: transparent;
      border-bottom: 1px solid rgba(0, 0, 0, 0.1);
      padding: 1.25rem 1.5rem;
      font-weight: 600;
      border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
    }
    
    .card-body {
      padding: 1.5rem;
    }
    
    .card-title {
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: var(--primary-color);
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
      color: white;
    }
    
    .btn-success {
      background-color: #28a745;
      border-color: #28a745;
    }
    
    .btn-success:hover {
      background-color: #218838;
      border-color: #1e7e34;
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
    
    /* Form controls */
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
    
    /* Alerts */
    .alert {
      border-radius: var(--border-radius);
      border: none;
      box-shadow: var(--card-shadow);
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
    }
    
    .alert-success {
      background-color: #d1e7dd;
      color: #0f5132;
    }
    
    .alert-danger {
      background-color: #f8d7da;
      color: #842029;
    }
    
    .alert-info {
      background-color: #cff4fc;
      color: #055160;
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
    }
    
    body.dark-mode .card,
    body.dark-mode .modal-content,
    body.dark-mode .form-control,
    body.dark-mode .form-select,
    body.dark-mode .table,
    body.dark-mode .list-group-item {
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
    
    body.dark-mode .file-upload-label{
      background-color: #1e1e1e;
      color: #ffffff;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    
    /* Responsive */
    @media (max-width: 992px) {    
      .main-content {
        margin-left: 0;
        padding-bottom: 5rem;
      }
    }
    
    @media (max-width: 576px) {
      .card-body {
        padding: 1rem;
      }
      
      .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
      }
      
      h1.h3 {
        font-size: 1.5rem;
      }
    }
    
    /* File input custom styling */
    .file-upload {
      position: relative;
      display: inline-block;
      width: 100%;
      margin-bottom: 1rem;
    }
    
    .file-upload-label {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      background-color: #f8f9fa;
      border: 2px dashed #dee2e6;
      border-radius: var(--border-radius);
      cursor: pointer;
      transition: all var(--transition-speed);
    }
    
    .file-upload-label:hover {
      background-color: #e9ecef;
      border-color: var(--primary-color);
    }

    .file-upload-input {
      position: absolute;
      left: 0;
      top: 0;
      right: 0;
      bottom: 0;
      opacity: 0;
      cursor: pointer;
      width: 100%;
    }
    
    .file-upload-text {
      margin-left: 0.5rem;
    }
    
    .file-name {
      margin-top: 0.5rem;
      font-size: 0.875rem;
      color: #6c757d;
      word-break: break-all;
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

  <!-- Main content -->
  <div class="main-content">
    <div class="container-fluid p-0">
      <!-- Page header -->
      <div class="d-flex justify-content-between align-items-center mb-4" data-aos="fade-down">
        <h1 class="h3 mb-0"><i class="fas fa-file-import me-2"></i>Importar Produtos e Variações</h1>
      </div>

      <!-- Dark-Mode -->
      <?php include_once '../../frontend/includes/darkmode.php'?>
      
      <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?> fade-in" data-aos="fade-up">
          <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : ($msgType === 'danger' ? 'exclamation-circle' : 'info-circle') ?> me-2"></i>
          <?= htmlentities($msg) ?>
        </div>
      <?php endif; ?>
      
      <!-- Import card -->
      <div class="card" data-aos="fade-up" data-aos-delay="100">
        <div class="card-header">
          <i class="fas fa-file-excel me-2"></i>Importar arquivo XLSX
        </div>
        <div class="card-body">
          <p class="mb-4">
            Selecione um arquivo Excel (.xlsx) contendo os dados dos produtos e variações para importar.
            Certifique-se de que o arquivo esteja no formato correto.
          </p>
          
          <form method="POST" enctype="multipart/form-data">
            <div class="file-upload mb-4">
              <label class="file-upload-label">
                <i class="fas fa-cloud-upload-alt fa-2x text-primary"></i>
                <span class="file-upload-text">Arraste e solte o arquivo ou clique para selecionar</span>
                <input type="file" name="arquivo" accept=".xlsx,.xls,.csv,.ods" class="file-upload-input" required id="fileInput">
              </label>
              <div class="file-name" id="fileName"></div>
            </div>
            
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-file-import me-2"></i>Importar Dados
            </button>
          </form>
        </div>
      </div>
      <!-- Template download card -->
      <div class="card mt-4" data-aos="fade-up" data-aos-delay="150">
        <div class="card-header">
          <i class="fas fa-download me-2"></i>Baixar Planilha Template
        </div>
        <div class="card-body">
          <p class="mb-4">
            Faça o download da planilha modelo para preencher e importar produtos e variações.
          </p>
          <!-- Botão que abre o modal -->
          <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#templateModal">
            <i class="fas fa-file-excel me-2"></i>Baixar Template
          </button>
        </div>
      </div>

      <!-- Modal de Download da Planilha -->
      <div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="templateModalLabel">Download da Planilha Template</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
              <p>Clique no botão abaixo para baixar a planilha modelo (.xlsx):</p>
            </div>
            <div class="modal-footer">
              <a href="Template.xlsx" download class="btn btn-primary">
                <i class="fas fa-file-excel me-2"></i>Baixar Planilha
              </a>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Instructions card -->
      <div class="card mt-4" data-aos="fade-up" data-aos-delay="200">
        <div class="card-header">
          <i class="fas fa-info-circle me-2"></i>Instruções
        </div>
        <div class="card-body">
          <h5 class="card-title">Formato do arquivo</h5>
          <p>O arquivo Excel deve conter as seguintes colunas:</p>
          <ul>
            <li><strong>Produtos:</strong> código, nome, descrição, preço, estoque</li>
            <li><strong>Variações:</strong> código_produto, cor, tamanho, SKU, preço_variação, estoque_variação</li>
          </ul>
          <p class="mt-3">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Certifique-se de que todos os dados estejam preenchidos corretamente para evitar erros durante a importação.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Mobile Menu -->
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

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
  <script>
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
    
    document.addEventListener('DOMContentLoaded', function() {
      // Display file name when selected
      document.getElementById('fileInput').addEventListener('change', function() {
        const fileName = this.files[0]?.name;
        document.getElementById('fileName').textContent = fileName || '';
      });
    });
  </script>
</body>
</html>