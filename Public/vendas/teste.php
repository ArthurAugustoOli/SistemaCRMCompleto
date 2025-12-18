<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// PHP code remains unchanged - keeping all your backend functionality
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
require_once '../../app/models/Funcionario.php'; // Para obter a lista de funcionários
require_once '../../app/models/VendaParcela.php';

// Re-inclusão do config (se necessário) e verificação de sessão
require_once '../../app/config/config.php';
require_once '../login/verificar_sessao.php';



// Configuração de paginação
$limite_por_pagina = 7;
$pagina_atual = max((int)($_GET['pagina'] ?? 1), 1);
$offset = ($pagina_atual - 1) * $limite_por_pagina;
$search     = trim($_GET['search']     ?? '');
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date']   ?? '';


// Instancia os models
$clienteModel      = new Cliente($mysqli);
$vendaModel        = new Venda($mysqli);
$itemModel         = new ItemVenda($mysqli);
$produtoModel      = new Produto($mysqli);
$variacaoModel     = new ProdutoVariacao($mysqli);
$vendaProdutoModel = new VendaProduto($mysqli);
$funcionarioModel  = new \App\Models\Funcionario($mysqli);
$vparcModel        = new \App\Models\VendaParcela($mysqli);

// Carrega os dados para a página
$produtos      = $produtoModel->getAll();
$clientes      = $clienteModel->getAll();
$funcionarios  = $funcionarioModel->getAll();
$total_filtradas = $vendaModel->getTotalVendasFiltradas($search, $start_date, $end_date);
$total_paginas   = ceil($total_filtradas / $limite_por_pagina);
$estatisticas  = $vendaModel->getEstatisticasDashboard();

$vendas = $vendaModel->getVendasFiltradas(
  $search,
  $start_date,
  $end_date,
  $offset,
  $limite_por_pagina
);

// =================================================
// 2. ENDPOINTS AJAX
// =================================================

// 2.1 Carrega variações de um produto (recebe o id do produto)
if (isset($_GET['carregar_variacoes'])) {
    header('Content-Type: application/json');
    echo json_encode($variacaoModel->getAllByProduto((int)$_GET['carregar_variacoes']));
    exit;
}

// 2.2 Busca produto por código de barras ou SKU
if (isset($_GET['buscar_codigo'])) {
    header('Content-Type: application/json');
    $codigo = $_GET['codigo'];
    // Tenta buscar pelo código de barras no produto
    $produto = $produtoModel->getByCode($codigo);
    if ($produto) {
        echo json_encode($produto);
    } else {
        // Caso não encontre, tenta buscar pelo SKU na variação
        $variacao = $variacaoModel->getBySKU($codigo);
        if ($variacao) {
            $produto = $produtoModel->getById($variacao['id_produto']);
            $variacao['produto_nome'] = $produto['nome'];
            echo json_encode($variacao);
        } else {
            echo json_encode(null);
        }
    }
    exit;
}

// 2.3 Carrega os detalhes de uma venda (para exibição em modal)
if (isset($_GET['detalhes_venda'])) {
    $id_venda = (int)$_GET['detalhes_venda'];
    $venda = $vendaModel->getById($id_venda);
    $itens = $itemModel->getByVenda($id_venda);
    ob_start();
    ?>
    <div class="mobile-card mb-3">
      <div class="mobile-card-header">
        <h6 class="mb-0">
          <i class="bi bi-receipt me-2"></i>Detalhes da Venda #<?= $venda['id_venda'] ?>
        </h6>
      </div>
      <div class="mobile-card-body">
        <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($venda['data_venda'])) ?></p>
        <p><strong>Desconto:</strong> R$ <?= number_format($venda['desconto'], 2, ',', '.') ?></p>
        <p><strong>Total:</strong> R$ <?= number_format($venda['total_venda'], 2, ',', '.') ?></p>
        <hr>
        <div class="table-responsive">
          <table class="table table-bordered mb-0">
            <thead class="table-light">
              <tr>
                <th>Produto</th>
                <th>Variação</th>
                <th>Quantidade</th>
                <th>Preço</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($itens as $item): ?>
              <tr>
                <td><?= htmlspecialchars($item['produto_nome']) ?></td>
                <td>
                  <?= $item['cor']     ? htmlspecialchars($item['cor'])     : '' ?> /
                  <?= $item['tamanho'] ? htmlspecialchars($item['tamanho']) : '' ?>
                </td>
                <td><?= $item['quantidade'] ?></td>
                <td>R$ <?= number_format($item['preco_venda'], 2, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php
    echo ob_get_clean();
    exit;
}

