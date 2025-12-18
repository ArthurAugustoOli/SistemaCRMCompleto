<?php
// Arquivo único que consolida todas as funcionalidades de produtos
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir dependências
require_once '../../app/config/config.php';
require_once '../login/verificar_sessao.php';
require_once '../../app/models/Produto.php';
require_once '../../app/models/ProdutoVariacao.php';
// Ajuste o caminho conforme necessário
require_once '../../app/models/Despesas.php';

use App\Models\Despesas;

$despesaModel = new Despesas();



// Inicializar modelos
$produtoModel = new Produto($mysqli);
$variacaoModel = new ProdutoVariacao($mysqli);

// Determinar a ação com base nos parâmetros GET
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id_produto = isset($_GET['id']) ? intval($_GET['id']) : 0;
$id_variacao = isset($_GET['id_variacao']) ? intval($_GET['id_variacao']) : 0;
$msg = isset($_GET['msg']) ? $_GET['msg'] : null;
$msg_type = isset($_GET['msg_type']) ? $_GET['msg_type'] : 'info';

// Processar AJAX para busca
if (isset($_GET['ajax_search'])) {
  $term = $_GET['term'] ?? '';
  $results = $produtoModel->search($term);
  header('Content-Type: application/json');
  echo json_encode($results);
  exit;
}

// Processar formulários POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  switch ($action) {
    case 'show_value':
      header('Content-Type: application/json');

      $senha_input = trim($_POST['senha'] ?? '');
      $senha_session = trim($_SESSION['senha'] ?? '');



      if (($senha_input !== $senha_session)) {
        echo json_encode([
          'status' => 'error',
          'message' => 'Senha inválida',
          'input' => $senha_input,
          'session' => $senha_session
        ]);
        exit;
      }
      // Em teste.php, na parte “default” do switch($action):


      // --- Início do cálculo de valor em estoque ---
      $produtos = $produtoModel->getAll();
      $valorEstoqueTotal = 0;
      foreach ($produtos as $prod) {
        $variacoes = $variacaoModel->getAllByProduto($prod['id_produto']);
        if (count($variacoes) > 0) {
          foreach ($variacoes as $var) {
            $valorEstoqueTotal += $var['preco_venda'] * $var['estoque_atual'];
          }
        } else {
          $valorEstoqueTotal += $prod['preco_venda'] * $prod['estoque_atual'];
        }
      }
      // --- Fim do cálculo ---

      echo json_encode([
        'status' => 'success',
        'value' => number_format($valorEstoqueTotal, 2, ',', '.')
      ]);
      exit;


    case 'store':
      // gera código de barras automático se não informado
      if (empty($_POST['codigo_barras'])) {
        $_POST['codigo_barras'] = $produtoModel->generateCodigoBarras($_POST['nome']);
      }
      // valida duplicidade de código de barras
      if (!empty($_POST['codigo_barras'])) {
        $produtoExistente = $produtoModel->getByCode($_POST['codigo_barras']);
        if ($produtoExistente) {
          $mensagem = "O produto '{$produtoExistente['nome']}' já está cadastrado com o código '{$produtoExistente['codigo_barras']}'.";
          header("Location: teste.php?msg=" . urlencode($mensagem) . "&msg_type=danger&error_duplicate=1");
          exit;
        }
      }

      // upload de foto
      $foto = null;
      if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/images/';
        if (!is_dir($uploadDir))
          mkdir($uploadDir, 0755, true);
        $fileName = uniqid() . '-' . basename($_FILES['foto']['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetPath)) {
          $foto = '../assets/images/' . $fileName;
        }
      }

      // prepara dados do produto
      $dados = [
        'nome' => $_POST['nome'],
        'descricao' => $_POST['descricao'],
        'estoque_atual' => intval($_POST['estoque_atual'] ?? 0),
        'foto' => $foto,
        'estoque_min' => intval($_POST['estoque_min'] ?? 0),
        'estoque_max' => intval($_POST['estoque_max'] ?? 0),
        'localizacao_estoque' => $_POST['localizacao_estoque'],
        'preco_custo' => floatval($_POST['preco_custo']),
        'preco_venda' => floatval($_POST['preco_venda']),
        'codigo_barras' => $_POST['codigo_barras']
      ];

      // cria o produto
      if ($produtoModel->create($dados)) {
        $idProduto = $mysqli->insert_id;

        // 1) despesa de estoque inicial do produto
        if ($dados['estoque_atual'] > 0) {
          $itensP = [
            [
              'id_produto' => $idProduto,
              'id_variacao' => null,
              'quantidade' => $dados['estoque_atual'],
              'preco_unitario' => $dados['preco_custo']
            ]
          ];
          $despesaModel->createDespesa(
            'Compra de Produtos',
            'Estoque inicial via cadastro de produto',
            $dados['estoque_atual'] * $dados['preco_custo'],
            date('Y-m-d'),
            'pendente',
            $itensP
          );
        }

        // 2) loop e criação das variações
        $cors = $_POST['var_cor'] ?? [];
        $tams = $_POST['var_tamanho'] ?? [];
        $skus = $_POST['var_sku'] ?? [];
        $pvendas = $_POST['var_preco_venda'] ?? [];
        $estoqs = $_POST['var_estoque'] ?? [];

        foreach ($cors as $i => $cor) {
          // pula linhas totalmente vazias
          $cor     = trim($cor);
          $tam     = trim($tams[$i]    ?? '');
          $sku     = trim($skus[$i]    ?? '');
          $pvenda  = trim($pvendas[$i] ?? '');
          $estoque = trim($estoqs[$i]  ?? '');

          if ($cor !== '' || $tam !== '' || $pvenda !== '' || $estoque !== '') {
            $dadosVar = [
            'id_produto' => $idProduto,
            'cor' => $cor,
            'tamanho' => $tam,
            'sku' => $sku,
            'preco_venda' => floatval($pvenda),
            'estoque_atual' => intval($estoque),
            ];

            // cria variação
            $variacaoModel->create($dadosVar);
            $idVar = $mysqli->insert_id;
          }

          // gera despesa se tiver estoque inicial na variação
          if ($dadosVar['estoque_atual'] > 0) {
            $itensV = [
              [
                'id_produto' => $idProduto,
                'id_variacao' => $idVar,
                'quantidade' => $dadosVar['estoque_atual'],
                'preco_unitario' => $dados['preco_custo']
              ]
            ];
            $despesaModel->createDespesa(
              'Compra de Variações',
              "Estoque inicial (SKU {$dadosVar['sku']})",
              $dadosVar['estoque_atual'] * $dados['preco_custo'],
              date('Y-m-d'),
              'pendente',
              $itensV
            );
          }
        }

        // 3) redireciona com sucesso
        header("Location: teste.php?msg=Produto e variações criados com sucesso!&msg_type=success");
      } else {
        header("Location: teste.php?msg=Erro ao criar produto!&msg_type=danger");
      }
      exit;
    case 'update':
      if (empty($_POST['codigo_barras'])) {
        $_POST['codigo_barras'] = $produtoModel->generateCodigoBarras($_POST['nome']);
      }
      // Código de atualização de produto (inalterado)
      $foto = null;
      if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../assets/images/';
        $fileName = uniqid() . '-' . basename($_FILES['foto']['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetPath)) {
          $foto = '../assets/images/' . $fileName;
        }
      } else {
        $produtoExistente = $produtoModel->getById($id_produto);
        $foto = $produtoExistente['foto'];
      }
      $produtoAntigo = $produtoModel->getById($id_produto);
      $dados = [
        'nome' => $_POST['nome'],
        'descricao' => $_POST['descricao'],
        'foto' => $foto,
        'estoque_atual' => intval($_POST['estoque_atual'] ?? 0),
        'estoque_min' => intval($_POST['estoque_min'] ?? 0),
        'estoque_max' => intval($_POST['estoque_max'] ?? 0),
        'localizacao_estoque' => $_POST['localizacao_estoque'],
        'preco_custo' => floatval($_POST['preco_custo']),
        'preco_venda' => floatval($_POST['preco_venda']),
        'codigo_barras' => $_POST['codigo_barras']
      ];

      // chama update em vez de create
      if (!$produtoModel->update($id_produto, $dados)) {
        header("Location: teste.php?msg=Erro ao atualizar produto!&msg_type=danger");
        exit;
      }



      // 3) Despesa do estoque inicial do produto (se estoque_atual>0)
      if ($dados['estoque_atual'] > 0) {
        $itens = [
          [
            'id_produto' => $idProduto,
            'id_variacao' => null,
            'quantidade' => $dados['estoque_atual'],
            'preco_unitario' => $dados['preco_custo']
          ]
        ];
        $valorTotal = $dados['preco_custo'] * $dados['estoque_atual'];
        $despesaModel->createDespesa(
          'Compra de Produtos',
          'Estoque inicial via cadastro de produto',
          $valorTotal,
          date('Y-m-d'),
          'pendente',
          $itens
        );
      }

      // 4) Gravar as variações vindas do form
      $cors = $_POST['var_cor'] ?? [];
      $tams = $_POST['var_tamanho'] ?? [];
      $skus = $_POST['var_sku'] ?? [];
      $pvendas = $_POST['var_preco_venda'] ?? [];
      $estoqs = $_POST['var_estoque'] ?? [];

      foreach ($cors as $i => $cor) {
        // ignora linha vazia
        if (
          empty($cor) &&
          empty($tams[$i]) &&
          empty($skus[$i]) &&
          empty($pvendas[$i]) &&
          empty($estoqs[$i])
        )
          continue;

        $dadosVar = [
          'id_produto' => $idProduto,
          'cor' => $cor,
          'tamanho' => $tams[$i],
          'sku' => $skus[$i],
          'preco_venda' => floatval($pvendas[$i]),
          'estoque_atual' => intval($estoqs[$i])
        ];
        // cria variação
        $variacaoModel->create($dadosVar);

        // despesa de estoque da variação
        if ($dadosVar['estoque_atual'] > 0) {
          $itensV = [
            [
              'id_produto' => $idProduto,
              'id_variacao' => $idVar,
              'quantidade' => $dadosVar['estoque_atual'],
              'preco_unitario' => $dados['preco_custo']
            ]
          ];
          $valorV = $dadosVar['estoque_atual'] * $dados['preco_custo'];
          $despesaModel->createDespesa(
            'Compra de Variações',
            "Estoque inicial (SKU {$dadosVar['sku']})",
            $valorV,
            date('Y-m-d'),
            'pendente',
            $itensV
          );
        }
      }

      // 5) Finalmente redireciona com sucesso
      header("Location: teste.php");
      exit;

    case 'add_stock':
      $quantidade = intval($_POST['quantidade'] ?? 0);
      $preco_custo = floatval(str_replace(',', '.', $_POST['preco_custo'] ?? 0));

      if ($id_variacao > 0) {
        // 1) atualiza a variação
        $ok = $variacaoModel->adicionarEstoqueEAtualizarCustoVariacao(
          $id_variacao,
          $quantidade,
          $preco_custo
        );
        // 2) pega o produto-pai para o redirect
        $var = $variacaoModel->getById($id_variacao);
        $parentId = $var['id_produto'] ?? 0;
      } else {
        // atualiza o produto
        $ok = $produtoModel->adicionarEstoqueEAtualizarCusto(
          $id_produto,
          $quantidade,
          $preco_custo
        );
        $parentId = $id_produto;
      }

      // mensagem
      if ($ok) {
        $msg = $id_variacao > 0
          ? 'Estoque e custo da variação atualizados com sucesso!'
          : 'Estoque e custo do produto atualizados com sucesso!';
        $msg_type = 'success';
      } else {
        $msg = 'Erro ao atualizar estoque e custo.';
        $msg_type = 'danger';
      }

      // redirect correto
      if ($id_variacao > 0) {
        header("Location: teste.php?action=variacoes&id={$parentId}&msg=" . urlencode($msg) . "&msg_type={$msg_type}");
      } else {
        header("Location: teste.php?msg=" . urlencode($msg) . "&msg_type={$msg_type}");
      }
      exit;

    case 'store_variacao':
      if (empty($_POST['sku'])) {
        $produtoDados = $produtoModel->getById($id_produto);
        $_POST['sku'] = $variacaoModel->generateSku(
          $produtoDados['nome'] ?? '',
          $_POST['tamanho'] ?? '',
          $_POST['cor'] ?? ''
        );
      }
      // Criar/atualizar variação de produto
      $dados = [
        'id_produto' => $id_produto,
        'cor' => $_POST['cor'] ?? '',
        'tamanho' => $_POST['tamanho'] ?? '',
        'sku' => $_POST['sku'],
        'preco_venda' => $_POST['preco_venda'] ?? 0,
        'estoque_atual' => $_POST['estoque_atual'] ?? 0
      ];

      try {
        if (!empty($_POST['id_variacao'])) {
          // Edição de variação existente
          $id_variacao = intval($_POST['id_variacao']);
          if ($variacaoModel->update($id_variacao, $dados)) {
            $msg = "Variação atualizada com sucesso!";
            $msg_type = "success";
            // —————— GERAÇÃO DE DESPESA SE AUMENTOU ESTOQUE NA VARIAÇÃO ——————
            $oldVar = $variacaoModel->getById($id_variacao);
            $difVar = (int) $dados['estoque_atual'] - (int) $oldVar['estoque_atual'];

            if ($difVar > 0) {
              $pai = $produtoModel->getById($id_produto);
              $custo = (float) $pai['preco_custo'];

              $itens = [
                [
                  'id_produto' => $id_produto,
                  'id_variacao' => $id_variacao,
                  'quantidade' => $difVar,
                  'preco_unitario' => $custo
                ]
              ];
              $valorTotal = $difVar * $custo;

              $despesaModel->createDespesa(
                'Compra de Produtos',
                "Acréscimo de estoque na variação SKU '{$dados['sku']}'",
                $valorTotal,
                date('Y-m-d'),
                'pendente',
                $itens
              );
            }


            // —————— GERAÇÃO DE DESPESA SE AUMENTOU ESTOQUE NA VARIAÇÃO ——————
            $oldVar = $variacaoModel->getById($id_variacao);
            $difVar = (int) $dados['estoque_atual'] - (int) $oldVar['estoque_atual'];

            if ($difVar > 0) {
              $pai = $produtoModel->getById($id_produto);
              $custo = (float) $pai['preco_custo'];

              $itens = [
                [
                  'id_produto' => $id_produto,
                  'id_variacao' => $id_variacao,
                  'quantidade' => $difVar,
                  'preco_unitario' => $custo
                ]
              ];
              $valorTotal = $difVar * $custo;

              $despesaModel->createDespesa(
                'Compra de Produtos',
                "Acréscimo de estoque na variação SKU '{$dados['sku']}'",
                $valorTotal,
                date('Y-m-d'),
                'pendente',
                $itens
              );
            }
          } else {
            $msg = "Erro ao atualizar variação.";
            $msg_type = "danger";
          }
        } else {
          // Obter variações existentes para o produto
          $variacoesExistentes = $variacaoModel->getAllByProduto($id_produto);
          if (count($variacoesExistentes) === 0) {
            $produtoModel->atualizarEstoqueAtual($id_produto, 0);
          }
          // Criar nova variação
          if ($variacaoModel->create($dados)) {
            // —————— GERAÇÃO DE DESPESA PARA VARIAÇÃO NOVA COM ESTOQUE ——————
            $qtd = (int) $dados['estoque_atual'];
            if ($qtd > 0) {
              // pega custo do produto-pai
              $pai = $produtoModel->getById($id_produto);
              $custo = (float) $pai['preco_custo'];

              $itens = [
                [
                  'id_produto' => $id_produto,
                  'id_variacao' => $idVar,
                  'quantidade' => $qtd,
                  'preco_unitario' => $custo
                ]
              ];
              $valorTotal = $qtd * $custo;

              $despesaModel->createDespesa(
                'Compra de Produtos',
                "Estoque inicial da variação SKU '{$dados['sku']}' do produto '{$pai['nome']}'",
                $valorTotal,
                date('Y-m-d'),
                'pendente',
                $itens
              );
            }

            $msg = "Variação criada com sucesso!";
            $msg_type = "success";
          } else {
            $msg = "Erro ao criar variação.";
            $msg_type = "danger";
          }
        }
      } catch (mysqli_sql_exception $ex) {
        if ($ex->getCode() == 1062) {
          // SKU duplicado: obtém os dados da variação já cadastrada
          $sku = $dados['sku'];
          $variacaoExistente = $variacaoModel->getBySKU($sku);
          if ($variacaoExistente) {
            $produtoExistente = $produtoModel->getById($variacaoExistente['id_produto']);
            // Construindo a mensagem informando o produto e os detalhes da variação (cor e tamanho)
            $msg = "Erro: SKU duplicado. Já existe uma variação do produto '{$produtoExistente['nome']}' com o SKU '{$sku}'";
            if (!empty($variacaoExistente['cor']) || !empty($variacaoExistente['tamanho'])) {
              $detalhes = [];
              if (!empty($variacaoExistente['cor'])) {
                $detalhes[] = "cor: {$variacaoExistente['cor']}";
              }
              if (!empty($variacaoExistente['tamanho'])) {
                $detalhes[] = "tamanho: {$variacaoExistente['tamanho']}";
              }
              $msg .= " (" . implode(", ", $detalhes) . ")";
            }
            $msg .= ". Por favor, informe um SKU único.";
          } else {
            $msg = "Erro: SKU duplicado. Por favor, informe um SKU único.";
          }
        } else {
          $msg = "Erro ao processar variação: " . $ex->getMessage();
        }
        $msg_type = "danger";
      }


      header("Location: teste.php?action=variacoes&id=$id_produto&msg=" . urlencode($msg) . "&msg_type=$msg_type");
      exit;
  }
}

