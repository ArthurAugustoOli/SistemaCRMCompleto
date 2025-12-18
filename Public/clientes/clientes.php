<?php
date_default_timezone_set('America/Sao_Paulo');
require_once '../../app/config/config.php';
require_once '../login/verificar_sessao.php';

require_once '../../app/config/config.php';
require_once '../../app/models/Cliente.php';
require_once '../../app/models/Venda.php';
require_once '../../app/models/ItemVenda.php';
require_once '../../app/models/Produto.php';
require_once '../../app/models/ProdutoVariacao.php';
require_once '../../app/models/VendaProduto.php';

$clienteModel      = new Cliente($mysqli);
$vendaModel        = new Venda($mysqli);
$itemModel         = new ItemVenda($mysqli);
$produtoModel      = new Produto($mysqli);
$variacaoModel     = new ProdutoVariacao($mysqli);
$vendaProdutoModel = new VendaProduto($mysqli);
$aniversariantesProximos = $clienteModel->getAniversariantesProximos();
$clientesInativos = $clienteModel->getClientesInativos(60);


$produtos  = $produtoModel->getAll();
$clientes  = $clienteModel->getAll();
$limite = 10;
$pagina_atual = max((int)($_GET['pagina'] ?? 1), 1);
$offset = ($pagina_atual - 1) * $limite;
$clientes = $clienteModel->getPaginado($offset, $limite);

$total_paginas = ceil($clienteModel->getTotalClientes() / $limite);
$estatisticas  = $vendaModel->getEstatisticasDashboard();

$aniversariantesHoje = $clienteModel->getAniversariantesHoje();
$aniversariantesProximos = $clienteModel->getAniversariantesProximos();

$message = '';

// =============================
// ENDPOINTS AJAX
// =============================        