// 2.4 Verifica a senha do funcionário via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'checkFuncionario' &&
    isset($_GET['password'])) 
{
    $password = $_GET['password'];

    // Agora buscamos somente pelo campo senha:
    $stmt = $mysqli->prepare("SELECT id_funcionario FROM funcionarios WHERE senha = ? LIMIT 1");
    $stmt->bind_param("s", $password);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res) {
        echo json_encode([
            'success'       => true,
            'id_funcionario'=> (int)$res['id_funcionario']
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}


// =================================================
// 3. PROCESSAMENTO DO POST (FINALIZAÇÃO DA VENDA)
// =================================================
  if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
      $id_venda = (int) $_GET['id'];
      if ($vendaModel->delete($id_venda)) {
          header('Location: index.php?msg=Venda excluída com sucesso.&msg_type=success');
          exit;
      } else {
          header('Location: index.php?msg=Erro ao excluir venda.&msg_type=danger');
          exit;
      }
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) Verifica funcionário
    if (!isset($_POST['id_funcionario']) || empty($_POST['id_funcionario'])) {
        header("Location: index.php?msg=Venda não finalizada. Funcionário não informado.&msg_type=warning");
        exit;
    }

    $id_cliente      = $_POST['id_cliente'] ?? null;
    $itens           = $_POST['itens']      ?? [];
    $data            = $_POST['data']       ?? date('Y-m-d H:i:s');
    $desconto_reais  = floatval(str_replace(',', '.', $_POST['desconto_reais'] ?? 0));
    $metodo          = $_POST['metodo_pagamento'] ?? 'pix';
    $isParcelado     = isset($_POST['parcelado']) ? 1 : 0;    // --- validação do método de pagamento ---
    $metodosValidos = ['cartao_credito','pix','dinheiro','cartao_debito'];
    if (! in_array($metodo, $metodosValidos, true)) {
        header("Location: index.php?msg=Método de pagamento inválido.&msg_type=warning");
        exit;
    }

    $numParcelas     = $isParcelado ? max(1, (int)$_POST['num_parcelas']) : 1;
    $id_funcionario  = (int) $_POST['id_funcionario'];

    // 2) Inicia a transação antes de criar a venda
    $mysqli->begin_transaction();

    try {
        // 3) Cria a venda dentro da transação
        $id_venda = $vendaModel->criarComData($data, $id_cliente, $id_funcionario);

        // 4) Processa itens (lança Exception se faltar estoque)
        $subtotal = $vendaModel->processarItensComValidacao(
            $id_venda,
            $itens,
            $produtoModel,
            $variacaoModel,
            $vendaProdutoModel
        );

 // 5) Calcula total com desconto
        $total_com_desconto = max($subtotal - $desconto_reais, 0.0);
            // --- taxas da maquininha para cada método ---
    $taxaCredito = 0.025;  // 2,5%
    $taxaDebito  = 0.015;  // 1,5%

    // 6) Calcula taxa diferenciada para crédito e débito
    if ($metodo === 'cartao_credito') {
        // taxa de crédito: 2,5% POR parcela
        $taxaTotal = $total_com_desconto * $taxaCredito * $numParcelas;
    }
    elseif ($metodo === 'cartao_debito') {
        // taxa de débito: 1,5% (sempre 1 parcela)
        $taxaTotal = $total_com_desconto * $taxaDebito;
    }
    else {
        // pix e dinheiro não têm taxa
        $taxaTotal = 0.0;
    }


        // 6) Atualiza a venda: método, parcelado, número de parcelas, taxa e desconto
        $vendaModel->atualizarPagamentoEParcelas(
            $id_venda,
            $metodo,
            $isParcelado,
            $numParcelas,
            $taxaTotal,
            $desconto_reais
        );

        // 7) Se parcelado, cria as parcelas
        if ($isParcelado) {
            for ($i = 1; $i <= $numParcelas; $i++) {
                $dtVenc = (new \DateTime($data))
                          ->modify('+'.($i-1).' month')
                          ->format('Y-m-d');

                $valorBase    = $total_com_desconto / $numParcelas;
                $taxaParc     = $taxaTotal        / $numParcelas;
                $valorParcela = $valorBase + $taxaParc;

                $vparcModel->criarParcela(
                    $id_venda,
                    $i,
                    $valorParcela,
                    $dtVenc,
                    $taxaParc
                );
            }
        }

        // 8) Se tudo deu certo, faz commit e redireciona com sucesso
        $mysqli->commit();
        header('Location: index.php?msg=Venda finalizada com sucesso.&msg_type=success');
        exit;
    }
    catch (Exception $e) {
        // 9) Em caso de qualquer erro (ex.: estoque insuficiente), faz rollback e notifica
        $mysqli->rollback();
        $mensagem = urlencode("Erro ao processar venda: " . $e->getMessage());
        header("Location: index.php?msg=$mensagem&msg_type=danger");
        exit;
    }
  }

  if (isset($_GET['action'])) {
  $action = $_GET['action'];

  switch ($action) {
    case 'create':
      // (já existente: formulário de criação)
      break;

    case 'store':
      // (já existente: salvar nova venda)
      break;

    // =========================
    // *** NOVO: Editar venda ***
    // =========================
    case 'edit':
      $id_venda = (int)($_GET['id'] ?? 0);
      if ($id_venda <= 0) {
        // redireciona ou mostra erro
        header("Location: index.php?msg=ID inválido para edição&msg_type=danger");
        exit;
      }
      // Carrega a venda
      $sale = $vendaModel->getById($id_venda);
      if (!$sale) {
        header("Location: index.php?msg=Venda não encontrada&msg_type=danger");
        exit;
      }
      // Carrega itens
      $saleItems = $itemModel->getByVenda($id_venda);
      // (A tela de edição: exibir "edit_venda_form.php" ou algo do tipo)
      require 'edit_venda_form.php';
      exit;
      
  }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Vendas | SysGestão</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 CSS, Bootstrap Icons, Font Awesome, Google Fonts, Animate.css, AOS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css">
  <style>
    :root {
      /* Cores principais */
      --primary: #4361ee;
      --primary-dark: #3a56d4;
      --primary-light: #eef2ff;
      --secondary: #7209b7;
      --success: #06d6a0;
      --info: #4cc9f0;
      --warning: #f9c74f;
      --danger: #ef476f;
      --light: #f8f9fa;
      --dark: #212529;
      --gray: #6c757d;
      --gray-dark: #343a40;
      --gray-light: #f1f3f5;
      
      /* Variáveis de layout */
      --body-bg: #f5f7fb;
      --sidebar-width: 280px;
      --topbar-height: 70px;
      --card-border-radius: 0.75rem;
      --btn-border-radius: 0.5rem;
      --transition-speed: 0.3s;
      --bottom-nav-height: 60px;
      --mobile-header-height: 60px;
      
      /* Cores do sidebar (baseadas na imagem) */
      --sidebar-bg-start: #4e54c8;
      --sidebar-bg-end: #5a67d8;
    }
    
    /* Estilos Gerais */
    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--body-bg);
      color: var(--dark);
      overflow-x: hidden;
      transition: background-color var(--transition-speed);
      margin: 0;
      padding: 0;
    }
    
    /* Scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }
    ::-webkit-scrollbar-track {
      background: var(--light);
    }
    ::-webkit-scrollbar-thumb {
      background: var(--gray);
      border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb:hover {
      background: var(--primary);
    }
    
    /* Sidebar */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: var(--sidebar-width);
      height: 100vh;
      background: linear-gradient(135deg, var(--sidebar-bg-start) 0%, var(--sidebar-bg-end) 100%);
      color: white;
      z-index: 1000;
      transition: transform var(--transition-speed);
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
      overflow-y: auto;
    }
    
    .sidebar-logo {
      padding: 1.5rem;
      display: flex;
      align-items: center;
      font-size: 1.5rem;
      font-weight: 700;
      color: white;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .sidebar-logo i {
      font-size: 1.75rem;
      margin-right: 0.75rem;
    }
    
    .sidebar-menu {
      padding: 1.5rem 0;
    }
    
    .menu-header {
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.5);
      letter-spacing: 0.05rem;
      margin-top: 1rem;
      padding: 0 1.5rem;
    }
    
    .sidebar-menu a {
      display: flex;
      align-items: center;
      padding: 0.75rem 1.5rem;
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      transition: all var(--transition-speed);
      border-left: 3px solid transparent;
    }
    
    .sidebar-menu a:hover, 
    .sidebar-menu a.active {
      background-color: rgba(255, 255, 255, 0.1);
      color: white;
      border-left-color: white;
    }
    
    .sidebar-menu a i {
      margin-right: 0.75rem;
      font-size: 1.1rem;
      width: 1.5rem;
      text-align: center;
    }
    
    /* Main Content */
    .main-content {
      margin-left: var(--sidebar-width);
      transition: margin-left var(--transition-speed), width var(--transition-speed);
      width: calc(100% - var(--sidebar-width));
      padding: 1.5rem;
      padding-top: 70px; /* Altura do header */
    }
    
    .sidebar-collapsed .main-content {
      margin-left: 0;
      width: 100%;
    }
    
    /* Header */
    .app-header {
      position: fixed;
      top: 0;
      right: 0;
      left: var(--sidebar-width);
      height: 60px;
      background-color: #fff;
      border-bottom: 1px solid #eaeaea;
      display: flex;
      align-items: center;
      padding: 0 1.5rem;
      z-index: 999;
      transition: left var(--transition-speed);
    }
    
    .sidebar-collapsed .app-header {
      left: 0;
    }
    
    .header-search {
      flex: 1;
      max-width: 400px;
      position: relative;
    }
    
    .header-search input {
      width: 100%;
      height: 40px;
      padding: 0 1rem 0 2.5rem;
      border-radius: 0.5rem;
      border: 1px solid #e9ecef;
      background-color: #f8f9fa;
      font-size: 0.875rem;
    }
    
    .header-search i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: #6c757d;
    }
    
    .header-actions {
      display: flex;
      align-items: center;
      margin-left: auto;
    }
    
    .header-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #6c757d;
      font-size: 1.25rem;
      margin-left: 0.5rem;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .header-icon:hover {
      background-color: #f8f9fa;
      color: var(--primary);
    }
    
    /* Breadcrumb */
    .breadcrumb-container {
      margin-bottom: 1.5rem;
    }
    
    .breadcrumb-item a {
      color: var(--primary);
      text-decoration: none;
    }
    
    .breadcrumb-item.active {
      color: var(--gray);
    }
    
    /* Page Header */
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }
    
    .page-title {
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0;
      display: flex;
      align-items: center;
    }
    
    .page-title i {
      margin-right: 0.75rem;
      color: var(--primary);
    }
    
    /* Stats Cards */
    .stat-card {
      background-color: white;
      border-radius: var(--card-border-radius);
      padding: 1.5rem;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      margin-bottom: 1.5rem;
      transition: transform 0.3s, box-shadow 0.3s;
      border: none;
      display: flex;
      align-items: center;
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.75rem;
      margin-right: 1.25rem;
      position: relative;
      z-index: 1;
    }
    
    .stat-icon::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      border-radius: 12px;
      background: currentColor;
      opacity: 0.15;
      z-index: -1;
    }
    
    .stat-icon.blue {
      color: var(--primary);
    }
    
    .stat-icon.green {
      color: var(--success);
    }
    
    .stat-icon.orange {
      color: var(--warning);
    }
    
    .stat-icon.red {
      color: var(--danger);
    }
    
    .stat-info {
      flex: 1;
    }
    
    .stat-value {
      font-size: 1.75rem;
      font-weight: 700;
      margin-bottom: 0.25rem;
      color: var(--dark);
    }
    
    .stat-label {
      color: var(--gray);
      margin: 0;
      font-size: 0.875rem;
    }
    
    .stat-card .stat-bg {
      position: absolute;
      bottom: -15px;
      right: -15px;
      font-size: 5rem;
      opacity: 0.05;
      transform: rotate(-15deg);
    }
    
    /* Data Card */
    .data-card {
      background-color: white;
      border-radius: var(--card-border-radius);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      margin-bottom: 1.5rem;
      overflow: hidden;
      border: none;
    }
    
    .card-header {
      padding: 1.25rem 1.5rem;
      background-color: white;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .card-header h5 {
      margin: 0;
      font-weight: 600;
      display: flex;
      align-items: center;
    }
    
    .card-header h5 i {
      margin-right: 0.75rem;
      color: var(--primary);
      font-size: 1.25rem;
    }
    
    .card-body {
      padding: 1.5rem;
    }
    
    /* Enhanced Data Table */
    .data-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      margin-bottom: 0;
    }
    
    .data-table th {
      background-color: var(--gray-light);
      font-weight: 600;
      color: var(--dark);
      padding: 1rem 1.5rem;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05rem;
      border: none;
      white-space: nowrap;
    }
    
    .data-table td {
      padding: 1rem 1.5rem;
      vertical-align: middle;
      border-top: 1px solid #f1f3f5;
      color: var(--dark);
    }
    
    .data-table tr:hover {
      background-color: rgba(67, 97, 238, 0.03);
    }
    
    .data-table tr:last-child td {
      border-bottom: none;
    }
    
    /* Status Badges */
    .status-badge {
      padding: 0.5rem 1rem;
      border-radius: 50px;
      font-size: 0.75rem;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
    }
    
    .status-badge i {
      margin-right: 0.5rem;
      font-size: 0.75rem;
    }
    
    .status-badge.success {
      background-color: rgba(6, 214, 160, 0.1);
      color: var(--success);
    }
    
    .status-badge.warning {
      background-color: rgba(249, 199, 79, 0.1);
      color: var(--warning);
    }
    
    .status-badge.danger {
      background-color: rgba(239, 71, 111, 0.1);
      color: var(--danger);
    }
    
    /* Action Buttons */
    .action-buttons {
      display: flex;
      gap: 0.5rem;
    }
    
    .btn-action {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: none;
      cursor: pointer;
      transition: all 0.2s;
      font-size: 1rem;
    }
    
    .btn-action.view {
      background-color: rgba(76, 201, 240, 0.1);
      color: var(--info);
    }
    
    .btn-action.edit {
      background-color: rgba(249, 199, 79, 0.1);
      color: var(--warning);
    }
    
    .btn-action.delete {
      background-color: rgba(239, 71, 111, 0.1);
      color: var(--danger);
    }
    
    .btn-action:hover {
      transform: translateY(-3px);
    }
    
    .btn-action.view:hover {
      background-color: var(--info);
      color: white;
    }
    
    .btn-action.edit:hover {
      background-color: var(--warning);
      color: white;
    }
    
    .btn-action.delete:hover {
      background-color: var(--danger);
      color: white;
    }
    
    /* Buttons */
    .btn {
      border-radius: var(--btn-border-radius);
      padding: 0.5rem 1.25rem;
      font-weight: 500;
      transition: all 0.3s;
      position: relative;
      overflow: hidden;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    
    .btn i {
      margin-right: 0.5rem;
      font-size: 1rem;
    }
    
    .btn::before {
      content: "";
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      background-color: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      transform: translate(-50%, -50%);
      transition: width 0.5s, height 0.5s;
      z-index: 0;
    }
    
    .btn:hover::before {
      width: 300%;
      height: 300%;
    }
    
    .btn * {
      position: relative;
      z-index: 1;
    }
    
    .btn-primary {
      background-color: var(--primary);
      border-color: var(--primary);
    }
    
    .btn-primary:hover,
    .btn-primary:focus {
      background-color: var(--primary-dark);
      border-color: var(--primary-dark);
    }
    
    .btn-add {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.75rem 1.5rem;
      font-weight: 500;
      border-radius: var(--btn-border-radius);
      box-shadow: 0 4px 10px rgba(67, 97, 238, 0.2);
    }
    
    .btn-add:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(67,97,238,0.2);
    }
    
    .btn-add:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(67, 97, 238, 0.3);
    }
    
    /* Form Styling */
    .form-control, .form-select {
      border-radius: 0.5rem;
      padding: 0.6rem 1rem;
      border: 1px solid #e9ecef;
      transition: all 0.3s;
    }
    
    .form-control:focus, .form-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
    }
    
    .form-label {
      font-weight: 500;
      margin-bottom: 0.5rem;
      color: var(--dark);
    }
    
    .input-group-text {
      border-radius: 0.5rem;
      background-color: var(--gray-light);
      border: 1px solid #e9ecef;
    }
    
    /* Filter Form */
    .filter-form {
      background-color: var(--light);
      border-radius: var(--card-border-radius);
      padding: 1.25rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      border: none;
    }
    
    /* Item Card */
    .item-card {
      background-color: white;
      border-radius: var(--card-border-radius);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      border: none;
      position: relative;
      transition: all 0.3s;
    }
    
    .item-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .item-remove-btn {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: none;
      border: none;
      color: var(--danger);
      font-size: 1.25rem;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    .item-remove-btn:hover {
      transform: scale(1.2);
    }
    
    /* Mobile Drawer Menu */
    .mobile-drawer {
      position: fixed;
      top: 0;
      left: 0;
      width: var(--sidebar-width);
      height: 100vh;
      background: linear-gradient(135deg, var(--sidebar-bg-start) 0%, var(--sidebar-bg-end) 100%);
      color: white;
      z-index: 2000;
      transform: translateX(-100%);
      transition: transform 0.3s ease-in-out;
      box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
      overflow-y: auto;
    }
    
    .mobile-drawer.show {
      transform: translateX(0);
    }
    
    .drawer-header {
      padding: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .drawer-logo {
      display: flex;
      align-items: center;
      font-size: 1.5rem;
      font-weight: 700;
      color: white;
    }
    
    .drawer-logo i {
      font-size: 1.75rem;
      margin-right: 0.75rem;
    }
    
    .drawer-close {
      background: none;
      border: none;
      color: white;
      font-size: 1.5rem;
      cursor: pointer;
    }
    
    .drawer-menu {
      padding: 1.5rem 0;
    }
    
    .menu-section {
      margin-bottom: 1.5rem;
    }
    
    .section-title {
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.5);
      letter-spacing: 0.05rem;
      padding: 0 1.5rem;
      margin-bottom: 0.5rem;
    }
    
    .drawer-item {
      display: flex;
      align-items: center;
      padding: 0.75rem 1.5rem;
      color: rgba(255, 255, 255, 0.8);
      text-decoration: none;
      transition: all 0.3s;
    }
    
    .drawer-item i {
      margin-right: 0.75rem;
      font-size: 1.1rem;
      width: 1.5rem;
      text-align: center;
    }
    
    .drawer-item:hover,
    .drawer-item.active {
      background-color: rgba(255, 255, 255, 0.1);
      color: white;
    }
    
    .drawer-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1999;
      display: none;
    }
    
    .drawer-overlay.show {
      display: block;
    }
    
    /* Mobile Bottom Navigation */
    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      height: var(--bottom-nav-height);
      background-color: white;
      box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
      display: flex;
      justify-content: space-around;
      align-items: center;
      z-index: 1001;
      border-top-left-radius: 20px;
      border-top-right-radius: 20px;
    }
    
    .nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: var(--gray);
      text-decoration: none;
      font-size: 0.7rem;
      padding: 0.5rem 0;
      transition: all 0.3s;
    }
    
    .nav-item i {
      font-size: 1.25rem;
      margin-bottom: 0.25rem;
      transition: all 0.3s;
    }
    
    .nav-item:hover,
    .nav-item.active {
      color: var(--primary);
    }
    
    .nav-item:hover i,
    .nav-item.active i {
      transform: translateY(-2px);
    }
    
    .nav-item.center-item {
      transform: translateY(-15px);
    }
    
    .nav-item.center-item .nav-circle {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--sidebar-bg-start) 0%, var(--sidebar-bg-end) 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
      transition: all 0.3s;
    }
    
    .nav-item.center-item:hover .nav-circle {
      transform: scale(1.1);
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
    }
    
    /* Mobile FAB Button */
    .mobile-fab {
      position: fixed;
      bottom: 80px;
      right: 20px;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--sidebar-bg-start) 0%, var(--sidebar-bg-end) 100%);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
      border: none;
      cursor: pointer;
      z-index: 999;
      transition: all 0.3s;
    }
    
    .mobile-fab:hover {
      transform: scale(1.1) rotate(10deg);
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
    }
    
    /* Mobile Card */
    .mobile-card {
      background-color: white;
      border-radius: var(--card-border-radius);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      margin-bottom: 1.5rem;
      overflow: hidden;
      transition: all 0.3s;
    }
    
    .mobile-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .mobile-card-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    .mobile-card-header h6 {
      margin: 0;
      font-weight: 600;
      display: flex;
      align-items: center;
    }
    
    .mobile-card-header h6 i {
      margin-right: 0.75rem;
      color: var(--primary);
    }
    
    .mobile-card-body {
      padding: 1.5rem;
    }
    
    .mobile-card-footer {
      padding: 1rem 1.5rem;
      border-top: 1px solid rgba(0, 0, 0, 0.05);
      background-color: var(--gray-light);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    
    /* Mobile Stats */
    .mobile-stats {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
    }
    
    .mobile-stat-card {
      background-color: white;
      border-radius: var(--card-border-radius);
      padding: 1.25rem;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
    }
    
    .mobile-stat-icon {
      width: 50px;
      height: 50px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 1rem;
    }
    
    .mobile-stat-value {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }
    
    .mobile-stat-label {
      font-size: 0.75rem;
      color: var(--gray);
    }
    
    /* Mobile Pull-to-refresh */
    .mobile-ptr-container {
      position: relative;
      overflow: hidden;
    }
    
    .mobile-ptr-indicator {
      position: absolute;
      top: -50px;
      left: 0;
      width: 100%;
      height: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--gray-light);
      transition: transform 0.3s;
      z-index: 10;
    }
    
    /* Loader */
    .loader {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(255, 255, 255, 0.9);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      transition: opacity 0.5s;
    }
    
    .spinner {
      width: 50px;
      height: 50px;
      border: 5px solid rgba(67, 97, 238, 0.2);
      border-radius: 50%;
      border-top-color: var(--primary);
      animation: spin 1s ease-in-out infinite;
    }
    
    @keyframes spin {
      to { transform: rotate(360deg); }
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
    
    /* Modal Styling */
    .modal-content {
      border: none;
      border-radius: var(--card-border-radius);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }
    
    .modal-header {
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      padding: 1.25rem 1.5rem;
      background-color: white;
    }
    
    .modal-title {
      font-weight: 600;
      display: flex;
      align-items: center;
    }
    
    .modal-title i {
      margin-right: 0.75rem;
      color: var(--primary);
      font-size: 1.25rem;
    }
    
    .modal-body {
      padding: 1.5rem;
    }
    
    .modal-footer {
      border-top: 1px solid rgba(0, 0, 0, 0.05);
      padding: 1.25rem 1.5rem;
      background-color: var(--gray-light);
    }
    
    /* Pagination */
    .pagination {
      display: flex;
      justify-content: center;
      margin-top: 1.5rem;
    }
    
    .pagination .page-item .page-link {
      border: none;
      margin: 0 0.25rem;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--dark);
      transition: all 0.3s;
    }
    
    .pagination .page-item .page-link:hover {
      background-color: var(--primary-light);
      color: var(--primary);
    }
    
    .pagination .page-item.active .page-link {
      background-color: var(--primary);
      color: white;
      box-shadow: 0 4px 10px rgba(67, 97, 238, 0.2);
    }
    
    .pagination .page-item.disabled .page-link {
      color: var(--gray);
      background-color: transparent;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 992px) {
      .sidebar {
        transform: translateX(-100%);
      }
      
      .sidebar.show {
        transform: translateX(0);
      }
      
      .main-content {
        margin-left: 0;
        width: 100%;
        padding-bottom: calc(var(--bottom-nav-height) + 1rem);
      }
      
      .app-header {
        left: 0;
      }
      
      .mobile-header {
        display: flex;
      }
      
      .header, .breadcrumb-container {
        display: none;
      }
      
      .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
      }
      
      .bottom-nav, .mobile-fab {
        display: flex;
      }
    }
    
    /* Dark Mode Support */
    body.dark-mode {
      --body-bg: #121212;
      --dark: #ffffff;
      --light: #1e1e1e;
      --gray-light: #2d2d2d;
      --gray: #adb5bd;
      --gray-dark: #6c757d;
    }
    body.dark-mode input::placeholder,
    body.dark-mode .form-select::placeholder {
      color: #adb5bd;   /* ou qualquer cinza que você goste */
      opacity: 1; 
    }
    body.dark-mode .filter-form,