// Processar ações GET
switch ($action) {
  case 'delete':
    if ($id_produto > 0) {
      if ($produtoModel->delete($id_produto)) {
        header("Location: teste.php?msg=Produto excluído com sucesso!&msg_type=success");
      } else {
        header("Location: teste.php?msg=Erro ao excluir produto!&msg_type=danger");
      }
    } else {
      header("Location: teste.php?msg=ID de produto inválido!&msg_type=warning");
    }
    exit;

  case 'delete_variacao':
    if ($id_variacao > 0) {
      if ($variacaoModel->delete($id_variacao)) {
        $msg = "Variação excluída com sucesso!";
        $msg_type = "success";
      } else {
        $msg = "Erro ao excluir variação.";
        $msg_type = "danger";
      }
      header("Location: teste.php?action=variacoes&id=$id_produto&msg=$msg&msg_type=$msg_type");
    } else {
      header("Location: teste.php?action=variacoes&id=$id_produto&msg=ID de variação inválido!&msg_type=warning");
    }
    exit;
}

// Define título da página
$page_title = "Sistema de Gestão";
switch ($action) {
  case 'create':
    $page_title = "Novo Produto | Sistema de Gestão";
    break;
  case 'edit':
    $page_title = "Editar Produto | Sistema de Gestão";
    break;
  case 'variacoes':
    $page_title = "Variações de Produto | Sistema de Gestão";
    break;
}

// Verificar se está editando uma variação
$editando_variacao = false;
$variacaoEdicao = null;
if ($action === 'variacoes' && isset($_GET['edit_variacao']) && $_GET['edit_variacao'] == 1 && $id_variacao > 0) {
  $editando_variacao = true;
  $variacaoEdicao = $variacaoModel->getById($id_variacao);
}

// Processar AJAX para editar variação sem recarregar
if (isset($_GET['ajax_variacao'])) {
  $idv = isset($_GET['id_variacao']) ? intval($_GET['id_variacao']) : 0;
  $variacao = $variacaoModel->getById($idv);
  header('Content-Type: application/json');
  echo json_encode($variacao);
  exit;
}

$produtos = $produtoModel->getAll();
$valorEstoqueTotal = 0;
foreach ($produtos as $prod) {
  $variacoes = $variacaoModel->getAllByProduto($prod['id_produto']);
  if (count($variacoes) > 0) {
    foreach ($variacoes as $var) {
      $valorEstoqueTotal += $var['preco_venda'] * $var['estoque_atual'];
    }
  } else {
    $valorEstoqueTotal += $prod['preco_venda'] * $prod['estoque_atual'];
  }
}

