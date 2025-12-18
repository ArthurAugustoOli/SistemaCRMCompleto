<?php
// admin_financeiro.php

require_once '../../app/config/config.php';
require_once '../../app/models/Despesas.php';
require_once '../../app/models/Venda.php';
require_once '../login/verificar_sessao.php'; // Ajuste o caminho conforme necessário
require_once '../../app/models/Funcionario.php';

use App\Models\Funcionario;
use App\Models\Despesas;
use App\Models\Venda;

$funcModel    = new Funcionario($mysqli);
$despesaModel = new Despesas();

// 1) Pegar Mês/Ano do formulário ou usar mês atual
$mesAno       = $_GET['mes_ano'] ?? date('Y-m');
list($ano, $mes) = explode('-', $mesAno);

// --- INÍCIO correção de filtros ---
// Qual tipo de transação: 'ambos' | 'despesas' | 'vendas'
$tipoFiltro = $_GET['tipo'] ?? 'ambos';

// Filtro de funcionário (se vier ID via GET)
$funcionarioFiltro = !empty($_GET['funcionario'])
    ? intval($_GET['funcionario'])
    : null;

// Consideramos um "filtro ativo" se qualquer parâmetro vier por GET
$filtroAtivo = isset($_GET['mes_ano'])
             || isset($_GET['tipo'])
             || isset($_GET['funcionario']);
// --- FIM correção de filtros ---

// 2) Construir primeiro e último dia desse mês
$dataInicial  = "{$ano}-{$mes}-01";
$dataFinal    = date('Y-m-t', strtotime($dataInicial));



// 2) Agora sim soma as despesas no período
$totalDespesasMes = $despesaModel->getSumDespesas($dataInicial, $dataFinal);



// ================================================================

// ============================================

  $todosFuncs = $funcModel->getAll(); // ou getAllFuncionarios()


// restringe acesso ao admin
if (!isset($_SESSION['login']) || $_SESSION['login'] !== 'Silvania') {
    header('Location: ../produtos/index.php');
    exit;
}