// Buscar dados do cliente para edição
if (isset($_GET['getCliente']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $cliente = $clienteModel->getById($id);
    header('Content-Type: application/json');
    echo json_encode($cliente);
    exit;
}

// Carregar variações de um produto
if (isset($_GET['carregar_variacoes'])) {
  try {
      header('Content-Type: application/json');
      $produtoId = (int) $_GET['carregar_variacoes'];
      $variacoes = $variacaoModel->getAllByProduto($produtoId);
      echo json_encode($variacoes);
  } catch (Exception $e) {
      // Em caso de erro, retornamos um JSON com status false e a mensagem de erro
      header('Content-Type: application/json');
      echo json_encode([
          'success' => false,
          'error'   => "Erro ao carregar variações para o produto ID $produtoId: " . $e->getMessage()
      ]);
  }
  exit;
}

// Buscar produto por código de barras
if (isset($_GET['buscar_codigo'])) {
    header('Content-Type: application/json');
    $codigo = $_GET['codigo'];
    $produto = $produtoModel->getByCode($codigo);
    echo json_encode($produto);
    exit;
}

// Carregar detalhes da venda (usado na página de vendas)
if (isset($_GET['detalhes_venda'])) {
    $id_venda = (int)$_GET['detalhes_venda'];
    $venda = $vendaModel->getById($id_venda);
    $itens = $itemModel->getByVenda($id_venda);
    ob_start(); ?>
    <div class="mobile-card mb-3">
      <div class="mobile-card-header">
        <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Detalhes da Venda #<?= $venda['id_venda'] ?></h6>
      </div>
      <div class="mobile-card-body">
        <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($venda['data'])) ?></p>
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
                <td><?= $item['produto_nome'] ?></td>
                <td><?= $item['cor'] ? $item['cor'] : 'N/A' ?> / <?= $item['tamanho'] ? $item['tamanho'] : 'N/A' ?></td>
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



// Carregar histórico de compras de um cliente
if (isset($_GET['historico_cliente']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id_cliente = (int)$_GET['id'];
    $query = "SELECT v.*, c.nome AS nome_cliente 
              FROM vendas v 
              LEFT JOIN clientes c ON v.id_cliente = c.id_cliente 
              WHERE v.id_cliente = ? 
              ORDER BY v.data DESC";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $historico = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    echo json_encode($historico);
    exit;
}

// Carregar detalhes do pedido (para o modal de Detalhes do Pedido)
if (isset($_GET['detalhes_pedido']) && isset($_GET['id'])) {
    $id_venda = (int)$_GET['id'];
    $stmt = $mysqli->prepare("SELECT v.*, c.nome AS nome_cliente FROM vendas v LEFT JOIN clientes c ON v.id_cliente = c.id_cliente WHERE v.id_venda = ?");
    $stmt->bind_param("i", $id_venda);
    $stmt->execute();
    $venda = $stmt->get_result()->fetch_assoc();
    $itens = $itemModel->getByVenda($id_venda);
    ob_start(); ?>
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalhes do Pedido #<?= $venda['id_venda'] ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
        <p><strong>Data/Hora:</strong> <?= date('d/m/Y H:i', strtotime($venda['data'])) ?></p>
        <p><strong>Cliente:</strong> <?= $venda['nome_cliente'] ?? 'N/A' ?></p>
        <hr>
        <div class="table-responsive">
          <table class="table table-bordered">
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
              <?php
              $nome_variacao = $item['nome_variacao'] ?? '';
              $partes = explode('_', $nome_variacao);
              $produto_nome = $partes[0] ?? '-';
              $variacao_nome = isset($partes[1]) ? implode('_', array_slice($partes, 1)) : '-';
              ?>
              <td><?= htmlspecialchars($produto_nome) ?></td>
              <td><?= htmlspecialchars($variacao_nome) ?></td>
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

// =============================
// PROCESSAMENTO DO CLIENTE (CRIAÇÃO/ATUALIZAÇÃO/EXCLUSÃO)
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_cliente'])) {
    $id = (int) $_POST['id_cliente_excluir'];
    if ($clienteModel->delete($id)) {
        $message = "Cliente excluído com sucesso!";
    } else {
        $message = "Erro ao excluir o cliente.";
    }
    header("Location: clientes.php?message=" . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_cliente'])) {
    $id_cliente       = isset($_POST['id_cliente']) ? (int)$_POST['id_cliente'] : null;
    $nome             = trim($_POST['nome'] ?? '');
    $cpf_cnpj         = trim($_POST['cpf_cnpj'] ?? '');
    $data_nascimento  = trim($_POST['data_nascimento'] ?? '');
    $telefone         = trim($_POST['telefone'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $cep              = trim($_POST['cep'] ?? '');
    $logradouro       = trim($_POST['logradouro'] ?? '');
    $numero           = trim($_POST['numero'] ?? '');
    $complemento      = trim($_POST['complemento'] ?? '');
    $bairro           = trim($_POST['bairro'] ?? '');
    $cidade           = trim($_POST['cidade'] ?? '');
    $estado           = trim($_POST['estado'] ?? '');
    $pontos_fidelidade = 0;

    $data = [
        'nome'              => $nome,
        'cpf_cnpj'          => $cpf_cnpj,
        'data_nascimento'   => $data_nascimento,
        'telefone'          => $telefone,
        'email'             => $email,
        'cep'               => $cep,
        'logradouro'        => $logradouro,
        'numero'            => $numero,
        'complemento'       => $complemento,
        'bairro'            => $bairro,
        'cidade'            => $cidade,
        'estado'            => $estado,
        'pontos_fidelidade' => $pontos_fidelidade
    ];

    if ($id_cliente) {
        if ($clienteModel->update($id_cliente, $data)) {
            $message = "Cliente atualizado com sucesso!";
        } else {
            $message = "Erro ao atualizar o cliente.";
        }
    } else {
        if ($clienteModel->create($data)) {
            $message = "Cliente criado com sucesso!";
        } else {
            $message = "Erro ao criar o cliente.";
        }
    }
    header("Location: clientes.php?message=" . urlencode($message));
    exit;
}

if (isset($_GET['buscar_cliente'])) {
    $termo = '%' . $mysqli->real_escape_string($_GET['termo']) . '%';

    $stmt = $mysqli->prepare("
        SELECT * FROM clientes 
        WHERE nome LIKE ? OR telefone LIKE ? OR email LIKE ? 
        ORDER BY nome ASC LIMIT 10
    ");
    $stmt->bind_param("sss", $termo, $termo, $termo);
    $stmt->execute();
    $clientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Como a tabela original usava data-aos e delay para animação, precisamos de um índice:
    $index = 0;
    foreach ($clientes as $cliente) {
        $iniciais = explode(' ', trim($cliente['nome']));
        $avatar = count($iniciais) >= 2
            ? strtoupper(substr($iniciais[0], 0, 1) . substr(end($iniciais), 0, 1))
            : strtoupper(substr($cliente['nome'], 0, 1) . substr($cliente['nome'], -1));

        // Incrementa o índice para calcular data-aos-delay
        $delay = 50 * (++$index);

        // Agora geramos o <tr> exatamente como na listagem principal
        echo '<tr ondblclick="abrirHistorico(' . $cliente['id_cliente'] . ')" style="cursor:pointer;" '
            . 'data-aos="fade-up" data-aos-delay="' . $delay . '">';
        echo    '<td>';
        echo      '<div class="d-flex align-items-center">';
        echo        '<div class="avatar" style="background-color: ' . sprintf('#%06X', crc32($cliente['nome'])) . ';">';
        echo          $avatar;
        echo        '</div>';
        echo        htmlspecialchars($cliente['nome']);
        echo      '</div>';
        echo    '</td>';
        echo    '<td>' . htmlspecialchars($cliente['cpf_cnpj']) . '</td>';
        echo    '<td>' . htmlspecialchars($cliente['telefone']) . '</td>';
        echo    '<td>' . htmlspecialchars($cliente['email']) . '</td>';
        echo    '<td>' . htmlspecialchars($cliente['cidade']) . '</td>';
        echo    '<td>';
        echo      '<span class="status-badge success">';
        echo        '<i class="bi bi-star-fill"></i> ' . htmlspecialchars($cliente['pontos_fidelidade']);
        echo      '</span>';
        echo    '</td>';
        echo    '<td>';
        echo      '<div class="action-buttons justify-content-center">';
        echo        // Botão de editar (já existia)
                    '<button class="btn-action edit" onclick="editarClienteAjax(' . $cliente['id_cliente'] . ')">'
                    . '<i class="bi bi-pencil"></i></button>';
        echo        // Botão de mensagem (também igual)
                    '<button class="btn-action msg" onclick="abrirModalMensagemDireta('
                    . htmlspecialchars(json_encode($cliente['nome'])) . ', '
                    . htmlspecialchars(json_encode($cliente['telefone'])) . ')">'
                    . '<i class="bi bi-chat-dots"></i></button>';
        echo        // Botão de visualizar – aqui é CRUCIAL usar visualizarCliente, NÃO verCliente
                    '<button class="btn-action view" onclick="visualizarCliente(' . $cliente['id_cliente'] . ')">'
                    . '<i class="bi bi-eye"></i></button>';
        echo        // Botão de excluir
                    '<button class="btn-action delete" onclick="confirmarExclusao(' . $cliente['id_cliente'] . ')">'
                    . '<i class="bi bi-trash"></i></button>';
        echo      '</div>';
        echo    '</td>';
        echo  '</tr>';
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_mensagem_padrao'])) {
  $mensagem = trim($_POST['mensagem']);

  $stmt = $mysqli->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'mensagem_aniversario'");
  $stmt->bind_param("s", $mensagem);

  if ($stmt->execute()) {
      echo json_encode(['status' => 'ok']);
  } else {
      echo json_encode(['status' => 'erro', 'mensagem' => $stmt->error]);
  }
  exit;
}

// Buscar mensagem padrão de aniversário do banco
$stmt = $mysqli->prepare("SELECT valor FROM configuracoes WHERE chave = 'mensagem_aniversario' LIMIT 1");
$stmt->execute();
$resultado = $stmt->get_result()->fetch_assoc();
$mensagemAtual = $resultado['valor'] ?? "Olá {nome}, a equipe da loja deseja um feliz aniversário! 🎉";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Clientes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5 CSS -->
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
            --primary-color: #4e54e9;
            --primary-hover: #3a40d4;
            --header-height: 60px;
            --light-bg: #f8f9fa;
            --border-radius: 8px;
            --card-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }
        .btn-action.msg {
  background-color: #0d6efd;
  color: #fff;
}
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: all 0.3s;
        }
        
        /* Header */
        .header {
            height: var(--header-height);
            background-color: white;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            align-items: center;
            padding: 0 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .search-bar {
            flex-grow: 1;
            max-width: 500px;
            margin: 0 20px;
        }
        
        .search-input {
            background-color: #f5f5f5;
            border: none;
            border-radius: 50px;
            padding: 8px 15px 8px 40px;
            width: 100%;
            font-size: 14px;
        }
        
        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
        }
        
        .header-actions button {
            background: none;
            border: none;
            font-size: 20px;
            color: #555;
            margin-left: 15px;
            cursor: pointer;
            position: relative;
        }
        
        /* Breadcrumb */
        .breadcrumb-container {
            padding: 15px 20px;
            background-color: white;
            border-bottom: 1px solid #eaeaea;
        }
        
        /* Page Content */
        .page-content {
            padding: 20px;
        }
        
        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-title {
            display: flex;
            align-items: center;
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }
        
        .page-title i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        /* Cards */
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.2s;
            border: none;
            display: flex;
            align-items: center;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
        }
        
        .stat-icon.blue {
            background-color: rgba(78, 84, 233, 0.1);
            color: var(--primary-color);
        }
        
        .stat-icon.green {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }
        
        .stat-icon.orange {
            background-color: rgba(255, 159, 67, 0.1);
            color: #ff9f43;
        }
        
        .stat-icon.red {
            background-color: rgba(255, 71, 87, 0.1);
            color: #ff4757;
        }
        
        .stat-info {
            flex-grow: 1;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .stat-label {
            font-size: 14px;
            color: #777;
        }
        
        /* Table */
        .data-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 20px;
            overflow: hidden;
            border: none;
        }
        
        .card-header {
            padding: 15px 20px;
            background-color: white;
            border-bottom: 1px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
        }
        
        .card-header h5 i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #555;
            text-align: left;
            padding: 12px 15px;
            border-bottom: 1px solid #eaeaea;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eaeaea;
            vertical-align: middle;
        }
        
        .data-table tr:last-child td {
            border-bottom: none;
        }
        
        .data-table tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 5px 10px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        
        .status-badge i {
            margin-right: 5px;
            font-size: 10px;
        }
        
        .status-badge.success {
            background-color: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
        }
        
        .status-badge.warning {
            background-color: rgba(255, 159, 67, 0.1);
            color: #ff9f43;
        }
        
        .status-badge.danger {
            background-color: rgba(255, 71, 87, 0.1);
            color: #ff4757;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-action {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-action.edit {
            background-color: #ffc107;
            color: white;
        }
        
        .btn-action.view {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-action.delete {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-action:hover {
            opacity: 0.8;
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
        
        .btn-add {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Form Styling */
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 84, 233, 0.25);
        }
        
        /* Avatar */
        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
            margin-right: 10px;
        }

        /* Dark mode support */
body.dark-mode .mobile-drawer {
  background-color: #1a1a1a;
}

        
        /* Dark Mode */
        body.dark-mode {
            background-color: #121212;
            color: #f1f1f1;
        }
        
        body.dark-mode .header,
        body.dark-mode .breadcrumb-container,
        body.dark-mode .stat-card,
        body.dark-mode .data-card,
        body.dark-mode .card-header,
        body.dark-mode .modal-content {
            background-color: #1e1e1e;
            color: #f1f1f1;
            border-color: #333;
        }
        
        body.dark-mode .data-table th {
            background-color: #2a2a2a;
            color: #f1f1f1;
            border-color: #333;
        }
        
        body.dark-mode .data-table td {
            border-color: #333;
            color: #f1f1f1;
        }
        
        body.dark-mode .data-table tr:hover {
            background-color: #252525;
        }
        
        body.dark-mode .search-input,
        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background-color: #2a2a2a;
            color: #f1f1f1;
            border-color: #333;
        }
        
        body.dark-mode .page-title,
        body.dark-mode .card-header h5,
        body.dark-mode .stat-value,
        body.dark-mode label {
            color: #f1f1f1;
        }
        
        body.dark-mode .stat-label,
        body.dark-mode .text-muted {
            color: #aaa !important;
        }

        body.dark-mode .pagination .page-link {
            background-color: #2a2a2a;
            color: var(--primary-color);
        }
        
        body.dark-mode .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            color: white;
        }

        body.dark-mode .topbar-icon:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        body.dark-mode .mobile-header,
        body.dark-mode .mobile-search-container, 
        body.dark-mode .mobile-search-input, 
        body.dark-mode .mobile-stat-card,
        body.dark-mode .mobile-card-header, 
        body.dark-mode .mobile-card-body, 
        body.dark-mode .mobile-card-footer{
          background-color: #1e1e1e;
          border-color: #1e1e1e;
          color: #ffffff;
        }

        body.dark-mode .card-body, 
        body.dark-mode .list-group-item{
          background-color: #1e1e1e;
          border-color: #1e1e1e;
          color: #ffffff;
        }
        
        /* Mobile Styles */
        @media (max-width: 992px) {
            /* Hide desktop elements on mobile */
            
            .main-content {
                margin-left: 0;
                padding-bottom: 70px; /* Space for bottom nav */
            }
            
            .header {
                position: sticky;
                top: 0;
                z-index: 1000;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .breadcrumb-container {
                display: none;
            }
            
            /* Mobile Header */
            .mobile-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 15px;
                background-color: white;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                position: sticky;
                top: 0;
                z-index: 1000;
            }
            
            .mobile-logo {
                display: flex;
                align-items: center;
                font-size: 20px;
                font-weight: 600;
                color: var(--primary-color);
            }
            
            .mobile-logo i {
                margin-right: 8px;
                font-size: 24px;
            }
            
            .mobile-actions {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .mobile-actions button {
                background: none;
                border: none;
                font-size: 20px;
                color: #555;
                position: relative;
            }
            
            /* Bottom Navigation */
            .bottom-nav {
                display: flex;
                justify-content: space-around;
                align-items: center;
                background-color: white;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                height: 60px;
                z-index: 1000;
                border-top-left-radius: 15px;
                border-top-right-radius: 15px;
            }
            
            .nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                color: #777;
                text-decoration: none;
                font-size: 10px;
                padding: 8px 0;
                width: 20%;
                transition: all 0.2s;
            }
            
            .nav-item i {
                font-size: 20px;
                margin-bottom: 4px;
            }
            
            .nav-item.active {
                color: var(--primary-color);
            }
            
            .nav-item.center-item {
                transform: translateY(-15px);
            }
            
            .nav-item.center-item .nav-circle {
                width: 50px;
                height: 50px;
                background-color: var(--primary-color);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            }
            
            /* Mobile Cards */
            .mobile-card {
                background-color: white;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                margin-bottom: 15px;
                overflow: hidden;
                transition: transform 0.2s;
            }
            
            .mobile-card:active {
                transform: scale(0.98);
            }
            
            .mobile-card-header {
                padding: 15px;
                border-bottom: 1px solid #eaeaea;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .mobile-card-body {
                padding: 15px;
            }
            
            .mobile-card-footer {
                padding: 10px 15px;
                border-top: 1px solid #eaeaea;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background-color: #f8f9fa;
            }
            
            /* Mobile Stats */
            .mobile-stats {
                display: flex;
                overflow-x: auto;
                padding: 10px 0;
                margin: 0 -10px 20px;
                scrollbar-width: none; /* Firefox */
            }
            
            .mobile-stats::-webkit-scrollbar {
                display: none; /* Chrome, Safari, Opera */
            }
            
            .mobile-stat-card {
                min-width: 140px;
                background-color: white;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                padding: 15px;
                margin: 0 10px;
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .mobile-stat-icon {
                width: 40px;
                height: 40px;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 10px;
                font-size: 20px;
            }
            
            .mobile-stat-value {
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 5px;
            }
            
            .mobile-stat-label {
                font-size: 12px;
                color: #777;
            }
            
            /* Mobile Fab Button */
            .mobile-fab {
                position: fixed;
                bottom: 80px;
                right: 20px;
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background-color: var(--primary-color);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 999;
                border: none;
                font-size: 24px;
            }
            
            /* Mobile Search */
            .mobile-search-container {
                padding: 15px;
                background-color: white;
                margin-bottom: 20px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            
            .mobile-search-input {
                background-color: #f5f5f5;
                border: none;
                border-radius: 50px;
                padding: 10px 15px 10px 40px;
                width: 100%;
                font-size: 14px;
            }
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
            border: 5px solid rgba(78, 84, 233, 0.2);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {to {transform: rotate(360deg);}}

        /* Animations */
        @keyframes fadeIn {from {opacity: 0;} to {opacity: 1;}}
        @keyframes fadeInUp {from {opacity: 0; transform: translateY(20px);} to {opacity: 1; transform: translateY(0);}}
        @keyframes fadeInDown {from {opacity: 0; transform: translateY(-20px);} to {opacity: 1; transform: translateY(0);}}
        @keyframes slideInLeft {from {transform: translateX(-100%);} to {transform: translateX(0);}}
        @keyframes slideInRight {from {transform: translateX(100%);} to {transform: translateX(0);}}

        .fade-in {animation: fadeIn 0.5s ease-in-out;}
        .fade-in-up {animation: fadeInUp 0.5s ease-in-out;}
        .fade-in-down {animation: fadeInDown 0.5s ease-in-out;}
        .slide-in-left {animation: slideInLeft 0.5s ease-in-out;}
        .slide-in-right {animation: slideInRight 0.5s ease-in-out;}

        /* Mobile Animations */
        .mobile-fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }

        .mobile-slide-up {
            animation: slideUp 0.3s ease-in-out;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes pulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.05); }
  100% { transform: scale(1); }
}

#aniversarianteShortcut {
  position: fixed;
  bottom: 20px;
  left: 20px;
  background-color: #ffc107;
  color: #000;
  padding: 10px 15px;
  border-radius: 30px;
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  display: none;
  cursor: pointer;
  z-index: 999; /* <= DIMINUI ISSO! */
}
  </style>

</head>
<body>
<?php if (!empty($aniversariantesHoje)): ?>
  <div class="modal fade" id="modalAniversariantes" tabindex="-1" aria-labelledby="modalAniversariantesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title" id="modalAniversariantesLabel"><i class="bi bi-gift me-2"></i>Aniversariantes de Hoje</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <ul class="list-group">
            <?php foreach ($aniversariantesHoje as $aniversariante): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                  <strong><?= htmlspecialchars($aniversariante['nome']) ?></strong><br>
                  <small><?= htmlspecialchars($aniversariante['email']) ?> | <?= htmlspecialchars($aniversariante['telefone']) ?></small>
                </div>
                <span class="badge bg-primary rounded-pill"><?= date('d/m', strtotime($aniversariante['data_nascimento'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
  <!-- Loader -->
  <div class="loader" id="pageLoader">
    <div class="spinner"></div>
  </div>

  <!-- Sidebar (Desktop) -->
  <?php include_once '../../frontend/includes/sidebar.php'?>

  <!-- Main Content -->
  <div class="main-content">
    <!-- Header (Desktop) -->
    <div class="header d-none d-lg-flex p-3">
      <div class="d-flex justify-content-center align-items-center">
        <div class="search-bar position-relative">
          <i class="bi bi-search search-icon"></i>
          <input type="text" class="search-input" placeholder="Buscar clientes..." id="searchCliente" oninput="buscarCliente(this.value)">
        </div>

        <div class="d-flex flex-row justify-content-center align-items-center gap-3">
          <div class="topbar-actions" style="z-index: 9999;">
            <?php require_once __DIR__ . '/../includes/notificacoes.php'; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Dark-Mode -->
    <?php include_once '../../frontend/includes/darkmode.php'?>

    <!-- Mobile Header -->
    <div class="mobile-header d-lg-none">
      <button class="mobile-menu-btn" style="display: none">
        <i class="bi bi-list"></i>
      </button>
      <div class="mobile-logo">
        <i class="bi bi-box-seam"></i>
        <span>Gestão</span>
      </div>
      <div class="mobile-actions">
        <button id="btn-darkmode-mobile">
          <i class="bi bi-moon"></i>
        </button>
      </div>
    </div>

    <!-- Breadcrumb (Desktop) -->
    <div class="breadcrumb-container d-none d-lg-block">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 gap-3">
          <li class="breadcrumb-item"><a href="#"><i class="bi bi-house-door"></i> Home</a></li>
          <li> / </li>
          <li aria-current="page">Relatórios</li>
        </ol>
      </nav>
    </div>

    <!-- Page Content -->
    <div class="page-content">
      <!-- Page Header (Desktop) -->
      <div class="page-header d-none d-lg-flex" data-aos="fade-down">
        <h1 class="page-title">
          <i class="bi bi-people"></i> Gerenciamento de Clientes
        </h1>
        <button class="btn btn-primary btn-add" data-bs-toggle="modal" data-bs-target="#modalCliente" onclick="limparFormulario()">
          <i class="bi bi-plus-lg"></i> Novo Cliente
        </button>
      </div>

      <!-- Mobile Search -->
      <div class="mobile-search-container d-lg-none">
        <div class="position-relative">
          <i class="bi bi-search search-icon"></i>
          <input type="text" class="mobile-search-input" placeholder="Buscar clientes...">
        </div>
      </div>

      <!-- Alert Message -->
      <?php if (!empty($message)): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
          <i class="bi bi-info-circle me-2"></i><?= htmlspecialchars($message) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
      <?php endif; ?>

      <!-- Statistics Cards (Desktop) -->
      <div class="row d-none d-lg-flex">
        <div class="col-md-3" data-aos="fade-right" data-aos-delay="100">
          <div class="stat-card">
            <div class="stat-icon blue">
              <i class="bi bi-people"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value"><?= count($clientes) ?></div>
              <div class="stat-label">Total de Clientes</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Mobile Stats -->
      <div class="mobile-stats d-lg-none">
        <div class="mobile-stat-card mobile-fade-in">
          <div class="mobile-stat-icon" style="background-color: rgba(78, 84, 233, 0.1); color: #4e54e9;">
            <i class="bi bi-people"></i>
          </div>
          <div class="mobile-stat-value"><?= count($clientes) ?></div>
          <div class="mobile-stat-label">Total de Clientes</div>
        </div>
      </div>

      <!-- Clients Table (Desktop) -->
      <div class="data-card d-none d-lg-block" data-aos="fade-up" data-aos-delay="200">
        <div class="card-header py-4 px-4">
          <h5><i class="bi bi-list-ul"></i> Lista de Clientes</h5>
          <input type="text" class="search-input" placeholder="Buscar clientes..." id="searchCliente" oninput="buscarCliente(this.value)">
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Nome</th>
                  <th>CPF/CNPJ</th>
                  <th>Telefone</th>
                  <th>Email</th>
                  <th>Cidade</th>
                  <th>Pontos</th>
                  <th class="text-center">Ações</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($clientes)): ?>
                  <?php foreach ($clientes as $index => $cliente): ?>
                    <tr ondblclick="abrirHistorico(<?= $cliente['id_cliente'] ?>)" style="cursor:pointer;" data-aos="fade-up" data-aos-delay="<?php echo 50 * ($index + 1); ?>">
                      <td>
                        <div class="d-flex align-items-center">
                          <div class="avatar" style="background-color: <?= sprintf('#%06X', crc32($cliente['nome'])) ?>;">
                            <?= strtoupper(substr($cliente['nome'], 0, 1)) ?>
                          </div>
                          <?= htmlspecialchars($cliente['nome']) ?>
                        </div>
                      </td>
                      <td><?= htmlspecialchars($cliente['cpf_cnpj']) ?></td>
                      <td><?= htmlspecialchars($cliente['telefone']) ?></td>
                      <td><?= htmlspecialchars($cliente['email']) ?></td>
                      <td><?= htmlspecialchars($cliente['cidade']) ?></td>
                      <td>
                        <span class="status-badge success">
                          <i class="bi bi-star-fill"></i>
                          <?= htmlspecialchars($cliente['pontos_fidelidade']) ?>
                        </span>
                      </td>
                      <td>
                        <div class="action-buttons justify-content-center">
                          <button class="btn-action edit" onclick="editarClienteAjax(<?= $cliente['id_cliente'] ?>)">
                            <i class="bi bi-pencil"></i>
                          </button>
                          <!-- Aplicação do trim() no telefone -->
                          <button class="btn-action msg" onclick='abrirModalMensagemDireta(<?= json_encode($cliente["nome"]) ?>, <?= json_encode($cliente["telefone"]) ?>)'>
                            <i class="bi bi-chat-dots"></i>
                          </button>
                          <button class="btn-action view" onclick="visualizarCliente(<?= $cliente['id_cliente'] ?>)">
                            <i class="bi bi-eye"></i>
                          </button>
                          <button class="btn-action delete" onclick="confirmarExclusao(<?= $cliente['id_cliente'] ?>)">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="8" class="text-center py-4 px-4">
                      <div class="text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        <p>Nenhum cliente encontrado</p>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCliente">
                          <i class="bi bi-plus-circle me-1"></i>Novo Cliente
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <!-- Paginação (Desktop) -->
          <div class="p-5 d-flex flex-row justify-content-center align-items-center">
            <nav>
              <ul class="pagination">
                <?php if ($pagina_atual > 1): ?>
                  <li class="page-item">
                    <a class="page-link" href="?pagina=<?= $pagina_atual - 1 ?>">Anterior</a>
                  </li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                  <li class="page-item <?= $i === $pagina_atual ? 'active' : '' ?>">
                    <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                <?php if ($pagina_atual < $total_paginas): ?>
                  <li class="page-item">
                    <a class="page-link" href="?pagina=<?= $pagina_atual + 1 ?>">Próxima</a>
                  </li>
                <?php endif; ?>
              </ul>
            </nav>
          </div>
        </div>
      </div>

      <!-- Mobile Clients List -->
      <div class="d-lg-none">
        <?php if (!empty($clientes)): ?>
          <?php foreach ($clientes as $index => $cliente): ?>
            <div class="mobile-card mobile-slide-up" style="animation-delay: <?php echo 0.1 * $index; ?>s">
              <div class="mobile-card-header">
                <div class="d-flex align-items-center">
                  <div class="avatar" style="background-color: <?= sprintf('#%06X', crc32($cliente['nome'])) ?>;">
                    <?= strtoupper(substr($cliente['nome'], 0, 1)) ?>
                  </div>
                  <div class="fw-bold"><?= htmlspecialchars($cliente['nome']) ?></div>
                </div>
                <span class="status-badge success">
                  <i class="bi bi-star-fill"></i>
                  <?= htmlspecialchars($cliente['pontos_fidelidade']) ?>
                </span>
              </div>
              <div class="mobile-card-body">
                <div class="row mb-2">
                  <div class="col-6">
                    <div class="text-muted small">CPF/CNPJ:</div>
                    <div><?= htmlspecialchars($cliente['cpf_cnpj']) ?></div>
                  </div>
                  <div class="col-6">
                    <div class="text-muted small">Telefone:</div>
                    <div><?= htmlspecialchars($cliente['telefone']) ?></div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-6">
                    <div class="text-muted small">Email:</div>
                    <div><?= htmlspecialchars($cliente['email']) ?></div>
                  </div>
                  <div class="col-6">
                    <div class="text-muted small">Cidade:</div>
                    <div><?= htmlspecialchars($cliente['cidade']) ?></div>
                  </div>
                </div>
              </div>
              <div class="mobile-card-footer">
                <button class="btn btn-sm btn-outline-primary" onclick="editarClienteAjax(<?= $cliente['id_cliente'] ?>)">
                  <i class="bi bi-pencil me-1"></i>Editar
                </button>
                <div>
                  <button class="btn btn-sm btn-outline-danger" onclick="confirmarExclusao(<?= $cliente['id_cliente'] ?>)">
                    <i class="bi bi-trash me-1"></i>Excluir
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="mobile-card text-center py-4">
            <div class="text-muted">
              <i class="bi bi-inbox fs-1 d-block mb-2"></i>
              <p>Nenhum cliente encontrado</p>
              <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalCliente">
                <i class="bi bi-plus-circle me-1"></i>Novo Cliente
              </button>
            </div>
          </div>
        <?php endif; ?>
        <!-- Paginação Mobile -->
        <div class="d-flex justify-content-center mt-3 mb-5">
          <nav>
            <ul class="pagination">
              <li class="page-item disabled">
                <span class="page-link">
                  <i class="bi bi-chevron-left"></i>
                </span>
              </li>
              <li class="page-item">
                <span class="page-link">1 de 1</span>
              </li>
              <li class="page-item disabled">
                <span class="page-link">
                  <i class="bi bi-chevron-right"></i>
                </span>
              </li>
            </ul>
          </nav>
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

<!-- Mobile FAB Button -->
<button class="mobile-fab d-lg-none" data-bs-toggle="modal" data-bs-target="#modalCliente" onclick="limparFormulario()">
  <i class="bi bi-person-plus"></i>
</button>
<!-- Modal para Histórico de Compras -->
<div class="modal fade" id="modalHistorico" tabindex="-1" aria-labelledby="modalHistoricoLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalHistoricoLabel"><i class="bi bi-clock-history me-2"></i>Histórico de Compras</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body modal-fixed-height">
        <div id="historicoConteudo">
          <p class="text-muted">Carregando histórico...</p>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Modal: Detalhes do Pedido -->
<div class="modal fade" id="modalDetalhesPedido" tabindex="-1" aria-labelledby="modalDetalhesPedidoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" id="detalhesPedidoConteudo">
      <!-- Conteúdo carregado via AJAX -->
    </div>
  </div>
</div>

<!-- Modal Cliente (criar/editar) -->
<div class="modal fade" id="modalCliente" tabindex="-1" aria-labelledby="modalClienteLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-md-down modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalClienteLabel">
          <i class="bi bi-person-plus me-2"></i>Novo Cliente
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <form method="POST" action="clientes.php" id="formCliente">
          <input type="hidden" name="id_cliente" id="id_cliente" value="">
          <!-- Abas do formulário -->
          <ul class="nav nav-tabs mb-4" id="clienteTabs" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" id="dados-tab" data-bs-toggle="tab" href="#dados" role="tab" aria-controls="dados" aria-selected="true">
                <i class="bi bi-person me-1"></i>Dados Pessoais
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="endereco-tab" data-bs-toggle="tab" href="#endereco" role="tab" aria-controls="endereco" aria-selected="false">
                <i class="bi bi-geo-alt me-1"></i>Endereço
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="fidelidade-tab" data-bs-toggle="tab" href="#fidelidade" role="tab" aria-controls="fidelidade" aria-selected="false">
                <i class="bi bi-star me-1"></i>Fidelidade
              </a>
            </li>
          </ul>
          <!-- Conteúdo das abas -->
          <div class="tab-content" id="clienteTabsContent">
            <!-- Aba Dados Pessoais -->
            <div class="tab-pane fade show active" id="dados" role="tabpanel" aria-labelledby="dados-tab">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label for="nome" class="form-label">Nome</label>
                    <input type="text" name="nome" id="nome" class="form-control" required>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label for="cpf_cnpj" class="form-label">CPF/CNPJ</label>
                    <input type="text" name="cpf_cnpj" id="cpf_cnpj" class="form-control">
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group mb-3">
                    <label for="data_nascimento" class="form-label">Data de Nascimento</label>
                    <input type="date" name="data_nascimento" id="data_nascimento" class="form-control">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group mb-3">
                    <label for="telefone" class="form-label">Telefone</label>
                    <input type="text" name="telefone" id="telefone" class="form-control">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" id="email" class="form-control">
                  </div>
                </div>
              </div>
            </div>
            <!-- Aba Endereço -->
            <div class="tab-pane fade" id="endereco" role="tabpanel" aria-labelledby="endereco-tab">
              <div class="row">
                <div class="col-md-3">
                  <div class="form-group mb-3">
                    <label for="cep" class="form-label">CEP</label>
                    <div class="input-group">
                      <input type="text" name="cep" id="cep" class="form-control">
                      <button class="btn btn-outline-secondary" type="button" id="btnBuscarCep">
                        <i class="bi bi-search"></i>
                      </button>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label for="logradouro" class="form-label">Logradouro</label>
                    <input type="text" name="logradouro" id="logradouro" class="form-control">
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="form-group mb-3">
                    <label for="numero" class="form-label">Número</label>
                    <input type="text" name="numero" id="numero" class="form-control">
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group mb-3">
                    <label for="complemento" class="form-label">Complemento</label>
                    <input type="text" name="complemento" id="complemento" class="form-control">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group mb-3">
                    <label for="bairro" class="form-label">Bairro</label>
                    <input type="text" name="bairro" id="bairro" class="form-control">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group mb-3">
                    <label for="cidade" class="form-label">Cidade</label>
                    <input type="text" name="cidade" id="cidade" class="form-control">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group mb-3">
                    <label for="estado" class="form-label">Estado</label>
                    <input type="text" name="estado" id="estado" class="form-control">
                  </div>
                </div>
              </div>
            </div>
            <!-- Aba Fidelidade -->
            <div class="tab-pane fade" id="fidelidade" role="tabpanel" aria-labelledby="fidelidade-tab">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label for="pontos_fidelidade" class="form-label">Pontos Fidelidade</label>
                    <input type="number" name="pontos_fidelidade" id="pontos_fidelidade" class="form-control" value="0" readonly>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="card bg-light">
                    <div class="card-body">
                      <h6 class="card-title"><i class="bi bi-info-circle me-1"></i>Informações</h6>
                      <p class="card-text small">Os pontos de fidelidade são acumulados automaticamente com base nas compras do cliente.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div> <!-- Fim das abas -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-1"></i>Cancelar
          </button>
          <button type="submit" name="salvar_cliente" class="btn btn-primary" id="btnSalvarCliente">
            <i class="bi bi-check-circle me-1"></i>Salvar
          </button>
          </form>
        </div>
      </div>
    </div>
  </div>


  <!-- Modal Excluir Cliente -->
  <div class="modal fade" id="modalExcluirCliente" tabindex="-1" aria-labelledby="modalExcluirClienteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="POST" action="clientes.php">
          <div class="modal-header">
            <h5 class="modal-title" id="modalExcluirClienteLabel"><i class="bi bi-exclamation-triangle me-2"></i>Confirmar Exclusão</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <div class="text-center mb-3">
              <i class="bi bi-trash text-danger fs-1 mb-3 d-block"></i>
              <p>Tem certeza de que deseja excluir este cliente?</p>
              <p class="text-muted small">Esta ação não poderá ser desfeita.</p>
            </div>
            <input type="hidden" name="id_cliente_excluir" id="id_cliente_excluir" value="">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              <i class="bi bi-x-circle me-1"></i>Cancelar
            </button>
            <button type="submit" name="excluir_cliente" class="btn btn-danger">
              <i class="bi bi-trash me-1"></i>Excluir
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Visualizar Cliente -->
  <div class="modal fade" id="modalVisualizarCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalhes do Cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-4">
            <div id="clienteAvatar" class="rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px; font-size: 32px; font-weight: bold; color: white;"></div>
            <h4 id="detalhe_nome" class="mt-2 mb-0"></h4>
            <small class="text-muted" id="detalhe_data_cadastro"></small>
          </div>
          <ul class="list-group" id="detalhes_cliente_lista">
            <!-- Detalhes do cliente serão inseridos via JS -->
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <!-- AOS - Animate On Scroll -->
  <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
 <script>
// Função para formatar o telefone no formato: (XX) X XXXX-XXXX
function formatPhone(value) {
  // Remove caracteres não numéricos
  let digits = value.replace(/\D/g, '');
  let formatted = '';
  
  if (digits.length > 0) {
    formatted += '(' + digits.substring(0, Math.min(2, digits.length));
  }
  if (digits.length >= 2) {
    formatted += ') ';
  }
  if (digits.length >= 3) {
    formatted += digits.substring(2, 3);
  }
  if (digits.length >= 4) {
    formatted += ' ' + digits.substring(3, Math.min(7, digits.length));
  }
  if (digits.length >= 7) {
    formatted += '-' + digits.substring(7, Math.min(11, digits.length));
  }
  
  return formatted;
}

// Função para formatar CPF ou CNPJ
function formatCpfCnpj(value) {
  let digits = value.replace(/\D/g, '');
  
  if (digits.length <= 11) {
    // Formato CPF: xxx.xxx.xxx-xx
    let formatted = '';
    if (digits.length > 0) {
      formatted += digits.substring(0, Math.min(3, digits.length));
    }
    if (digits.length >= 4) {
      formatted += '.' + digits.substring(3, Math.min(6, digits.length));
    }
    if (digits.length >= 7) {
      formatted += '.' + digits.substring(6, Math.min(9, digits.length));
    }
    if (digits.length >= 10) {
      formatted += '-' + digits.substring(9, Math.min(11, digits.length));
    }
    return formatted;
  } else {
    // Formato CNPJ: xx.xxx.xxx/xxxx-xx
    let formatted = '';
    if (digits.length > 0) {
      formatted += digits.substring(0, Math.min(2, digits.length));
    }
    if (digits.length >= 3) {
      formatted += '.' + digits.substring(2, Math.min(5, digits.length));
    }
    if (digits.length >= 6) {
      formatted += '.' + digits.substring(5, Math.min(8, digits.length));
    }
    if (digits.length >= 9) {
      formatted += '/' + digits.substring(8, Math.min(12, digits.length));
    }
    if (digits.length >= 13) {
      formatted += '-' + digits.substring(12, Math.min(14, digits.length));
    }
    return formatted;
  }
}

// Adiciona os eventos de input para aplicar a máscara manualmente
document.addEventListener("DOMContentLoaded", function(){
  const telefoneInput = document.getElementById("telefone");
  const cpfCnpjInput = document.getElementById("cpf_cnpj");
  
  if(telefoneInput) {
    telefoneInput.addEventListener("input", function(e) {
      e.target.value = formatPhone(e.target.value);
    });
  }
  
  if(cpfCnpjInput) {
    cpfCnpjInput.addEventListener("input", function(e) {
      e.target.value = formatCpfCnpj(e.target.value);
    });
  }
});
</script>

  <script>
    // Inicializar AOS
    AOS.init({
      duration: 800,
      once: true
    });
    
    let mensagemPadrao = <?= json_encode($mensagemAtual) ?>;
    // Esconder loader quando a página estiver carregada
    window.addEventListener('load', function() {
      const loader = document.getElementById('pageLoader');
      if (loader) {
        loader.style.opacity = '0';
        setTimeout(() => {
          loader.style.display = 'none';
        }, 500);
      }
    });


    // Toggle Mobile Drawer
    function toggleDrawer() {
    const mobileDrawer = document.getElementById("mobileDrawer")
    const drawerOverlay = document.getElementById("drawerOverlay")

    mobileDrawer.classList.toggle("show")
    drawerOverlay.classList.toggle("show")

    // Prevent body scrolling when drawer is open
    if (mobileDrawer.classList.contains("show")) {
      document.body.style.overflow = "hidden"
    } else {
      document.body.style.overflow = ""
    }
  }

    // Função para limpar formulário do modal de cliente
    function limparFormulario() {
      document.getElementById('id_cliente').value = '';
      document.getElementById('nome').value = '';
      document.getElementById('cpf_cnpj').value = '';
      document.getElementById('data_nascimento').value = '';
      document.getElementById('telefone').value = '';
      document.getElementById('email').value = '';
      document.getElementById('cep').value = '';
      document.getElementById('logradouro').value = '';
      document.getElementById('numero').value = '';
      document.getElementById('complemento').value = '';
      document.getElementById('bairro').value = '';
      document.getElementById('cidade').value = '';
      document.getElementById('estado').value = '';
      document.getElementById('pontos_fidelidade').value = 0;
      document.getElementById('modalClienteLabel').innerHTML = '<i class="bi bi-person-plus me-2"></i>Novo Cliente';
    }

    // Função para editar cliente via AJAX
    function editarClienteAjax(id) {
      var modalCliente = new bootstrap.Modal(document.getElementById('modalCliente'));
      modalCliente.show();
      fetch('clientes.php?getCliente=1&id=' + id)
        .then(response => response.json())
        .then(data => {
          document.getElementById('id_cliente').value = data.id_cliente;
          document.getElementById('nome').value = data.nome;
          document.getElementById('cpf_cnpj').value = data.cpf_cnpj;
          document.getElementById('data_nascimento').value = data.data_nascimento;
          document.getElementById('telefone').value = data.telefone;
          document.getElementById('email').value = data.email;
          document.getElementById('cep').value = data.cep;
          document.getElementById('logradouro').value = data.logradouro;
          document.getElementById('numero').value = data.numero;
          document.getElementById('complemento').value = data.complemento;
          document.getElementById('bairro').value = data.bairro;
          document.getElementById('cidade').value = data.cidade;
          document.getElementById('estado').value = data.estado;
          document.getElementById('pontos_fidelidade').value = data.pontos_fidelidade;
          document.getElementById('modalClienteLabel').innerHTML = '<i class="bi bi-person-edit me-2"></i>Editar Cliente';
        })
        .catch(error => {
          console.error('Erro ao buscar dados do cliente:', error);
          alert('Erro ao carregar dados do cliente');
          modalCliente.hide();
        });
    }

    // Função para confirmar exclusão de cliente
    function confirmarExclusao(id) {
      document.getElementById('id_cliente_excluir').value = id;
      var modalExcluir = new bootstrap.Modal(document.getElementById('modalExcluirCliente'));
      modalExcluir.show();
    }

    // CEP: buscar endereço via API do ViaCEP
    document.getElementById('btnBuscarCep').addEventListener('click', function() {
      const cep = document.getElementById('cep').value.replace(/\D/g, '');
      if (cep.length !== 8) {
        alert('CEP inválido');
        return;
      }
      this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
      this.disabled = true;
      fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => {
          this.innerHTML = '<i class="bi bi-search"></i>';
          this.disabled = false;
          if (!data.erro) {
            document.getElementById('logradouro').value = data.logradouro;
            document.getElementById('bairro').value = data.bairro;
            document.getElementById('cidade').value = data.localidade;
            document.getElementById('estado').value = data.uf;
            document.getElementById('numero').focus();
          } else {
            alert('CEP não encontrado.');
          }
        })
        .catch(error => {
          console.error('Erro ao buscar endereço:', error);
          this.innerHTML = '<i class="bi bi-search"></i>';
          this.disabled = false;
          alert('Erro ao buscar endereço');
        });
    });

    // Função para abrir o modal de Histórico de Compras do cliente (duplo clique na linha)
    function abrirHistorico(id_cliente) {
      fetch(`clientes.php?historico_cliente=1&id=${id_cliente}`)
        .then(response => response.json())
        .then(data => {
          let html = '';
          if (data.length > 0) {
            html += '<table class="table table-bordered">';
            html += '<thead class="table-light"><tr><th>ID Pedido</th><th>Data/Hora</th><th>Total</th><th>Ação</th></tr></thead><tbody>';
            data.forEach(venda => {
              html += `<tr>
                        <td>${venda.id_venda}</td>
                        <td>${new Date(venda.data).toLocaleString('pt-BR')}</td>
                        <td>R$ ${parseFloat(venda.total_venda).toFixed(2).replace('.', ',')}</td>
                        <td><button class="btn btn-sm btn-outline-primary" onclick="abrirDetalhesPedido(${venda.id_venda})">Ver detalhes</button></td>
                       </tr>`;
            });
            html += '</tbody></table>';
          } else {
            html = '<p class="text-muted">Nenhum histórico de compra encontrado para este cliente.</p>';
          }
          document.getElementById('historicoConteudo').innerHTML = html;
          var modalHistorico = new bootstrap.Modal(document.getElementById('modalHistorico'));
          modalHistorico.show();
        })
        .catch(error => {
          console.error('Erro ao carregar histórico:', error);
          alert('Erro ao carregar histórico de compras.');
        });
    }

    // Função para abrir o modal de Detalhes do Pedido
    function abrirDetalhesPedido(id_venda) {
      fetch(`clientes.php?detalhes_pedido=1&id=${id_venda}`)
        .then(response => response.text())
        .then(html => {
          document.getElementById('detalhesPedidoConteudo').innerHTML = html;
          var modalDetalhes = new bootstrap.Modal(document.getElementById('modalDetalhesPedido'));
          modalDetalhes.show();
        })
        .catch(error => {
          console.error('Erro ao carregar detalhes do pedido:', error);
          alert('Erro ao carregar detalhes do pedido.');
        });
    }

    function visualizarCliente(id) {
      fetch(`clientes.php?getCliente=1&id=${id}`)
        .then(res => res.json())
        .then(data => {
          document.getElementById('detalhe_nome').innerText = data.nome || 'Sem nome';
          document.getElementById('detalhe_data_cadastro').innerText =
            data.data_cadastro ? `Cadastrado em ${new Date(data.data_cadastro).toLocaleDateString('pt-BR')}` : '';

          const lista = document.getElementById('detalhes_cliente_lista');
          lista.innerHTML = '';

          const campos = {
            'CPF/CNPJ': data.cpf_cnpj,
            'Data de Nascimento': data.data_nascimento ? new Date(data.data_nascimento).toLocaleDateString('pt-BR') : '',
            'Telefone': data.telefone,
            'Email': data.email,
            'CEP': data.cep,
            'Logradouro': data.logradouro,
            'Número': data.numero,
            'Complemento': data.complemento,
            'Bairro': data.bairro,
            'Cidade': data.cidade,
            'Estado': data.estado,
            'Pontos de Fidelidade': data.pontos_fidelidade
          };

          Object.entries(campos).forEach(([label, valor]) => {
            if (valor && valor !== '0') {
              const li = document.createElement('li');
              li.className = 'list-group-item d-flex justify-content-between align-items-center';
              li.innerHTML = `<strong>${label}:</strong> <span>${valor}</span>`;
              lista.appendChild(li);
            }
          });

          const modal = new bootstrap.Modal(document.getElementById('modalVisualizarCliente'));
          modal.show();
        })
        .catch(err => {
          console.error('Erro ao buscar cliente', err);
          alert('Erro ao carregar dados do cliente');
        });
    }

    function gerarIniciais(nome) {
      if (!nome) return '??';
      const partes = nome.trim().split(' ');
      if (partes.length === 1) {
        const unico = partes[0];
        return (unico[0] + unico[unico.length - 1]).toUpperCase();
      }
      return (partes[0][0] + partes[partes.length - 1][0]).toUpperCase();
    }

    function buscarCliente(termo) {
      fetch('clientes.php?buscar_cliente=1&termo=' + encodeURIComponent(termo))
        .then(response => response.text())
        .then(html => {
          document.querySelector('.data-table tbody').innerHTML = html;
        });
    }

    window.addEventListener('DOMContentLoaded', () => {
      const modalHoje = document.getElementById('modalAniversariantes');
      if (modalHoje) {
        const bsModalHoje = new bootstrap.Modal(modalHoje);
        setTimeout(() => bsModalHoje.show(), 1000);
      }

      const modalProximos = document.getElementById('modalAniversarios');
      if (modalProximos) {
        const bsModalProximos = new bootstrap.Modal(modalProximos);
        setTimeout(() => bsModalProximos.show(), 1500);
      }
    });

    function abrirModalAniversariantes() {
      const modal = new bootstrap.Modal(document.getElementById('modalAniversariantes'));
      modal.show();
      document.getElementById('aniversarianteShortcut').style.display = 'none';
    }

    document.getElementById('modalAniversariantes').addEventListener('hidden.bs.modal', () => {
      document.getElementById('aniversarianteShortcut').style.display = 'block';
    });

    function abrirTodosAniversariantes() {
      const modal = new bootstrap.Modal(document.getElementById('modalTodosAniversariantes'));
      modal.show();
      document.getElementById('aniversarianteShortcut').style.display = 'none';
    }
    document.getElementById('modalTodosAniversariantes').addEventListener('hidden.bs.modal', () => {
      document.getElementById('aniversarianteShortcut').style.display = 'block';
    });

    function abrirModalParabenizar(nome, telefone) {
      const msg = mensagemPadrao.replace('{nome}', nome);
      document.getElementById('clienteNomeWhats').innerText = `Para: ${nome}`;
      document.getElementById('mensagemPersonalizada').value = msg;

      const telefoneFormatado = telefone.replace(/\D/g, '');
      document.getElementById('btnEnviarWhats').href = `https://wa.me/55${telefoneFormatado}?text=${encodeURIComponent(msg)}`;

      const modal = new bootstrap.Modal(document.getElementById('modalParabenizar'));
      modal.show();
    }

    function salvarMensagemPadrao() {
      const novaMensagem = document.getElementById('mensagemPersonalizada').value;
      fetch('clientes.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'salvar_mensagem_padrao=1&mensagem=' + encodeURIComponent(novaMensagem)
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'ok') {
          mensagemPadrao = novaMensagem;
          alert('Mensagem atualizada com sucesso!');
        } else {
          alert('Erro ao salvar: ' + (data.mensagem || 'erro desconhecido'));
        }
      })
      .catch(() => alert('Erro ao salvar mensagem padrão'));
    }

    function abrirModalFraseAniversario(nomeCliente = '') {
      const texto = mensagemPadrao.replace('{nome}', nomeCliente || '{nome}');
      document.getElementById('campoFrasePadrao').value = texto;
      const modal = new bootstrap.Modal(document.getElementById('modalFraseAniversario'));
      modal.show();
    }

    function salvarMensagemPadraoModal() {
      const novaMensagem = document.getElementById('campoFrasePadrao').value;
      fetch('clientes.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'salvar_mensagem_padrao=1&mensagem=' + encodeURIComponent(novaMensagem)
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'ok') {
          mensagemPadrao = novaMensagem;
          alert('Mensagem atualizada com sucesso!');
          bootstrap.Modal.getInstance(document.getElementById('modalFraseAniversario')).hide();
        } else {
          alert('Erro ao salvar: ' + (data.mensagem || 'erro desconhecido'));
        }
      })
      .catch(() => alert('Erro ao salvar nova frase.'));
    }

    function abrirModalMensagemDireta(nome, telefone) {
  console.log("abrirModalMensagemDireta chamada com:", nome, telefone);
  document.getElementById('clienteNomeMensagemDireta').innerText = `Para: ${nome}`;
  document.getElementById('mensagemDiretaTexto').value = '';
  document.getElementById('btnEnviarMensagemDireta').dataset.telefone = telefone.trim();

  const modalEl = document.getElementById('modalMensagemDireta');
  if (!modalEl) {
    console.error("Modal não encontrado!");
    return;
  }
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
}

    function enviarMensagemWhatsDireta() {
      const mensagem = document.getElementById('mensagemDiretaTexto').value.trim();
      const telefone = document.getElementById('btnEnviarMensagemDireta').dataset.telefone;
      if (!mensagem || !telefone) {
        alert("Telefone ou mensagem ausente.");
        return;
      }
      const telefoneFormatado = telefone.trim().replace(/\D/g, '');
      const url = `https://wa.me/55${telefoneFormatado}?text=${encodeURIComponent(mensagem)}`;
      window.open(url, '_blank');
    }

    function enviarMensagemWhats() {
      const mensagem = document.getElementById('mensagemDiretaTexto').value.trim();
      const telefone = document.getElementById('btnEnviarMensagemDireta').dataset.telefone;
      if (!mensagem || !telefone) {
        alert("Telefone ou mensagem ausente.");
        return;
      }
      const telefoneFormatado = telefone.replace(/\D/g, '');
      const url = `https://wa.me/55${telefoneFormatado}?text=${encodeURIComponent(mensagem)}`;
      window.open(url, '_blank');
    }
  </script>

  <div class="modal fade" id="modalTodosAniversariantes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title">
            <i class="bi bi-balloon-heart me-2"></i> Aniversariantes
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="d-flex justify-content-end mb-2">
            <button class="btn btn-outline-secondary btn-sm" onclick="abrirModalFraseAniversario()">
              <i class="bi bi-pencil me-1"></i> Editar mensagem padrão
            </button>
          </div>
          <?php if (!empty($aniversariantesHoje)): ?>
            <h6 class="mb-3"><i class="bi bi-calendar-day me-1"></i> Hoje</h6>
            <ul class="list-group mb-4">
              <?php foreach ($aniversariantesHoje as $cli): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <strong><?= htmlspecialchars($cli['nome']) ?></strong>
                    <small class="text-muted d-block"><?= htmlspecialchars($cli['email']) ?> | <?= htmlspecialchars($cli['telefone']) ?></small>
                  </div>
                  <span class="badge bg-primary"><?= date('d/m', strtotime($cli['data_nascimento'])) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <?php if (!empty($aniversariantesProximos)): ?>
            <h6 class="mb-3"><i class="bi bi-calendar-week me-1"></i> Próximos 7 dias</h6>
            <ul class="list-group">
              <?php foreach ($aniversariantesProximos as $cli): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <strong><?= htmlspecialchars($cli['nome']) ?></strong>
                    <div class="text-muted small">Aniversário em <?= date('d/m', strtotime($cli['data_nascimento'])) ?></div>
                  </div>
                  <?php
                    $telefone = preg_replace('/\D/', '', $cli['telefone'] ?? '');
                    $msg = str_replace('{nome}', $cli['nome'], $mensagemAtual);
                    $linkZap = strlen($telefone) >= 10 ? "https://wa.me/55{$telefone}?text=" . urlencode($msg) : null;
                  ?>
                  <?php if ($linkZap): ?>
                    <a href="<?= $linkZap ?>" class="btn btn-success btn-sm" target="_blank">
                      <i class="bi bi-whatsapp me-1"></i>Parabenizar
                    </a>
                  <?php else: ?>
                    <span class="badge bg-secondary">Sem telefone</span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div id="aniversarianteShortcut" onclick="abrirTodosAniversariantes()" ...>
    🎉 Ver Aniversariantes
  </div>

  <div class="modal fade" id="modalParabenizar" tabindex="-1" aria-labelledby="modalParabenizarLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title" id="modalParabenizarLabel"><i class="bi bi-balloon-heart me-2"></i>Parabenizar Cliente</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p><strong id="clienteNomeWhats"></strong></p>
          <textarea id="mensagemPersonalizada" class="form-control mb-3" rows="4"></textarea>
          <div class="d-flex justify-content-between">
            <button class="btn btn-outline-secondary" onclick="salvarMensagemPadrao()">Salvar como padrão</button>
            <button type="button" class="btn btn-success" onclick="enviarMensagemWhats()">
              <i class="bi bi-whatsapp me-1"></i>Enviar no WhatsApp
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalFraseAniversario" tabindex="-1" aria-labelledby="modalFraseAniversarioLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title" id="modalFraseAniversarioLabel"><i class="bi bi-pencil-square me-2"></i>Editar Frase Padrão</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <textarea id="campoFrasePadrao" class="form-control mb-3" rows="5"></textarea>
          <div class="d-flex justify-content-between">
            <span class="text-muted small">Use <code>{nome}</code> para o nome do cliente.</span>
            <button class="btn btn-primary" onclick="salvarMensagemPadraoModal()">Salvar</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="modalMensagemDireta" tabindex="-1" aria-labelledby="modalMensagemDiretaLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="modalMensagemDiretaLabel">
            <i class="bi bi-chat-dots me-2"></i>Enviar Mensagem
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p id="clienteNomeMensagemDireta" class="fw-bold mb-2"></p>
          <textarea id="mensagemDiretaTexto" class="form-control" rows="4" placeholder="Digite sua mensagem..."></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-success" id="btnEnviarMensagemDireta" onclick="enviarMensagemWhatsDireta()">
            <i class="bi bi-whatsapp me-1"></i>Enviar no WhatsApp
          </button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            Cancelar
          </button>
        </div>
      </div>
    </div>
  </div>
