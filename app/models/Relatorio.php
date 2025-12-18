<?php
namespace App\Models;

class Relatorio
{
    private $mysqli;

    public function __construct(\mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Retorna um array com faturamento de cada mês do ano (1–12).
     * Ex: [1=>1234.56, 2=>2345.67, …, 12=>0.00]
     */
    public function getFaturamentoMensal(int $ano): array
    {
        $stmt = $this->mysqli->prepare("
            SELECT MONTH(data_venda) AS mes, SUM(total_venda) AS valor
              FROM vendas
             WHERE YEAR(data_venda)=?
             GROUP BY mes
        ");
        $stmt->bind_param("i", $ano);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Preenche todos os meses com zero
        $resultado = array_fill(1, 12, 0.0);
        foreach ($rows as $r) {
            $resultado[(int)$r['mes']] = (float)$r['valor'];
        }
        return $resultado;
    }

    /**
     * Retorna faturamento diário entre duas datas (inclusive).
     * Ex: ['2025-06-01'=>123.45, '2025-06-02'=>0.00, …]
     */
    public function getFaturamentoPorPeriodo(string $inicio, string $fim): array
    {
        $stmt = $this->mysqli->prepare("
            SELECT DATE(data_venda) AS dia, SUM(total_venda) AS valor
              FROM vendas
             WHERE DATE(data_venda) BETWEEN ? AND ?
             GROUP BY dia
             ORDER BY dia
        ");
        $stmt->bind_param("ss", $inicio, $fim);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Garante que cada dia do período apareça, mesmo sem vendas
        $dtInicio = new \DateTime($inicio);
        $dtFim    = new \DateTime($fim);
        $interval = new \DateInterval('P1D');
        $periodo  = new \DatePeriod($dtInicio, $interval, $dtFim->modify('+1 day'));

        $resultado = [];
        foreach ($periodo as $d) {
            $resultado[$d->format('Y-m-d')] = 0.0;
        }
        foreach ($rows as $r) {
            $resultado[$r['dia']] = (float)$r['valor'];
        }
        return $resultado;
    }

    /**
     * Retorna um array associativo [cliente=>faturamento] dos top N clientes
     * no período informado.
     */
    public function getTopClientesPorFaturamento(string $inicio, string $fim, int $limite = 5): array
    {
        $stmt = $this->mysqli->prepare("
            SELECT c.nome AS cliente, SUM(v.total_venda) AS faturamento
              FROM vendas v
              JOIN clientes c ON v.id_cliente = c.id_cliente
             WHERE DATE(v.data_venda) BETWEEN ? AND ?
             GROUP BY c.id_cliente
             ORDER BY faturamento DESC
             LIMIT ?
        ");
        $stmt->bind_param("ssi", $inicio, $fim, $limite);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $resultado = [];
        foreach ($rows as $r) {
            $resultado[$r['cliente']] = (float)$r['faturamento'];
        }
        return $resultado;
    }

    /**
     * Retorna um array de até N produtos mais vendidos no período,
     * cada item com ['nome_produto'=>string, 'quantidade'=>int].
     */
    public function getTopProdutosVendidos(string $inicio, string $fim, int $limite = 5): array
    {
        $sql = "
            SELECT 
                COALESCE(pv.id_produto, iv.id_produto) AS id_produto,
                COALESCE(p.nome, p2.nome)           AS nome_produto,
                SUM(iv.quantidade)                  AS quantidade
              FROM itens_venda iv
              JOIN vendas v ON iv.id_venda = v.id_venda
         LEFT JOIN produto_variacoes pv ON iv.id_variacao = pv.id_variacao
         LEFT JOIN produtos p            ON pv.id_produto = p.id_produto
         LEFT JOIN produtos p2           ON iv.id_produto = p2.id_produto
             WHERE DATE(v.data_venda) BETWEEN ? AND ?
             GROUP BY nome_produto
             ORDER BY quantidade DESC
             LIMIT ?
        ";
        $stmt = $this->mysqli->prepare($sql);
        $stmt->bind_param("ssi", $inicio, $fim, $limite);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * Estatísticas do painel: vendas hoje, faturamento hoje,
     * total de produtos e total de clientes.
     */
    public function getEstatisticasDashboard(): array
    {
        $hoje = date('Y-m-d');
        $stmt = $this->mysqli->prepare("
            SELECT COUNT(*) AS vendas_hoje, 
                   COALESCE(SUM(total_venda),0) AS faturamento_hoje
              FROM vendas
             WHERE DATE(data_venda)=?
        ");
        $stmt->bind_param("s", $hoje);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $prod = $this->mysqli->query("SELECT COUNT(*) AS total_produtos FROM produtos")
                           ->fetch_assoc();
        $cli  = $this->mysqli->query("SELECT COUNT(*) AS total_clientes FROM clientes")
                           ->fetch_assoc();

        return [
            'vendas_hoje'      => (int)$stats['vendas_hoje'],
            'faturamento_hoje' => (float)$stats['faturamento_hoje'],
            'total_produtos'   => (int)$prod['total_produtos'],
            'total_clientes'   => (int)$cli['total_clientes'],
        ];
    }
}