// ==========================================================================
// ==========================================================================
// ENDPOINT AJAX PARA PAGINAÇÃO DAS TRANSAÇÕES
if (isset($_GET['ajax_transactions'])) {
    // ----------------------------------------------------------------------
    // 1) Desativa notices/warnings para não vazar nada antes do JSON
    ini_set('display_errors', 'Off');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
    // ----------------------------------------------------------------------

    // 2) Lê parâmetros de filtro
    $tipoFiltro        = $_GET['tipo']         ?? 'ambos';
    $dataInicial       = $_GET['data_inicial'] ?? $dataInicial;
    $dataFinal         = $_GET['data_final']   ?? $dataFinal;
    $funcionarioFiltro = !empty($_GET['funcionario'])
                         ? intval($_GET['funcionario'])
                         : null;

    // 3) Prepara SQL extra só para vendas
    $whereFuncVendas   = $funcionarioFiltro
                         ? " AND v.id_funcionario = {$funcionarioFiltro} "
                         : "";
    $whereFuncParcelas = $funcionarioFiltro
                         ? " AND vp.id_venda IN (
                                SELECT id_venda FROM vendas
                                 WHERE id_funcionario = {$funcionarioFiltro}
                             )"
                         : "";

    // 4) Paginação
    $page   = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit  = 10;
    $offset = ($page - 1) * $limit;

    // 5) Busca despesas **APENAS** se não houver filtro de funcionário
    $despesasFiltro = [];
    if (is_null($funcionarioFiltro)
        && ($tipoFiltro === 'ambos' || $tipoFiltro === 'despesas')
    ) {
       // Depois: exclui descrições que começam com "Estoque inicial"
$res = $mysqli->query("
  SELECT * FROM despesas
   WHERE data_despesa BETWEEN '$dataInicial' AND '$dataFinal'
     AND descricao NOT LIKE 'Estoque inicial%'
     AND descricao NOT LIKE 'Acréscimo de estoque no produto%'
");

        $despesasFiltro = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    // 6) Busca vendas à vista (sempre que for 'ambos' ou 'vendas')
    $vendasAvista = [];
    if ($tipoFiltro === 'ambos' || $tipoFiltro === 'vendas') {
        $res = $mysqli->query("
          SELECT
            v.id_venda,
            v.total_venda AS valor,
            v.data_venda  AS data,
            v.status,
            v.desconto,
            f.nome        AS funcionario
          FROM vendas v
     LEFT JOIN funcionarios f ON v.id_funcionario = f.id_funcionario
         WHERE v.parcelado = 0
           AND v.data_venda BETWEEN '$dataInicial' AND '$dataFinal'
           {$whereFuncVendas}
      ORDER BY v.id_venda DESC
        ");
        $vendasAvista = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    // 7) Busca parcelas de vendas
    $parcelas = [];
    if ($tipoFiltro === 'ambos' || $tipoFiltro === 'vendas') {
        $res = $mysqli->query("
          SELECT
            vp.id_venda,
            vp.valor_parcela AS valor,
            vp.data_vencimento AS data,
            CONCAT('Parcela ', vp.numero_parcela,'/',v.num_parcelas) AS descricao,
            f.nome             AS funcionario
          FROM venda_parcelas vp
          JOIN vendas v ON v.id_venda = vp.id_venda
     LEFT JOIN funcionarios f ON v.id_funcionario = f.id_funcionario
         WHERE vp.data_vencimento BETWEEN '$dataInicial' AND '$dataFinal'
           {$whereFuncParcelas}
        ");
        $allParc = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        // Filtra 1 parcela por venda (mês atual ou número 1)
        $tmp = []; $mesAtual = date('m');
        foreach ($allParc as $p) {
            $id = $p['id_venda'];
            if (!isset($tmp[$id]) ||
                date('m', strtotime($p['data'])) === $mesAtual
            ) {
                $tmp[$id] = $p;
            }
        }
        $parcelas = array_values($tmp);
    }

    // 8) Monta o array unificado
    $transacoes = [];
    // Despesas
    foreach ($despesasFiltro as $d) {
        $transacoes[] = [
            'tipo'       => 'Despesa',
            'id'         => $d['id_despesa'],
            'valor'      => $d['valor'],
            'data'       => $d['data_despesa'],
            'descricao'  => $d['descricao'],
            'funcionario'=> null
        ];
    }
    // Vendas à vista
    foreach ($vendasAvista as $v) {
        $transacoes[] = [
            'tipo'        => 'Venda',
            'id'          => $v['id_venda'],
            'valor'       => $v['valor'],
            'data'        => $v['data'],
            'descricao'   => 'Venda '.$v['status']
                             .($v['desconto']>0
                                ? ' (Desc: R$ '.number_format($v['desconto'],2,',','.').')'
                                : ''),
            'funcionario' => $v['funcionario']
        ];
    }
    // Parcelas
    foreach ($parcelas as $p) {
        $transacoes[] = [
            'tipo'        => 'Parcela',
            'id'          => $p['id_venda'],
            'valor'       => $p['valor'],
            'data'        => $p['data'],
            'descricao'   => $p['descricao'],
            'funcionario' => $p['funcionario']
        ];
    }

    // 9) Ordena e pagina
    usort($transacoes, fn($a,$b)=>($b['id']<=>$a['id']));
    $total      = count($transacoes);
    $pageItems  = array_slice($transacoes, $offset, $limit);

    // 10) Gera apenas o HTML da tabela
    ob_start();
    if ($pageItems) {
        foreach ($pageItems as $t) {
            $badge    = $t['tipo']==='Venda' ? 'success' : 'danger';
            $funcName = $t['funcionario'] ?? '—';
            echo "<tr>
                    <td>#{$t['id']}</td>
                    <td><span class=\"badge bg-{$badge}\">{$t['tipo']}</span></td>
                    <td>".date('d/m/Y',strtotime($t['data']))."</td>
                    <td>{$t['descricao']}</td>
                    <td>".htmlspecialchars($funcName)."</td>
                    <td class=\"text-end fw-bold text-{$badge}\">
                      R$ ".number_format($t['valor'],2,',','.')."
                    </td>
                  </tr>";
        }
    } else {
        echo '<tr>
                <td colspan="6" class="text-center py-4">
                  <i class="fas fa-inbox fa-2x text-muted mb-2 d-block"></i>
                  <p class="mb-0">Nenhuma transação encontrada.</p>
                </td>
              </tr>';
    }
    $html = ob_get_clean();

    // 11) Retorna JSON puro
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'html'        => $html,
        'total'       => $total,
        'currentPage' => $page,
        'totalPages'  => ceil($total / $limit),
    ]);
    exit;
}
// ==========================================================================

// ==========================================================================
// CONTINUA COM A PÁGINA PRINCIPAL

// Obter todas as despesas e calcular o total (campo "valor")
$despesas = $despesaModel->getAllDespesas();
$totalDespesas = 0;
foreach ($despesas as $d) {
    $totalDespesas += floatval($d['valor']);
}

// Obter o total de vendas (usando o campo "total_venda")
// Obter o total de vendas **no período** (usando o campo "total_venda")
$sqlTotalVendas = "
  SELECT SUM(total_venda) AS total
    FROM vendas
   WHERE data_venda BETWEEN '$dataInicial' AND '$dataFinal'
";
$resultTotal   = $mysqli->query($sqlTotalVendas);
$rowTotal      = $resultTotal ? $resultTotal->fetch_assoc() : null;
$totalVendas   = $rowTotal ? floatval($rowTotal['total']) : 0;


// Se houver filtro ativo, refazer as consultas de acordo com o período
if ($filtroAtivo) {
    if ($tipoFiltro === 'ambos' || $tipoFiltro === 'despesas') {
        $sqlFiltroDesp = "SELECT * FROM despesas WHERE data_despesa BETWEEN '$dataInicial' AND '$dataFinal'";
        $resultFiltroDesp = $mysqli->query($sqlFiltroDesp);
        $despesasFiltro = $resultFiltroDesp ? $resultFiltroDesp->fetch_all(MYSQLI_ASSOC) : [];
    } else {
        $despesasFiltro = [];
    }
    if ($tipoFiltro === 'ambos' || $tipoFiltro === 'vendas') {
$sqlFiltroVend = "
  SELECT  
    v.id_venda,
    v.total_venda,
    v.data_venda   AS data,
    v.status,
    v.desconto,
    f.nome         AS funcionario
  FROM vendas v
  LEFT JOIN funcionarios f
    ON v.id_funcionario = f.id_funcionario
  WHERE v.data_venda BETWEEN '$dataInicial' AND '$dataFinal'
  ORDER BY v.id_venda DESC
";
$resultFiltroVend = $mysqli->query($sqlFiltroVend);
$vendasFiltro = $resultFiltroVend
    ? $resultFiltroVend->fetch_all(MYSQLI_ASSOC)
    : [];
    }
}

if ($filtroAtivo) {
    $totalDespesasFiltro = 0;
    foreach ($despesasFiltro as $d) {
        $totalDespesasFiltro += floatval($d['valor']);
    }
    $totalVendasFiltro = 0;
    foreach ($vendasFiltro as $v) {
        $totalVendasFiltro += floatval($v['total_venda']);
    }
    $totalDespesasExibido = $totalDespesasFiltro;
    $totalVendasExibido = $totalVendasFiltro;
} else {
    $totalDespesasExibido = $totalDespesas;
    $totalVendasExibido = $totalVendas;
}

// ==== NOVO: soma das comissões de todos os funcionários
$sqlComissoes = "SELECT SUM(comissao_atual) AS total_comissao FROM funcionarios";
$resultCom = $mysqli->query($sqlComissoes);
$rowCom = $resultCom ? $resultCom->fetch_assoc() : null;
$totalComissoes = $rowCom ? floatval($rowCom['total_comissao']) : 0;

// ==== Soma do custo de todos os produtos vendidos no período,
//      puxando sempre o preco_custo da tabela produtos ====
$sqlTotalCusto = "
  SELECT
    COALESCE(SUM(
      iv.quantidade
      * COALESCE(pdirect.preco_custo, pvar.preco_custo)
    ), 0) AS total_custo
  FROM itens_venda iv
  JOIN vendas v
    ON iv.id_venda = v.id_venda
  -- custo direto (produto sem variação)
  LEFT JOIN produtos pdirect
    ON iv.id_produto = pdirect.id_produto
  -- variação e, a partir dela, produto 'pai'
  LEFT JOIN produto_variacoes pv
    ON iv.id_variacao = pv.id_variacao
  LEFT JOIN produtos pvar
    ON pv.id_produto = pvar.id_produto
  WHERE v.data_venda BETWEEN '$dataInicial' AND '$dataFinal'
";
$resultCusto = $mysqli->query($sqlTotalCusto);
$rowCusto    = $resultCusto ? $resultCusto->fetch_assoc() : null;
$totalCusto  = $rowCusto ? floatval($rowCusto['total_custo']) : 0;

// ==== NOVO: lucro líquido = receita – custo dos produtos – comissão ====
$lucroLiquido = $totalVendasExibido - $totalCusto - $totalComissoes;

// Dados para o gráfico de faturamento (linha) – usando campo "total_venda"
// MODIFICADO: Aplicar o filtro de período ao gráfico de faturamento
$sqlGrafico = "
  SELECT DATE_FORMAT(data_venda, '%Y-%m') AS mes, SUM(total_venda) AS total 
    FROM vendas 
   WHERE data_venda BETWEEN '$dataInicial' AND '$dataFinal'
   GROUP BY mes 
   ORDER BY mes ASC
";

$resultGrafico = $mysqli->query($sqlGrafico);
$labels = [];
$dataGrafico = [];
if ($resultGrafico) {
    while ($row = $resultGrafico->fetch_assoc()) {
        $labels[] = $row['mes'];
        $dataGrafico[] = floatval($row['total']);
    }
}

// Gráfico de Pizza – Compara Total de Vendas e Despesas
$totalGeral = $totalDespesasExibido + $totalVendasExibido;
$pizzaLabels = ['Despesas', 'Vendas'];
$pizzaData = [
    $totalDespesasExibido,
    $totalVendasExibido
];

// Gráfico de Barras – Distribuição das vendas por status
// MODIFICADO: Aplicar o filtro de período ao gráfico de barras
$sqlBarra = "SELECT status, COUNT(*) AS total 
             FROM vendas 
             WHERE data BETWEEN '$dataInicial' AND '$dataFinal'
             GROUP BY status";
$resultBarra = $mysqli->query($sqlBarra);
$barLabels = [];
$barData = [];
if ($resultBarra) {
    while ($row = $resultBarra->fetch_assoc()) {
        $barLabels[] = ucfirst($row['status']);
        $barData[] = intval($row['total']);
    }
}

// Obter transações para "Transações Recentes" e "Todas as Transações"
$transacoes = [];
if ($filtroAtivo) {
    foreach ($despesasFiltro as $d) {
        $transacoes[] = [
            'tipo'        => 'Despesa',
            'id'          => $d['id_despesa'],
            'valor'       => $d['valor'],
            'data'        => $d['data_despesa'],
            'descricao'   => $d['descricao']
        ];
    }
foreach ($vendasFiltro as $v) {
    $transacoes[] = [
        'tipo'        => 'Venda',
        'id'          => $v['id_venda'],
        'valor'       => $v['total_venda'],
        'data'        => $v['data'],
        'descricao'   => 'Venda '.$v['status']
                         . (isset($v['desconto']) && $v['desconto']>0
                             ? ' (Desconto: R$ '.number_format($v['desconto'],2,',','.').')'
                             : ''),
        'funcionario' => $v['funcionario'] ?? '—'
    ];
}

} else {
    foreach ($despesas as $d) {
        $transacoes[] = [
            'tipo'        => 'Despesa',
            'id'          => $d['id_despesa'],
            'valor'       => $d['valor'],
            'data'        => $d['data_despesa'],
            'descricao'   => $d['descricao']
        ];
    }
    $sqlVendas = "
    SELECT
      id_venda,
      total_venda,
      data,
      status,
      desconto,
      id_funcionario
    FROM vendas
    WHERE data_venda BETWEEN '$dataInicial' AND '$dataFinal'
    ORDER BY data_venda DESC
  ";
    $resultVendas = $mysqli->query($sqlVendas);
    if ($resultVendas) {
        while ($v = $resultVendas->fetch_assoc()) {
            $transacoes[] = [
                'tipo'        => 'Venda',
                'id'          => $v['id_venda'],
                'valor'       => $v['total_venda'],
                'data'        => $v['data'],
                'descricao'   => 'Venda ' . $v['status'],
                'funcionario' => $v['id_funcionario']
            ];
        }
    }
}
usort($transacoes, function($a, $b) {
    return strtotime($b['data']) - strtotime($a['data']);
});
$transacoesRecentes = array_slice($transacoes, 0, 5);

// ==== NOVO: ranking de funcionários por comissão_atual
$sqlFuncs = "SELECT nome, comissao_atual FROM funcionarios ORDER BY comissao_atual DESC";
$resultFuncs = $mysqli->query($sqlFuncs);
$rankingFuncs = $resultFuncs ? $resultFuncs->fetch_all(MYSQLI_ASSOC) : [];

// Limitar para os top 3 funcionários para exibição no card principal
$top3Funcs = array_slice($rankingFuncs, 0, 3);

// Preparar dados para o gráfico de funcionários no modal
$funcNomes = [];
$funcComissoes = [];
foreach ($rankingFuncs as $func) {
    $funcNomes[] = $func['nome'];
    $funcComissoes[] = floatval($func['comissao_atual']);
}

// ==== NOVO: soma das comissões de todos os funcionários
$sqlComissoes = "SELECT SUM(comissao_atual) AS total_comissao FROM funcionarios";
$resultCom = $mysqli->query($sqlComissoes);
$rowCom = $resultCom ? $resultCom->fetch_assoc() : null;
$totalComissoes = $rowCom ? floatval($rowCom['total_comissao']) : 0;

// Agora o lucro líquido passa a descontar também as comissões:
$lucroLiquido = $totalVendasExibido - $totalCusto - $totalComissoes;

// === Novo: Lucro Líquido Real (antes: Lucro – Despesas do mês) ===
$lucroAntesDespesas = $lucroLiquido;  // $lucroLiquido é Venda – Custo – Comissão
$lucroLiquidoReal  = $lucroAntesDespesas - $totalDespesasMes;
// ==== NOVAS MÉTRICAS REQUERIDAS ====
// Faturamento Bruto (total de vendas, já filtrado)
$faturamentoBruto        = $totalVendasExibido;
// Faturamento após comissão (bruto menos o total de comissões)
$faturamentoAposComissao = $totalVendasExibido - $totalComissoes;
// (O $lucroLiquido já foi calculado: vendas – comissões – despesas)

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
  <title>Dashboard Financeiro | Sistema de Gestão</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Google Fonts - Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
  <style>
    :root {
      --primary-color: #4e54e9;
      --sidebar-bg: #4e54e9;
      --sidebar-width: 240px;
      --sidebar-width-collapsed: 70px;
      --header-height: 60px;
      --bottom-nav-height: 70px;
      --border-radius: 8px;
      --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      --transition-speed: 0.3s;
    }
    
    /* CORREÇÕES GERAIS PARA MOBILE */
    html, body {
      overflow-x: hidden;
      max-width: 100vw;
      font-family: 'Poppins', sans-serif;
      background-color: #f8f9fa;
      margin: 0;
      padding: 0;
    }
    
    * {
      box-sizing: border-box;
    }
    
    img {
      max-width: 100%;
      height: auto;
    }
    
    .container-fluid,
    .row,
    .col-*,
    [class*="col-"] {
      max-width: 100%;
      overflow-x: hidden;
    }
    
    /* Main Content */
    .main-content {
      margin-left: var(--sidebar-width);
      padding: 20px;
      transition: all var(--transition-speed);
      min-height: 100vh;
      padding-top: calc(var(--header-height) + 20px);
      width: 85%;
      max-width: 100vw;
    }
    
    /* Top Navbar */
    .top-navbar {
      position: fixed;
      top: 0;
      right: 0;
      left: var(--sidebar-width);
      height: var(--header-height);
      background-color: #fff;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 20px;
      z-index: 999;
      transition: all var(--transition-speed);
    }

    .user-menu {
      display: flex;
      align-items: center;
    }
    
    .theme-toggle {
      background: none;
      border: none;
      color: #555;
      font-size: 1.25rem;
      cursor: pointer;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all var(--transition-speed);
    }
    
    .theme-toggle:hover {
      background-color: rgba(0, 0, 0, 0.05);
    }
    
    /* Breadcrumb */
    .breadcrumb-container {
      margin-bottom: 20px;
      background-color: #fff;
      border-radius: var(--border-radius);
      padding: 10px 15px;
      box-shadow: var(--card-shadow);
    }
    
    .breadcrumb {
      margin: 0;
      padding: 0;
    }
    
    .breadcrumb-item a {
      color: #6c757d;
      text-decoration: none;
    }
    
    .breadcrumb-item a:hover {
      color: var(--primary-color);
    }
    
    .breadcrumb-item.active {
      color: #343a40;
    }
    
    /* Page Title */
    .page-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .page-title h1 {
      font-size: 1.75rem;
      font-weight: 600;
      margin: 0;
      display: flex;
      align-items: center;
    }
    
    .page-title h1 i {
      margin-right: 10px;
      color: var(--primary-color);
    }
    
    .page-actions {
      display: flex;
      gap: 10px;
    }
    
    /* Stats Card */
    .stat-card {
      background-color: #fff;
      border-radius: var(--border-radius);
      padding: 20px;
      box-shadow: var(--card-shadow);
      display: flex;
      align-items: center;
      height: 100%;
      transition: transform 0.2s;
    }
    
    .stat-card:hover {
      transform: translateY(-5px);
    }
    
    .stat-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-right: 15px;
    }
    
    .stat-icon.primary {
      background-color: rgba(78, 84, 233, 0.1);
      color: var(--primary-color);
    }
    
    .stat-icon.success {
      background-color: rgba(16, 185, 129, 0.1);
      color: #10b981;
    }
    
    .stat-icon.danger {
      background-color: rgba(239, 68, 68, 0.1);
      color: #ef4444;
    }
    
    .stat-icon.warning {
      background-color: rgba(245, 158, 11, 0.1);
      color: #f59e0b;
    }
    
    .stat-details {
      flex: 1;
    }
    
    .stat-value {
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 5px;
    }
    
    .stat-label {
      color: #6c757d;
      font-size: 0.875rem;
    }
    
    /* Card */
    .card {
      border: none;
      border-radius: var(--border-radius);
      box-shadow: var(--card-shadow);
      margin-bottom: 20px;
    }
    
    .card-header {
      background-color: #fff;
      border-bottom: 1px solid #e2e8f0;
      padding: 15px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .card-header h5 {
      margin: 0;
      font-weight: 600;
      font-size: 1rem;
      display: flex;
      align-items: center;
    }
    
    .card-header h5 i {
      margin-right: 10px;
      color: var(--primary-color);
    }
    
    .card-body {
      padding: 20px;
    }
    
    .card-footer {
      background-color: #fff;
      border-top: 1px solid #e2e8f0;
      padding: 15px 20px;
    }
    
    /* Filter Card */
    .filter-card .filter-toggle {
      cursor: pointer;
    }
    
    .filter-card .filter-toggle i {
      transition: transform var(--transition-speed);
    }
    
    .filter-card .filter-toggle[aria-expanded="true"] i {
      transform: rotate(180deg);
    }
    
    /* Chart Container */
    .chart-container {
      position: relative;
      height: 300px;
      width: 100%;
    }
    
    /* Transaction List */
    .transaction-list {
      display: flex;
      flex-direction: column;
    }
    
    .transaction-item {
      padding: 15px 20px;
      border-bottom: 1px solid #e2e8f0;
    }
    
    .transaction-item:last-child {
      border-bottom: none;
    }
    
    .transaction-icon {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 15px;
      font-size: 1rem;
    }
    
    .transaction-icon.success {
      background-color: rgba(16, 185, 129, 0.1);
      color: #10b981;
    }
    
    .transaction-icon.danger {
      background-color: rgba(239, 68, 68, 0.1);
      color: #ef4444;
    }
    
    .transaction-details {
      flex: 1;
    }
    
    .transaction-details h6 {
      margin: 0 0 5px;
      font-weight: 600;
      font-size: 0.875rem;
    }
    
    .transaction-amount {
      font-weight: 700;
      font-size: 1rem;
    }
    
    /* Table */
    .table {
      margin-bottom: 0;
    }
    
    .table th {
      font-weight: 600;
      background-color: #f8f9fa;
      border-bottom-width: 1px;
    }
    
    .table td, .table th {
      padding: 12px 20px;
      vertical-align: middle;
    }
    
    /* Pagination */
    .pagination {
      margin-bottom: 0;
    }
    
    .page-link {
      color: var(--primary-color);
      border-color: #e2e8f0;
    }
    
    .page-item.active .page-link {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
    }
    
    /* Empty State */
    .empty-state {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 30px;
      color: #6c757d;
    }
    
    .empty-state i {
      font-size: 2.5rem;
      margin-bottom: 10px;
      opacity: 0.5;
    }
    
    /* Floating Action Button */
    .floating-action-btn {
      position: fixed;
      bottom: 30px;
      right: 30px;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background-color: var(--primary-color);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
      border: none;
      cursor: pointer;
      transition: all var(--transition-speed);
      z-index: 990;
    }
    
    .floating-action-btn:hover {
      transform: translateY(-5px);
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
    }
    
    /* SIDEBAR MOBILE */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: var(--sidebar-width);
      height: 100vh;
      background-color: var(--sidebar-bg);
      z-index: 1050;
      overflow-y: auto;
      transition: transform var(--transition-speed) ease;
    }
    
    .sidebar-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1040;
      opacity: 0;
      visibility: hidden;
      transition: all var(--transition-speed) ease;
    }
    
    .sidebar-overlay.show {
      opacity: 1;
      visibility: visible;
    }
    
    /* BOTTOM NAVIGATION */
    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      height: var(--bottom-nav-height);
      background-color: #ffffff;
      box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
      display: none;
      justify-content: space-around;
      align-items: center;
      z-index: 1001;
      padding: 5px 10px;
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
      min-height: 50px;
      background: none;
      border: none;
      cursor: pointer;
    }
    
    .bottom-nav-item i {
      font-size: 18px;
      margin-bottom: 4px;
      transition: all 0.2s;
    }
    
    .bottom-nav-item span {
      font-size: 10px;
      text-align: center;
      line-height: 1.2;
    }
    
    .bottom-nav-item:hover,
    .bottom-nav-item.active {
      color: var(--primary-color);
      text-decoration: none;
    }
    
    .bottom-nav-item:hover i,
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
    .bottom-nav-item.active::after {
      width: 40%;
    }
    
    /* RESPONSIVE STYLES */
    @media (max-width: 991.98px) {
      .sidebar {
        transform: translateX(-100%);
      }
      
      .sidebar.show {
        transform: translateX(0);
      }
      
      .main-content {
        margin-left: 0;
        padding: 10px;
        padding-top: calc(var(--header-height) + 10px);
        padding-bottom: calc(var(--bottom-nav-height) + 10px);
      }
      
      .top-navbar {
        left: 0;
        padding: 0 15px;
      }
      
      .floating-action-btn {
        bottom: calc(var(--bottom-nav-height) + 20px);
      }
      
      .bottom-nav {
        display: flex !important;
      }
      
      /* Ajustar cards para mobile */
      .stat-card {
        padding: 15px;
        margin-bottom: 10px;
      }
      
      .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
      }
      
      .stat-value {
        font-size: 1.25rem;
      }
      
      /* Corrigir tabelas em mobile */
      .table-responsive {
        border: none;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
      
      .table {
        min-width: 600px;
        font-size: 0.875rem;
      }
      
      .table td,
      .table th {
        padding: 8px 12px;
        white-space: nowrap;
      }
      
      /* Ajustar formulários */
      .form-control,
      .form-select {
        font-size: 16px; /* Evita zoom no iOS */
      }
      
      /* Corrigir breadcrumb */
      .breadcrumb-container {
        margin-bottom: 15px;
        padding: 8px 12px;
      }
      
      /* Ajustar gráficos */
      .chart-container {
        height: 250px;
        margin-bottom: 15px;
      }
      
      /* Corrigir modal em mobile */
      .modal-dialog {
        margin: 10px;
        max-width: calc(100vw - 20px);
      }
      
      .modal-chart-container {
        height: 300px;
      }
    }
    
    @media (max-width: 767.98px) {
      .page-title {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
      }
      
      .page-actions {
        width: 100%;
      }
      
      .chart-container {
        height: 250px;
      }
      
      .page-title h1 {
        font-size: 1.5rem;
      }
      
      .card-header h5 {
        font-size: 0.9rem;
      }
      
      .col-md-4, .col-md-3, .col-md-6 {
        margin-bottom: 15px;
      }
    }
    
    /* Correções para telas muito pequenas */
    @media (max-width: 576px) {
      .main-content {
        padding: 8px;
        padding-top: calc(var(--header-height) + 8px);
        padding-bottom: calc(var(--bottom-nav-height) + 8px);
      }
      
      .stat-card {
        padding: 12px;
      }
      
      .stat-value {
        font-size: 1.1rem;
      }
      
      .card-body {
        padding: 15px;
      }
      
      .table td,
      .table th {
        padding: 6px 8px;
        font-size: 0.8rem;
      }
      
      .bottom-nav-item {
        padding: 6px 2px;
      }
      
      .bottom-nav-item i {
        font-size: 16px;
      }
      
      .bottom-nav-item span {
        font-size: 9px;
      }
      
      /* Correções para inputs em mobile */
      .input-group {
        flex-wrap: nowrap;
      }
      
      .input-group-text {
        min-width: auto;
        padding: 0.375rem 0.5rem;
      }
      
      .form-control {
        min-width: 0;
      }
    }
    
    /* Dark Mode */
    body.dark-mode {
      background-color: #121212;
      color: #f8f9fa;
    }
    
    body.dark-mode .sidebar {
      background-color: #1e1e1e;
    }
    
    body.dark-mode .top-navbar,
    body.dark-mode .breadcrumb-container,
    body.dark-mode .card,
    body.dark-mode .stat-card,
    body.dark-mode .bottom-nav,
    body.dark-mode .modal-content {
      background-color: #1e1e1e;
      color: #f8f9fa;
    }
    
    body.dark-mode .card-header,
    body.dark-mode .card-footer,
    body.dark-mode .modal-header,
    body.dark-mode .modal-footer {
      background-color: #1e1e1e;
      border-color: #2d2d2d;
    }
    
    body.dark-mode .table {
      color: #f8f9fa;
    }
    
    body.dark-mode .table th {
      background-color: #2d2d2d;
      color: #f8f9fa;
    }
    
    body.dark-mode .table td,
    body.dark-mode .form-select, 
    body.dark-mode .input-group-text, 
    body.dark-mode .form-control,
    body.dark-mode .pagination {
      border-color: #1e1e1e;
      background-color: #2d2d2d;
      color: #ffffff;
    }
    
    body.dark-mode .transaction-item {
      border-color: #2d2d2d;
    }
    
    body.dark-mode .search-input {
      background-color: #2d2d2d;
      border-color: #2d2d2d;
      color: #f8f9fa;
    }
    
    body.dark-mode .theme-toggle:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }
    
    body.dark-mode .breadcrumb-item a {
      color: #adb5bd;
    }
    
    body.dark-mode .breadcrumb-item.active {
      color: #f8f9fa;
    }
    
    body.dark-mode .stat-label,
    body.dark-mode .text-muted {
      color: #adb5bd !important;
    }
    
    body.dark-mode .page-link {
      background-color: #1e1e1e;
      border-color: #2d2d2d;
      color: #f8f9fa;
    }
    
    body.dark-mode .page-item.active .page-link {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
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
    
    /* Animation */
    .animate-fade-in {
      animation: fadeIn 0.5s ease forwards;
    }
    
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    /* Employee Ranking Card */
    .ranking-card {
      cursor: pointer;
      transition: all 0.3s ease;
    }
    
    .ranking-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .ranking-card .see-all {
      display: flex;
      justify-content: center;
      padding: 10px;
      color: var(--primary-color);
      font-weight: 500;
      border-top: 1px solid #e2e8f0;
      margin-top: 10px;
    }
    
    body.dark-mode .ranking-card .see-all {
      border-color: #2d2d2d;
    }
    
    /* Modal Styles */
    .modal-xl {
      max-width: 1140px;
    }
    
    .modal-chart-container {
      height: 400px;
      margin-bottom: 20px;
    }
    
    .employee-chart-legend {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 15px;
      margin-bottom: 20px;
    }
    
    .employee-chart-legend-item {
      display: flex;
      align-items: center;
      font-size: 0.875rem;
    }
    
    .employee-chart-legend-color {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      margin-right: 5px;
    }
  </style>
</head>
<body>
  <!-- Sidebar Overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Sidebar -->
  <?php include_once '../../frontend/includes/sidebar.php'?>

  <!-- Top Navbar -->
  <div class="top-navbar">
    <div class="user-menu">
      <?php require_once __DIR__ . '/../includes/notificacoes.php'; ?>
    </div>
  </div>

  <!-- Dark-Mode -->
  <?php include_once '../../frontend/includes/darkmode.php'?>

  <!-- Main Content -->
  <div class="main-content">
    <!-- Breadcrumb -->
    <div class="breadcrumb-container">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="#"><i class="fas fa-home"></i> Home</a></li>
          <li class="breadcrumb-item active">Financeiro</li>
        </ol>
      </nav>
    </div>
    
    <!-- Page Title -->
    <div class="page-title">
      <h1><i class="fas fa-chart-line"></i> Dashboard Financeiro</h1>
      <div class="page-actions">

      </div>
    </div>
        <!-- Filtro de Período -->
    <div class="card filter-card animate-fade-in" style="animation-delay: 0.6s">
      <div class="card-header" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="true" aria-controls="filterCollapse">
        <h5><i class="fas fa-filter"></i> Filtro de Período</h5>
        <i class="fas fa-chevron-down"></i>
      </div>
      <div class="collapse show" id="filterCollapse">
        <div class="card-body">
          <form method="GET" id="filtroPeriodoForm" action="index.php" class="row gx-2 align-items-end">
          <div class="col-auto">
            <label for="mes_ano" class="form-label">Mês/Ano</label>
            <input 
              type="month" 
              id="mes_ano" 
              name="mes_ano" 
              class="form-control" 
              value="<?= htmlspecialchars($_GET['mes_ano'] ?? date('Y-m')) ?>"
            >
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-search me-1"></i> Filtrar
            </button>
          </div>
           <!-- campos ocultos para AJAX/pagination -->
    <input type="hidden" id="data_inicial" name="data_inicial" value="<?= $dataInicial ?>">
    <input type="hidden" id="data_final"   name="data_final"   value="<?= $dataFinal ?>">
    <input type="hidden" id="tipo"         name="tipo"         value="<?= $tipoFiltro ?>">
    <input type="hidden" id="funcionario"  name="funcionario"  value="<?= $_GET['funcionario'] ?? '' ?>">
        </form>

        </div>
      </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="row g-3 mb-4">
  <!-- Faturamento Bruto -->
  <div class="col-md-4 col-sm-6 animate-fade-in" style="animation-delay: 0.1s">
    <div class="stat-card">
      <div class="stat-icon success">
        <i class="fas fa-dollar-sign"></i>
      </div>
      <div class="stat-details">
        <div class="stat-value text-success">
          R$ <?= number_format($faturamentoBruto, 2, ',', '.') ?>
        </div>
        <div class="stat-label">Faturamento Bruto</div>
        <?php if($filtroAtivo): ?>
          <div class="small text-muted">Período filtrado</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Faturamento – Comissão -->
  <div class="col-md-4 col-sm-6 animate-fade-in" style="animation-delay: 0.2s">
    <div class="stat-card">
      <div class="stat-icon warning">
        <i class="fas fa-percent"></i>
      </div>
      <div class="stat-details">
        <div class="stat-value text-warning">
          R$ <?= number_format($faturamentoAposComissao, 2, ',', '.') ?>
        </div>
        <div class="stat-label">Total de Vendas – Comissão</div>
        <?php if($filtroAtivo): ?>
          <div class="small text-muted">Período filtrado</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Lucro Líquido (já descontadas comissões e despesas) -->
  <div class="col-md-4 col-sm-6 animate-fade-in" style="animation-delay: 0.3s">
    <div class="stat-card">
      <div class="stat-icon primary">
        <i class="fas fa-chart-line"></i>
      </div>
      <div class="stat-details">
        <div class="stat-value text-<?= $lucroAntesDespesas  >= 0 ? 'primary' : 'danger' ?>">
          R$ <?= number_format($lucroAntesDespesas , 2, ',', '.') ?>
        </div>
        <div class="stat-label">Vendas - preço de custo - comissão</div>
        <?php if($filtroAtivo): ?>
          <div class="small text-muted">Período filtrado</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
        <!-- Card: Despesas do Mês Atual -->
        <div class="col-md-4 col-sm-6 animate-fade-in" style="animation-delay: 0.4s">
          <div class="stat-card">
            <div class="stat-icon danger">
              <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-details">
              <div class="stat-value text-danger">
                R$ <?= number_format($totalDespesasMes, 2, ',', '.') ?>
              </div>
              <div class="stat-label">Despesas Mês Atual</div>
              <div class="small text-muted">Mês vigente</div>
            </div>
          </div>
        </div>
        
        <!-- Lucro Líquido Real (Venda – Preço de Custo – Comissão – Despesas) -->
        <div class="col-md-4 col-sm-6 animate-fade-in" style="animation-delay: 0.5s">
          <div class="stat-card">
            <div class="stat-icon success">
              <i class="fas fa-coins"></i>
            </div>
            <div class="stat-details">
              <div class="stat-value text-<?= $lucroLiquidoReal >= 0 ? 'success' : 'danger' ?>">
                R$ <?= number_format($lucroLiquidoReal, 2, ',', '.') ?>
              </div>
              <div class="stat-label">Lucro Líquido Real</div>
              <div class="small text-muted">Mês vigente</div>
            </div>
          </div>
        </div>

        
</div>

    <!-- Ranking de Funcionários (Card Clicável) -->
    <div class="card mb-4 ranking-card animate-fade-in" style="animation-delay: 0.5s" data-bs-toggle="modal" data-bs-target="#funcionariosModal">
      <div class="card-header d-flex flex-column gap-2">
        <h5><i class="fas fa-user-tie"></i> Top 3 Funcionários (Por Comissão)</h5>
        <span class="badge bg-primary">Ver todos</span>
      </div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Posição</th>
              <th>Funcionário</th>
              <th class="text-end">Comissão (R$)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($top3Funcs as $i => $f): ?>
              <tr>
                <td>#<?= $i + 1 ?></td>
                <td><?= htmlspecialchars($f['nome']) ?></td>
                <td class="text-end">R$ <?= number_format($f['comissao_atual'], 2, ',', '.') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($top3Funcs)): ?>
              <tr>
                <td colspan="3" class="text-center py-3">Nenhum funcionário cadastrado.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="see-all">
        <i class="fas fa-chart-bar me-2"></i> Ver ranking completo
      </div>
    </div>



    <div class="row">
      <!-- Gráfico Financeiro (Linha) -->
      <div class="col-lg-8 animate-fade-in" style="animation-delay: 0.7s">
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-chart-area"></i> Faturamento Mensal</h5>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="chartOptionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
              </button>
              <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="chartOptionsDropdown">
                <li><a class="dropdown-item" href="#"><i class="fas fa-sync me-2"></i>Atualizar</a></li>
                <li><a class="dropdown-item" href="#"><i class="fas fa-expand me-2"></i>Tela cheia</a></li>
              </ul>
            </div>
          </div>
          <div class="card-body">
            <div class="chart-container">
              <canvas id="financeChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Gráfico de Pizza: Despesas vs Vendas -->
      <div class="col-lg-4 animate-fade-in" style="animation-delay: 0.8s">
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-chart-pie"></i> Despesas x Vendas</h5>
          </div>
          <div class="card-body">
            <div class="chart-container">
              <canvas id="pieChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <!-- Gráfico de Barras: Vendas por Status -->
      <div class="col-lg-6 animate-fade-in" style="animation-delay: 0.9s">
        <div class="card mb-4">
          <div class="card-header">
            <h5><i class="fas fa-chart-bar"></i> Vendas por Status</h5>
          </div>
          <div class="card-body">
            <div class="chart-container">
              <canvas id="barChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Transações Recentes -->
      <div class="col-lg-6 animate-fade-in" style="animation-delay: 1s">
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-history"></i> Transações Recentes</h5>
            
          </div>
          <div class="card-body p-0">
            <?php
            if (!empty($transacoesRecentes)):
            ?>
              <div class="transaction-list">
                <?php foreach ($transacoesRecentes as $t):
                  $badgeClass = ($t['tipo'] === 'Venda') ? 'success' : 'danger';
                ?>
                  <div class="transaction-item">
                    <div class="d-flex justify-content-between align-items-center">
                      <div class="d-flex align-items-center">
                        <div class="transaction-icon <?= $badgeClass ?>">
                          <i class="fas fa-<?= ($t['tipo'] === 'Venda') ? 'shopping-cart' : 'money-bill-wave' ?>"></i>
                        </div>
                        <div class="transaction-details">
                          <h6><?= $t['tipo'] ?> #<?= $t['id'] ?></h6>
                          <small class="text-muted"><?= date('d/m/Y', strtotime($t['data'])) ?></small>
                          <p class="small text-muted mb-0"><?= $t['descricao'] ?></p>
                        </div>
                      </div>
                      <div class="transaction-amount text-<?= $badgeClass ?>">
                        R$ <?= number_format($t['valor'], 2, ',', '.') ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>Nenhuma transação encontrada.</p>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-footer text-center">
            <a href="#" class="btn d-none btn-sm btn-outline-secondary w-100" id="loadMoreBtn">Carregar mais</a>
          </div>
        </div>
      </div>
    </div>

    <!-- Todas as Transações (Tabela com paginação) -->
    <div class="card-header d-flex flex-column gap-2">
      <div class="d-flex justify-content-between align-items-center p-3">
        <h5><i class="fas fa-list"></i> Todas as Transações</h5>
      </div>

     <form id="transactionsFilterForm" class="row g-2 align-items-end p-3">
      <div class="col-auto">
        <label for="filterTableDataInicial" class="form-label">Data Início</label>
        <input type="date" id="filterTableDataInicial" class="form-control" value="<?= htmlspecialchars($dataInicial) ?>">
      </div>

      <div class="col-auto">
        <label for="filterTableDataFinal" class="form-label">Data Fim</label>
        <input type="date" id="filterTableDataFinal" class="form-control" value="<?= htmlspecialchars($dataFinal) ?>">
      </div>

      <div class="col-auto">
        <label for="filterTableTipo" class="form-label">Tipo</label>
        <select id="filterTableTipo" class="form-select">
          <option value="ambos" <?= $tipoFiltro === 'ambos' ? 'selected' : '' ?>>Ambos</option>
          <option value="despesas" <?= $tipoFiltro === 'despesas' ? 'selected' : '' ?>>Despesas</option>
          <option value="vendas" <?= $tipoFiltro === 'vendas' ? 'selected' : '' ?>>Vendas</option>
        </select>
      </div>

      <div class="col-auto">
      <label for="filterTableFuncionario" class="form-label">Funcionário</label>
        <select id="filterTableFuncionario" name="funcionario" class="form-select">
          <option value="">Todos</option>
          <?php foreach($todosFuncs as $f): ?>
            <option
              value="<?= $f['id_funcionario'] ?>"
              <?= (isset($_GET['funcionario']) && $_GET['funcionario']==$f['id_funcionario']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($f['nome']) ?>
            </option>
            <?php endforeach; ?>
       </select>
      </div>

    <div class="col-auto">
      <button type="submit" class="btn btn-sm btn-primary">
        <i class="fas fa-search me-1"></i> Filtrar
      </button>
    </div>
  </form>
</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0" id="transactionsTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Tipo</th>
                <th>Data</th>
                <th>Descrição</th>
                <th>Funcionário</th>
                <th>Valor</th>
              </tr>
            </thead>
            <tbody id="transactionsTableBody">
              <?php
              $listaTransacoesAll = array_slice($transacoes, 0, 10);
              if (!empty($listaTransacoesAll)):
                  foreach ($listaTransacoesAll as $t):
                      $badgeClass = ($t['tipo'] === 'Venda') ? 'success' : 'danger';
              ?>
                <tr>
                  <td><span class="badge bg-<?= $badgeClass ?>"><?= $t['tipo'] ?></span></td>
                  <td><?= date('d/m/Y', strtotime($t['data'])) ?></td>
                  <td><?= $t['descricao'] ?></td>
                  <td><?= htmlspecialchars($t['funcionario'] ?? '—') ?></td>
                  <td class="text-end fw-bold text-<?= $badgeClass ?>">R$ <?= number_format($t['valor'], 2, ',', '.') ?></td>
                </tr>
              <?php
                  endforeach;
              else:
              ?>
                <tr>
                  <td colspan="5" class="text-center py-4">
                    <div class="empty-state">
                      <i class="fas fa-inbox"></i>
                      <p>Nenhuma transação encontrada.</p>
                    </div>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer bg-white">
        <nav aria-label="Paginação de transações">
          <ul class="pagination justify-content-center p-3" id="paginationControls">
            <li class="page-item" id="prevPage"><a class="page-link" href="#"><i class="fas fa-chevron-left"></i></a></li>
            <li class="page-item active"><a class="page-link" href="#">1</a></li>
            <li class="page-item" id="nextPage"><a class="page-link" href="#"><i class="fas fa-chevron-right"></i></a></li>
          </ul>
        </nav>
      </div>
    </div>
  </div>

  <!-- Modal de Funcionários -->
  <div class="modal fade" id="funcionariosModal" tabindex="-1" aria-labelledby="funcionariosModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="funcionariosModalLabel"><i class="fas fa-user-tie me-2"></i>Ranking Completo de Funcionários</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- Gráfico de Barras para Funcionários -->
          <div class="modal-chart-container">
            <canvas id="funcionariosChart"></canvas>
          </div>
          
          <!-- Tabela Completa de Funcionários -->
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Posição</th>
                  <th>Funcionário</th>
                  <th class="text-end">Comissão (R$)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rankingFuncs as $i => $f): ?>
                  <tr>
                    <td>#<?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($f['nome']) ?></td>
                    <td class="text-end">R$ <?= number_format($f['comissao_atual'], 2, ',', '.') ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($rankingFuncs)): ?>
                  <tr>
                    <td colspan="3" class="text-center py-3">Nenhum funcionário cadastrado.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>

        </div>
      </div>
    </div>
  </div>

  <!-- Floating Action Button (Mobile) -->
  <button class="floating-action-btn d-none" id="floatingActionBtn">
    <i class="fas fa-plus"></i>
  </button>

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

  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // JAVASCRIPT CORRIGIDO PARA MOBILE
    
    // Variáveis globais
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mobileToggle = document.getElementById('desktopSidebarToggle');
    
    // Função para detectar se é dispositivo móvel
    function isMobileDevice() {
      return window.innerWidth <= 991.98;
    }
    
    // Ajustar layout quando a orientação mudar
    window.addEventListener('orientationchange', function() {
      setTimeout(function() {
        window.dispatchEvent(new Event('resize'));
      }, 100);
    });
    
    // Corrigir problemas de viewport em dispositivos móveis
    function setViewportHeight() {
      const vh = window.innerHeight * 0.01;
      document.documentElement.style.setProperty('--vh', `${vh}px`);
    }
    
    setViewportHeight();
    window.addEventListener('resize', setViewportHeight);
    
    // Ajustar comportamento baseado no dispositivo
    function adjustForDevice() {
      const mainContent = document.querySelector('.main-content');
      const topNavbar = document.querySelector('.top-navbar');
      
      if (isMobileDevice()) {
        // Ajustes específicos para mobile
        if (mainContent) {
          mainContent.style.marginLeft = '0';
        }
        if (topNavbar) {
          topNavbar.style.left = '0';
        }
      } else {
        // Ajustes para desktop
        if (mainContent) {
          mainContent.style.marginLeft = 'var(--sidebar-width)';
        }
        if (topNavbar) {
          topNavbar.style.left = 'var(--sidebar-width)';
        }
      }
    }
    
    // Executar ajustes quando a janela redimensionar
    window.addEventListener('resize', adjustForDevice);
    window.addEventListener('load', adjustForDevice);
    
    // Corrigir problemas de touch em dispositivos móveis
    if ('ontouchstart' in window) {
      document.body.classList.add('touch-device');
    }
    
    // Prevenir zoom em inputs no iOS
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(function(input) {
      if (input.style.fontSize === '' || parseFloat(input.style.fontSize) < 16) {
        input.style.fontSize = '16px';
      }
    });
    
    // Melhorar performance de scroll em mobile
    let ticking = false;
    
    function updateScrollPosition() {
      document.body.classList.add('scrolling');
      
      clearTimeout(window.scrollTimeout);
      window.scrollTimeout = setTimeout(function() {
        document.body.classList.remove('scrolling');
      }, 150);
      
      ticking = false;
    }
    
    window.addEventListener('scroll', function() {
      if (!ticking) {
        requestAnimationFrame(updateScrollPosition);
        ticking = true;
      }
    });
    
    // Floating action button
    document.getElementById('floatingActionBtn').addEventListener('click', function() {
      alert('Nova transação será implementada em breve!');
    });
    
    // GRÁFICO DE LINHA - Faturamento Mensal
    const ctx = document.getElementById('financeChart').getContext('2d');
    const financeChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [{
                label: 'Faturamento Mensal (R$)',
                data: <?= json_encode($dataGrafico) ?>,
                borderColor: 'rgba(90, 90, 243, 0.7)',
                backgroundColor: 'rgba(90, 90, 243, 0.1)',
                fill: true,
                tension: 0.2,
                pointBackgroundColor: 'rgba(90, 90, 243, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { 
                    grid: { 
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                },
                y: { 
                    grid: { 
                        borderDash: [3, 3],
                        drawBorder: false
                    },
                    beginAtZero: true,
                    ticks: {
                        font: {
                            size: 11
                        },
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    }
                }
            },
            plugins: {
                legend: { 
                    display: false 
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleFont: { size: 13, weight: 'bold' },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 4,
                    callbacks: {
                        label: function(context) {
                            return 'R$ ' + context.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    }
                }
            }
        }
    });

    // GRÁFICO DE PIZZA - Despesas x Vendas
    const ctxPie = document.getElementById('pieChart').getContext('2d');
    const pieChart = new Chart(ctxPie, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($pizzaLabels) ?>,
            datasets: [{
                data: <?= json_encode($pizzaData) ?>,
                backgroundColor: ['rgba(240, 98, 146, 0.85)', 'rgba(76, 175, 80, 0.85)'],
                hoverBackgroundColor: ['rgba(240, 98, 146, 1)', 'rgba(76, 175, 80, 1)'],
                borderWidth: 0,
                borderRadius: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleFont: { size: 13, weight: 'bold' },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 4,
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed;
                            const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return context.label + ': R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // GRÁFICO DE BARRAS - Vendas por Status
    const ctxBar = document.getElementById('barChart').getContext('2d');
    const barChart = new Chart(ctxBar, {
        type: 'bar',
        data: {
            labels: <?= json_encode($barLabels) ?>,
            datasets: [{
                label: 'Quantidade de Vendas',
                data: <?= json_encode($barData) ?>,
                backgroundColor: 'rgba(90, 90, 243, 0.7)',
                borderColor: 'rgba(90, 90, 243, 1)',
                borderWidth: 0,
                borderRadius: 6,
                maxBarThickness: 35,
                hoverBackgroundColor: 'rgba(90, 90, 243, 0.9)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { 
                    beginAtZero: true,
                    grid: { 
                        borderDash: [3, 3],
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        },
                        stepSize: 1
                    }
                },
                x: {
                    grid: { 
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                }
            },
            plugins: {
                legend: { 
                    display: false 
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleFont: { size: 13, weight: 'bold' },
                    bodyFont: { size: 12 },
                    padding: 10,
                    cornerRadius: 4
                }
            }
        }
    });
    
    // GRÁFICO DE BARRAS - Funcionários (no Modal)
    let funcionariosChart;
    
    // Função para criar ou atualizar o gráfico de funcionários
    function initFuncionariosChart() {
      const ctxFunc = document.getElementById('funcionariosChart').getContext('2d');
      
      // Gerar cores para cada funcionário
      const generateColors = (count) => {
        const colors = [];
        const baseColors = [
          'rgba(78, 84, 233, 0.8)',
          'rgba(16, 185, 129, 0.8)',
          'rgba(245, 158, 11, 0.8)',
          'rgba(239, 68, 68, 0.8)',
          'rgba(139, 92, 246, 0.8)',
          'rgba(14, 165, 233, 0.8)',
          'rgba(236, 72, 153, 0.8)'
        ];
        
        for (let i = 0; i < count; i++) {
          colors.push(baseColors[i % baseColors.length]);
        }
        
        return colors;
      };
      
      const backgroundColor = generateColors(<?= count($funcNomes) ?>);
      
      // Se o gráfico já existe, destrua-o para recriar
      if (funcionariosChart) {
        funcionariosChart.destroy();
      }
      
      funcionariosChart = new Chart(ctxFunc, {
        type: 'bar',
        data: {
          labels: <?= json_encode($funcNomes) ?>,
          datasets: [{
            label: 'Comissão (R$)',
            data: <?= json_encode($funcComissoes) ?>,
            backgroundColor: backgroundColor,
            borderColor: backgroundColor.map(color => color.replace('0.8', '1')),
            borderWidth: 0,
            borderRadius: 6,
            maxBarThickness: 50
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              grid: {
                borderDash: [3, 3],
                drawBorder: false
              },
              ticks: {
                font: {
                  size: 11
                },
                callback: function(value) {
                  return 'R$ ' + value.toLocaleString('pt-BR');
                }
              }
            },
            x: {
              grid: {
                display: false,
                drawBorder: false
              },
              ticks: {
                font: {
                  size: 11
                }
              }
            }
          },
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              backgroundColor: 'rgba(0, 0, 0, 0.8)',
              titleFont: { size: 13, weight: 'bold' },
              bodyFont: { size: 12 },
              padding: 10,
              cornerRadius: 4,
              callbacks: {
                label: function(context) {
                  return 'R$ ' + context.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
              }
            }
          }
        }
      });
    }
    
    // Inicializar o gráfico quando o modal for aberto
    document.getElementById('funcionariosModal').addEventListener('shown.bs.modal', function() {
      initFuncionariosChart();
    });

    // PAGINAÇÃO DA TABELA "TODAS AS TRANSAÇÕES"
    let currentPage = 1;
    const limit = 10;
    let totalPages = 1;

    function loadTransactionsPage(page) {
      const mesAno      = document.getElementById('mes_ano').value;
      const dataInicial = document.getElementById('filterTableDataInicial').value;
      const dataFinal   = document.getElementById('filterTableDataFinal').value;
      const tipo        = document.getElementById('filterTableTipo').value;
      const func        = document.getElementById('filterTableFuncionario').value;
        
        const url = 
    `index.php?ajax_transactions=1` +
    `&page=${page}` +
    `&mes_ano=${mesAno}` +
    `&data_inicial=${dataInicial}` +
    `&data_final=${dataFinal}` +
    `&tipo=${tipo}` +
    `&funcionario=${func}`;
    
       fetch(url)
        .then(response => response.json())
        .then(data => {
            document.getElementById('transactionsTableBody').innerHTML = data.html;
            totalPages = data.totalPages;
            currentPage = data.currentPage;
            updatePaginationControls();
        })
        .catch(error => {
            console.error('Erro ao carregar transações:', error);
        });
    }

    // When the small filter-form is submitted, reload page 1 with new filters
    document.getElementById('transactionsFilterForm')
      .addEventListener('submit', function(e) {
        e.preventDefault();
        // pega os valores do formulário
        const dIni = document.getElementById('filterTableDataInicial').value;
        const dFim = document.getElementById('filterTableDataFinal').value;
        const tipo = document.getElementById('filterTableTipo').value;
        // atualiza os inputs ocultos (ou hidden) que o loadTransactionsPage lê
        document.getElementById('data_inicial').value = dIni;
        document.getElementById('data_final').value   = dFim;
        document.getElementById('tipo').value         = tipo;
        // carrega via AJAX
        loadTransactionsPage(1);
      });

    function updatePaginationControls() {
        const prevBtn = document.getElementById('prevPage');
        const nextBtn = document.getElementById('nextPage');
        prevBtn.classList.toggle('disabled', currentPage <= 1);
        nextBtn.classList.toggle('disabled', currentPage >= totalPages);
        
        // Atualiza o número da página exibida
        const paginationControls = document.getElementById('paginationControls');
        // Remover itens existentes entre os botões
        while (paginationControls.children.length > 2) {
            paginationControls.removeChild(paginationControls.children[1]);
        }
        
        // Adiciona a página atual
        const pageItem = document.createElement('li');
        pageItem.className = 'page-item active';
        pageItem.innerHTML = `<a class="page-link" href="#">${currentPage} / ${totalPages}</a>`;
        paginationControls.insertBefore(pageItem, nextBtn);
    }

    // Botões de paginação
    document.getElementById('prevPage').addEventListener('click', function(e) {
        e.preventDefault();
        if (currentPage > 1) {
            loadTransactionsPage(currentPage - 1);
        }
    });
    
    document.getElementById('nextPage').addEventListener('click', function(e) {
        e.preventDefault();
        if (currentPage < totalPages) {
            loadTransactionsPage(currentPage + 1);
        }
    });

    // Botão "Carregar mais" para a lista de transações recentes
    document.getElementById('loadMoreBtn').addEventListener('click', function(e) {
        e.preventDefault();
        let nextPage = currentPage + 1;
        if (nextPage <= totalPages) {
            loadTransactionsPage(nextPage);
        }
    });

    // Dark Mode Toggle
    function toggleDarkMode() {
      const btnDark = document.querySelector('#DarkModeButton');
      // Alterna a classe no body
      document.body.classList.toggle('dark-mode');
      
      // Verifica se ficou dark
      const isDark = document.body.classList.contains('dark-mode');
      // Salva no localStorage
      localStorage.setItem('darkMode', isDark ? 'true' : 'false');
      
      // Altera o ícone: 'fa-sun' se dark, 'fa-moon' se light
      btnDark.innerHTML = isDark 
        ? '<i class="fas fa-sun"></i>' 
        : '<i class="fas fa-moon"></i>';
        
      // Atualiza o gráfico de funcionários se o modal estiver aberto
      if (document.getElementById('funcionariosModal').classList.contains('show')) {
        initFuncionariosChart();
      }
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadTransactionsPage(1);
        
        // Animar elementos com fade-in
        const animatedElements = document.querySelectorAll('.animate-fade-in');
        animatedElements.forEach((element, index) => { 
            element.style.animationDelay = `${0.1 * index}s`;
            element.style.opacity = '1'; 
        });
        
        // Toggle do filtro
        const filterToggle = document.querySelector('.filter-toggle');
        if (filterToggle) {
            filterToggle.addEventListener('click', function() {
                const icon = this.querySelector('.fas');
                if (icon.classList.contains('fa-chevron-down')) {
                    icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                } else {
                    icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                }
            });
        }
        
        // Filtros pré-definidos
       // Seletor de Mês/Ano
const mesAnoInput = document.getElementById('mes_ano');
mesAnoInput.addEventListener('change', function() {
  const [y, m] = this.value.split('-');
  const primeiro = `${y}-${m}-01`;
  // calcula último dia do mês:
  const ultimo = new Date(y, parseInt(m), 0).toISOString().slice(0,10);
  
  // preenche os inputs de data
  document.getElementById('data_inicial').value = primeiro;
  document.getElementById('data_final').value   = ultimo;
  
  // submete o formulário de filtro principal
  document.getElementById('filtroPeriodoForm').submit();
});

        
        // Dark Mode
        const btnDark = document.querySelector('#DarkModeButton');
        if (btnDark) {
            // Se já estiver salvo no localStorage, aplica
            if (localStorage.getItem('darkMode') === 'true') {
                document.body.classList.add('dark-mode');
                btnDark.innerHTML = '<i class="fas fa-sun"></i>';
            }
            
            // Ao clicar, chama toggleDarkMode
            btnDark.addEventListener('click', toggleDarkMode);
        }
    });
  </script>
</body>
</html>
