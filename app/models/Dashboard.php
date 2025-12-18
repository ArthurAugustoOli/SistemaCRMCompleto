<?php
namespace App\Models;

use Exception;

class Dashboard {
    private $conn;

    public function __construct() {
        require_once __DIR__ . '/../config/config.php';
        global $mysqli;
        $this->conn = $mysqli;
    }

    /**
     * 1. Estatísticas básicas do dashboard:
     * - Vendas hoje (quantidade e faturamento)
     * - Total de produtos
     * - Total de clientes
     */
    public function getEstatisticasDashboard() {
        $stats = [];

        // Vendas Hoje (utilizando data de venda atual – CURDATE())
        $sql = "SELECT COUNT(*) AS vendas_hoje, COALESCE(SUM(total_venda),0) AS faturamento_hoje 
                FROM vendas WHERE DATE(data_venda) = CURDATE()";
        $result = $this->conn->query($sql);
        $row = $result->fetch_assoc();
        $stats['vendas_hoje'] = (int)$row['vendas_hoje'];
        $stats['faturamento_hoje'] = (float)$row['faturamento_hoje'];

        // Total de Produtos
        $sql = "SELECT COUNT(*) AS total_produtos FROM produtos";
        $result = $this->conn->query($sql);
        $row = $result->fetch_assoc();
        $stats['total_produtos'] = (int)$row['total_produtos'];

        // Total de Clientes
        $sql = "SELECT COUNT(*) AS total_clientes FROM clientes";
        $result = $this->conn->query($sql);
        $row = $result->fetch_assoc();
        $stats['total_clientes'] = (int)$row['total_clientes'];

        return $stats;
    }

    /**
     * 2. Valor total do estoque dos produtos.
     */
    public function getValorEstoqueTotal() {
        $sql = "SELECT COALESCE(SUM(preco_venda * estoque_atual), 0) AS valor_total FROM produtos";
        $result = $this->conn->query($sql);
        $row = $result->fetch_assoc();
        return (float)$row['valor_total'];
    }

