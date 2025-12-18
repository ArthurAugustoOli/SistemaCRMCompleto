<?php

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/models/Etiqueta.php';

use App\Models\Etiqueta;

$model     = new Etiqueta($mysqli);
$produtos  = $model->getAllWithVariacoes();
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiquetas de Produtos - Sistema de Gestão</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css">
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
        padding-bottom: 60px;
        /* Espaço para o menu mobile */
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

        .sidebar {
            background-color: var(--bg-sidebar);
        }
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

    body.dark-mode .sidebar {
        background-color: var(--bg-sidebar);
    }

    body.dark-mode .dark-mode-toggle {
        background-color: #343a40;
        color: #f8f9fa;
    }

    body.dark-mode .loader {
        background-color: rgba(0, 0, 0, 0.8);
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

    /* Label styles */
    .label-item {
        display: inline-block;
        width: 302px;
        height: 151px;
        margin: 5px;
        padding: 10px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        text-align: center;
        vertical-align: top;
        transition: transform 0.2s ease;
    }

    .label-item:hover {
        transform: scale(1.02);
    }

    .label-item svg {
        width: 100%;
        height: 100px;
    }

    .label-item .text-code {
        font-family: monospace;
        margin-top: 4px;
        word-break: break-all;
        font-weight: 500;
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

    .modal-title {
        font-weight: 600;
        color: var(--primary-color);
    }

    /* List group */
    .list-group-item {
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        border: 1px solid rgba(0, 0, 0, 0.1);
        transition: background-color var(--transition-speed);
    }

    .list-group-item:last-child {
        margin-bottom: 0;
    }

    /* Form controls */
    .form-control,
    .form-select {
        border-radius: 0.5rem;
        border: 1px solid #dee2e6;
        transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(84, 104, 255, 0.25);
    }

    .form-check-input:checked {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
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
        to {
            transform: rotate(360deg);
        }
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

    /* Só imprime o printArea */
    /* =========================
   Estilos de Tela (screen)
   ========================= */
    .label-item {
        display: inline-block;
        width: 302px;
        height: 151px;
        margin: 5px;
        padding: 10px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        text-align: center;
        vertical-align: top;
        transition: transform 0.2s ease;
    }

    .label-item:hover {
        transform: scale(1.02);
    }

    .label-item svg {
        width: 100%;
        height: 100px;
    }

    .label-item .text-code {
        font-family: monospace;
        margin-top: 4px;
        word-break: break-all;
        font-weight: 500;
    }

    @media print {

        /* 1) Esconde tudo que não for #printArea */
        body>*:not(#printArea) {
            display: none !important;
        }

        /* 2) Exibe só o #printArea */
        #printArea {
            display: block !important;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
        }

        /* 3) Cada .page vira uma folha e só gera as N necessárias */
        .page {
            display: flex;
            flex-wrap: wrap;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        /* 4) Tamanho real da etiqueta */
        .label-item {
            width: 80mm;
            height: 40mm;
            margin: 3mm;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            padding: 2mm;
        }

    }


    .label-item .header-row {
        display: flex;
        flex-direction: row;
        justify-content: space-between;
        margin-bottom: 1mm;
    }

    /* 6) Bloco esquerdo do cabeçalho (nome do produto + variação) */
    .label-item .left-block {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }

    .label-item .left-block .item-name {
        font-size: 9pt;
        font-weight: bold;
        margin-bottom: 0.5mm;
        word-break: break-word;
    }

    .label-item .left-block .item-variation {
        font-size: 8pt;
        font-style: italic;
    }

    /* 7) Bloco direito do cabeçalho (nome da loja + garantia) */
    .label-item .right-block {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
    }

    .label-item .right-block .store-name {
        font-size: 10pt;
        font-weight: bold;
        margin-bottom: 0.5mm;
    }

    .label-item .right-block .warranty {
        font-size: 7pt;
    }

    /* 8) Container do código de barras (centralizado) */
    .label-item .barcode-wrapper {
        flex-grow: 1;
        /* ocupa o espaço intermediário */
        display: flex;
        flex-direction: column;
        justify-content: center;
        /* centraliza verticalmente o SVG */
        align-items: center;
        /* centraliza horizontalmente */
        margin-bottom: 0.5mm;
    }

    .label-item .barcode-wrapper svg {
        width: auto;
        /* mantém a proporção do CODE128 */
        height: 15mm;
        /* altura do código de barras em milímetros */
        display: block;
    }

    /* 9) ID legível (texto abaixo do barcode), alinhado à esquerda */
    .label-item .id-code {
        font-size: 8pt;
        font-family: monospace;
        text-align: left;
        margin-top: 0;
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
                <h1 class="h3 mb-0"><i class="fas fa-tags me-2"></i>Etiquetas de Produtos</h1>
            </div>

            <!-- Dark-Mode -->
            <?php include_once '../../frontend/includes/darkmode.php'?>

            <!-- Batch print button -->
            <div class="mb-4" data-aos="fade-up">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalBatch">
                    <i class="fas fa-tags me-2"></i>Imprimir em lote
                </button>
            </div>

            <!-- Products grid -->
            <div class="row" data-aos="fade-up" data-aos-delay="100">
                <?php foreach($produtos as $index => $p): ?>
                <div class="col-md-4 col-lg-3 mb-4" data-aos="fade-up" data-aos-delay="<?= 50 * ($index + 1) ?>">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?=htmlspecialchars($p['nome'])?></h5>
                            <div class="text-muted mb-3">
                                <i class="fas fa-barcode me-1"></i>
                                <code><?=htmlspecialchars($p['codigo_barras'])?></code>
                            </div>
                            <div class="mt-auto d-flex flex-column">
                                <button class="btn btn-sm btn-primary mb-2"
                                    onclick="adicionarLote('<?=addslashes($p['codigo_barras'])?>')">
                                    <i class="fas fa-tag me-1"></i>Etiqueta
                                </button>
                                <?php if($p['variacoes']): ?>
                                <button class="btn btn-sm btn-outline-primary"
                                    onclick="abrirModalVariacoes(<?=$p['id_produto']?>)">
                                    <i class="fas fa-list me-1"></i>Variações
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div class="bottom-nav d-block d-md-none d-flex justify-content-center align-items-center">
        <a href="index.php"
            class="bottom-nav-item <?php echo in_array($action, ['list','create','edit','variacoes'])?'active':''; ?>">
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

    <!-- Modal Batch -->
    <div class="modal fade" id="modalBatch" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-tags me-2"></i>Selecionar etiquetas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height:60vh; overflow:auto;">
                    <ul class="list-group">
                        <?php foreach($produtos as $p): ?>
                        <li class="list-group-item">
                            <div class="form-check form-switch d-flex align-items-center">
                                <?php if (empty($p['variacoes'])): ?>

                                <input class="form-check-input batch-item me-2" type="checkbox"
                                    data-code="<?=htmlspecialchars($p['codigo_barras'])?>"
                                    data-name="<?=htmlspecialchars($p['nome'])?>" data-variation="">
                                <?php endif; ?>

                                <label class="form-check-label flex-grow-1">
                                    <strong>Produto:</strong> <?=htmlspecialchars($p['nome'])?> —
                                    <code><?=htmlspecialchars($p['codigo_barras'])?></code>
                                </label>

                                <?php if (empty($p['variacoes'])): ?>
                                <!-- só exibe a quantidade se NÃO houver variações -->
                                <input type="number" class="form-control form-control-sm batch-qty" value="1" min="1"
                                    style="width:80px;">
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($p['variacoes'])): ?>
                            <ul class="list-group mt-2">
                                <?php foreach($p['variacoes'] as $v): ?>
                                <li class="list-group-item d-flex align-items-center">
                                    <!-- para cada variação, o checkbox sempre aparece -->
                                    <input class="form-check-input batch-item me-2" type="checkbox"
                                        data-code="<?=htmlspecialchars($v['sku'])?>"
                                        data-name="<?=htmlspecialchars($p['nome'])?>"
                                        data-variation="<?=htmlspecialchars($v['cor'])?> / <?=htmlspecialchars($v['tamanho'])?>">
                                    <div class="flex-grow-1">
                                        <strong>Var:</strong> <?=htmlspecialchars($v['cor'])?> /
                                        <?=htmlspecialchars($v['tamanho'])?> —
                                        <code><?=htmlspecialchars($v['sku'])?></code>
                                    </div>
                                    <input type="number" class="form-control form-control-sm batch-qty" value="1"
                                        min="1" style="width:80px;">
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button class="btn btn-success" onclick="gerarBatch()">
                        <i class="fas fa-print me-1"></i>Gerar etiquetas
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Variações -->
    <div class="modal fade" id="modalVariacoes" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-list me-2"></i>Variações</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="listaVariacoes">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Área de impressão -->
    <div id="printArea" style="display:none;"></div>

    <!-- Scripts -->
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
            setTimeout(() => {
                loader.style.display = 'none';
            }, 500);
        }
    });

    // abre modal de variações
    function abrirModalVariacoes(id) {
        const $body = $('#listaVariacoes').html(
            '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>');
        new bootstrap.Modal('#modalVariacoes').show();
        $.getJSON('../api/variacoes.php?id_produto=' + id, vars => {
            if (!vars.length) return $body.html(
                '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Sem variações disponíveis.</div>'
            );
            let html = '<ul class="list-group">';
            vars.forEach(v => {
                html += `<li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <span class="fw-medium">${v.cor} / ${v.tamanho}</span>
              <div class="text-muted small"><i class="fas fa-barcode me-1"></i><code>${v.sku}</code></div>
            </div>
            <button class="btn btn-sm btn-primary" onclick="adicionarLote('${v.sku}')">
              <i class="fas fa-tag me-1"></i>Etiqueta
            </button>
          </li>`;
            });
            $body.html(html + '</ul>');
        });
    }

    // marca direto no batch
    function adicionarLote(code) {
        $('#modalBatch').modal('show');
        const chk = $(`.batch-item[data-code="${code}"]`);
        chk.prop('checked', true);
        // Scroll to the checked item
        setTimeout(() => {
            const container = document.querySelector('#modalBatch .modal-body');
            const element = chk[0];
            if (container && element) {
                container.scrollTop = element.offsetTop - container.offsetTop - 20;
            }
        }, 300);
    }

    function gerarBatch() {
        const items = [];
        $('.batch-item:checked').each(function(i, el) {
            const code = $(el).data('code');
            const qty = parseInt($(el).closest('li').find('.batch-qty').val()) || 1;
            const name = $(el).data('name') || '';
            const variation = $(el).data('variation') || '';
            items.push({
                code,
                name,
                variation,
                qty
            });
        });

        if (!items.length) {
            return alert('Selecione ao menos um item');
        }
        $('#modalBatch').modal('hide');
        $('.modal-backdrop').remove();

        window.addEventListener('afterprint', () => {
            window.location.reload();
        });

        // 1. Explode em array plano
        const flatLabels = [];
        items.forEach(it => {
            for (let i = 0; i < it.qty; i++) {
                flatLabels.push({
                    ...it
                });
            }
        });

        const perPage = 12;
        const $print = $('#printArea').empty().show();

        // 2. Gera uma <div.page> para cada grupo de perPage etiquetas
        for (let offset = 0; offset < flatLabels.length; offset += perPage) {
            const grupo = flatLabels.slice(offset, offset + perPage);
            const $page = $('<div class="page"></div>').appendTo($print);

            grupo.forEach(it => {
                const $lbl = $('<div class="label-item"></div>').appendTo($page);
                $lbl.html(`
        <div class="header-row">
          <div class="left-block">
            <div class="item-name">${it.name}</div>
            <div class="item-variation">${it.variation}</div>
          </div>
          <div class="right-block">
            <div class="store-name">@silbela_flor</div>
            <div class="warranty">Garantia 7 dias</div>
          </div>
        </div>
        <div class="barcode-wrapper"></div>
        <div class="id-code">${it.code}</div>
      `);
                // gera o SVG
                const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                $lbl.find('.barcode-wrapper')[0].appendChild(svg);
                JsBarcode(svg, it.code, {
                    format: 'CODE128',
                    width: 2,
                    height: 60,
                    displayValue: false,
                    margin: 0
                });
            });
        }

        // dispara o print e limpa
        setTimeout(() => {
            window.print();
            $print.hide().empty();
        }, 200);
    }
    </script>
</body>

</html>