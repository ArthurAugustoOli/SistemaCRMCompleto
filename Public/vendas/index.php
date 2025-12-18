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
$produtos = $produtoModel->getAll();
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

// Buscar clientes 
if (isset($_GET['search_cliente'])) {
    header('Content-Type: application/json');
    $term = "%{$_GET['search_cliente']}%";
    $clientes = $clienteModel->searchByName($term);
    echo json_encode($clientes);
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

// 2.4 Autocomplete: busca produtos por nome
if (isset($_GET['search_produto'])) {
    header('Content-Type: application/json');
    $term  = "%{$_GET['search_produto']}%";
    $lista = $produtoModel->searchByName($term);
    echo json_encode($lista);
    exit;
}

// 2.5 Busca dados completos de um produto por ID (nome, preço, etc.)
if (isset($_GET['search_produto_id'])) {
    header('Content-Type: application/json');
    echo json_encode($produtoModel->getById((int)$_GET['search_produto_id']));
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
    $data            = $_POST['data_venda']       ?? date('Y-m-d H:i:s');
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
    $taxasCredito = [
    2  => 0.0526,  // 5,26%
    3  => 0.0586,  // 5,86%
    4  => 0.0648,  // 6,48%
    5  => 0.0716,  // 7,16%
    6  => 0.0778,  // 7,78%
    7  => 0.0838,  // 8,38%
    8  => 0.0917,  // 9,17%
    9  => 0.1023,  // 10,23%
    10 => 0.1055,  // 10,55%
    11 => 0.1154,  // 11,54%
    // …adicione até o máximo suportado
];
    $taxaDebito  = 0.015;  // 1,5%

    // 6) Calcula taxa diferenciada para crédito e débito
    if ($metodo === 'cartao_credito') {
    // busca a taxa exata para o nº de parcelas
    if (isset($taxasCredito[$numParcelas])) {
        $taxaRate = $taxasCredito[$numParcelas];
    } else {
        // fallback caso o nº de parcelas seja inesperado
        $taxaRate = $taxasCredito[2];
    }
    // aplica a % única (MDR+RA) sobre o total
    $taxaTotal = $total_com_desconto * $taxaRate;
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css">

    <!-- css -->
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

    /* Main Content */
    .main-content {
        margin-left: var(--sidebar-width);
        transition: margin-left var(--transition-speed), width var(--transition-speed);
        width: calc(100% - var(--sidebar-width));
        padding: 1.5rem;
        padding-top: 70px;
        /* Altura do header */
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
        box-shadow: 0 6px 15px rgba(67, 97, 238, 0.2);
    }

    .btn-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(67, 97, 238, 0.3);
    }

    /* Form Styling */
    .form-control,
    .form-select {
        border-radius: 0.5rem;
        padding: 0.6rem 1rem;
        border: 1px solid #e9ecef;
        transition: all 0.3s;
    }

    .form-control:focus,
    .form-select:focus {
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

    .item-card {
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        padding: 1rem;
        margin-bottom: 1rem;
        background-color: #fff;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .item-card .item-remove-btn {
        background: transparent;
        border: none;
        font-size: 1.2rem;
        color: #dc3545;
    }

    .item-card .item-remove-btn:hover {
        color: #a71d2a;
    }

    .item-card .form-label {
        font-weight: 500;
    }

    .variacoes-container {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #f0f0f0;
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
        to {
            transform: rotate(360deg);
        }
    }

    /* Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.05);
        }

        100% {
            transform: scale(1);
        }
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

    body.dark-mode {
        background-color: black;
    }

    body.dark-mode input::placeholder,
    body.dark-mode .form-select::placeholder {
        color: #fff;
        /* ou qualquer cinza que você goste */
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
        background-color: #2d2d2d;
        border-top-color: #2d2d2d;
    }

    body.dark-mode .main-content {
        background-color: #121212;
    }

    body.dark-mode form {
        background-color: #2d2d2d;
        color: white;
    }

    body.dark-mode .form-control {
        color: white;
    }

    body.dark-mode .filter-form,
    body.dark-mode .form-control,
    body.dark-mode .form-select,
    body.dark-mode .input-group-text {
        background-color: #2d2d2d;
        border-color: #3d3d3d;
        color: #ffffff;
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

    body.dark-mode .breadcrumb-container {
        background-color: #121212;
    }

    body.dark-mode .stat-value,
    body.dark-mode .stat-label,
    body.dark-mode .form-label,
    body.dark-mode .label-breadcrumb,
    body.dark-mode .label-vendas {
        color: #ffffff;
    }

    /* Responsive Adjustments */
    @media (max-width: 992px) {
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

        .header,
        .breadcrumb-container {
            display: none;
        }

        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .bottom-nav,
        .mobile-fab {
            display: flex;
        }
    }

    @media (prefers-color-scheme: dark-mode) {
        input::placeholder {
            color: white;
            opacity: 1;
        }
    }

    .autocomplete-wrapper,
    .modal-body,
    .modal-content {
        overflow: visible !important;
    }

    /* Faça seu dropdown voar acima de tudo */
    /* Wrapper para o input + dropdown */
    .autocomplete-wrapper {
        position: relative;
    }

    /* Container das sugestões */
    .autocomplete-list {
        position: relative;
        top: 100%;
        left: 0;
        width: 100%;
        margin-top: 0.5rem;
        padding: 0;
        list-style: none;
        background: #ffffff;
        border: 1px solid rgba(33, 37, 41, 0.15);
        border-radius: 0.5rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        max-height: 250px;
        overflow-y: auto;
        z-index: 1060;
    }

    /* Scrollbar customizado */
    .autocomplete-list::-webkit-scrollbar {
        width: 6px;
    }

    .autocomplete-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .autocomplete-list::-webkit-scrollbar-thumb {
        background-color: rgba(0, 0, 0, 0.2);
        border-radius: 3px;
    }

    /* Itens da lista */
    .autocomplete-list li {
        padding: 0.75rem 1rem;
        cursor: pointer;
        transition: background-color 0.2s, color 0.2s;
        color: #343a40;
        font-size: 0.95rem;
    }

    /* Separador fino entre itens */
    .autocomplete-list li+li {
        border-top: 1px solid rgba(33, 37, 41, 0.08);
    }

    /* Bordas arredondadas no primeiro e último item */
    .autocomplete-list li:first-child {
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
    }

    .autocomplete-list li:last-child {
        border-bottom-left-radius: 0.5rem;
        border-bottom-right-radius: 0.5rem;
    }

    /* Hover / seleção */
    .autocomplete-list li:hover,
    .autocomplete-list li.active {
        background-color: #e9f5ff;
        color: #0d6efd;
    }

    /* Dark-mode styles */
    body.dark-mode .autocomplete-list {
        background: #2b2b2b;
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.7);
    }

    body.dark-mode .autocomplete-list::-webkit-scrollbar-thumb {
        background-color: rgba(255, 255, 255, 0.2);
    }

    body.dark-mode .autocomplete-list li {
        color: #e0e0e0;
        background: transparent;
    }

    body.dark-mode .autocomplete-list li+li {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    body.dark-mode .autocomplete-list li:first-child {
        border-top-left-radius: 0.5rem;
        border-top-right-radius: 0.5rem;
    }

    body.dark-mode .autocomplete-list li:last-child {
        border-bottom-left-radius: 0.5rem;
        border-bottom-right-radius: 0.5rem;
    }

    body.dark-mode .autocomplete-list li:hover,
    body.dark-mode .autocomplete-list li.active {
        background-color: rgba(13, 110, 253, 0.2);
        color: #ffffff;
    }
    </style>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/notificacoes.php'; ?>

    <!-- Loader -->
    <div class="loader" id="pageLoader">
        <div class="spinner"></div>
    </div>

    <!-- Sidebar -->
    <?php include_once '../../frontend/includes/sidebar.php'?>

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
            <!-- inclui aqui o dropdown de notificações -->
            <div class="header-actions">
            </div>

            <!-- dark-mode -->
            <?php include_once '../../frontend/includes/darkmode.php'?>
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
                <ol class="breadcrumb mb-0 gap-3">
                    <li class="breadcrumb-item"><a href="#"><i class="bi bi-house-door"></i> Home</a></li>
                    <li class="label-breadcrumb"> / </li>
                    <li class="label-breadcrumb"> Vendas </li>
                </ol>
            </nav>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Header (Desktop) -->
            <div class="page-header d-lg-flex" data-aos="fade-down">
                <h1 class="page-title">
                    <i class="bi bi-cart-check"></i>
                    <div class="label-vendas"> Vendas </div>
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
                            <div class="stat-label">Faturamento (Hoje)</div>
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
                <form method="get" class="row g-3 align-items-end d-flex flex-row justify-content-center">
                    <div class="col-md-4">
                        <label class="form-label">Busca</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                class="form-control" placeholder="Cliente ou Funcionário">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data Início</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>"
                                class="form-control">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data Fim</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-calendar-check"></i></span>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>"
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
                <div class="card-header py-3 px-4">
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
                                    <td><span class="fw-bold">R$
                                            <?= number_format($v['total_venda'], 2, ',', '.') ?></span></td>
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
                                                class="btn-action delete" title="Excluir"
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
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                                data-bs-target="#modalNovaVenda">
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
                                    <a class="page-link"
                                        href="?pagina=<?= $pagina_atual - 1 ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>">
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
                                    <a class="page-link"
                                        href="?pagina=1&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>">1</a>
                                </li>
                                <?php if ($start_page > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                                <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?= $i == $pagina_atual ? 'active' : '' ?>">
                                    <a class="page-link"
                                        href="?pagina=<?= $i ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($end_page < $total_paginas): ?>
                                <?php if ($end_page < $total_paginas - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link"
                                        href="?pagina=<?= $total_paginas ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"><?= $total_paginas ?></a>
                                </li>
                                <?php endif; ?>

                                <?php if ($pagina_atual < $total_paginas): ?>
                                <li class="page-item">
                                    <a class="page-link"
                                        href="?pagina=<?= $pagina_atual + 1 ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>">
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
                        <button class="btn btn-sm btn-outline-primary"
                            onclick="abrirModalDetalhes(<?= $v['id_venda'] ?>)">
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
                    <a href="?pagina=<?= $pagina_atual - 1 ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"
                        class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-chevron-left"></i> Anterior
                    </a>
                    <?php else: ?>
                    <button class="btn btn-sm btn-outline-secondary" disabled>
                        <i class="bi bi-chevron-left"></i> Anterior
                    </button>
                    <?php endif; ?>

                    <span class="text-muted">Página <?= $pagina_atual ?> de <?= $total_paginas ?></span>

                    <?php if ($pagina_atual < $total_paginas): ?>
                    <a href="?pagina=<?= $pagina_atual + 1 ?>&search=<?= urlencode($search) ?>&start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>"
                        class="btn btn-sm btn-outline-primary">
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
    =
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


    <!-- ============================
       MODAL: Detalhes da Venda (Mobile)
  ============================ -->
    <div class="modal fade" id="modalDetalhesVenda" tabindex="-1" aria-labelledby="modalDetalhesVendaLabel"
        aria-hidden="true">
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
                <div class="modal-body" style="max-height:auto; overflow-y:auto;">
                    <form method="POST" id="saleForm" class="needs-validation" novalidate>
                        <div class="container">
                            <div class="row g-4">
                                <!-- Coluna Esquerda: Itens da Venda e Observações -->
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Código de Barras</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                                            <input type="text" id="globalBarcode" class="form-control"
                                                placeholder="Digite ou escaneie o código de barras">
                                            <button type="button" class="btn btn-outline-secondary"
                                                id="btnBuscarProduto">
                                                Buscar
                                            </button>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <i class="bi bi-box-seam me-2"></i>Itens da Venda
                                        </h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick="addItem()">
                                            <i class="bi bi-plus-circle"></i> Adicionar Produto
                                        </button>
                                    </div>
                                    <div id="itens"></div>
                                    <div class="mt-3">
                                        <label class="form-label">Observações</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-pencil"></i></span>
                                            <textarea name="observacoes" class="form-control obs-venda" rows="3"
                                                placeholder="Observações sobre a venda..."></textarea>
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
                                                    <span class="input-group-text"><i
                                                            class="bi bi-calculator"></i></span>
                                                    <input type="text" class="form-control" id="subtotal" readonly
                                                        value="R$ 0,00">
                                                </div>
                                            </div>
                                            <!-- Pagamento -->
                                            <div class="mb-4">
                                                <label class="form-label">Pagamento:</label>
                                                <select name="metodo_pagamento" id="metodoPagamento"
                                                    class="form-select mb-2" required>
                                                    <option value="pix">PIX</option>
                                                    <option value="dinheiro">Dinheiro</option>
                                                    <option value="cartao_credito">Cartão de Crédito</option>
                                                    <option value="cartao_debito">Cartão de Débito</option>
                                                </select>

                                                <!-- Só aparece se for Cartão de Crédito -->
                                                <div id="parcelamentoOptions" style="display: none;">
                                                    <div class="form-check form-switch mb-2">
                                                        <input class="form-check-input" type="checkbox" name="parcelado"
                                                            id="parceladoSwitch">
                                                        <label class="form-check-label"
                                                            for="parceladoSwitch">Parcelado?</label>
                                                    </div>
                                                    <div>
                                                        <input type="number" name="num_parcelas" id="numParcelasInput"
                                                            class="form-control" value="1" min="1"
                                                            placeholder="Número de parcelas">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label">Desconto (%):</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-percent"></i></span>
                                                    <input type="number" step="0.01" name="desconto_porcentagem"
                                                        id="desconto_porcentagem" class="form-control" value="0" min="0"
                                                        max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                                <input type="hidden" name="desconto_reais" id="desconto_reais">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Total:</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="bi bi-cash"></i></span>
                                                    <input type="text" class="form-control text-primary fw-bold"
                                                        id="total" readonly value="R$ 0,00">
                                                </div>
                                            </div>
                                            <div class="mt-4">
                                                <h6 class="mb-3"><i class="bi bi-person me-1"></i>Cliente</h6>
                                                <div class="input-group mb-3">
                                                    <span class="input-group-text"><i class="bi bi-people"></i></span>
                                                    <input type="text" name="input_cliente" id="cliente_venda"
                                                        class="form-control cliente-autocomplete" autocomplete="off">
                                                    <input type="hidden" name="id_cliente" id="cliente-id">
                                                </div>

                                                <!-- Script -->
                                                <script>
                                                document.getElementById('cliente_venda').addEventListener('input',
                                                    async e => {
                                                        // Remove listas antigas
                                                        document.querySelectorAll('.autocomplete-list').forEach(
                                                            el => el.remove());

                                                        const term = e.target.value.trim();
                                                        if (term.length < 2) return;

                                                        const listId = 'autocomplete-list-cliente';
                                                        // Cria container de sugestões
                                                        const container = document.createElement('ul');
                                                        container.id = listId;
                                                        container.className = 'autocomplete-list';
                                                        e.target.parentNode.appendChild(container);

                                                        // Busca sugestões via AJAX
                                                        const resp = await fetch(
                                                            `index.php?search_cliente=${encodeURIComponent(term)}`
                                                        );
                                                        const clientes = await resp.json();

                                                        clientes.forEach(c => {
                                                            const item = document.createElement('li');
                                                            item.className =
                                                                'list-group-item list-group-item-action';
                                                            item.textContent = c.nome;
                                                            item.dataset.id = c.id_cliente;
                                                            item.addEventListener('click', () => {
                                                                e.target.value = c.nome;
                                                                document.getElementById(
                                                                        'cliente-id').value = c
                                                                    .id_cliente;
                                                                container.remove();
                                                                e.target.blur();
                                                                e.target.dispatchEvent(
                                                                    new Event('change', {
                                                                        bubbles: true
                                                                    }));
                                                            });
                                                            container.appendChild(item);
                                                        });
                                                    });
                                                </script>

                                                <h6 class="mb-2"><i class="bi bi-calendar me-1"></i>Data</h6>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i
                                                            class="bi bi-calendar-date"></i></span>
                                                    <input type="datetime-local" name="data" class="form-control"
                                                        value="<?= (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d\TH:i') ?>">
                                                </div>
                                            </div>
                                            <!-- O input oculto para id_funcionario será adicionado via JS após validação no modal de funcionário -->
                                            <div class="mt-5 d-grid gap-2">
                                                <button type="submit" id="finalizarVendaBtn"
                                                    class="btn btn-primary btn-lg">
                                                    <i class="bi bi-check-circle me-1"></i> Finalizar Venda
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary"
                                                    data-bs-dismiss="modal">
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
    <div class="modal fade" id="modalFuncionario" tabindex="-1" aria-labelledby="modalFuncionarioLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <form id="formFuncionario">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalFuncionarioLabel">
                            <i class="bi bi-shield-lock me-2"></i>Confirme o Funcionário
                        </h5>
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
                        <button type="button" class="btn btn-secondary btn-cancel" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i> Cancelar
                        </button>



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
    // pega as instâncias dos modais
    const modalFuncionarioEl = document.getElementById('modalFuncionario');
    const modalNovaVendaEl = document.getElementById('modalNovaVenda');
    const modalFuncionario = bootstrap.Modal.getOrCreateInstance(modalFuncionarioEl);
    const modalNovaVenda = bootstrap.Modal.getOrCreateInstance(modalNovaVendaEl);

    // quando o usuário fechar o modal de funcionário (por cancelamento ou ESC)
    modalFuncionarioEl.addEventListener('hidden.bs.modal', () => {
        // limpa eventual backdrop sobrando
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        // remove a classe que bloqueia o body
        document.body.classList.remove('modal-open');
        // reabre o modal de nova venda
        modalNovaVenda.show();
    });

    // opcional: intercepta o botão “Cancelar” para garantir que o evento hidden seja disparado
    document.querySelector('#modalFuncionario .btn-cancel')
        .addEventListener('click', () => {
            modalFuncionario.hide();
        });
    </script>

    <script>
    // ===== Inicializações gerais =====
    AOS.init({
        duration: 800,
        once: true
    });
    window.addEventListener('load', () => {
        const loader = document.getElementById('pageLoader');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => loader.style.display = 'none', 500);
        }
    });

    // Índice global para itens
    let globalProdutoIndex = 0;

    // ===== Função: adiciona um novo item de venda =====
    function addItem(preselectedProductId = null, preselectedProductData = null) {
        const container = document.getElementById('itens');
        const index = globalProdutoIndex++;
        const div = document.createElement('div');
        div.className = 'card mb-4 item-card';
        div.setAttribute('data-produto-item', index);
        div.innerHTML = `
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="card-title mb-0">
            <i class="bi bi-box-seam me-1"></i>Produto #${index+1}
          </h6>
          <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(this)">
            <i class="bi bi-x-circle"></i>
          </button>
        </div>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Produto</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-box"></i></span>
              <input type="text"
                     class="form-control produto-autocomplete"
                     placeholder="Digite para buscar..."
                     autocomplete="off"
                     data-index="${index}"
                     required>
              <input type="hidden"
                     name="itens[${index}][id_produto]"
                     class="produto-id"
                     data-index="${index}">
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Quantidade</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-123"></i></span>
              <input type="number"
                     name="itens[${index}][quantidade]"
                     class="form-control"
                     min="1"
                     value="1"
                     required
                     oninput="atualizarTotal()">
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Preço Unitário</label>
            <div class="input-group">
              <span class="input-group-text">R$</span>
              <input type="text"
                     id="preco-${index}"
                     class="form-control preco-unitario"
                     name="itens[${index}][preco_unitario]"
                     readonly
                     value="0,00">
            </div>
          </div>
        </div>
        <div id="variacoes-${index}" class="variacoes-container mt-3" style="display:none"></div>
      </div>`;
        container.appendChild(div);

        // Se tiver pré-seleção, dispara carga de variações
        if (preselectedProductId !== null) {
            setTimeout(() => {
                const card = container.querySelector(`[data-produto-item="${index}"]`);
                const hidden = card.querySelector('.produto-id');
                hidden.value = preselectedProductId;
                loadVariacoes(card);
                if (preselectedProductData?.preco_venda) {
                    const precoInput = card.querySelector('.preco-unitario');
                    precoInput.value = parseFloat(preselectedProductData.preco_venda).toFixed(2).replace('.',
                        ',');
                    atualizarTotal();
                }
            }, 100);
        }
    }

    // ===== Função: remove e reindexa itens =====
    function removeItem(btn) {
        const card = btn.closest('.item-card');
        if (!card) return;
        card.remove();
        reindexItems();
        atualizarTotal();
    }

    function reindexItems() {
        const cards = document.querySelectorAll('.item-card');
        cards.forEach((card, i) => {
            card.setAttribute('data-produto-item', i);
            // Atualiza data-index e names
            card.querySelector('.produto-autocomplete').dataset.index = i;
            card.querySelector('.produto-id').dataset.index = i;
            card.querySelector('input[name*="[quantidade]"]').name = `itens[${i}][quantidade]`;
            const preco = card.querySelector('.preco-unitario');
            preco.id = `preco-${i}`;
            preco.name = `itens[${i}][preco_unitario]`;
            const varContainer = card.querySelector('.variacoes-container');
            varContainer.id = `variacoes-${i}`;
            // Header
            card.querySelector('.card-title').innerHTML =
                `<i class="bi bi-box-seam me-1"></i>Produto #${i+1}`;
        });
        globalProdutoIndex = cards.length;
    }

    // ===== Autocomplete + carga de variações =====
    (function() {
        const modal = document.getElementById('modalNovaVenda');

        // 1) Autocomplete: mostra lista de sugestões
        modal.addEventListener('input', async e => {
            if (!e.target.classList.contains('produto-autocomplete')) return;
            const term = e.target.value.trim();
            const idx = e.target.dataset.index;
            const listId = `autocomplete-list-${idx}`;
            document.getElementById(listId)?.remove();
            if (term.length < 2) return;

            const ul = document.createElement('ul');
            ul.id = listId;
            ul.className = 'autocomplete-list';
            e.target.parentNode.appendChild(ul);

            const resp = await fetch(`index.php?search_produto=${encodeURIComponent(term)}`);
            const produtos = await resp.json();

            produtos.forEach(p => {
                const li = document.createElement('li');
                li.className = 'list-group-item list-group-item-action';
                li.textContent = p.nome;
                li.addEventListener('click', () => {
                    ul.remove();
                    const card = e.target.closest('.item-card');
                    e.target.value = p.nome;
                    card.querySelector('.produto-id').value = p.id_produto;
                    loadVariacoes(card);
                });
                ul.appendChild(li);
            });
        });

        // 2) Change: caso usuario cole ou mude manualmente
        modal.addEventListener('change', e => {
            if (!e.target.classList.contains('produto-autocomplete')) return;
            const card = e.target.closest('.item-card');
            loadVariacoes(card);
        });

        // Função para buscar variações/preço conforme card
        window.loadVariacoes = async function(card) {
            const idx = card.dataset.produtoItem;
            const idProduto = card.querySelector('.produto-id').value;
            const varContainer = card.querySelector('.variacoes-container');
            const precoInput = card.querySelector('.preco-unitario');
            varContainer.innerHTML = '';
            varContainer.style.display = 'none';
            precoInput.value = '0,00';
            if (!idProduto) return;
            try {
                const resp = await fetch(`index.php?carregar_variacoes=${idProduto}`);
                const vars = await resp.json();
                if (vars.length) {
                    const select = document.createElement('select');
                    select.name = `itens[${idx}][id_variacao]`;
                    select.required = true;
                    select.className = 'form-select variacao-select mb-2';
                    select.innerHTML = `<option value="">Selecione...</option>` +
                        vars.map(v => {
                            const lbl = v.nome || `${v.cor} / ${v.tamanho}`;
                            return `<option value="${v.id_variacao}" data-preco="${v.preco_venda}">
                        ${lbl} – R$ ${parseFloat(v.preco_venda).toFixed(2).replace('.',',')}
                      </option>`;
                        }).join('');
                    varContainer.appendChild(select);
                    varContainer.style.display = 'block';
                    select.addEventListener('change', () => {
                        const opt = select.options[select.selectedIndex];
                        precoInput.value = (parseFloat(opt.dataset.preco) || 0).toFixed(2).replace(
                            '.', ',');
                        atualizarTotal();
                    });
                } else {
                    const r2 = await fetch(`index.php?search_produto_id=${idProduto}`);
                    const prod = await r2.json();
                    if (prod?.preco_venda != null) {
                        precoInput.value = parseFloat(prod.preco_venda).toFixed(2).replace('.', ',');
                        atualizarTotal();
                    }
                }
            } catch (err) {
                console.error('Erro ao carregar variações:', err);
            }
        };
    })();

    // ===== Atualiza total =====
    function atualizarTotal() {
        let subtotal = 0;
        document.querySelectorAll('.item-card').forEach(card => {
            const preco = parseFloat(card.querySelector('.preco-unitario').value.replace(',', '.')) || 0;
            const qtd = parseInt(card.querySelector('input[name*="[quantidade]"]').value) || 0;
            subtotal += preco * qtd;
        });
        const descontoPerc = parseFloat(document.getElementById('desconto_porcentagem').value) || 0;
        const descontoReais = subtotal * (descontoPerc / 100);
        const total = subtotal - descontoReais;
        document.getElementById('desconto_reais').value = descontoReais.toFixed(2);
        document.getElementById('subtotal').value = `R$ ${subtotal.toFixed(2).replace('.',',')}`;
        document.getElementById('total').value = `R$ ${total.toFixed(2).replace('.',',')}`;
    }

    // ===== DOMContentLoaded: itens iniciais e descontos =====
    document.addEventListener('DOMContentLoaded', () => {
        if (!document.getElementById('itens').children.length) addItem();
        document.getElementById('desconto_porcentagem')
            .addEventListener('input', atualizarTotal);
    });

    // ===== Captura de código de barras =====
    function buscarPorCodigo() {
        const codigo = document.getElementById('globalBarcode').value.trim();
        if (!codigo) return;
        fetch(`index.php?buscar_codigo=1&codigo=${encodeURIComponent(codigo)}`)
            .then(r => r.json())
            .then(data => {
                if (!data?.id_produto) {
                    alert('Produto ou variação não encontrado: ' + codigo);
                    return;
                }
                const firstCard = document.querySelector('.item-card');
                if (firstCard && !firstCard.querySelector('.produto-id').value) {
                    firstCard.querySelector('.produto-id').value = data.id_produto;
                    loadVariacoes(firstCard);
                    if (data.preco_venda) {
                        firstCard.querySelector('.preco-unitario').value =
                            parseFloat(data.preco_venda).toFixed(2).replace('.', ',');
                        atualizarTotal();
                    }
                } else {
                    addItem(data.id_produto, data);
                }
                document.getElementById('globalBarcode').value = '';
            })
            .catch(() => alert('Erro ao buscar produto.'));
    }
    document.getElementById('btnBuscarProduto')
        .addEventListener('click', buscarPorCodigo);
    document.getElementById('globalBarcode')
        .addEventListener('keypress', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarPorCodigo();
            }
        });

    // ===== Toggle de parcelamento =====
    document.addEventListener('DOMContentLoaded', () => {
        const metodo = document.getElementById('metodoPagamento');
        const opts = document.getElementById('parcelamentoOptions');
        const sw = document.getElementById('parceladoSwitch');
        const fn = () => {
            if (metodo.value === 'cartao_credito') opts.style.display = 'block';
            else {
                opts.style.display = 'none';
                sw.checked = false;
            }
        };
        metodo.addEventListener('change', fn);
        fn();
    });

    // ===== Intercepta submit para verificação do funcionário =====
    const saleForm = document.getElementById('saleForm');
    saleForm.addEventListener('submit', e => {
        e.preventDefault();
        bootstrap.Modal.getInstance(document.getElementById('modalNovaVenda')).hide();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFuncionario')).show();
        setTimeout(() => document.getElementById('funcionarioSenha').focus(), 200);
    });
    document.getElementById('formFuncionario').addEventListener('submit', async e => {
        e.preventDefault();
        const senha = document.getElementById('funcionarioSenha').value.trim();
        const fb = document.getElementById('funcionarioFeedback');
        if (!senha) {
            fb.style.display = 'block';
            fb.textContent = 'Digite sua senha.';
            return;
        }
        try {
            const res = await fetch(
                `index.php?action=checkFuncionario&password=${encodeURIComponent(senha)}`);
            const j = await res.json();
            if (j.success) {
                let inp = saleForm.querySelector('[name="id_funcionario"]');
                if (!inp) {
                    inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'id_funcionario';
                    saleForm.appendChild(inp);
                }
                inp.value = j.id_funcionario;
                bootstrap.Modal.getInstance(document.getElementById('modalFuncionario')).hide();
                saleForm.submit();
            } else {
                fb.style.display = 'block';
                fb.textContent = 'Senha incorreta.';
            }
        } catch {
            fb.style.display = 'block';
            fb.textContent = 'Erro na verificação.';
        }
    });

    // ===== Abre detalhes da venda via AJAX =====
    async function abrirModalDetalhes(id) {
        try {
            const html = await fetch(`index.php?detalhes_venda=${id}`).then(r => r.text());
            document.getElementById('modalDetalhesBody').innerHTML = html;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalhesVenda')).show();
        } catch {
            alert('Erro ao carregar detalhes da venda.');
        }
    }
    async function mostrarDetalhes(id) {
        try {
            const html = await fetch(`index.php?detalhes_venda=${id}`).then(r => r.text());
            const dv = document.getElementById('detalhes-venda');
            dv.innerHTML = html;
            dv.style.display = 'block';
            dv.scrollIntoView({
                behavior: 'smooth'
            });
        } catch {
            alert('Erro ao carregar detalhes da venda.');
        }
    }

    // ===== Pull-to-refresh mobile =====
    (function() {
        let startY = 0,
            endY = 0;
        const ptr = document.querySelector('.mobile-ptr-container');
        const ind = document.querySelector('.mobile-ptr-indicator');
        if (!ptr || !ind) return;
        ptr.addEventListener('touchstart', e => startY = e.touches[0].clientY, {
            passive: true
        });
        ptr.addEventListener('touchmove', e => {
            endY = e.touches[0].clientY;
            const d = endY - startY;
            if (d > 0 && window.scrollY === 0) {
                ind.style.transform = `translateY(${Math.min(d*0.5,50)}px)`;
                e.preventDefault();
            }
        }, {
            passive: false
        });
        ptr.addEventListener('touchend', () => {
            if (endY - startY > 70 && window.scrollY === 0) {
                ind.style.transform = 'translateY(50px)';
                setTimeout(() => window.location.reload(), 1000);
            } else ind.style.transform = 'translateY(0)';
        });
    })();

    // ===== Placeholder CSV =====
    function exportarCSV() {
        alert("Função de exportação para CSV será implementada");
    }
    </script>


</body>

</html>