body.dark-mode .breadcrumb-container,
body.dark-mode .card-header {
  background-color: var(--light);
}

body.dark-mode .btn-outline-primary {
  border-color: var(--gray-dark);
  color: var(--gray);
}
    body.dark-mode .card,
    body.dark-mode .stat-card,
    body.dark-mode .data-card,
    body.dark-mode .item-card,
    body.dark-mode .mobile-card,
    body.dark-mode .mobile-stat-card,
    body.dark-mode .modal-content,
    body.dark-mode .app-header,
    body.dark-mode .header,
    body.dark-mode .bottom-nav {
      background-color: #1e1e1e;
      color: white;
    }
    
    body.dark-mode .card-header,
    body.dark-mode .mobile-card-header,
    body.dark-mode .modal-header {
      background-color: #1e1e1e;
      border-bottom-color: #2d2d2d;
    }
    
    body.dark-mode .card-footer,
    body.dark-mode .mobile-card-footer,
    body.dark-mode .modal-footer {
      background-color: #1e1e1e;
      border-top-color: #2d2d2d;
    }
    body.dark-mode .main-content {
      background-color: #2d2d2d;
    }

    body.dark-mode form {
      background-color: #2d2d2d;
      color: white;
    }
    body.dark-mode .form-control {
      color: white;
    }

    body.dark-mode .filter-form {
      background-color:rgb(61, 61, 61)
    }

    
    body.dark-mode .form-control,
    body.dark-mode .form-select,
    body.dark-mode .input-group-text {
      background-color: #2d2d2d;
      border-color: #2d2d2d;
      color: white;
    }
    
    body.dark-mode .data-table th {
      background-color: #2d2d2d;
      color: white;
    }
    
    body.dark-mode .data-table td {
      border-color: #2d2d2d;
      color: white;
    }
    
    body.dark-mode .data-table tr:hover {
      background-color: rgba(255, 255, 255, 0.05);
    }
    
    body.dark-mode #btn-darkmode i {
      color: #ffc107;
    }
    
    body.dark-mode .header-search input {
      background-color: #2d2d2d;
      border-color: #2d2d2d;
      color: white;
    }
    
    body.dark-mode .header-search i {
      color: #adb5bd;
    }
  @media (prefers-color-scheme: dark-mode) {
    input::placeholder {
      color: white;
      opacity: 1;
    }
  }
  /* Dark-mode toggle sem fundo nem borda */