// Sessao 
$type_session = trim($_SESSION['type'] ?? '');?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $page_title; ?></title>
  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Animate.css -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- AOS - Animate On Scroll -->
  <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />

  <style>
    :root {
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
      --body-bg: #f5f7fb;
      --sidebar-width: 280px;
      --topbar-height: 70px;
      --card-border-radius: 0.75rem;
      --btn-border-radius: 0.5rem;
      --transition-speed: 0.3s;
      --bottom-nav-height: 60px;
      --mobile-header-height: 60px;
      --primary-color: #4e54e9;
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

    /* Base Styles */
    body {
      font-family: "Poppins", sans-serif;
      background-color: var(--body-bg);
      color: var(--dark);
      overflow-x: hidden;
      transition: background-color var(--transition-speed);
      margin: 0;
      padding: 0;
    }

    /* Scrollbar & Layout */
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

    .app-container {
      display: flex;
      min-height: 100vh;
    }

    /* Main Content - FIXED TO RESPECT SIDEBAR WIDTH */
    .main-content {
      margin-left: var(--sidebar-width);
      width: calc(100% - var(--sidebar-width));
      padding: 1.5rem;
      transition: margin-left var(--transition-speed), width var(--transition-speed);
    }

    /* Topbar */
    .topbar {
      position: fixed;
      top: 0;
      right: 0;
      left: var(--sidebar-width);
      height: var(--topbar-height);
      background-color: white;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 1.5rem;
      z-index: 999;
      transition: left var(--transition-speed);
    }

    .topbar-toggle {
      background: none;
      border: none;
      color: var(--gray);
      font-size: 1.5rem;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 50%;
    }

    .topbar-toggle:hover {
      color: var(--primary);
      background-color: var(--gray-light);
    }

    .topbar-search {
      position: relative;
      max-width: 400px;
      width: 100%;
    }

    .topbar-search input {
      padding-left: 2.5rem;
      border-radius: 50px;
      border: 1px solid #e9ecef;
      background-color: var(--gray-light);
      transition: all 0.3s;
    }

    .topbar-search input:focus {
      background-color: white;
      box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.15);
      border-color: var(--primary);
    }

    .topbar-search i {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--gray);
      transition: color 0.3s;
    }

    .topbar-search input:focus+i {
      color: var(--primary);
    }

    .topbar-actions {
      display: flex;
      align-items: center;
    }

    .topbar-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--gray);
      font-size: 1.25rem;
      margin-left: 0.5rem;
      cursor: pointer;
      transition: all 0.2s;
      position: relative;
    }

    .topbar-icon:hover {
      background-color: var(--gray-light);
      color: var(--primary);
    }

    .topbar-icon .badge {
      position: absolute;
      top: -5px;
      right: -5px;
      font-size: 0.65rem;
      padding: 0.25rem 0.4rem;
    }

    .topbar-divider {
      width: 1px;
      height: 2rem;
      background-color: #e9ecef;
      margin: 0 1rem;
    }

    /* Cards & Components */
    .card {
      border: none;
      border-radius: var(--card-border-radius);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      transition: transform 0.3s, box-shadow 0.3s;
      margin-bottom: 1.5rem;
      overflow: hidden;
      background-color: white;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .card-header {
      background-color: white;
      border-bottom: 1px solid rgba(0, 0, 0, 0.05);
      padding: 1.25rem 1.5rem;
      font-weight: 600;
      display: flex;
      align-items: center;
    }

    .card-header i {
      margin-right: 0.75rem;
      color: var(--primary);
      font-size: 1.25rem;
    }

    .card-body {
      padding: 1.5rem;
    }

    /* Stats Cards */
    .stats-card {
      position: relative;
      overflow: hidden;
      border-radius: var(--card-border-radius);
      padding: 1.5rem;
      background-color: white;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
      transition: transform 0.3s, box-shadow 0.3s;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
    }

    .stats-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .stats-card-icon {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.75rem;
      margin-right: 1.25rem;
      position: relative;
      z-index: 1;
    }

    .stats-card-icon::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      border-radius: 50%;
      background: currentColor;
      opacity: 0.15;
      z-index: -1;
    }

    .stats-card-content {
      flex: 1;
    }

    .stats-card-label {
      font-size: 0.875rem;
      color: var(--gray);
      margin-bottom: 0.25rem;
    }

    .stats-card-value {
      font-size: 1.75rem;
      font-weight: 700;
      margin-bottom: 0;
      color: var(--dark);
    }

    .stats-card-bg {
      position: absolute;
      bottom: -15px;
      right: -15px;
      font-size: 5rem;
      opacity: 0.05;
      transform: rotate(-15deg);
    }

    /* Color Variants */
    .stats-card.primary .stats-card-icon {
      color: var(--primary);
    }

    .stats-card.success .stats-card-icon {
      color: var(--success);
    }

    .stats-card.warning .stats-card-icon {
      color: var(--warning);
    }

    .stats-card.danger .stats-card-icon {
      color: var(--danger);
    }

    /* Tables */
    .table {
      margin-bottom: 0;
    }

    .table thead th {
      background-color: var(--gray-light);
      color: var(--dark);
      font-weight: 600;
      border: none;
      padding: 1rem;
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.05rem;
    }

    .table tbody td {
      padding: 1rem;
      vertical-align: middle;
      border-color: #f1f3f5;
    }

    .table-striped tbody tr:nth-of-type(odd) {
      background-color: rgba(0, 0, 0, 0.01);
    }

    .table-hover tbody tr:hover {
      background-color: rgba(67, 97, 238, 0.05);
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

    /* Button Variants */
    .btn-primary {
      background-color: var(--primary);
      border-color: var(--primary);
    }

    .btn-primary:hover,
    .btn-primary:focus {
      background-color: var(--primary-dark);
      border-color: var(--primary-dark);
    }

    .btn-success {
      background-color: var(--success);
      border-color: var(--success);
    }

    .btn-info {
      background-color: var(--info);
      border-color: var(--info);
      color: white;
    }

    .btn-warning {
      background-color: var(--warning);
      border-color: var(--warning);
      color: white;
    }

    .btn-danger {
      background-color: var(--danger);
      border-color: var(--danger);
    }

    .btn-outline-primary {
      color: var(--primary);
      border-color: var(--primary);
    }

    .btn-outline-primary:hover {
      background-color: var(--primary);
      border-color: var(--primary);
    }

    /* Button Sizes */
    .btn-sm {
      padding: 0.25rem 0.75rem;
      font-size: 0.875rem;
    }

    .btn-lg {
      padding: 0.75rem 1.5rem;
      font-size: 1.1rem;
    }

    /* Icon Buttons */
    .btn-icon {
      width: 40px;
      height: 40px;
      padding: 0;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .btn-icon i {
      margin: 0;
      font-size: 1.1rem;
    }

    .btn-icon.btn-sm {
      width: 32px;
      height: 32px;
    }

    .btn-icon.btn-sm i {
      font-size: 0.875rem;
    }

    .btn-icon.btn-lg {
      width: 48px;
      height: 48px;
    }

    .btn-icon.btn-lg i {
      font-size: 1.25rem;
    }

    /* Forms */
    .form-control {
      border-radius: 0.5rem;
      border: 1px solid #e9ecef;
      padding: 0.6rem 1rem;
      transition: all 0.3s;
    }

    .form-control:focus {
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
      border: 1px solid #e9ecef;
      background-color: var(--gray-light);
    }

    /* Badges */
    .badge {
      padding: 0.35rem 0.65rem;
      font-weight: 600;
      border-radius: 50px;
    }

    /* Alerts */
    .alert {
      border: none;
      border-radius: var(--card-border-radius);
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
    }

    .alert i {
      margin-right: 0.75rem;
      font-size: 1.25rem;
    }

    /* Alert Variants */
    .alert-success {
      background-color: rgba(6, 214, 160, 0.15);
      color: var(--success);
    }

    .alert-info {
      background-color: rgba(76, 201, 240, 0.15);
      color: var(--info);
    }

    .alert-warning {
      background-color: rgba(249, 199, 79, 0.15);
      color: var(--warning);
    }

    .alert-danger {
      background-color: rgba(239, 71, 111, 0.15);
      color: var(--danger);
    }

    /* Breadcrumb */
    .breadcrumb {
      background-color: transparent;
      padding: 0;
      margin-bottom: 1.5rem;
    }

    .breadcrumb-item a {
      color: var(--primary);
      text-decoration: none;
      transition: color 0.2s;
    }

    .breadcrumb-item a:hover {
      color: var(--primary-dark);
    }

    .breadcrumb-item.active {
      color: var(--gray);
    }

    /* Page Header */
    .page-header {
      margin-bottom: 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .page-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark);
      margin: 0;
      display: flex;
      align-items: center;
    }

    .page-title i {
      margin-right: 0.75rem;
      color: var(--primary);
      font-size: 1.75rem;
    }

    /* Suggestions Dropdown */
    .suggestions-dropdown {
      position: absolute;
      top: 100%;
      left: 0;
      width: 100%;
      z-index: 999;
      background: white;
      border-radius: 0.5rem;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
      max-height: 300px;
      overflow-y: auto;
      display: none;
      animation: fadeInDown 0.3s ease;
    }

    .suggestions-item {
      padding: 0.75rem 1rem;
      cursor: pointer;
      border-bottom: 1px solid #f1f3f5;
      transition: all 0.2s;
    }

    .suggestions-item:hover {
      background-color: var(--gray-light);
    }

    .suggestions-item:last-child {
      border-bottom: none;
    }

    /* Mobile Header */
    .mobile-header {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      background-color: white;
      z-index: 1000;
      padding: 12px 16px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .mobile-header-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .mobile-header-title {
      font-size: 1.25rem;
      font-weight: 600;
      margin: 0 0 0 8px;
      color: var(--dark);
      font-family: "Inter", sans-serif;
    }

    .mobile-menu-btn {
      background: transparent;
      border: none;
      color: var(--dark);
      font-size: 1.5rem;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .mobile-header-actions {
      display: flex;
      align-items: center;
    }

    .mobile-header-icon {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--gray);
      font-size: 1.25rem;
      margin-left: 0.5rem;
      cursor: pointer;
      transition: all 0.2s;
      position: relative;
    }

    .mobile-header-icon .badge {
      position: absolute;
      top: -5px;
      right: -5px;
      font-size: 0.65rem;
      padding: 0.25rem 0.4rem;
    }

    .mobile-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background-color: var(--primary);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 1rem;
    }

    /* Mobile Search */
    .mobile-search {
      margin-top: 8px;
      position: relative;
    }

    .mobile-search input {
      width: 100%;
      padding: 10px 16px 10px 40px;
      border-radius: 50px;
      border: none;
      background-color: var(--gray-light);
      font-size: 0.95rem;
      font-family: "Inter", sans-serif;
    }

    .mobile-search i {
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--gray);
    }

    /* Mobile Stats Cards */
    .mobile-stats-card {
      background-color: white;
      border-radius: 16px;
      padding: 24px;
      margin: 0 16px 16px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      text-align: center;
    }

    .mobile-stats-icon {
      width: 64px;
      height: 64px;
      background-color: var(--primary-light);
      color: var(--primary);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.75rem;
      margin: 0 auto 1rem;
    }

    .mobile-stats-value {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--dark);
      margin: 0;
      line-height: 1;
      font-family: "Inter", sans-serif;
    }

    .mobile-stats-label {
      font-size: 0.95rem;
      color: var(--gray);
      margin: 8px 0 0;
      font-family: "Inter", sans-serif;
    }

    /* Mobile Carousel Indicators */
    .mobile-carousel-indicators {
      display: none;
      justify-content: center;
      gap: 6px;
      margin: 16px 0 24px;
    }

    .mobile-carousel-indicator {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background-color: var(--gray-light);
      transition: all 0.3s;
    }

    .mobile-carousel-indicator.active {
      width: 24px;
      border-radius: 4px;
      background-color: var(--primary);
    }

    /* Mobile Action Buttons */
    .mobile-actions {
      display: none;
      margin: 0 16px 24px;
    }

    .mobile-actions-title {
      font-size: 1.25rem;
      font-weight: 600;
      margin-bottom: 16px;
      color: var(--dark);
      font-family: "Inter", sans-serif;
    }

    /* Mobile Product List */
    .mobile-product-list {
      margin: 0 16px;
    }

    .mobile-product-item {
      background-color: white;
      border-radius: 16px;
      padding: 16px;
      margin-bottom: 12px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: center;
      position: relative;
    }

    .mobile-product-number {
      position: absolute;
      top: 12px;
      left: 12px;
      width: 24px;
      height: 24px;
      background-color: var(--primary-light);
      color: var(--primary);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      font-weight: 600;
      font-family: "Inter", sans-serif;
    }

    .mobile-product-image {
      width: 64px;
      height: 64px;
      background-color: var(--gray-light);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 16px;
      overflow: hidden;
    }

    .mobile-product-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .mobile-product-image i {
      font-size: 1.5rem;
      color: var(--gray);
    }

    .mobile-product-details {
      flex: 1;
    }

    .mobile-product-name {
      font-size: 1rem;
      font-weight: 600;
      margin: 0 0 4px;
      color: var(--dark);
      font-family: "Inter", sans-serif;
    }

    .mobile-product-price {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--primary);
      margin: 0;
      font-family: "Inter", sans-serif;
    }

    .mobile-product-stock {
      font-size: 0.8rem;
      color: var(--gray);
      margin: 4px 0 0;
      font-family: "Inter", sans-serif;
    }

    .mobile-product-actions {
      display: flex;
      gap: 8px;
    }

    .mobile-product-action-btn {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--gray-light);
      color: var(--gray-dark);
      border: none;
      font-size: 1rem;
      transition: all 0.2s;
    }

    .mobile-product-action-btn:hover {
      background-color: var(--primary-light);
      color: var(--primary);
    }

    /* Floating Action Button */
    .fab {
      position: fixed;
      bottom: 80px;
      right: 20px;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: var(--primary);
      color: white;
      display: none;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
      transition: all 0.3s;
      z-index: 99;
    }

    .fab:hover,
    .fab:focus {
      transform: scale(1.1);
      background: var(--primary-dark);
      color: white;
      box-shadow: 0 6px 16px rgba(67, 97, 238, 0.4);
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

    @keyframes slideInLeft {
      from {
        transform: translateX(-100%);
      }

      to {
        transform: translateX(0);
      }
    }

    @keyframes slideInRight {
      from {
        transform: translateX(100%);
      }

      to {
        transform: translateX(0);
      }
    }

    @keyframes bounce {

      0%,
      20%,
      50%,
      80%,
      100% {
        transform: translateY(0);
      }

      40% {
        transform: translateY(-20px);
      }

      60% {
        transform: translateY(-10px);
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

    .slide-in-left {
      animation: slideInLeft 0.5s ease-in-out;
    }

    .slide-in-right {
      animation: slideInRight 0.5s ease-in-out;
    }

    .bounce {
      animation: bounce 1s;
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

    /* Utilities */
    .cursor-pointer {
      cursor: pointer;
    }

    .text-truncate-2 {
      display: -webkit-box;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    /* New Animations & Effects */
    .hover-scale {
      transition: transform 0.3s ease;
    }

    .hover-scale:hover {
      transform: scale(1.05);
    }

    .hover-shadow {
      transition: box-shadow 0.3s ease;
    }

    .hover-shadow:hover {
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }

    .hover-lift {
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .hover-lift:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    /* Glassmorphism Effect */
    .glass {
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.1);
    }

    /* Responsive Adjustments */
    @media (min-width: 992px) {

      .mobile-drawer,
      .drawer-overlay,
      .mobile-bottom-nav,
      .bottom-nav {
        display: none;
      }
    }

    @media (max-width: 992px) {
      :root {
        --topbar-height: 60px;
      }

      .drawer-overlay.show {
        z-index: 1999;
        /* opcional, se usar overlay */
      }

      .topbar {
        left: 0;
      }

      .main-content {
        margin-left: 0;
        width: 100%;
        padding-top: calc(var(--topbar-height) + 1rem);
        padding-bottom: calc(var(--bottom-nav-height) + 1rem);
      }

      .btn-float {
        display: none;
      }

      /* Show mobile components */
      .bottom-nav {
        display: flex;
      }

      .fab {
        display: flex;
      }
    }

    /* Dark Mode Fixes */
    body.dark-mode {
      --body-bg: #121212;
      --dark: #ffffff;
      /* Changed from #e0e0e0 to white for better contrast */
      --gray: #adb5bd;
      --gray-dark: #6c757d;
      --gray-light: #2d2d2d;

      --bg-main: #000000;
      --bg-sidebar: #333333;
      --bg-card: #1e1e1e;
      --text-primary: #e0e0e0;
      --text-secondary: #aaaaaa;
      --text-sidebar: #ffffff;
      --border-color: #444444;
    }

    body.dark-mode {
      background-color: #000000;
    }

    body.dark-mode .card,
    body.dark-mode .stats-card,
    body.dark-mode .topbar,
    body.dark-mode .suggestions-dropdown,
    body.dark-mode .product-card {
      background-color: #1e1e1e;
      color: var(--dark);
    }

    body.dark-mode .table {
      color: red;
    }

    body.dark-mode .table thead th {
      background-color: #1e1e1e;
      color: #ffffff;
    }

    body.dark-mode .table tbody td {
      border-color: #2d2d2d;
      background-color: #1e1e1e;
      color: #ffffff;
    }

    body.dark-mode .table-striped tbody tr:nth-of-type(odd) {
      background-color: #1e1e1e;
    }

    body.dark-mode .form-control,
    body.dark-mode .input-group-text {
      background-color: #2d2d2d;
      border-color: #2d2d2d;
      color: var(--dark);
    }

    body.dark-mode .topbar-search input {
      background-color: #2d2d2d;
      color: var(--dark);
    }

    body.dark-mode .topbar-search input::placeholder {
      color: var(--light);
    }

    body.dark-mode .sidebar {
      background-color: var(--bg-sidebar);
    }

    body.dark-mode .page-title,
    body.dark-mode .card-header,
    body.dark-mode .stats-card-value,
    body.dark-mode .product-card-title {
      color: var(--dark);
    }

    body.dark-mode .card-header {
      background-color: var(--gray-light);
    }

    body.dark-mode .text-muted {
      color: #adb5bd !important;
    }

    body.dark-mode .mobile-bottom-nav,
    body.dark-mode .bottom-nav,
    body.dark-mode .mobile-header {
      background-color: #1e1e1e;
    }

    body.dark-mode .mobile-nav-item,
    body.dark-mode .bottom-nav-item {
      color: #adb5bd;
    }

    body.dark-mode .toggle-sidebar::before {
      background-color: var(--bg-sidebar)
    }

    body.dark-mode .glass {
      background: rgba(30, 30, 30, 0.7);
    }


    /* Toggle switch for dark mode */
    .dark-mode-toggle {
      position: fixed;
      top: 1rem;
      right: 1rem;
      z-index: 1001;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #f8f9fa;
      color: #212529;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    body.dark-mode .dark-mode-toggle {
      background-color: #343a40;
      color: #f8f9fa;
    }

    body.dark-mode .mobile-stats-card {
      background-color: #1e1e1e;
      color: #ffffff;
    }

    body.dark-mode .mobile-product-item {
      background-color: #1e1e1e;
      color: #ffffff;
    }

    /* Mobile-specific styles (smaller screens) */
    @media (max-width: 768px) {
      body {
        padding-bottom: var(--bottom-nav-height);
        padding-top: var(--mobile-header-height);
        font-family: "Inter", sans-serif;
      }

      :root {
        --topbar-height: 0;
        /* Hide desktop topbar */
      }

      .topbar {
        display: none;
      }

      .mobile-header {
        display: block;
      }

      .mobile-carousel-indicators {
        display: flex;
      }

      .mobile-actions {
        display: block;
      }

      .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
        margin: 16px;
      }

      .page-title {
        font-size: 1.25rem;
        font-family: "Inter", sans-serif;
      }

      .breadcrumb {
        display: none;
      }

      /* Adjust card styles for mobile */
      .card {
        margin: 0 16px 16px;
        border-radius: 16px;
      }

      .card-header {
        padding: 16px;
        border-radius: 16px 16px 0 0;
      }

      .card-body {
        padding: 16px;
      }

      /* Adjust table for mobile */
      .table-responsive {
        border-radius: 16px;
        overflow: hidden;
        margin: 0 16px;
      }

      /* Adjust stats cards for mobile */
      .stats-card {
        margin: 0 16px 16px;
        padding: 16px;
        border-radius: 16px;
      }

      /* Form adjustments */
      .form-label {
        margin-bottom: 8px;
        font-family: "Inter", sans-serif;
      }

      .form-control {
        padding: 12px 16px;
        border-radius: 12px;
        font-family: "Inter", sans-serif;
      }

      /* Button adjustments */
      .btn {
        padding: 12px 20px;
        border-radius: 12px;
        font-family: "Inter", sans-serif;
      }

      /* Alert adjustments */
      .alert {
        margin: 16px;
        border-radius: 16px;
      }
    }

    #msgmsg {
      z-index: 9999;
    }
  </style>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <!-- AOS - Animate On Scroll -->
  <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
</head>

<body>



  <!-- Loader -->
  <div class="loader" id="pageLoader">
    <div class="spinner"></div>
  </div>

  <!-- Mobile Header (New) -->
  <div class="mobile-header">
    <div class="mobile-header-top">
      <div class="d-flex align-items-center">
        <h1 class="mobile-header-title">Gestão</h1>
      </div>
      <div class="mobile-header-actions">
        <div class="mobile-header-icon" id="mobileDarkModeToggle" title="Alternar Modo Escuro">

        </div>
      </div>
    </div>
    <div class="mobile-search">
      <input type="text" class="form-control" id="mobileSearchInput" placeholder="Buscar produtos, variações...">
      <i class="bi bi-search"></i>
    </div>
  </div>



  <div class="app-container" id="appContainer">
    <!-- Sidebar (Desktop) -->
    <?php include_once '../../frontend/includes/sidebar.php' ?>

    <!-- Main Content -->
    <div class="main-content mt-3">
      <!-- Topbar -->
      <div class="topbar">
        <div class="d-flex align-items-center gap-3">
          <button class="topbar-toggle me-3 d-none" id="sidebarToggle">
            <i class="bi bi-list"></i>
          </button>

          <form id="searchForm" method="GET" action="teste.php" class="d-flex">
            <input type="text" name="filter" id="filterInput"
              value="<?php echo htmlspecialchars($_GET['filter'] ?? ''); ?>" class="form-control" placeholder="Buscar…"
              autocomplete="off">
            <button class="btn btn-primary ms-2" type="submit"><i class="bi bi-search"></i></button>
          </form>



          <div class="d-flex flex-row justify-content-center align-items-center gap-3">
            <div class="topbar-actions" style="z-index: 9999;">
              <?php require_once __DIR__ . '/../includes/notificacoes.php'; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Dark-Mode -->
      <?php include_once '../../frontend/includes/darkmode.php' ?>

      <!-- Mensagens -->
      <?php if ($msg):
        $icon = 'info-circle';
        switch ($msg_type) {
          case 'success':
            $icon = 'check-circle';
            break;
          case 'danger':
            $icon = 'exclamation-circle';
            break;
          case 'warning':
            $icon = 'exclamation-triangle';
            break;
        }
      ?>
        <div id='msgmsg' class="alert alert-<?php echo $msg_type; ?> fade-in-up">
          <i class="bi bi-<?php echo $icon; ?>"></i>
          <span><?php echo $msg; ?></span>
        </div>
      <?php endif; ?>

      <!-- Breadcrumb -->
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="teste.php"><i class="bi bi-house-door me-1"></i>Home</a></li>
          <?php switch ($action):
            case 'create': ?>
              <li class="breadcrumb-item"><a href="teste.php">Produtos</a></li>
              <li class="breadcrumb-item active" aria-current="page">Novo Produto</li>
            <?php break;
            case 'edit': ?>
              <li class="breadcrumb-item"><a href="teste.php">Produtos</a></li>
              <li class="breadcrumb-item active" aria-current="page">Editar Produto</li>
            <?php break;
            case 'variacoes':
              $produto = $produtoModel->getById($id_produto); ?>
              <li class="breadcrumb-item"><a href="teste.php">Produtos</a></li>
              <li class="breadcrumb-item active" aria-current="page">Variações:
                <?php echo $produto ? $produto['nome'] : 'Produto'; ?>
              </li>
            <?php break;
            default: ?>
              <li class="breadcrumb-item active" aria-current="page">Produtos</li>
          <?php endswitch; ?>
        </ol>
      </nav>


      <?php switch ($action):
        case 'create': ?>



          <div class="page-header" data-aos="fade-down">
            <h1 class="page-title"><i class="bi bi-plus-circle"></i> Novo Produto</h1>
          </div>

          <div class="card fade-in-up" data-aos="fade-up">
            <div class="card-header">
              <i class="bi bi-pencil-square"></i> Cadastro de Produto
            </div>
            <div class="card-body">
              <form action="teste.php?action=store" method="POST" enctype="multipart/form-data" class="needs-validation"
                novalidate>

                <!-- ===== Campos do Produto ===== -->
                <div class="row mb-3">
                  <div class="col-md-8">
                    <label for="nome" class="form-label">Nome do Produto</label>
                    <input type="text" class="form-control" name="nome" id="nome" required>
                    <div class="invalid-feedback">Por favor, informe o nome.</div>
                  </div>
                  <div class="col-md-4">
                    <label for="codigo_barras" class="form-label">Código de Barras</label>
                    <input type="text" class="form-control" name="codigo_barras" id="codigo_barras" readonly>
                  </div>
                </div>

                <div class="mb-3">
                  <label for="descricao" class="form-label">Descrição</label>
                  <textarea class="form-control" name="descricao" id="descricao" rows="3"></textarea>
                </div>

                <div class="mb-3">
                  <label for="foto" class="form-label">Foto (Upload)</label>
                  <input type="file" class="form-control" name="foto" id="foto" accept="image/*">
                </div>

                <div class="row mb-3">
                  <div class="col-md-6">
                    <label for="preco_custo" class="form-label">Preço de Custo</label>
                    <div class="input-group">
                      <span class="input-group-text">R$</span>
                      <input type="number" step="0.01" class="form-control" name="preco_custo" id="preco_custo">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label for="preco_venda" class="form-label">Preço de Venda</label>
                    <div class="input-group">
                      <span class="input-group-text">R$</span>
                      <input type="number" step="0.01" class="form-control" name="preco_venda" id="preco_venda">
                    </div>
                  </div>
                </div>

                <div class="row mb-3">
                  <div class="col-md-4">
                    <label for="estoque_min" class="form-label">Estoque Mínimo</label>
                    <input type="number" class="form-control" name="estoque_min" id="estoque_min">
                  </div>
                  <div class="col-md-4">
                    <label for="estoque_max" class="form-label">Estoque Máximo</label>
                    <input type="number" class="form-control" name="estoque_max" id="estoque_max">
                  </div>
                  <div class="col-md-4">
                    <label for="estoque_atual" class="form-label">Estoque Atual</label>
                    <input type="number" class="form-control" name="estoque_atual" id="estoque_atual">
                  </div>
                </div>

                <div class="mb-3">
                  <label for="localizacao_estoque" class="form-label">Localização no Estoque</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                    <input type="text" class="form-control" name="localizacao_estoque" id="localizacao_estoque">
                  </div>
                </div>

                <!-- ===== Seção de Variações ===== -->
                <div class="card mt-4">
                  <div class="card-header"><i class="bi bi-tags"></i> Variações</div>
                  <div class="card-body p-0">
                    <table class="table mb-0" id="variationsTable">
                      <thead>
                        <tr>
                          <th>Cor</th>
                          <th>Tamanho</th>
                          <th>SKU</th>
                          <th>Preço de Venda</th>
                          <th>Estoque Inicial</th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr class="variation-row">
                          <td><input type="text" name="var_cor[]" class="form-control"></td>
                          <td><input type="text" name="var_tamanho[]" class="form-control"></td>
                          <td><input type="text" name="var_sku[]" class="form-control" readonly></td>
                          <td>
                            <div class="input-group">
                              <span class="input-group-text">R$</span>
                              <input type="number" step="0.01" name="var_preco_venda[]" class="form-control">
                            </div>
                          </td>
                          <td><input type="number" name="var_estoque[]" class="form-control" min="0"></td>
                          <td>
                            <button type="button" class="btn btn-sm btn-danger remove-variation">
                              <i class="bi bi-trash"></i>
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                    <div class="p-3">
                      <button type="button" id="addVariation" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-plus-circle"></i> Adicionar variação
                      </button>
                    </div>
                  </div>
                </div>

                <!-- ===== Botões de Ação ===== -->
                <div class="d-flex justify-content-end mt-4">
                  <a href="teste.php" class="btn btn-outline-secondary me-2 hover-lift">
                    <i class="bi bi-x-circle"></i> Cancelar
                  </a>
                  <button type="submit" class="btn btn-success hover-lift">
                    <i class="bi bi-check-circle"></i> Salvar Produto
                  </button>
                </div>
              </form>
            </div>
          </div>


          <!-- ===== JavaScript para duplicar/remover variações ===== -->
          <script>
            document.getElementById('addVariation').addEventListener('click', function() {
              const tbody = document.querySelector('#variationsTable tbody');
              const newRow = tbody.querySelector('.variation-row').cloneNode(true);
              newRow.querySelectorAll('input').forEach(i => i.value = '');
              tbody.appendChild(newRow);
            });

            document.getElementById('variationsTable').addEventListener('click', function(e) {
              if (e.target.closest('.remove-variation')) {
                const rows = this.querySelectorAll('tbody tr');
                if (rows.length > 1) {
                  e.target.closest('tr').remove();
                }
              }
            });
          </script>
        <?php break;

        case 'edit':
          // Buscar dados do produto para edição
          $produto = $produtoModel->getById($id_produto);
          if (!$produto) {
            echo '<div class="alert alert-danger fade-in-up"><i class="bi bi-exclamation-circle"></i>Produto não encontrado!</div>';
            echo '<a href="teste.php" class="btn btn-primary hover-lift"><i class="bi bi-arrow-left"></i> Voltar para a lista</a>';
            break;
          }
        ?>
          <!-- Formulário de Edição de Produto -->
          <div class="page-header" data-aos="fade-down">
            <h1 class="page-title"><i class="bi bi-pencil-square"></i>Editar Produto</h1>
          </div>

          <div class="card fade-in-up" data-aos="fade-up">
            <div class="card-header">
              <i class="bi bi-pencil"></i> Edição de Produto #<?php echo $id_produto; ?>
            </div>
            
            <div class="card-body">
              <form action="teste.php?action=update&id=<?php echo $id_produto; ?>" method="POST"
                enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="row mb-3">
                  <div class="col-md-8">
                    <label for="nome" class="form-label">Nome do Produto</label>
                    <input type="text" class="form-control" name="nome" id="nome" value="<?php echo $produto['nome']; ?>"
                      required>
                    <div class="invalid-feedback">Por favor, informe o nome do produto.</div>
                  </div>
                  <div class="col-md-4">
                    <label for="codigo_barras" class="form-label">Código de Barras</label>
                    <input type="text" class="form-control" name="codigo_barras" id="codigo_barras"
                      value="<?php echo $produto['codigo_barras']; ?>">
                  </div>
                </div>

                <div class="mb-3">
                  <label for="descricao" class="form-label">Descrição</label>
                  <textarea class="form-control" name="descricao" id="descricao"
                    rows="3"><?php echo $produto['descricao']; ?></textarea>
                </div>

                <!-- Input de imagem atualizado para edição -->
                <div class="mb-3">
                  <label for="foto" class="form-label">Foto (Upload)</label>
                  <div class="input-group">
                    <input type="file" class="form-control" name="foto" id="foto" accept="image/*">
                  </div>
                  <!-- Campo hidden para manter o valor atual caso não seja feita nova seleção -->
                  <input type="hidden" name="foto_atual" value="<?php echo $produto['foto']; ?>">
                  <?php if (!empty($produto['foto'])): ?>
                    <div class="mt-2">
                      <img src="<?php echo $produto['foto']; ?>" alt="Imagem do Produto" class="img-thumbnail hover-shadow"
                        style="max-height: 100px;">
                    </div>
                  <?php endif; ?>
                </div>

                <div class="row mb-3">
                  <div class="col-md-6">
                    <label for="preco_custo" class="form-label">Preço de Custo</label>
                    <div class="input-group">
                      <span class="input-group-text">R$</span>
                      <input type="number" step="0.01" class="form-control" name="preco_custo" id="preco_custo"
                        value="<?php echo $produto['preco_custo']; ?>">
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label for="preco_venda" class="form-label">Preço de Venda</label>
                    <div class="input-group">
                      <span class="input-group-text">R$</span>
                      <input type="number" step="0.01" class="form-control" name="preco_venda" id="preco_venda"
                        value="<?php echo $produto['preco_venda']; ?>">
                    </div>
                  </div>
                </div>

                <div class="row mb-3">
                  <div class="col-md-4">
                    <label for="estoque_min" class="form-label">Estoque Mínimo</label>
                    <input type="number" class="form-control" name="estoque_min" id="estoque_min"
                      value="<?php echo $produto['estoque_min']; ?>">
                  </div>
                  <div class="col-md-4">
                    <label for="estoque_max" class="form-label">Estoque Máximo</label>
                    <input type="number" class="form-control" name="estoque_max" id="estoque_max"
                      value="<?php echo $produto['estoque_max']; ?>">
                  </div>
                  <div class="col-md-4">
                    <label for="estoque_atual" class="form-label">Estoque Atual</label>
                    <input type="number" class="form-control" name="estoque_atual" id="estoque_atual"
                      value="<?php echo $produto['estoque_atual'] ?? 0; ?>">
                  </div>
                </div>

                <div class="mb-3">
                  <label for="localizacao_estoque" class="form-label">Localização no Estoque</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                    <input type="text" class="form-control" name="localizacao_estoque" id="localizacao_estoque"
                      value="<?php echo $produto['localizacao_estoque']; ?>">
                  </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                  <a href="teste.php" class="btn btn-outline-secondary me-2 hover-lift">
                    <i class="bi bi-x-circle"></i> Cancelar
                  </a>
                  <button type="submit" class="btn btn-primary hover-lift">
                    <i class="bi bi-check-circle"></i> Atualizar Produto
                  </button>
                </div>
              </form>
            </div>
          </div>
        <?php
          break;

        case 'variacoes':
          // Buscar dados do produto
          $produto = $produtoModel->getById($id_produto);
          if (!$produto) {
            echo '<div class="alert alert-danger fade-in-up"><i class="bi bi-exclamation-circle"></i>Produto não encontrado!</div>';
            echo '<a href="teste.php" class="btn btn-primary hover-lift"><i class="bi bi-arrow-left"></i> Voltar para a lista</a>';
            break;
          }

          // Buscar todas as variações do produto
          $variacoes = $variacaoModel->getAllByProduto($id_produto);
        ?>

          <div class="page-header" data-aos="fade-down">
            <h1 class="page-title"><i class="bi bi-tags"></i>Variações do Produto</h1>
            <a href="teste.php" class="btn btn-outline-primary hover-lift">
              <i class="bi bi-arrow-left"></i> Voltar para Produtos
            </a>
          </div>

          <div class="card mb-4 fade-in-up glass" data-aos="fade-up">
            <div class="card-header bg-primary text-white">
              <i class="bi bi-info-circle"></i> Informações do Produto
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-2 col-4">
                  <div class="mb-3 mb-md-0 text-center">
                    <?php if (!empty($produto['foto'])): ?>
                      <img src="<?php echo $produto['foto']; ?>" alt="<?php echo $produto['nome']; ?>"
                        class="img-fluid rounded hover-shadow" style="max-height: 150px;">
                    <?php else: ?>
                      <div class="bg-light rounded d-flex align-items-center justify-content-center hover-shadow"
                        style="height: 150px; width: 100%;">
                        <i class="bi bi-image text-secondary" style="font-size: 3rem;"></i>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="col-md-10 col-8">
                  <h4 class="mb-2"><?php echo $produto['nome']; ?></h4>
                  <p class="text-muted mb-3"><?php echo $produto['descricao'] ?: 'Sem descrição'; ?></p>

                  <div class="row">
                    <div class="col-md-3 col-6 mb-2" data-aos="fade-up" data-aos-delay="100">
                      <div class="d-flex align-items-center hover-scale">
                        <i class="bi bi-tag text-primary me-2"></i>
                        <div>
                          <small class="text-muted d-block">Código</small>
                          <strong>#<?php echo $produto['id_produto']; ?></strong>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3 col-6 mb-2" data-aos="fade-up" data-aos-delay="200">
                      <div class="d-flex align-items-center hover-scale">
                        <i class="bi bi-currency-dollar text-success me-2"></i>
                        <div>
                          <small class="text-muted d-block">Preço de Venda</small>
                          <strong>R$
                            <?php echo number_format($produto['preco_venda'], 2, ',', '.'); ?></strong>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3 col-6 mb-2" data-aos="fade-up" data-aos-delay="300">
                      <div class="d-flex align-items-center hover-scale">
                        <i class="bi bi-box text-warning me-2"></i>
                        <div>
                          <small class="text-muted d-block">Estoque Min/Max</small>
                          <strong><?php echo $produto['estoque_min']; ?> /
                            <?php echo $produto['estoque_max']; ?></strong>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3 col-6 mb-2" data-aos="fade-up" data-aos-delay="400">
                      <div class="d-flex align-items-center hover-scale">
                        <i class="bi bi-upc text-info me-2"></i>
                        <div>
                          <small class="text-muted d-block">Código de Barras</small>
                          <strong><?php echo $produto['codigo_barras'] ?: 'N/A'; ?></strong>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <input type="hidden" id="produto_nome" value="<?php echo addslashes($produto['nome'] ?? ''); ?>">
          </div>

          <!-- Modal Editar Variação -->
          <!-- Botão flutuante + container geral -->
          <div class="row" id="variacoes-container">
            <!-- Coluna: Lista de variações -->
            <div class="col-lg-8 order-lg-1 order-2 mt-3 mt-lg-0">
              <div class="card fade-in-up" data-aos="fade-right">
                <div class="card-header">
                  <i class="bi bi-list-check"></i> Variações Cadastradas
                  <span class="badge bg-primary ms-2"><?php echo count($variacoes); ?></span>
                </div>
                <div class="card-body p-0">
                  <table class="table table-striped table-hover mb-0">
                    <thead>
                      <tr>
                        <th>ID</th><th>Cor</th><th>Tamanho</th><th>SKU</th><th>Preço</th><th>Estoque</th><th class="text-center">Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($variacoes as $var): ?>
                        <?php
                          // Monta classe estoque
                          $cls = 'success';
                          if ($var['estoque_atual'] <= $produto['estoque_min']) $cls = 'danger';
                          elseif ($var['estoque_atual'] <= $produto['estoque_min']*2) $cls = 'warning';
                        ?>
                        <tr>
                          <td><?= $var['id_variacao'] ?></td>
                          <td>
                            <?php if ($var['cor']): ?>
                              <span class="badge bg-light text-dark"><i class="bi bi-palette me-1"></i><?= $var['cor'] ?></span>
                            <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                          </td>
                          <td>
                            <?php if ($var['tamanho']): ?>
                              <span class="badge bg-light text-dark"><i class="bi bi-rulers me-1"></i><?= $var['tamanho'] ?></span>
                            <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                          </td>
                          <td><?= $var['sku']?:'-' ?></td>
                          <td class="fw-bold text-primary">R$ <?= number_format($var['preco_venda'],2,',','.') ?></td>
                          <td><span class="badge bg-<?= $cls ?>"><?= $var['estoque_atual'] ?> un</span></td>
                          <td class="text-center">
                            <!-- Editar -->
                            <button
                              class="btn btn-sm btn-warning me-1"
                              data-action="edit"
                              data-id="<?= $var['id_variacao'] ?>">
                              <i class="bi bi-pencil"></i>
                            </button>
                            <!-- Excluir -->
                            <a
                              href="teste.php?action=delete_variacao&id=<?= $id_produto ?>&id_variacao=<?= $var['id_variacao'] ?>"
                              class="btn btn-sm btn-danger me-1"
                              onclick="return confirm('Deseja excluir esta variação?')">
                              <i class="bi bi-trash"></i>
                            </a>
                            <!-- Adicionar Estoque -->
                            <button
                              class="btn btn-sm btn-success"
                              data-bs-toggle="modal"
                              data-bs-target="#addStockVarModal"
                              data-id="<?= $var['id_variacao'] ?>">
                              <i class="bi bi-plus-circle"></i>
                            </button>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Coluna: Botão Nova e Editar (ao lado) -->
            <div class="col-lg-4 order-lg-2 order-1">
              <div class="card fade-in-up" data-aos="fade-left">
                <div class="card-header" id="header-variacao">
                  <i class="bi bi-plus-circle"></i> Nova Variação
                </div>
                <div class="card-body">
                  <form
                    id="form-variacao"
                    method="POST"
                    action="teste.php?action=store_variacao&id=<?php echo $id_produto; ?>"
                    class="needs-validation"
                    novalidate>
                    <input type="hidden" name="id_variacao" id="var_id" value="">
                    <!-- Cor -->
                    <div class="mb-3">
                      <label class="form-label">Cor</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-palette"></i></span>
                        <input
                          type="text"
                          name="cor"
                          id="var_cor"
                          class="form-control"
                          value="">
                      </div>
                    </div>
                    <!-- Tamanho -->
                    <div class="mb-3">
                      <label class="form-label">Tamanho</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-rulers"></i></span>
                        <input
                          type="text"
                          name="tamanho"
                          id="var_tamanho"
                          class="form-control"
                          value="">
                      </div>
                    </div>
                    <!-- SKU -->
                    <div class="mb-3">
                      <label class="form-label">SKU</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                        <input
                          type="text"
                          name="sku"
                          id="var_sku"
                          class="form-control"
                          readonly>
                      </div>
                    </div>
                    <!-- Preço -->
                    <div class="mb-3">
                      <label class="form-label">Preço de Venda</label>
                      <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input
                          type="number"
                          step="0.01"
                          name="preco_venda"
                          id="var_preco"
                          class="form-control"
                          value="">
                      </div>
                    </div>
                    <!-- Estoque -->
                    <div class="mb-3">
                      <label class="form-label">Estoque Atual</label>
                      <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-boxes"></i></span>
                        <input
                          type="number"
                          name="estoque_atual"
                          id="var_estoque"
                          class="form-control"
                          value="">
                      </div>
                    </div>

                    <div class="d-grid gap-2">
                      <button
                        type="submit"
                        id="btn-submit-variacao"
                        class="btn btn-success hover-lift">
                        <i class="bi bi-plus-circle"></i> Adicionar Variação
                      </button>
                      <button
                        type="button"
                        id="btn-cancel-edit"
                        class="btn btn-outline-secondary hover-lift d-none">
                        <i class="bi bi-x-circle"></i> Cancelar
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        <?php
          break;





        default:

          $filter = trim($_GET['filter'] ?? '');

          // Para versão ATUALIZADA 
          if ($filter !== '') {
            // BUSCA SEM PAGINAÇÃO
            $produtos = $produtoModel->searchMainPage($filter);
            $totalItems = count($produtos);
            $totalPages = 1;
            $page = 1;
            // NÃO definir limit nem offset aqui
          } else {
            // PAGINAÇÃO NORMAL
            $limit = 10;
            $page = isset($_GET['page']) && is_numeric($_GET['page'])
              ? (int) $_GET['page']
              : 1;
            $offset = ($page - 1) * $limit;
            $totalItems = $produtoModel->countAll();
            $totalPages = (int) ceil($totalItems / $limit);
            $produtos = $produtoModel->getPaged($limit, $offset);
          }
        ?>
          <div class="page-header" data-aos="fade-down">
            <h1 class="page-title"><i class="bi bi-box"></i>Produtos</h1>



            <a href="teste.php?action=create" class="btn btn-primary hover-lift">
              <i class="bi bi-plus-lg"></i> Novo Produto
            </a>
          </div>

          <!-- Mobile Stats Cards (New) -->
          <div class="d-md-none">
            <div class="mobile-stats-card">
              <div class="mobile-stats-icon">
                <i class="bi bi-box-seam"></i>
              </div>
              <div class="mobile-stats-value"><?php echo count($produtos); ?></div>
              <div class="mobile-stats-label">Total de Produtos</div>
            </div>

            <!-- Mobile Carousel Indicators -->


            <!-- Mobile Actions -->
            <div class="mobile-actions">
              <h2 class="mobile-actions-title">Ações Rápidas</h2>
              <div class="row g-2">
                <div class="col-6">
                  <a href="teste.php?action=create" class="btn btn-primary w-100 py-3">
                    <i class="bi bi-plus-lg d-block mb-1" style="font-size: 1.5rem;"></i>
                    Novo Produto
                  </a>
                </div>
                <div class="col-6">
                  <a href="../../teste.php" class="btn btn-outline-primary w-100 py-3">
                    <i class="bi bi-file-earmark-bar-graph d-block mb-1" style="font-size: 1.5rem;"></i>
                    Relatórios
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Desktop Stats Cards -->
          <div class="row d-none d-md-flex">
            <div class="col-md-6" data-aos="fade-right" data-aos-delay="100">
              <div class="stats-card primary hover-lift">
                <div class="stats-card-icon">
                  <i class="bi bi-box-seam"></i>
                </div>
                <div class="stats-card-content">
                  <div class="stats-card-label">Total de Produtos</div>
                  <h2 class="stats-card-value"><?php echo count($produtos); ?></h2>
                </div>
                <i class="bi bi-box-seam stats-card-bg"></i>
              </div>
            </div>
            <div class="col-md-6" data-aos="fade-left" data-aos-delay="200">
              <div class="stats-card danger hover-lift">
                <div class="stats-card-icon">
                  <i class="bi bi-currency-dollar"></i>
                </div>
                <div class="stats-card-content">
                  <div class="stats-card-label">
                    Valor em Estoque (Preço de Venda x Quantidade em Estoque)
                  </div>
                  <h2 class="stats-card-value">
                    <span id="stockValue">•••••••••</span>
                    <?php if ($type_session === 'admin'): ?>
                      <button id="btnVerValor" class="btn btn-sm btn-outline-light ms-2">
                        Ver Valor
                      </button>
                    <?php endif; ?>
                  </h2>
                </div>
                <i class="bi bi-currency-dollar stats-card-bg"></i>
              </div>
            </div>


            <!-- Lista de Produtos (Desktop) -->
            <div class="card fade-in-up d-none d-md-block" data-aos="fade-up" data-aos-delay="300">
              <div class="card-header">
                <i class="bi bi-list-check"></i> Produtos Cadastrados
                <span class="badge bg-primary ms-2"><?php echo count($produtos); ?></span>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table id="productsTable" class="table table-striped table-hover mb-0">
                    <thead>
                      <tr>
                        <th width="60">ID</th>
                        <th>Produto</th>
                        <th>Preço</th>
                        <th class="d-none d-md-table-cell">Código</th>
                        <th>Estoque</th>
                        <th class="text-center">Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($produtos)): ?>
                        <?php foreach ($produtos as $prod):
                          // Buscar variações para contar estoque total
                          $variacoes = $variacaoModel->getAllByProduto($prod['id_produto']);
                          if (count($variacoes) > 0) {
                            $estoque_total = 0;
                            foreach ($variacoes as $var) {
                              $estoque_total += $var['estoque_atual'];
                            }
                          } else {
                            $estoque_total = $prod['estoque_atual'];
                          }

                          $estoque_class = 'success';
                          if ($estoque_total <= $prod['estoque_min']) {
                            $estoque_class = 'danger';
                          } elseif ($estoque_total <= $prod['estoque_min'] * 2) {
                            $estoque_class = 'warning';
                          }
                        ?>
                          <tr class="fade-in-up" data-aos="fade-up" data-aos-delay="<?php echo 50 * $prod['id_produto']; ?>">
                            <td><?php echo $prod['id_produto']; ?></td>
                            <td>
                              <div class="d-flex align-items-center">
                                <?php if (!empty($prod['foto'])): ?>
                                  <img src="<?php echo $prod['foto']; ?>" alt="<?php echo $prod['nome']; ?>"
                                    class="me-2 hover-shadow"
                                    style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                  <div class="me-2 bg-light d-flex align-items-center justify-content-center"
                                    style="width: 40px; height: 40px; border-radius: 4px;">
                                    <i class="bi bi-image text-secondary"></i>
                                  </div>
                                <?php endif; ?>
                                <div>
                                  <div class="fw-medium"><?php echo $prod['nome']; ?></div>
                                  <small
                                    class="text-muted text-truncate-2 d-none d-md-block"><?php echo substr($prod['descricao'], 0, 50) . (strlen($prod['descricao']) > 50 ? '...' : ''); ?></small>
                                </div>
                              </div>
                            </td>
                            <td class="fw-bold text-primary">R$
                              <?php echo number_format($prod['preco_exibicao'], 2, ',', '.'); ?>
                            </td>
                            <td class="d-none d-md-table-cell"><?php echo $prod['codigo_barras'] ?: '-'; ?></td>
                            <td>
                              <span class="badge bg-<?php echo $estoque_class; ?> hover-scale">
                                <?php echo $estoque_total; ?> un
                              </span>
                            </td>
                            <td class="text-center">
                              <div class="d-inline-block text-center mx-1">
                                <small class="d-block text-muted mb-1">Editar</small>
                                <a href="teste.php?action=edit&id=<?php echo $prod['id_produto']; ?>"
                                  class="btn btn-sm btn-warning hover-lift">
                                  <i class="bi bi-pencil"></i>
                                </a>
                              </div>
                              <div class="d-inline-block text-center mx-1">
                                <small class="d-block text-muted mb-1">Variações</small>
                                <a href="teste.php?action=variacoes&id=<?php echo $prod['id_produto']; ?>"
                                  class="btn btn-sm btn-info hover-lift">
                                  <i class="bi bi-tags"></i>
                                </a>
                              </div>
                              <div class="d-inline-block text-center mx-1">
                                <small class="d-block text-muted mb-1">Excluir</small>
                                <a href="#" class="btn btn-sm btn-danger hover-lift"
                                  onclick="confirmDelete(<?php echo $prod['id_produto']; ?>, '<?php echo addslashes($prod['nome']); ?>')">
                                  <i class="bi bi-trash"></i>
                                </a>
                              </div>
                              <!-- Novo: Adicionar Estoque -->
                              <div class="d-inline-block text-center mx-1">
                                <small class="d-block text-muted mb-1">Add Estoque</small>
                                <button type="button" class="btn btn-sm btn-success hover-lift" data-bs-toggle="modal"
                                  data-bs-target="#addStockModal" data-id="<?= $prod['id_produto']; ?>">
                                  <i class="bi bi-plus-circle"></i>
                                </button>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="6" class="text-center py-5">
                            <i class="bi bi-box2 text-muted d-block mb-3" style="font-size: 3rem;"></i>
                            <p class="text-muted mb-3">Nenhum produto cadastrado.</p>
                            <a href="teste.php?action=create" class="btn btn-primary pulse hover-lift">
                              <i class="bi bi-plus-lg"></i> Cadastrar Produto
                            </a>
                          </td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <?php if ($totalPages > 1): ?>
              <nav aria-label="Paginação">
                <ul class="pagination justify-content-center">
                  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                      <a class="page-link" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
                    </li>
                  <?php endfor; ?>
                </ul>
              </nav>
            <?php endif; ?>


            <!-- Mobile Product List (New) -->
            <div class="d-md-none mobile-product-list">
              <?php if (!empty($produtos)): ?>
                <?php
                $counter = 0;
                foreach ($produtos as $prod):
                  $counter++;
                  // Buscar variações para contar estoque total
                  $variacoes = $variacaoModel->getAllByProduto($prod['id_produto']);
                  if (count($variacoes) > 0) {
                    $estoque_total = 0;
                    foreach ($variacoes as $var) {
                      $estoque_total += $var['estoque_atual'];
                    }
                  } else {
                    $estoque_total = $prod['estoque_atual'];
                  }

                  $estoque_class = 'success';
                  if ($estoque_total <= $prod['estoque_min']) {
                    $estoque_class = 'danger';
                  } elseif ($estoque_total <= $prod['estoque_min'] * 2) {
                    $estoque_class = 'warning';
                  }
                ?>
                  <div class="mobile-product-item">
                    <div class="mobile-product-number"><?php echo $counter; ?></div>
                    <div class="mobile-product-image">
                      <?php if (!empty($prod['foto'])): ?>
                        <img src="<?php echo $prod['foto']; ?>" alt="<?php echo $prod['nome']; ?>">
                      <?php else: ?>
                        <i class="bi bi-image"></i>
                      <?php endif; ?>
                    </div>
                    <div class="mobile-product-details">
                      <h3 class="mobile-product-name"><?php echo $prod['nome']; ?></h3>
                      <p class="mobile-product-price">R$
                        <?php echo number_format($prod['preco_venda'], 2, ',', '.'); ?>
                      </p>
                      <p class="mobile-product-stock">
                        <i class="bi bi-box" style="color: var(--<?php echo $estoque_class; ?>);"></i>
                        <?php echo $estoque_total; ?> unidades
                      </p>
                    </div>
                    <div class="mobile-product-actions">
                      <a href="teste.php?action=edit&id=<?php echo $prod['id_produto']; ?>" class="mobile-product-action-btn">
                        <i class="bi bi-pencil"></i>
                      </a>
                      <a href="teste.php?action=variacoes&id=<?php echo $prod['id_produto']; ?>"
                        class="mobile-product-action-btn">
                        <i class="bi bi-tags"></i>
                      </a>
                      <a href="#" class="mobile-product-action-btn"
                        onclick="confirmDelete(<?php echo $prod['id_produto']; ?>, '<?php echo addslashes($prod['nome']); ?>')">
                        <i class="bi bi-trash"></i>
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div
                  style="background-color: white; border-radius: 16px; padding: 24px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); text-align: center;">
                  <i class="bi bi-box2" style="font-size: 3rem; color: var(--gray); margin-bottom: 16px;"></i>
                  <p style="color: var(--gray); margin-bottom: 16px;">Nenhum produto cadastrado.</p>
                  <a href="teste.php?action=create" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i> Cadastrar Produto
                  </a>
                </div>
              <?php endif; ?>
            </div>
        <?php
          break;
      endswitch;
        ?>
          </div>
    </div>

    <!-- Mobile Menu -->
    <div class="bottom-nav d-block d-md-none d-flex justify-content-center align-items-center">
      <a href="teste.php"
        class="bottom-nav-item <?php echo in_array($action, ['list', 'create', 'edit', 'variacoes']) ? 'active' : ''; ?>">
        <i class="fas fa-box"></i>
        <span>Produtos</span>
      </a>
      <a href="../clientes/clientes.php" class="bottom-nav-item <?php echo ($action == 'clientes') ? 'active' : ''; ?>">
        <i class="fas fa-users"></i>
        <span>Clientes</span>
      </a>
      <a href="../vendas/teste.php" class="bottom-nav-item <?php echo ($action == 'vendas') ? 'active' : ''; ?>">
        <i class="fas fa-shopping-cart"></i>
        <span>Vendas</span>
      </a>
      <a href="../financeiro/teste.php"
        class="bottom-nav-item <?php echo ($action == 'financeiro') ? 'active' : ''; ?>">
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
      #btnVerValor {
        background-color: var(--primary-dark);
        border-color: var(--primary-dark);
        color: #fff;
      }

      #btnVerValor:hover,
      #btnVerValor:focus {
        background-color: var(--primary);
        border-color: var(--primary);
        color: #fff;
      }

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



    <!-- Floating Action Button (New) -->
    <a href="teste.php?action=create" class="fab d-flex justify-content-center align-items-center">
      <i class="bi bi-plus-lg"></i>
    </a>

    <!-- Modal Adicionar Estoque -->
    <div class="modal fade" id="addStockModal" tabindex="-1" aria-labelledby="addStockModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form id="addStockForm" method="POST" action="">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="addStockModalLabel">Adicionar Estoque</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label for="stockQuantity" class="form-label">Quantidade</label>
                <input type="number" class="form-control" id="stockQuantity" name="quantidade" min="1" required>
              </div>
              <div class="mb-3">
                <label for="stockCost" class="form-label">Preço de Custo</label>
                <input type="text" class="form-control" id="stockCost" name="preco_custo" placeholder="0,00" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
              <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
          </div>
        </form>
      </div>
    </div>
      <script>
        var addStockModal = document.getElementById('addStockModal');
        addStockModal.addEventListener('show.bs.modal', function(event) {
          // Botão que disparou o modal
          var button = event.relatedTarget;
          var produtoId = button.getAttribute('data-id');

          // Ajusta o action do form para usar nossa rota add_stock
          var form = document.getElementById('addStockForm');
          form.action = 'teste.php?action=add_stock&id=' + produtoId;

          // Zera os campos do formulário
          form.querySelector('#stockQuantity').value = '';
          form.querySelector('#stockCost').value = '';
        });
      </script>
      <!-- Modal Adicionar Estoque na Variação -->
      <div class="modal fade" id="addStockVarModal" tabindex="-1" aria-labelledby="addStockVarModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
          <form id="addStockVarForm" method="POST" action="">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="addStockVarModalLabel">Adicionar Estoque à Variação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label for="varStockQuantity" class="form-label">Quantidade</label>
                  <input type="number" class="form-control" id="varStockQuantity" name="quantidade" min="1" required>
                </div>
                <div class="mb-3">
                  <label for="varStockCost" class="form-label">Preço de Custo</label>
                  <input type="text" class="form-control" id="varStockCost" name="preco_custo" placeholder="0,00"
                    required>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
              </div>
            </div>
          </form>
        </div>
      </div>
      <div class="modal fade" id="passwordModal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Digite sua senha</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="password" id="passwordInput" class="form-control" placeholder="Senha">
              <div id="passwordError" class="form-text text-danger d-none">Senha inválida</div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="button" id="btnConfirmPassword" class="btn btn-primary">Confirmar</button>
            </div>
          </div>
        </div>
      </div>
      <script>
        document.getElementById('btnVerValor').addEventListener('click', () => {
          // limpa estado anterior
          document.getElementById('passwordInput').value = '';
          document.getElementById('passwordError').classList.add('d-none');
          new bootstrap.Modal(document.getElementById('passwordModal')).show();
        });
      </script>
      <script>
        document.getElementById('btnConfirmPassword').addEventListener('click', async () => {
          const senha = document.getElementById('passwordInput').value.trim();
          const errorEl = document.getElementById('passwordError');
          errorEl.classList.add('d-none');

          try {
            const res = await fetch('teste.php?action=show_value', {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
              },
              body: 'senha=' + encodeURIComponent(senha)
            });
            const data = await res.json();

            if (data.status === 'success') {
              // Exibe o valor
              document.getElementById('stockValue').innerText = data.value;
              // Fecha o modal
              bootstrap.Modal.getInstance(document.getElementById('passwordModal')).hide();
            } else {
              errorEl.textContent = data.message;
              errorEl.classList.remove('d-none');
            }
          } catch (err) {
            console.error('Fetch error:', err);
            errorEl.textContent = 'Erro ao comunicar com o servidor.';
            errorEl.classList.remove('d-none');
          }
        });
      </script>


      <script>
        const addStockVarModal = document.getElementById('addStockVarModal');
        addStockVarModal.addEventListener('show.bs.modal', event => {
          const button = event.relatedTarget;
          const varId = button.getAttribute('data-id');
          const form = document.getElementById('addStockVarForm');
          // monta a rota, compatível com o case 'add_stock' no seu controller
          form.action = 'teste.php?action=add_stock&id_variacao=' + varId;
          // limpa campos
          form.querySelector('#varStockQuantity').value = '';
          form.querySelector('#varStockCost').value = '';
        });
      </script>



      <!-- Custom JS -->
      <script>
  document.addEventListener('DOMContentLoaded', () => {
    // —– 1) Função para editar variação —–
    function loadVariationEdit(idVariacao) {
      const form       = document.getElementById('form-variacao');
      const header     = document.getElementById('header-variacao');
      const submitBtn  = document.getElementById('btn-submit-variacao');
      const cancelBtn  = document.getElementById('btn-cancel-edit');
      form.closest('.card').classList.add('pulse');
      setTimeout(() => form.closest('.card').classList.remove('pulse'), 800);

      fetch(`teste.php?ajax_variacao=1&id_variacao=${idVariacao}`)
        .then(r => r.json())
        .then(data => {
          if (!data.id_variacao) return alert('Variação não encontrada!');
          document.getElementById('var_id').value      = data.id_variacao;
          document.getElementById('var_cor').value     = data.cor || '';
          document.getElementById('var_tamanho').value= data.tamanho || '';
          document.getElementById('var_sku').value     = data.sku || '';
          document.getElementById('var_preco').value   = data.preco_venda || '';
          document.getElementById('var_estoque').value = data.estoque_atual || '';

          // anima inputs
          Array.from(form.querySelectorAll('input')).forEach((inp, i) => {
            setTimeout(() => {
              inp.classList.add('pulse');
              setTimeout(() => inp.classList.remove('pulse'), 400);
            }, i * 80);
          });

          header.innerHTML    = '<i class="bi bi-pencil-square"></i> Editar Variação';
          submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> Atualizar Variação';
          submitBtn.classList.replace('btn-success','btn-primary');
          cancelBtn.classList.remove('d-none');
          form.closest('.card').scrollIntoView({behavior:'smooth'});
        })
        .catch(() => alert('Erro ao buscar a variação.'));
    }

    // Delegação de clique
    document
      .getElementById('variacoes-container')
      .addEventListener('click', e => {
        const btn = e.target.closest('button[data-action="edit"]');
        if (btn) loadVariationEdit(btn.dataset.id);
      });

    // Cancelar edição
    document.getElementById('btn-cancel-edit')
            .addEventListener('click', () => resetForm());

    function resetForm() {
      const form      = document.getElementById('form-variacao');
      const header    = document.getElementById('header-variacao');
      const submitBtn = document.getElementById('btn-submit-variacao');
      const cancelBtn = document.getElementById('btn-cancel-edit');

      form.reset();
      document.getElementById('var_id').value = '';
      header.innerHTML    = '<i class="bi bi-plus-circle"></i> Nova Variação';
      submitBtn.innerHTML = '<i class="bi bi-plus-circle"></i> Adicionar Variação';
      submitBtn.classList.replace('btn-primary','btn-success');
      cancelBtn.classList.add('d-none');
    }

    // —– 2) Função para alternar sidebar —–
    window.toggleDrawer = () => {
      const sb = document.querySelector('.sidebar');
      const ov = document.querySelector('.drawer-overlay');
      sb.classList.toggle('open');
      ov.classList.toggle('show');
    };

    document.getElementById('desktopSidebarToggle')
            .addEventListener('click', e => {
              e.preventDefault();
              toggleDrawer();
            });
    document.querySelector('.drawer-overlay')
            .addEventListener('click', () => toggleDrawer());

    // … (aqui podem ficar os outros handlers únicos, sem duplicar mais DOMContentLoaded) …
  });
  </script>


    <!-- Modal de Erro -->
    <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="errorModalLabel"><i class="bi bi-exclamation-triangle me-2"></i>Ocorreu um Erro
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
              aria-label="Fechar"></button>
          </div>
          <div class="modal-body">
            <p id="errorModalMessage"></p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal">Fechar</button>
          </div>
        </div>
      </div>
    </div>
    <?php if (isset($_GET['error_duplicate']) && $_GET['error_duplicate'] == 1 && isset($_GET['msg'])): ?>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          // Chama a função para exibir o modal com a mensagem de erro personalizada
          showErrorModal("<?php echo addslashes($_GET['msg']); ?>");
        });
      </script>
    <?php endif; ?>
    <?php if (isset($_GET['error_duplicate']) && $_GET['error_duplicate'] == 1 && isset($_GET['msg'])): ?>
      <script>
        document.addEventListener('DOMContentLoaded', function() {
          showErrorModal("<?php echo addslashes($_GET['msg']); ?>");
        });
      </script>
    <?php endif; ?>

    <script>
      // ——— Produto: auto preenche Código de Barras ———
      (function() {
        const nome = document.getElementById('nome');
        const cb = document.getElementById('codigo_barras');
        if (nome && cb) {
          nome.addEventListener('input', () => {
            const parts = nome.value.trim().split(/\s+/);
            const code = parts
              .map(w => w.replace(/[^A-Za-z]/g, '').substring(0, 3).toUpperCase())
              .join('');
            cb.value = code;
          });
        }
      })();

      document.addEventListener('DOMContentLoaded', () => {
        const nomeEl = document.getElementById('nome');
        const tbody = document.querySelector('#variationsTable tbody');
        const addBtn = document.getElementById('addVariation');

        // 1) clona/remover variações (mantém exatamente como você já tem)
        addBtn.addEventListener('click', () => {
          const newRow = tbody.querySelector('.variation-row').cloneNode(true);
          newRow.querySelectorAll('input').forEach(i => i.value = '');
          tbody.appendChild(newRow);
        });
        tbody.addEventListener('click', e => {
          if (e.target.closest('.remove-variation')) {
            const rows = tbody.querySelectorAll('tr');
            if (rows.length > 1) e.target.closest('tr').remove();
          }
        });

        // 2) função que gera o SKU a partir do nome, cor e tamanho
        function calculaSKU(row) {
          const nome = nomeEl.value.trim()
            .split(/\s+/)
            .map(w => w.replace(/[^A-Za-z]/g, '').slice(0, 3).toUpperCase())
            .join('');
          const cor = (row.querySelector('input[name="var_cor[]"]').value || '').trim();
          const tam = (row.querySelector('input[name="var_tamanho[]"]').value || '').trim();
          const inic = nome;
          const tClean = tam.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
          const cLetra = cor.charAt(0).toUpperCase();
          row.querySelector('input[name="var_sku[]"]').value = `${inic}${tClean}${cLetra}`;
        }

        // 3) toda vez que o nome mudar, atualiza todos os SKUs
        nomeEl.addEventListener('input', () => {
          tbody.querySelectorAll('tr.variation-row').forEach(row => calculaSKU(row));
        });

        // 4) delega o input de cor/tamanho para recalcular apenas aquela linha
        tbody.addEventListener('input', e => {
          if (e.target.matches('input[name="var_cor[]"], input[name="var_tamanho[]"]')) {
            calculaSKU(e.target.closest('tr'));
          }
        });
      });

      // ——— Variação: auto preenche SKU ———
      (function() {
        const cor = document.getElementById('var_cor');
        const tamanho = document.getElementById('var_tamanho');
        const skuField = document.getElementById('var_sku');
        const prodName = document.getElementById('produto_nome')?.value || '';

        function atualizarSku() {
          // iniciais do nome do produto
          const iniciais = prodName
            .trim()
            .split(/\s+/)
            .map(w => w.replace(/[^A-Za-z]/g, '').substring(0, 3).toUpperCase())
            .join('');
          // tamanho limpo
          const tam = (tamanho.value || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
          // primeira letra da cor
          const corLetra = (cor.value || '')
            .replace(/[^A-Za-z]/g, '').charAt(0).toUpperCase();
          skuField.value = iniciais + tam + corLetra;
        }

        if (cor && tamanho && skuField) {
          cor.addEventListener('input', atualizarSku);
          tamanho.addEventListener('input', atualizarSku);
        }
      })();

      document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('mobileSidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        const body = document.body;

        function toggleSidebar() {
          sidebar.classList.toggle('open');
          overlay.classList.toggle('active');
          body.classList.toggle('sidebar-open');
        }

        btn.addEventListener('click', e => {
          e.preventDefault();
          toggleSidebar();
        });

        overlay.addEventListener('click', () => {
          toggleSidebar();
        });
      });
    </script>

    <script>
      (function() {
        const input = document.getElementById('filterInput');
        const tableBody = document.querySelector('#productsTable tbody');
        let debounceTimer;

        input.addEventListener('input', () => {
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(() => {
            const term = input.value.trim();
            // se estiver vazio, recarrega a página completa para restaurar paginação e layout
            if (!term) {
              window.location.href = 'teste.php';
              return;
            }
            fetch(`teste.php?ajax_search=1&term=${encodeURIComponent(term)}`)
              .then(res => res.json())
              .then(data => {
                // esvazia tabela
                tableBody.innerHTML = '';
                if (data.length === 0) {
                  tableBody.innerHTML = '<tr><td colspan="6" class="text-center py-3">Nenhum resultado encontrado.</td></tr>';
                  return;
                }
                // monta linhas
                data.forEach(prod => {
                  // calcula estoque total (sem variações, ou adapte conforme sua estrutura)
                  const estoque = prod.estoque_atual ?? 0;
                  const tr = document.createElement('tr');
                  tr.innerHTML = `
                <td>${prod.id_produto}</td>
                <td>
                  <div class="d-flex align-items-center">
                    ${prod.foto
                      ? `<img src="${prod.foto}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;margin-right:8px">`
                      : `<div class="bg-light d-flex align-items-center justify-content-center" style="width:40px;height:40px;border-radius:4px;margin-right:8px">
                          <i class="bi bi-image text-secondary"></i>
                        </div>`
                    }
                    <div>
                      <div class="fw-medium">${prod.nome}</div>
                      <small class="text-muted d-none d-md-block">${(prod.descricao||'').substr(0,50)}${(prod.descricao||'').length>50?'…':''}</small>
                    </div>
                  </div>
                </td>
                <td class="fw-bold text-primary">R$ ${parseFloat(prod.preco_venda).toFixed(2).replace('.',',')}</td>
                <td class="d-none d-md-table-cell">${prod.codigo_barras||'-'}</td>
                <td><span class="badge bg-success">${estoque} un</span></td>
                <td class="text-center">
                  <a href="teste.php?action=edit&id=${prod.id_produto}" class="btn btn-sm btn-warning me-1"><i class="bi bi-pencil"></i></a>
                  <a href="teste.php?action=variacoes&id=${prod.id_produto}" class="btn btn-sm btn-info me-1"><i class="bi bi-tags"></i></a>
                  <button onclick="confirmDelete(${prod.id_produto}, '${prod.nome.replace(/'/g,"\\'")}')" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                </td>
              `;
                  tableBody.appendChild(tr);
                });
              })
              .catch(console.error);
          }, 300); // espera 300ms após o último teclaço
        });
      })();
    </script>
    <script>
      window.confirmDelete = function(id, nome) {
        if (confirm(`Tem certeza que deseja excluir o produto "${nome}"?`)) {
          window.location.href = `teste.php?action=delete&id=${id}`;
        }
      }
    </script>
  </body>
</html>