<!-- Mini Modal para Clientes Inativos -->
<div id="miniModalInativos" class="card" style="position: fixed; bottom: 20px; right: 20px; max-width: 300px; z-index: 1050; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
    <h6 class="mb-0">Clientes que não compram há 60 dias</h6>
    <button type="button" class="btn-close btn-close-white" aria-label="Close" onclick="document.getElementById('miniModalInativos').style.display='none'"></button>
  </div>
  <div class="card-body" style="max-height: 400px; overflow-y: auto;">
    <ul class="list-group list-group-flush">
      <?php
      // Limitar a exibição para os primeiros 3 clientes inativos
      $inativosParaMostrar = array_slice($clientesInativos, 0, 999);
      if (!empty($inativosParaMostrar)):
          foreach ($inativosParaMostrar as $cliente):
              // Calcula os dias de inatividade
              if (!empty($cliente['ultima_compra'])) {
                  $lastPurchase = new DateTime($cliente['ultima_compra']);
                  $today = new DateTime();
                  $diff = $today->diff($lastPurchase);
                  $diasInativos = $diff->days . " dias";
              } else {
                  $diasInativos = "Nunca";
              }
      ?>
        <li class="list-group-item">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <strong><?= htmlspecialchars($cliente['nome']) ?></strong><br>
              <small class="text-muted">
                Última compra: <?= $cliente['ultima_compra'] ? date('d/m/Y', strtotime($cliente['ultima_compra'])) : 'Nunca' ?>
              </small>
            </div>
            <span class="badge bg-danger"><?= $diasInativos ?></span>
          </div>
        </li>
      <?php
          endforeach;
      else:
      ?>
        <li class="list-group-item">Nenhum cliente inativo encontrado</li>
      <?php endif; ?>
    </ul>
    <?php if (count($clientesInativos) > 3): ?>
      <div class="mt-2 text-center">
        <!-- Link para a página com a listagem completa dos clientes inativos -->

      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>