#btn-darkmode.header-icon {
  background: transparent !important;
  box-shadow: none !important;
  border: none !important;
  transition: transform 0.2s ease, color 0.3s ease;
}

/* Apenas o ícone recebe a cor primária no hover */
#btn-darkmode.header-icon:hover {
  transform: scale(1.2);
  color: var(--primary) !important;
}

/* Feedback de clique */
#btn-darkmode.header-icon:active {
  transform: scale(0.9);
}

/* Gira o ícone na troca */
#btn-darkmode.header-icon i {
  transition: transform 0.3s ease, color 0.3s ease;
}

#btn-darkmode.header-icon:hover i {
  transform: rotate(180deg);
}

  </style>

</head>
<body class="bg-light">
  <?php require_once __DIR__ . '/../includes/notificacoes.php'; ?>

  <!-- Loader -->
  <div class="loader" id="pageLoader">
    <div class="spinner"></div>
  </div>
  

  <!-- Sidebar (Desktop) -->
  <div class="sidebar d-none d-lg-block">
    <div class="sidebar-logo">
      <i class="bi bi-box-seam"></i>
      <span>SysGestão</span>
    </div>
    <div class="sidebar-menu">
      <div class="menu-header">Catálogo</div>
      <a href="../produtos/index.php" class="d-flex align-items-center">
        <i class="fas fa-box"></i>
        <span>Produtos</span>
      </a>
          <a href="../etiqueta/index.php" class="nav-link">
            <i class="fas fa-tags"></i>
            Etiquetas
          </a>
          </li>
          <a href="../importar/index.php" class="nav-link">
            <i class="fas fa-file-import"></i>
            Importar
          </a>
      <a href="../clientes/clientes.php" class="d-flex align-items-center">
        <i class="fas fa-users"></i>
        <span>Clientes</span>
      </a>
      <a href="../funcionarios/index.php" class="nav-link">
      <i class="fas fa-user-tie"></i>
      <span>Funcionários</span>
    </a>
          <a href="../troca/index.php" class="sidebar-nav-link">
          <i class="fa-solid fa-right-left"></i>
            Troca
          </a>
      <a href="index.php" class="d-flex align-items-center active">
        <i class="fas fa-shopping-cart"></i>
        <span>Vendas</span>
      </a>
      <div class="menu-header">RELATÓRIOS</div>
      <a href="index.php" class="d-flex align-items-center">
        <i class="fas fa-chart-bar"></i>
        <span>Relatórios</span>
      </a>
      <a href="../financeiro/index.php" class="sidebar-link">
        <i class="fas fa-wallet"></i> Financeiro
      </a>
      <a href="../despesas/index.php" class="sidebar-link">
        <i class="fas fa-money-bill-wave"></i> Despesas
      </a>
    </div>
  </div>

  <!-- Mobile Drawer Menu -->
  <div class="mobile-drawer" id="mobileDrawer">
    <div class="drawer-header">
      <div class="drawer-logo">
        <i class="bi bi-box-seam"></i>
        <span>SysGestão</span>
      </div>
      <button class="drawer-close" onclick="toggleDrawer()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="drawer-menu">
      <div class="menu-section">
        <div class="section-title">CATÁLOGO</div>
        <a href="../produtos/index.php" class="drawer-item">
          <i class="bi bi-grid"></i>
          <span>Produtos</span>
        </a>
        <a href="../clientes/clientes.php" class="drawer-item">
          <i class="bi bi-people"></i>
          <span>Clientes</span>
        </a>
        <a href="../funcionarios/index.php" class="drawer-item">
          <i class="fas fa-user-tie"></i>
          <span>Funcionários</span>
        </a>
        <a href="index.php" class="drawer-item active">
          <i class="bi bi-cart"></i>
          <span>Vendas</span>
        </a>
      </div>
      <div class="menu-section">
        <div class="section-title">RELATÓRIOS</div>
        <a href="../relatorios/index.php" class="drawer-item">
          <i class="bi bi-file-earmark-text"></i>
          <span>Relatórios</span>
        </a>
        <a href="../financeiro/index.php" class="drawer-item">
          <i class="bi bi-wallet2"></i>
          <span>Financeiro</span>
        </a>
        <a href="../despesas/index.php" class="drawer-item">
          <i class="bi bi-cash-coin"></i>
          <span>Despesas</span>
        </a>
      </div>
    </div>
  </div>
  <div class="drawer-overlay" id="drawerOverlay" onclick="toggleDrawer()"></div>

   <!-- Main Content -->
   <div class="main-content"> 
      <?php
        if (isset($_GET['msg'], $_GET['msg_type'])):
        $texto = urldecode($_GET['msg']);
        $tipo  = $_GET['msg_type']; // “dangerr”
      ?>
    <div class="container mt-3">
      <div class="alert alert-<?= htmlspecialchars($tipo) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($texto) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
      </div>
    </div>
  <?php endif; ?>
    <!-- App Header (New Header) -->
    <div class="app-header">
      <button class="btn btn-sm" id="toggleSidebar" onclick="toggleDrawer()">
        <i class="bi bi-list fs-5"></i>
      </button>

      <div class="header-search">
        <i class="bi bi-search"></i>
      </div>

      <!-- inclui aqui o dropdown de notificações -->

      
      <div class="header-actions">
      </div>
    </div>

    <!-- Mobile Header -->
    <div class="mobile-header d-lg-none">
      <button class="mobile-menu-btn d-none" onclick="toggleDrawer()">
        <i class="bi bi-list"></i>
      </button>
      <div class="mobile-logo">
        <i class="bi bi-box-seam d-none"></i>
        <span class="d-none">Gestão</span>
      </div>
      <div class="mobile-actions"></div>
    </div>

    <!-- Breadcrumb (Desktop) -->
    <div class="breadcrumb-container d-none d-lg-block">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
          <li class="breadcrumb-item"><a href="index.php"><i class="bi bi-house-door"></i> Vendas</a></li>
          <li class="breadcrumb-item active" aria-current="page">Vendas</li>
        </ol>
      </nav>
    </div>

    <!-- Page Content -->
    <div class="page-content">
      <!-- Header (Desktop) -->
      <div class="page-header d-none d-lg-flex" data-aos="fade-down">
        <h1 class="page-title">
          <i class="bi bi-cart-check"></i> Vendas
        </h1>
        <button class="btn btn-primary btn-add" data-bs-toggle="modal" data-bs-target="#modalNovaVenda">
          <i class="bi bi-plus-lg"></i> Nova Venda
        </button>
      </div>

      <!-- Estatísticas (Desktop) -->
      <div class="row d-none d-lg-flex mb-4">
        <div class="col-md-3" data-aos="fade-right" data-aos-delay="100">
          <div class="stat-card">
            <div class="stat-icon blue">
              <i class="bi bi-cart-check"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">
                <?= (int)($estatisticas['vendas_hoje'] ?? 0) ?>
              </div>
              <div class="stat-label">Vendas Hoje</div>
            </div>
          </div>
        </div>
        <div class="col-md-3" data-aos="fade-right" data-aos-delay="200">
          <div class="stat-card">
            <div class="stat-icon green">
              <i class="bi bi-currency-dollar"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">
                R$ <?= number_format($estatisticas['faturamento_hoje'] ?? 0, 2, ',', '.') ?>
              </div>
              <div class="stat-label">Faturamento (Todo Período)</div>
            </div>
            <div class="stat-bg">
            </div>
          </div>
        </div>
        <div class="col-md-3" data-aos="fade-left" data-aos-delay="200">
          <div class="stat-card">
            <div class="stat-icon orange">
              <i class="bi bi-box-seam"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">
                <?= (int)($estatisticas['total_produtos'] ?? 0) ?>
              </div>
              <div class="stat-label">Produtos</div>
            </div>
            <div class="stat-bg">
            </div>
          </div>
        </div>
        <div class="col-md-3" data-aos="fade-left" data-aos-delay="100">
          <div class="stat-card">
            <div class="stat-icon red">
              <i class="bi bi-people"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">
                <?= (int)($estatisticas['total_clientes'] ?? 0) ?>
              </div>
              <div class="stat-label">Clientes</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Estatísticas (Mobile) -->
      <div class="mobile-stats d-lg-none mb-4">
        <div class="mobile-stat-card" data-aos="fade-in" style="animation-delay: 0.1s;">
          <div class="mobile-stat-icon" style="background-color: rgba(67, 97, 238, 0.1); color: #4361ee;">
            <i class="bi bi-cart-check"></i>
          </div>
          <div class="mobile-stat-value">
            <?= (int)($estatisticas['vendas_hoje'] ?? 0) ?>
          </div>
          <div class="mobile-stat-label">Vendas Hoje</div>
        </div>

        <div class="mobile-stat-card" data-aos="fade-in" style="animation-delay: 0.2s;">
          <div class="mobile-stat-icon" style="background-color: rgba(6, 214, 160, 0.1); color: #06d6a0;">
            <i class="bi bi-currency-dollar"></i>
          </div>
          <div class="mobile-stat-value">
            R$ <?= number_format($estatisticas['faturamento_hoje'] ?? 0, 2, ',', '.') ?>
          </div>
          <div class="mobile-stat-label">Faturamento</div>
        </div>

        <div class="mobile-stat-card" data-aos="fade-in" style="animation-delay: 0.3s;">
          <div class="mobile-stat-icon" style="background-color: rgba(249, 199, 79, 0.1); color: #f9c74f;">
            <i class="bi bi-box-seam"></i>
          </div>
          <div class="mobile-stat-value">
            <?= (int)($estatisticas['total_produtos'] ?? 0) ?>
          </div>
          <div class="mobile-stat-label">Produtos</div>
        </div>

        <div class="mobile-stat-card" data-aos="fade-in" style="animation-delay: 0.4s;">
          <div class="mobile-stat-icon" style="background-color: rgba(239, 71, 111, 0.1); color: #ef476f;">
            <i class="bi bi-people"></i>
          </div>
          <div class="mobile-stat-value">
            <?= (int)($estatisticas['total_clientes'] ?? 0) ?>
          </div>
          <div class="mobile-stat-label">Clientes</div>
        </div>
      </div>

      <!-- Filtros de Busca -->
      <div class="filter-form" data-aos="fade-up">
        <form method="get" class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Busca</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text"
                   name="search"
                   value="<?= htmlspecialchars($search) ?>"
                   class="form-control"
                   placeholder= "Cliente ou Funcionário">
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Data Início</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-calendar"></i></span>
              <input type="date"
                   name="start_date"
                   value="<?= htmlspecialchars($start_date) ?>"
                   class="form-control">
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Data Fim</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
              <input type="date"
                   name="end_date"
                   value="<?= htmlspecialchars($end_date) ?>"
                   class="form-control">
            </div>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-filter"></i> Filtrar
            </button>
          </div>
        </form>
      </div>

      <!-- Lista de Vendas (Desktop) -->
      <div class="data-card" id="listagem-vendas" data-aos="fade-up" data-aos-delay="300">
        <div class="card-header">
          <h5><i class="bi bi-list-ul"></i> Lista de Vendas</h5>
          <div>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Cliente</th>
                  <th>Data</th>
                  <th>Total</th>
                  <th>Status</th>
                  <th class="text-center">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($vendas) > 0): ?>
                  <?php foreach ($vendas as $index => $v): ?>
                    <tr data-aos="fade-up" data-aos-delay="<?= 50 * ($index + 1) ?>">
                      <td>#<?= $v['id_venda'] ?></td>
                      <td><?= $v['nome_cliente'] ?></td>
                      <td><?= date('d/m/Y H:i', strtotime($v['data_venda'])) ?></td>
                      <td><span class="fw-bold">R$ <?= number_format($v['total_venda'], 2, ',', '.') ?></span></td>
                      <td>
                        <?php
                          $status = $v['status'] ?? 'concluída';
                          $statusClass = 'success';
                          $statusIcon = 'bi-check-circle-fill';
                          if ($status === 'pendente') {
                              $statusClass = 'warning';
                              $statusIcon = 'bi-clock-fill';
                          } elseif ($status === 'cancelada') {
                              $statusClass = 'danger';
                              $statusIcon = 'bi-x-circle-fill';
                          }
                        ?>
                        <span class="status-badge <?= $statusClass ?>">
                          <i class="bi <?= $statusIcon ?>"></i> <?= ucfirst($status) ?>
                        </span>
                      </td>
