<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title> Sidebar - Sistema de Gestão</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css">
</head>
  <!-- Sidebar backdrop for mobile -->
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <!-- Sidebar -->
  <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
      <i class="fas fa-cube fa-lg"></i>
      <div class="sidebar-logo">Gestão</div>
    </div>
    
    <div class="sidebar-section">
      <!-- Catálogo -->
      <div class="sidebar-section-title">CATÁLOGO</div>
      <ul class="sidebar-nav">
        <!-- Produtos -->
        <li class="sidebar-nav-item">
          <a href="../produtos/index.php" class="sidebar-nav-link">
            <i class="fas fa-box"></i>
            Produtos
          </a>
        </li>

        <!-- Etiquetas -->
        <li class="sidebar-nav-item">
          <a href="../etiqueta/index.php" class="sidebar-nav-link active">
            <i class="fas fa-tags"></i>
            Etiquetas
          </a>
        </li>
        </li>

        <!-- Importar -->
        <li class="sidebar-nav-item">
          <a href="../importar/index.php" class="sidebar-nav-link">
            <i class="fas fa-file-import"></i>
            Importar
          </a>
        </li>

        <!-- Clientes -->
        <li class="sidebar-nav-item">
          <a href="../clientes/clientes.php" class="sidebar-nav-link">
            <i class="fas fa-users"></i>
            Clientes
          </a>
        </li>

        <!-- Funcionarios -->
        <li class="sidebar-nav-item">
          <a href="../funcionarios/index.php" class="sidebar-nav-link">
            <i class="fas fa-user-tie"></i>
            Funcionários
          </a>
        </li>

        <!-- Troca -->
        <li class="sidebar-nav-item">
          <a href="../troca/index.php" class="sidebar-nav-link">
          <i class="fa-solid fa-right-left"></i>
            Troca
          </a>
        </li>

        <!-- vendas -->
        <li class="sidebar-nav-item">
          <a href="../vendas/index.php" class="sidebar-nav-link">
            <i class="fas fa-shopping-cart"></i>
            Vendas
          </a>
        </li>
      </ul>
    </div>
    
    <div class="sidebar-section">
      <!-- Relatorios -->
      <div class="sidebar-section-title">RELATÓRIOS</div>
      <ul class="sidebar-nav">

        <!-- Relatorios -->
        <li class="sidebar-nav-item">
          <a href="../../index.php" class="sidebar-nav-link">
            <i class="fas fa-chart-bar"></i>
            Relatórios
          </a>
        </li>

        <!-- Financeiro -->
        <li class="sidebar-nav-item">
          <a href="../financeiro/index.php" class="sidebar-nav-link">
            <i class="fas fa-wallet"></i>
            Financeiro
          </a>
        </li>
      </ul>
    </div>

    <div class="sidebar-section">
      <!-- Despesas -->
      <div class="sidebar-section-title">Despesas</div>
      <ul class="sidebar-nav">
        <!-- Despesas gerais -->
        <li class="sidebar-nav-item">
          <a href="../despesas/index.php?view=despesas&page=1" class="sidebar-nav-link">
            <i class="fas fa-file-invoice-dollar"></i>
            Despesas Gerais 
          </a>
        </li>

        <!-- Linha divisória -->
        <li class="sidebar-nav-item">
          <div class="w-100 my-3" style="height: 1px; background-color: #fff;"></div>
        </li>

        <!-- Desconectar -->
        <li class="sidebar-nav-item">
          <a href="../login/logout.php" class="sidebar-nav-link text-white fw-semibold rounded-2 d-flex align-items-center py-1 px-2 small">
            <i class="bi bi-box-arrow-right me-1 fs-5"></i> Desconectar
          </a>
        </li>
      </ul>
    </div>
  </div>

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
    
    /* Sidebar styles */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: var(--sidebar-width);
      background: linear-gradient(180deg, #5468FF 0%, #4054F2 100%);
      color: white;
      z-index: 100;
      transition: transform var(--transition-speed);
      overflow-y: auto;
    }
    
    .sidebar-header {
      padding: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .sidebar-logo {
      font-size: 1.5rem;
      font-weight: bold;
    }
    
    .sidebar-section {
      margin-top: 1.5rem;
      padding: 0 1rem;
    }
    
    .sidebar-section-title {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      opacity: 0.7;
      padding: 0 0.5rem;
      margin-bottom: 0.75rem;
    }
    
    .sidebar-nav {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    
    .sidebar-nav-item {
      margin-bottom: 0.25rem;
    }
    
    .sidebar-nav-link {
      display: flex;
      align-items: center;
      padding: 0.75rem 1rem;
      color: white;
      text-decoration: none;
      border-radius: 0.5rem;
      transition: background-color var(--transition-speed);
    }
    
    .sidebar-nav-link:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }
    
    .sidebar-nav-link i {
      margin-right: 0.75rem;
      font-size: 1.25rem;
      width: 1.5rem;
      text-align: center;
    }

    @media (max-width: 992px) {
      .sidebar {
        transform: translateX(-100%);
        z-index: 1050;
      }
      
      .sidebar.show {
        transform: translateX(0);
      }

      .sidebar-toggle {
        display: block;
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1001;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: white;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
      }

      /* Sidebar backdrop for mobile */
    .sidebar-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1040;
      display: none;
    }
    
    .sidebar-backdrop.show {
      display: block;
    }

    body.dark-mode .sidebar{
      background-color: var(--bg-sidebar);
    }
  }
</style>


  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
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
      // Sidebar toggle for mobile
      const sidebar = document.getElementById('sidebar');
      const backdrop = document.getElementById('sidebarBackdrop');
      
      document.getElementById('sidebarToggle').addEventListener('click', function() {
        sidebar.classList.toggle('show');
        backdrop.classList.toggle('show');
      });
      
      // Close sidebar when clicking on backdrop
      backdrop.addEventListener('click', function() {
        sidebar.classList.remove('show');
        backdrop.classList.remove('show');
      });
      
      // Close sidebar when clicking on a menu item (mobile)
      const menuItems = document.querySelectorAll('.sidebar-nav-link');
      menuItems.forEach(item => {
        item.addEventListener('click', function() {
          if (window.innerWidth < 992) {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
          }
        });
      });
      
      // Adjust sidebar on window resize
      window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
          sidebar.classList.remove('show');
          backdrop.classList.remove('show');
        }
      });
    });
  </script>
</html>