    /**
     * 3. Faturamento Mensal (jan a dez)
     */
    public function getFaturamentoMensal() {
        $faturamento = array_fill(1, 12, 0.0);
        $sql = "SELECT MONTH(data_venda) AS mes, COALESCE(SUM(total_venda),0) AS total FROM vendas GROUP BY mes";
        $result = $this->conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $mes = (int)$row['mes'];
            $faturamento[$mes] = (float)$row['total'];
        }
        return $faturamento;
    }

    /**
     * 4. Faturamento dos Últimos 7 Dias
     */
    public function getFaturamentoUltimos7Dias() {
        $faturamento = [];
        for ($i = 6; $i >= 0; $i--) {
            $data = date('Y-m-d', strtotime("-$i days"));
            $faturamento[$data] = 0.0;
        }
        $sql = "SELECT DATE(data_venda) AS data, COALESCE(SUM(total_venda),0) AS total 
                FROM vendas 
                WHERE data_venda >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
                GROUP BY data";
        $result = $this->conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $data = $row['data'];
            $faturamento[$data] = (float)$row['total'];
        }
        return $faturamento;
    }

    /**
     * 5. Distribuição de Estoque – Retorna arrays com nomes dos produtos e seus estoques atuais
     */
    public function getDistribuicaoEstoque() {
        $produtos = [];
        $estoques = [];
        $sql = "
          SELECT 
            p.nome,
            COALESCE(SUM(v.estoque_atual), p.estoque_atual) AS estoque_total
          FROM produtos p
          LEFT JOIN produto_variacoes v ON p.id_produto = v.id_produto
          GROUP BY p.id_produto
          ORDER BY p.nome
        ";
        $result = $this->conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $produtos[] = $row['nome'];
            $estoques[] = (int)$row['estoque_total'];
        }
        return ['produtos' => $produtos, 'estoques' => $estoques];
    }

    /**
     * 6. Top 5 Produtos por Estoque Atual
     */
    public function getTopProdutos() {
        $top = [];
        $sql = "
          SELECT 
            p.nome,
            COALESCE(SUM(v.estoque_atual), p.estoque_atual) AS estoque_total
          FROM produtos p
          LEFT JOIN produto_variacoes v ON p.id_produto = v.id_produto
          GROUP BY p.id_produto
          ORDER BY estoque_total DESC
          LIMIT 5
        ";
        $result = $this->conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $top[$row['nome']] = (int)$row['estoque_total'];
        }
        return $top;
    }

    /**
     * 7. Novos Clientes por Mês (com base na data de cadastro)
     */
    public function getClientesPorMes() {
        $clientes = array_fill(1, 12, 0);
        $sql = "SELECT MONTH(data_cadastro) as mes, COUNT(*) as total FROM clientes GROUP BY mes";
        $result = $this->conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $mes = (int)$row['mes'];
            $clientes[$mes] = (int)$row['total'];
        }
        return $clientes;
    }

    /**
     * 8. Top 5 Clientes por Faturamento
     */
    public function getTopClientes() {
        $topClientes = [];
        $sql = "
          SELECT c.nome AS cliente, COALESCE(SUM(v.total_venda), 0) AS faturamento
          FROM vendas v
          JOIN clientes c ON v.id_cliente = c.id_cliente
          GROUP BY v.id_cliente
          ORDER BY faturamento DESC
          LIMIT 5
        ";
        $result = $this->conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $topClientes[$row['cliente']] = (float)$row['faturamento'];
        }
        return $topClientes;
    }

    /**
     * 10. Fluxo de Caixa – Retorna os registros do caixa ordenados por data
     */
    public function getFluxoCaixa() {
        $fluxo = [];
        $sql = "SELECT data_caixa, total_entradas, total_saidas, saldo_final FROM caixa ORDER BY data_caixa ASC";
        $result = $this->conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            $fluxo[] = [
                'data' => $row['data_caixa'],
                'entradas' => (float)$row['total_entradas'],
                'saidas' => (float)$row['total_saidas'],
                'saldo' => (float)$row['saldo_final']
            ];
        }
        return $fluxo;
    }

    /**
     * 11. Movimentações Bancárias – Agrupadas por data e tipo (crédito ou débito)
     */
    public function getMovimentacoesBancarias() {
        $movimentos = [];
        $sql = "SELECT DATE(data_movimentacao) as data, tipo, COALESCE(SUM(valor),0) as total 
                FROM movimentacoes_bancarias 
                GROUP BY data, tipo 
                ORDER BY data ASC";
        $result = $this->conn->query($sql);
        while($row = $result->fetch_assoc()){
            $movimentos[] = [
                'data' => $row['data'],
                'tipo' => $row['tipo'],
                'total' => (float)$row['total']
            ];
        }
        return $movimentos;
    }

    /**
     * 12. Comissões por Funcionário
     */
    public function getComissoesPorFuncionario() {
        $comissoes = [];
        $sql = "
            SELECT f.nome, COALESCE(SUM(c.valor_comissao),0) AS total_comissao
            FROM comissoes c
            JOIN funcionarios f ON c.id_funcionario = f.id_funcionario
            GROUP BY c.id_funcionario
            ORDER BY total_comissao DESC
        ";
        $result = $this->conn->query($sql);
        while($row = $result->fetch_assoc()){
            $comissoes[$row['nome']] = (float)$row['total_comissao'];
        }
        return $comissoes;
    }

    /**
     * 13. Vendas por Produto – Ranking (por quantidade vendida)
     */
    public function getVendasPorProduto() {
        $vendas = [];
        $sql = "
            SELECT p.nome, COALESCE(SUM(iv.quantidade),0) AS total_vendidos
            FROM itens_venda iv
            JOIN produtos p ON iv.id_produto = p.id_produto
            GROUP BY iv.id_produto
            ORDER BY total_vendidos DESC
            LIMIT 10
        ";
        $result = $this->conn->query($sql);
        while($row = $result->fetch_assoc()){
            $vendas[$row['nome']] = (int)$row['total_vendidos'];
        }
        return $vendas;
    }

    /**
     * 14. Despesas por Categoria
     */
    public function getDespesasPorCategoria() {
        $despesas = [];
        $sql = "SELECT categoria, COALESCE(SUM(valor),0) as total FROM despesas GROUP BY categoria";
        $result = $this->conn->query($sql);
        while($row = $result->fetch_assoc()){
            $despesas[$row['categoria']] = (float)$row['total'];
        }
        return $despesas;
    }

    /**
     * 15. Comparação: Despesas vs Receitas (vendas)
     */
    public function getComparacaoDespesasReceitas() {
        // Total de despesas
        $sqlDesp = "SELECT COALESCE(SUM(valor), 0) as total FROM despesas";
        $resDesp = $this->conn->query($sqlDesp);
        $rowDesp = $resDesp->fetch_assoc();
        $totalDespesas = (float)$rowDesp['total'];

        // Total de vendas (faturamento)
        $sqlVendas = "SELECT COALESCE(SUM(total_venda), 0) as total FROM vendas";
        $resVendas = $this->conn->query($sqlVendas);
        $rowVendas = $resVendas->fetch_assoc();
        $totalVendas = (float)$rowVendas['total'];

        return ['despesas' => $totalDespesas, 'receitas' => $totalVendas];
    }

    /**
     * 16. Margem de Lucro por Produto
     * Calcula o lucro unitário e total (lucro_unitario * quantidade_vendida)
     */
    public function getMargemLucroPorProduto() {
        $margens = [];
        $sql = "
            SELECT p.nome, p.preco_venda, p.preco_custo, COALESCE(SUM(iv.quantidade), 0) as total_vendidos
            FROM produtos p
            LEFT JOIN itens_venda iv ON p.id_produto = iv.id_produto
            GROUP BY p.id_produto
        ";
        $result = $this->conn->query($sql);
        while($row = $result->fetch_assoc()){
            $lucroUnitario = $row['preco_venda'] - $row['preco_custo'];
            $lucroTotal = $lucroUnitario * $row['total_vendidos'];
            $margens[$row['nome']] = [
                'lucro_unitario' => $lucroUnitario,
                'total_vendidos' => (int)$row['total_vendidos'],
                'lucro_total' => $lucroTotal
            ];
        }
        return $margens;
    }

    /**
     * 17. Histórico de Movimentação de Estoque
     */
    public function getMovimentacaoEstoqueHistorico() {
        $historico = [];
        $sql = "SELECT id_movimento, id_produto, tipo_movimento, quantidade, data_movimento, origem, observacoes 
                FROM movimentacao_estoque 
                ORDER BY data_movimento ASC";
        $result = $this->conn->query($sql);
        while($row = $result->fetch_assoc()){
            $historico[] = $row;
        }
        return $historico;
    }

    /**
     * 18. Status das Parcelas de Crediário
     */
    public function getParcelasCrediarioStatus() {
        $status = ['pago' => 0, 'pendente' => 0];
        $sql = "SELECT status, COUNT(*) as qtd FROM parcelas_crediario GROUP BY status";
        $result = $this->conn->query($sql);
        while($row = $result->fetch_assoc()){
            $status[$row['status']] = (int)$row['qtd'];
        }
        return $status;
    }

    /**
     * 19. Histórico de Mensagens (por tipo)
     */
    public function getHistoricoMensagens() {
        $mensagens = [];
        $sql = "SELECT tipo_mensagem, COUNT(*) as total FROM mensagens GROUP BY tipo_mensagem";
        $result = $this->conn->query($sql);
        while($row = $result->fetch_assoc()){
            $mensagens[$row['tipo_mensagem']] = (int)$row['total'];
        }
        return $mensagens;
    }
}
?>