<td class="text-center">
  <div class="action-buttons d-flex justify-content-center">
    <!-- Ver detalhes -->
    <button class="btn-action view me-1"
            onclick="abrirModalDetalhes(<?= $v['id_venda'] ?>)"
            title="Ver detalhes">
      <i class="bi bi-eye"></i>
    </button>

    <!-- Botão de excluir -->
    <a href="index.php?action=delete&id=<?= $v['id_venda'] ?>"
       class="btn-action delete"
       title="Excluir"
       onclick="return confirm('Confirma exclusão da venda #<?= $v['id_venda'] ?>?');">
      <i class="bi bi-trash"></i>
    </a>
  </div>
</td>

                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" class="text-center py-4">
                      <div class="text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        <p>Nenhuma venda encontrada</p>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaVenda">
                          <i class="bi bi-plus-circle me-1"></i> Nova Venda
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <!-- Paginação: Apenas seta para próxima página se houver mais vendas -->
          <div class="p-3 text-center">
            <nav aria-label="Navegação de páginas">
              <ul class="pagination justify-content-center">
                <?php if ($pagina_atual > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?pagina=<?= $pagina_atual - 1 ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>">
                      <i class="bi bi-chevron-left"></i>
                    </a>
                  </li>
                <?php else: ?>
                  <li class="page-item disabled">
                    <span class="page-link"><i class="bi bi-chevron-left"></i></span>
                  </li>
                <?php endif; ?>
                
                <?php
                // Mostrar no máximo 5 páginas
                $start_page = max(1, min($pagina_atual - 2, $total_paginas - 4));
                $end_page = min($total_paginas, max($pagina_atual + 2, 5));
                
                if ($start_page > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?pagina=1&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>">1</a>
                  </li>
                  <?php if ($start_page > 2): ?>
                    <li class="page-item disabled">
                      <span class="page-link">...</span>
                    </li>
                  <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                  <li class="page-item <?= $i == $pagina_atual ? 'active' : '' ?>">
                    <a class="page-link" href="?pagina=<?= $i ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_paginas): ?>
                  <?php if ($end_page < $total_paginas - 1): ?>
                    <li class="page-item disabled">
                      <span class="page-link">...</span>
                    </li>
                  <?php endif; ?>
                  <li class="page-item">
                    <a class="page-link" href="?pagina=<?= $total_paginas ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><?= $total_paginas ?></a>
                  </li>
                <?php endif; ?>
                
                <?php if ($pagina_atual < $total_paginas): ?>
                  <li class="page-item">
                    <a class="page-link" href="?pagina=<?= $pagina_atual + 1 ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>">
                      <i class="bi bi-chevron-right"></i>
                    </a>
                  </li>
                <?php else: ?>
                  <li class="page-item disabled">
                    <span class="page-link"><i class="bi bi-chevron-right"></i></span>
                  </li>
                <?php endif; ?>
              </ul>
            </nav>
          </div>
        </div>
      </div>

      <!-- Lista de Vendas (Mobile) -->
      <div class="d-lg-none mobile-ptr-container">
        <div class="mobile-ptr-indicator">
          <i class="bi bi-arrow-clockwise fa-spin"></i> Puxe para atualizar
        </div>
        <?php if (count($vendas) > 0): ?>
          <?php foreach ($vendas as $index => $v): ?>
            <div class="mobile-card" data-aos="fade-up" data-aos-delay="<?= 50 * ($index + 1) ?>">
              <div class="mobile-card-header">
                <h6 class="mb-0">Venda #<?= $v['id_venda'] ?></h6>
                <?php
                  $status = $v['status'] ?? 'concluída';
                  $statusClass = 'success';
                  $statusIcon = 'bi-check-circle-fill';
                  if ($status === 'pendente') {
                      $statusClass = 'warning';
                      $statusIcon = 'bi-clock-fill';
                  } elseif ($status === 'cancelada') {
                      $statusClass = 'danger';
                      $statusIcon = 'bi-x-circle-fill';
                  }
                ?>
                <span class="status-badge <?= $statusClass ?>">
                  <i class="bi <?= $statusIcon ?>"></i> <?= ucfirst($status) ?>
                </span>
              </div>
              <div class="mobile-card-body">
                <div class="d-flex justify-content-between mb-2">
                  <div>
                    <i class="bi bi-person text-primary me-2"></i>
                    <strong>Cliente:</strong>
                  </div>
                  <div><?= $v['nome_cliente'] ?></div>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <div>
                    <i class="bi bi-calendar text-primary me-2"></i>
                    <strong>Data:</strong>
                  </div>
                  <div><?= date('d/m/Y H:i', strtotime($v['data_venda'])) ?></div>
                </div>
                <div class="d-flex justify-content-between">
                  <div>
                    <i class="bi bi-cash text-primary me-2"></i>
                    <strong>Total:</strong>
                  </div>
                  <div class="fw-bold">R$ <?= number_format($v['total_venda'], 2, ',', '.') ?></div>
                </div>
              </div>
              <div class="mobile-card-footer">
                <button class="btn btn-sm btn-outline-primary" onclick="abrirModalDetalhes(<?= $v['id_venda'] ?>)">
                  <i class="bi bi-eye"></i> Detalhes
                </button>
                <div>
                  <button class="btn btn-sm btn-outline-warning me-1">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          
          <!-- Paginação Mobile -->
          <div class="d-flex justify-content-between align-items-center my-3">
            <?php if ($pagina_atual > 1): ?>
              <a href="?pagina=<?= $pagina_atual - 1 ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-chevron-left"></i> Anterior
              </a>
            <?php else: ?>
              <button class="btn btn-sm btn-outline-secondary" disabled>
                <i class="bi bi-chevron-left"></i> Anterior
              </button>
            <?php endif; ?>
            
            <span class="text-muted">Página <?= $pagina_atual ?> de <?= $total_paginas ?></span>
            
            <?php if ($pagina_atual < $total_paginas): ?>
              <a href="?pagina=<?= $pagina_atual + 1 ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>" class="btn btn-sm btn-outline-primary">
                Próxima <i class="bi bi-chevron-right"></i>
              </a>
            <?php else: ?>
              <button class="btn btn-sm btn-outline-secondary" disabled>
                Próxima <i class="bi bi-chevron-right"></i>
              </button>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="mobile-card text-center py-4">
            <div class="mobile-card-body">
              <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
              <p class="text-muted">Nenhuma venda encontrada</p>
              <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaVenda">
                <i class="bi bi-plus-circle me-1"></i> Nova Venda
              </button>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Seção de Detalhes da Venda (Desktop) -->
      <div id="detalhes-venda" class="data-card mb-4" style="display:none;"></div>
    </div>
  </div>

  <!-- Mobile Bottom Navigation -->
  <div class="bottom-nav d-lg-none">
    <a href="../produtos/index.php" class="nav-item">
      <i class="fas fa-box"></i>
      <span>Produtos</span>
    </a>
    <a href="index.php" class="nav-item active">
      <i class="fas fa-shopping-cart"></i>
      <span>Vendas</span>
    </a>
    <a href="#" class="nav-item center-item" data-bs-toggle="modal" data-bs-target="#modalNovaVenda">
      <div class="nav-circle">
        <i class="bi bi-plus-lg"></i>
      </div>
    </a>
    <a href="../financeiro/index.php" class="nav-item">
      <i class="fas fa-wallet"></i>
      <span>Financeiro</span>
    </a>
    <!-- NOVO "Mais" que abre a drawer -->
    <a href="#" class="nav-item" onclick="toggleDrawer()">
      <i class="bi bi-three-dots"></i>
      <span>Mais</span>
    </a>
  </div>

  <!-- Mobile FAB Button -->
  <button class="mobile-fab d-lg-none" data-bs-toggle="modal" data-bs-target="#modalNovaVenda">
    <i class="bi bi-cart-plus"></i>
  </button>

  <!-- ============================
       MODAL: Detalhes da Venda (Mobile)
  ============================ -->
  <div class="modal fade" id="modalDetalhesVenda" tabindex="-1" aria-labelledby="modalDetalhesVendaLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-md-down modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalDetalhesVendaLabel">
            <i class="bi bi-eye me-2"></i>Detalhes da Venda
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body" id="modalDetalhesBody">
          <!-- Conteúdo carregado via AJAX -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ============================
       MODAL: Nova Venda com Verificação do Funcionário
  ============================ -->
  <div class="modal fade" id="modalNovaVenda" tabindex="-1" aria-labelledby="modalNovaVendaLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-md-down modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalNovaVendaLabel">
            <i class="bi bi-cart-plus me-2"></i>Nova Venda
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body" style="max-height:70vh; overflow-y:auto;">
          <form method="POST" id="saleForm" class="needs-validation" novalidate>
            <div class="container">
              <div class="row g-4">
                <!-- Coluna Esquerda: Itens da Venda e Observações -->
                <div class="col-md-8">
                  <div class="mb-3">
  <label class="form-label">Código de Barras</label>
  <div class="input-group">
    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
    <input type="text"
           id="globalBarcode"
           class="form-control"
           placeholder="Digite ou escaneie o código de barras">
    <button type="button"
            class="btn btn-outline-secondary"
            id="btnBuscarProduto">
      Buscar
    </button>
  </div>
</div>
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">
                      <i class="bi bi-box-seam me-2"></i>Itens da Venda
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()">
                      <i class="bi bi-plus-circle"></i> Adicionar Produto
                    </button>
                  </div>
                  <div id="itens"></div>
                  <div class="mt-3">
                    <label class="form-label">Observações</label>
                    <div class="input-group">
                      <span class="input-group-text"><i class="bi bi-pencil"></i></span>
                      <textarea name="observacoes" class="form-control" rows="3" placeholder="Observações sobre a venda..."></textarea>
                    </div>
                  </div>
                </div>
                <!-- Coluna da Direita: Resumo, Cliente e Data -->
                <div class="col-md-4">
                  <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                      <h6 class="mb-3 border-bottom pb-2">
                        <i class="bi bi-receipt me-2"></i>Resumo
                      </h6>
                      <div class="mb-4">
                        <label class="form-label">Subtotal:</label>
                        <div class="input-group">
                          <span class="input-group-text"><i class="bi bi-calculator"></i></span>
                          <input type="text" class="form-control" id="subtotal" readonly value="R$ 0,00">
                        </div>
                      </div>
<!-- Pagamento -->
<div class="mb-4">
  <label class="form-label">Pagamento:</label>
  <select name="metodo_pagamento" id="metodoPagamento" class="form-select mb-2" required>
    <option value="pix">PIX</option>
    <option value="dinheiro">Dinheiro</option>
    <option value="cartao_credito">Cartão de Crédito</option>
    <option value="cartao_debito">Cartão de Débito</option>
  </select>

  <!-- Só aparece se for Cartão de Crédito -->
  <div id="parcelamentoOptions" style="display: none;">
    <div class="form-check form-switch mb-2">
      <input class="form-check-input" type="checkbox" name="parcelado" id="parceladoSwitch">
      <label class="form-check-label" for="parceladoSwitch">Parcelado?</label>
    </div>
    <div>
      <input type="number"
             name="num_parcelas"
             id="numParcelasInput"
             class="form-control"
             value="1"
             min="1"
             placeholder="Número de parcelas">
    </div>
  </div>
</div>
                      <div class="mb-4">
                        <label class="form-label">Desconto (%):</label>
                        <div class="input-group">
                          <span class="input-group-text"><i class="bi bi-percent"></i></span>
                          <input type="number" step="0.01" name="desconto_porcentagem" id="desconto_porcentagem" class="form-control" value="0" min="0" max="100">
                          <span class="input-group-text">%</span>
                        </div>
                        <input type="hidden" name="desconto_reais" id="desconto_reais">
                      </div>
                      <div class="mb-3">
                        <label class="form-label fw-bold">Total:</label>
                        <div class="input-group">
                          <span class="input-group-text"><i class="bi bi-cash"></i></span>
                          <input type="text" class="form-control text-primary fw-bold" id="total" readonly value="R$ 0,00">
                        </div>
                      </div>
                      <div class="mt-4">
                        <h6 class="mb-3"><i class="bi bi-person me-1"></i>Cliente</h6>
                        <div class="input-group mb-3">
                          <span class="input-group-text"><i class="bi bi-people"></i></span>
                          <select name="id_cliente" class="form-select">
                            <?php foreach ($clientes as $cliente): ?>
                              <option value="<?= $cliente['id_cliente'] ?>"><?= $cliente['nome'] ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <h6 class="mb-2"><i class="bi bi-calendar me-1"></i>Data</h6>
                        <div class="input-group">
                          <span class="input-group-text"><i class="bi bi-calendar-date"></i></span>
                          <input type="datetime-local" name="data" class="form-control" value="<?= (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d\TH:i') ?>">
                        </div>
                      </div>
                      <!-- O input oculto para id_funcionario será adicionado via JS após validação no modal de funcionário -->
                      <div class="mt-5 d-grid gap-2">
                        <button type="submit" id="finalizarVendaBtn" class="btn btn-primary btn-lg">
                          <i class="bi bi-check-circle me-1"></i> Finalizar Venda
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                          <i class="bi bi-x-circle me-1"></i> Cancelar
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div><!-- /row -->
            </div><!-- /container -->
          </form>
        </div><!-- /modal-body -->
      </div>
    </div>
  </div>

  <!-- ============================
       MODAL: Verificação do Funcionário
  ============================ -->
  <div class="modal fade" id="modalFuncionario" tabindex="-1" aria-labelledby="modalFuncionarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
      <form id="formFuncionario">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="modalFuncionarioLabel">
              <i class="bi bi-shield-lock me-2"></i>Confirme o Funcionário
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="funcionarioSenha" class="form-label">Senha</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-key"></i></span>
                <input type="password" id="funcionarioSenha" class="form-control" required>
              </div>
            </div>
            <div id="funcionarioFeedback" class="text-danger" style="display:none;">
              Senha incorreta. Tente novamente.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-unlock me-1"></i> Confirmar
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- ============================
       SCRIPTS: Bootstrap, AOS e Custom JS
  ============================ -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
  <script>
    // Inicializa o AOS
    AOS.init({ duration: 800, once: true });

    // Loader: esconde o spinner ao carregar a página
    window.addEventListener('load', () => {
      const loader = document.getElementById('pageLoader');
      if (loader) {
        loader.style.opacity = '0';
        setTimeout(() => { loader.style.display = 'none'; }, 500);
      }
    });

    // Toggle Sidebar (Desktop)
    document.getElementById('toggleSidebar').addEventListener('click', function() {
      document.querySelector('.sidebar').classList.toggle('show');
      document.querySelector('.main-content').classList.toggle('sidebar-open');
      this.classList.add('animate__animated','animate__rubberBand');
      setTimeout(() => { this.classList.remove('animate__animated','animate__rubberBand'); }, 1000);
    });

    // Toggle Mobile Drawer
    function toggleDrawer() {
      document.getElementById('mobileDrawer').classList.toggle('show');
      document.getElementById('drawerOverlay').classList.toggle('show');
    }

    // Variável global com os produtos (para montar os selects)
    const produtos = <?= json_encode($produtos) ?>;

    // FUNÇÃO: Adiciona novo item de venda
    function addItem(preselectedProductId = null, preselectedProductData = null) {
      const container = document.getElementById('itens');
      const index = container.children.length;
      const div = document.createElement('div');
      div.className = 'item-card';
      div.setAttribute('data-produto-item', index);

      let optionsProduto = '<option value="">Selecione...</option>';
      produtos.forEach(p => {
         optionsProduto += `<option value="${p.id_produto}">${p.nome}</option>`;
      });

      div.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0"><i class="bi bi-box-seam me-1"></i>Produto #${index + 1}</h6>
          <button type="button" class="item-remove-btn" onclick="removeItem(this)">
            <i class="bi bi-x-circle"></i>
          </button>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Produto</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-box"></i></span>
              <select name="itens[${index}][id_produto]" class="form-select produto-select" data-index="${index}" required>
                ${optionsProduto}
              </select>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Quantidade</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-123"></i></span>
              <input type="number" name="itens[${index}][quantidade]" min="1" value="1" class="form-control" required oninput="atualizarTotal()">
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Preço Unitário</label>
            <div class="input-group">
              <span class="input-group-text">R$</span>
              <input type="text" class="form-control preco-unitario" readonly id="preco-${index}" value="0,00">
            </div>
          </div>
        </div>
        <div id="variacoes-${index}" class="mt-3"></div>`;
      container.appendChild(div);

      if (preselectedProductId !== null) {
         const sel = div.querySelector('.produto-select');
         sel.value = preselectedProductId;
         setTimeout(() => { sel.dispatchEvent(new Event('change')); }, 100);
      }
      if (preselectedProductData && preselectedProductData.preco_venda) {
         setTimeout(() => {
            const precoInput = div.querySelector(`#preco-${index}`);
            if (precoInput && !div.querySelector('.variacao-select')) {
               precoInput.value = parseFloat(preselectedProductData.preco_venda).toFixed(2).replace('.',',');
               atualizarTotal();
            }
         }, 200);
      }
    }

    // FUNÇÃO: Remove item e reindexa os itens
    function removeItem(btn) {
      const card = btn.closest('.item-card[data-produto-item]');
      if (card) {
        card.remove();
        reindexItems();
        atualizarTotal();
      }
    }
    function reindexItems() {
      const container = document.getElementById('itens');
      const cards = container.querySelectorAll('.item-card[data-produto-item]');
      cards.forEach((card, newIndex) => {
         card.setAttribute('data-produto-item', newIndex);
         const sel = card.querySelector('.produto-select');
         if (sel) {
            sel.dataset.index = newIndex;
            sel.name = `itens[${newIndex}][id_produto]`;
         }
         const qty = card.querySelector("input[type='number']");
         if (qty) {
            qty.name = `itens[${newIndex}][quantidade]`;
         }
         const precoInput = card.querySelector('.preco-unitario');
         if (precoInput) {
            precoInput.id = `preco-${newIndex}`;
         }
         const varContainer = card.querySelector("[id^='variacoes-']");
         if (varContainer) {
            varContainer.id = `variacoes-${newIndex}`;
            const varSel = varContainer.querySelector('.variacao-select');
            if (varSel) {
               varSel.dataset.index = newIndex;
               varSel.name = `itens[${newIndex}][id_variacao]`;
            }
         }
         const header = card.querySelector('.item-card h6');
         if (header) {
            header.innerHTML = `<i class="bi bi-box-seam me-1"></i>Produto #${newIndex + 1}`;
         }
      });
    }

    // FUNÇÃO: Ao mudar o select de produto, carrega as variações (se houver)
    document.addEventListener('change', async function(e) {
      if (e.target.classList.contains('produto-select')) {
         const index = e.target.dataset.index;
         const idProduto = e.target.value;
         const container = document.getElementById(`variacoes-${index}`);
         const precoInput = document.getElementById(`preco-${index}`);
         container.innerHTML = '';
         precoInput.value = '0,00';
         if (!idProduto) return;
         try {
             const resp = await fetch(`index.php?carregar_variacoes=${idProduto}`);
             const variacoes = await resp.json();
             if (variacoes && variacoes.length > 0) {
                let html = `<div class="card border-light shadow-sm">
                   <div class="card-header bg-light">
                     <h6 class="mb-0"><i class="bi bi-tags me-2"></i>Variações Disponíveis</h6>
                   </div>
                   <div class="card-body">
                     <div class="mb-3">
                       <label class="form-label">Selecione uma variação:</label>
                       <div class="input-group">
                         <span class="input-group-text"><i class="bi bi-tag"></i></span>
                         <select name="itens[${index}][id_variacao]" class="form-select variacao-select" data-index="${index}" required>
                           <option value="">Selecione...</option>`;
                variacoes.forEach(v => {
                   const nomeVar = v.nome ? v.nome : (v.cor + ' - ' + v.tamanho);
                   const precoFormatted = parseFloat(v.preco_venda).toFixed(2).replace('.',',');
                   html += `<option value="${v.id_variacao}" data-preco="${v.preco_venda}">${nomeVar} - R$ ${precoFormatted}</option>`;
                });
                html += `</select></div></div></div></div>`;
                container.innerHTML = html;
                const varSel = container.querySelector('.variacao-select');
                if (varSel) {
                   varSel.addEventListener('change', function(){
                       const selected = this.options[this.selectedIndex];
                       const preco = parseFloat(selected.dataset.preco) || 0;
                       precoInput.value = preco.toFixed(2).replace('.',',');
                       atualizarTotal();
                   });
                   if (varSel.options.length > 1) {
                      varSel.selectedIndex = 1;
                      varSel.dispatchEvent(new Event('change'));
                   }
                }
             } else {
                const prodFound = produtos.find(p => p.id_produto == idProduto);
                if (prodFound && prodFound.preco_venda) {
                   precoInput.value = parseFloat(prodFound.preco_venda).toFixed(2).replace('.',',');
                   atualizarTotal();
                }
             }
         } catch (error) {
             console.error('Erro ao carregar variações:', error);
         }
      }
    });

    // FUNÇÃO: Atualiza o total do resumo com base nos itens atuais
    function atualizarTotal() {
       let subtotal = 0;
       const container = document.getElementById('itens');
       const cards = container.querySelectorAll('.item-card[data-produto-item]');
       cards.forEach((card, i) => {
          const precoInput = card.querySelector("input[id^='preco-']");
          const qtdInput = card.querySelector("input[name*='[quantidade]']");
          if (precoInput && qtdInput) {
             const preco = parseFloat(precoInput.value.replace(',', '.')) || 0;
             const qtd = parseInt(qtdInput.value) || 0;
             subtotal += preco * qtd;
          }
       });
       const descontoPerc = parseFloat(document.getElementById('desconto_porcentagem').value) || 0;
       const descontoReais = subtotal * (descontoPerc / 100);
       const total = subtotal - descontoReais;
       document.getElementById('desconto_reais').value = descontoReais.toFixed(2);
       document.getElementById('subtotal').value = `R$ ${subtotal.toFixed(2).replace('.',',')}`;
       document.getElementById('total').value = `R$ ${total.toFixed(2).replace('.',',')}`;
    }

    // EVENTO: Campo global de código de barras
    document.addEventListener('DOMContentLoaded', () => {
       const globalBarcode = document.getElementById('globalBarcode');
       if (globalBarcode) {

       }
       const descontoInput = document.getElementById('desconto_porcentagem');
       if (descontoInput) {
         descontoInput.addEventListener('input', atualizarTotal);
       }
       if (document.getElementById('itens').children.length === 0) {
         addItem();
       }
       const centerNavItem = document.querySelector('.nav-item.center-item');
       if (centerNavItem) {
         centerNavItem.addEventListener('click', function(e) {
           e.preventDefault();
           new bootstrap.Modal(document.getElementById('modalNovaVenda')).show();
         });
       }
    });

    // INTERCEPTA o submit do formulário de venda para abrir o modal de verificação do funcionário
    const saleForm = document.getElementById('saleForm');
    saleForm.addEventListener('submit', function(e) {
       e.preventDefault();
       new bootstrap.Modal(document.getElementById('modalFuncionario')).show();
    });

    // MODAL: Verificação do Funcionário – verifica senha via AJAX
    const formFuncionario = document.getElementById('formFuncionario');
    formFuncionario.addEventListener('submit', async function(e) {
       e.preventDefault();
       const funcionarioSenha = document.getElementById('funcionarioSenha');
       const feedback = document.getElementById('funcionarioFeedback');
       const senha = funcionarioSenha.value.trim();
       if (!senha) {
         feedback.style.display = 'block';
         feedback.textContent = 'Por favor, digite sua senha.';
         return;
       }
       try {
         // Chama SÓ com password:
         const resp = await fetch(`index.php?action=checkFuncionario&password=${encodeURIComponent(senha)}`);
         const result = await resp.json();
         
         if (result.success) {
           let inputFunc = saleForm.querySelector('input[name="id_funcionario"]');
           if (!inputFunc) {
             inputFunc = document.createElement('input');
             inputFunc.type = 'hidden';
             inputFunc.name = 'id_funcionario';
             saleForm.appendChild(inputFunc);
           }
           inputFunc.value = result.id_funcionario;
           bootstrap.Modal.getInstance(document.getElementById('modalFuncionario')).hide();
           saleForm.submit();
         } else {
           feedback.style.display = 'block';
           feedback.textContent = 'Senha incorreta. Tente novamente.';
         }
       } catch (error) {
         console.error('Erro na verificação do funcionário:', error);
         feedback.style.display = 'block';
         feedback.textContent = 'Erro na verificação. Tente novamente.';
       }
    });

    // MODAL: Abre os detalhes da venda (via AJAX)
    async function abrirModalDetalhes(id) {
       try {
           const resp = await fetch(`index.php?detalhes_venda=${id}`);
           const html = await resp.text();
           document.getElementById('modalDetalhesBody').innerHTML = html;
           new bootstrap.Modal(document.getElementById('modalDetalhesVenda')).show();
       } catch (error) {
           console.error('Erro ao carregar detalhes:', error);
           alert('Erro ao carregar detalhes da venda.');
       }
    }
    async function mostrarDetalhes(id) {
       try {
           const resp = await fetch(`index.php?detalhes_venda=${id}`);
           const html = await resp.text();
           const detalhesContainer = document.getElementById('detalhes-venda');
           detalhesContainer.innerHTML = html;
           detalhesContainer.style.display = 'block';
           detalhesContainer.scrollIntoView({ behavior: 'smooth' });
       } catch (error) {
           console.error('Erro ao carregar detalhes:', error);
           alert('Erro ao carregar detalhes da venda.');
       }
    }

    // Pull-to-refresh para mobile
    let touchStartY = 0, touchEndY = 0;
    const ptrContainer = document.querySelector('.mobile-ptr-container');
    const ptrIndicator = document.querySelector('.mobile-ptr-indicator');
    if (ptrContainer && ptrIndicator) {
      ptrContainer.addEventListener('touchstart', function(e) {
          touchStartY = e.touches[0].clientY;
      }, {passive:true});
      ptrContainer.addEventListener('touchmove', function(e) {
          touchEndY = e.touches[0].clientY;
          const distance = touchEndY - touchStartY;
          if (distance > 0 && window.scrollY === 0) {
              const pullDistance = Math.min(distance * 0.5, 50);
              ptrIndicator.style.transform = `translateY(${pullDistance}px)`;
              e.preventDefault();
          }
      }, {passive:false});
      ptrContainer.addEventListener('touchend', function() {
          const distance = touchEndY - touchStartY;
          if (distance > 70 && window.scrollY === 0) {
              ptrIndicator.style.transform = 'translateY(50px)';
              setTimeout(() => { window.location.reload(); }, 1000);
          } else {
              ptrIndicator.style.transform = 'translateY(0)';
          }
      });
    }

    // Dark Mode Toggle
    const btnDark = document.getElementById('btn-darkmode');
    if (btnDark) {
      const icon = btnDark.querySelector('i');
      btnDark.addEventListener('click', () => {
         document.body.classList.toggle('dark-mode');
         const isDark = document.body.classList.contains('dark-mode');
         localStorage.setItem('modoEscuro', isDark);
         icon.className = isDark ? 'bi bi-sun' : 'bi bi-moon';
      });
      // Verificar se o modo escuro estava ativo anteriormente
      document.addEventListener('DOMContentLoaded', () => {
         if (localStorage.getItem('modoEscuro') === 'true') {
            document.body.classList.add('dark-mode');
            if (icon) icon.className = 'bi bi-sun';
         }
      });
    }

    // Função placeholder para exportação CSV
    function exportarCSV() {
      alert("Função de exportação para CSV será implementada");
    }
    
  </script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const metodoSelect = document.getElementById('metodoPagamento');
    const parcOpts     = document.getElementById('parcelamentoOptions');
    const parcSwitch   = document.getElementById('parceladoSwitch');

    function toggleParcelamento() {
      if (metodoSelect.value === 'cartao_credito') {
        parcOpts.style.display = 'block';
      } else {
        parcOpts.style.display = 'none';
        parcSwitch.checked = false;
      }
    }

    // dispara ao mudar o método
    metodoSelect.addEventListener('change', toggleParcelamento);
    // estado inicial
    toggleParcelamento();
  });
  // reutiliza a lógica de busca pelo código
function buscarPorCodigo() {
  const codigo = globalBarcode.value.trim();
  if (!codigo) return;

  fetch(`index.php?buscar_codigo=1&codigo=${encodeURIComponent(codigo)}`)
    .then(r => r.json())
    .then(data => {
      if (!data || !data.id_produto) {
        alert('Produto ou variação não encontrado: ' + codigo);
        return;
      }

      const container = document.getElementById('itens');
      // tenta reaproveitar o primeiro card se estiver vazio
      const firstCard = container.querySelector('.item-card[data-produto-item="0"]');
      if (firstCard) {
        const sel = firstCard.querySelector('.produto-select');
        const precoInput = firstCard.querySelector('.preco-unitario');
        // se ainda não tiver nada selecionado, preenche aqui
if (sel && !sel.value) {
  sel.value = data.id_produto;
  sel.dispatchEvent(new Event('change'));

  // ───>> INJEÇÃO DO PREÇO AQUI <<───
  const precoInput = firstCard.querySelector('.preco-unitario');
  if (data.preco_venda) {
    precoInput.value = parseFloat(data.preco_venda)
                        .toFixed(2)
                        .replace('.', ',');
    atualizarTotal();
  }
  // ────────────────────────────────

  globalBarcode.value = '';
  return;
}
      }

      // caso contrário, adiciona um novo produto
      addItem(data.id_produto, data);
      globalBarcode.value = '';
    })
    .catch(() => {
      alert('Erro ao buscar produto.');
    });
}

// mantém o listener do Enter:
globalBarcode.addEventListener('keypress', function(e){
  if (e.key === 'Enter') {
    e.preventDefault();
    buscarPorCodigo();
  }
});
// e o listener do botão:
document
  .getElementById('btnBuscarProduto')
  .addEventListener('click', buscarPorCodigo);

// dispara ao apertar Enter


// dispara ao clicar no botão “Buscar”
document
  .getElementById('btnBuscarProduto')
  .addEventListener('click', buscarPorCodigo);

</script>

</body>
</html>