<?php

class Venda {
    private $conn;
    
        private const METODOS_PAGAMENTO = [
        'cartao_credito',
        'pix',
        'dinheiro',
        'cartao_debito'
    ];

    public function __construct($mysqli) {
        $this->conn = $mysqli;
    }

    /**
     * Retorna todas as vendas com o nome do cliente.
     */
    public function getAll(): array {
        $sql = "SELECT v.*, c.nome AS nome_cliente
                FROM vendas v
                LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
                ORDER BY v.data DESC";
        $result = $this->conn->query($sql);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Retorna uma venda pelo ID.
     */
    public function getById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM vendas WHERE id_venda = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Cria uma venda simples (sem funcionário).
     */
    public function criar(int $id_cliente): int {
        $stmt = $this->conn->prepare("INSERT INTO vendas (id_cliente, data) VALUES (?, NOW())");
        $stmt->bind_param("i", $id_cliente);
        $stmt->execute();
        return $stmt->insert_id;
    }

    /**
     * Insere venda com data e opcional funcionário.
     */
    public function criarComData(string $data, int $id_cliente, ?int $id_funcionario = null): int {
        if ($id_funcionario !== null) {
            $stmt = $this->conn->prepare(
                "INSERT INTO vendas (id_cliente, id_funcionario, data) VALUES (?, ?, ?)"
            );
            $stmt->bind_param("iis", $id_cliente, $id_funcionario, $data);
        } else {
            $stmt = $this->conn->prepare(
                "INSERT INTO vendas (id_cliente, data) VALUES (?, ?)"
            );
            $stmt->bind_param("is", $id_cliente, $data);
        }
        $stmt->execute();
        return $stmt->insert_id;
    }

    /**
     * **
    * Processa cada item da venda: verifica se há estoque, baixa o estoque e insere o item.
    * Lança Exception se algum item tiver estoque insuficiente ou falhar a inserção.
     */
    public function processarItensComValidacao(
        int $id_venda,
        array $itens,
        Produto $produtoModel,
        ProdutoVariacao $variacaoModel,
        VendaProduto $vendaProdutoModel
    ): float {
        $subtotal = 0.0;

        foreach ($itens as $item) {
            if (empty($item['id_produto'])) {
                continue;
            }

            $qtd = max(1, (int)$item['quantidade']);

            // ───────── VARIAÇÃO ─────────
            // ─────────────── VARIAÇÃO ───────────────
            if (!empty($item['id_variacao'])) {
                $id_variacao = (int)$item['id_variacao'];

                // 1) Busca a variação (para pegar preço, nome ou SKU):
                $v = $variacaoModel->getById($id_variacao);
                if (!$v) {
                    throw new \Exception("Variação não encontrada (ID: {$id_variacao}).");
                }
                $precoUnitario = floatval($v['preco_venda']);

                // 1.1) Buscar o produto pai, para obter o 'nome' principal
                $produtoPai = $produtoModel->getById($v['id_produto']);
                $nomeProdutoPai = $produtoPai['nome'] ?? "ID {$v['id_produto']}";

                // 1.2) Tentar montar um nome amigável para a variação
                $nomeVariacao = $v['nome'] ?? null;
                if (empty($nomeVariacao)) {
                    // Se não houver campo 'nome', concatene cor/tamanho, se existir:
                    $atributos = [];
                    if (!empty($v['cor']))     $atributos[] = $v['cor'];
                    if (!empty($v['tamanho'])) $atributos[] = $v['tamanho'];
                    $nomeVariacao = implode(' / ', $atributos);
                }

                // 1.3) Montar um identificador final
                if (!empty($nomeVariacao)) {
                    $identVar = "{$nomeProdutoPai} (Variação: {$nomeVariacao})";
                } else {
                    $identVar = "{$nomeProdutoPai} (SKU: " 
                                . ($v['sku'] ?? $id_variacao) 
                                . ")";
                }

                // 2) Verifica estoque atual da variação:
                $estoqueAtualVar = (int)$variacaoModel->getEstoqueAtual($id_variacao);
                if ($estoqueAtualVar < $qtd) {
                    throw new \Exception(
                        "Estoque insuficiente para o produto “{$identVar}”. Disponível: {$estoqueAtualVar}."
                    );
                }

                // 3) Baixa o estoque:
                $baixou = $variacaoModel->baixarEstoque($id_variacao, $qtd);
                if (! $baixou) {
                    throw new \Exception("Falha ao diminuir estoque da variação “{$identVar}”.");
                }

                // 4) Insere o item na tabela itens_venda (usando VendaProduto):
                $vendaProdutoModel->adicionarItem(
                    $id_venda,
                    $id_variacao,
                    $qtd,
                    $precoUnitario
                );
                if ($this->conn->affected_rows === 0) {
                    throw new \Exception("Falha ao inserir item de variação na venda “{$identVar}”.");
                }

                $subtotal += $precoUnitario * $qtd;
            }

            // ─────── PRODUTO SEM VARIAÇÃO ───────
            else {
                $id_produto = (int)$item['id_produto'];
                $p = $produtoModel->getById($id_produto);
                if (!$p) {
                    throw new \Exception("Produto não encontrado (ID: {$id_produto}).");
                }

                $precoUnitario    = floatval($p['preco_venda']);
                $nomeProduto      = $p['nome'];
                $estoqueAtualProd = (int)$produtoModel->getEstoqueAtual($id_produto);

                // Aqui também alteramos para a frase exata:
                if ($estoqueAtualProd < $qtd) {
                    throw new \Exception(
                        "Não foi possível finalizar a compra. Produto “{$nomeProduto}” não está disponível em estoque."
                    );
                }

                $baixou = $produtoModel->baixarEstoque($id_produto, $qtd);
                if (! $baixou) {
                    throw new \Exception("Falha ao diminuir estoque do produto “{$nomeProduto}”.");
                }

                $vendaProdutoModel->adicionarItemSemVariacao(
                    $id_venda,
                    $id_produto,
                    $qtd,
                    $precoUnitario
                );
                if ($this->conn->affected_rows === 0) {
                    throw new \Exception("Falha ao inserir item de produto na venda.");
                }

                $subtotal += $precoUnitario * $qtd;
            }
        }

        return $subtotal;
    }



    /**
     * Atualiza apenas o total_venda bruto.
     */
    public function atualizarTotal(int $id_venda, float $valor_total): void {
        $stmt = $this->conn->prepare("UPDATE vendas SET total_venda = ? WHERE id_venda = ?");
        $stmt->bind_param("di", $valor_total, $id_venda);
        $stmt->execute();
    }

    /**
     * Recalcula e atualiza total_venda somando itens.
     */
    public function recalcularTotal(int $id_venda): void {
        $stmt = $this->conn->prepare(
            "SELECT SUM(total_item) AS soma FROM itens_venda WHERE id_venda = ?"
        );
        $stmt->bind_param("i", $id_venda);
        $stmt->execute();
        $soma = (float) $stmt->get_result()->fetch_assoc()['soma'];
        $stmt->close();

        $stmt2 = $this->conn->prepare(
            "UPDATE vendas SET total_venda = ? WHERE id_venda = ?"
        );
        $stmt2->bind_param("di", $soma, $id_venda);
        $stmt2->execute();
    }

    /**
     * Atualiza dados de pagamento e total: aplica desconto e taxa se cartão.
     */
public function atualizarPagamentoEParcelas(
    int $id_venda,
    string $metodo,
    int $parcelado,
    int $numParcelas,
    float $taxaMaquininha,
    float $descontoReais
): void {
    // valida método
    if (!in_array($metodo, self::METODOS_PAGAMENTO, true)) {
        throw new \InvalidArgumentException("Método de pagamento inválido: {$metodo}");
    }

    // Insere método, parcelamento e descontos na venda
    $stmt = $this->conn->prepare(
        "UPDATE vendas
           SET metodo_pagamento  = ?,
               parcelado        = ?,
               num_parcelas     = ?,
               taxa_maquininha  = ?,
               desconto         = ?
         WHERE id_venda = ?"
    );
    $stmt->bind_param(
        "siiddi",
        $metodo,
        $parcelado,
        $numParcelas,
        $taxaMaquininha,
        $descontoReais,
        $id_venda
    );
    $stmt->execute();

    // Recalcula soma dos itens
    $stmt2 = $this->conn->prepare(
        "SELECT SUM(total_item) AS soma FROM itens_venda WHERE id_venda = ?"
    );
    $stmt2->bind_param("i", $id_venda);
    $stmt2->execute();
    $soma = (float) $stmt2->get_result()->fetch_assoc()['soma'];
    $stmt2->close();

    // Aplica desconto
    $totalComDesconto = max($soma - $descontoReais, 0.0);

    // Total final SEM somar a taxa da maquininha
    $totalFinal = $totalComDesconto;
    
    // Atualiza o total (já com desconto, mas ignorando a taxa)
    $stmt3 = $this->conn->prepare(
        "UPDATE vendas SET total_venda = ? WHERE id_venda = ?"
    );
    $stmt3->bind_param("di", $totalFinal, $id_venda);
    $stmt3->execute();
    }
    public function delete(int $id_venda): bool {
    // 1) Apaga das trocas todos os registros que pertencem à venda X
    //    — isso garante que não haverá mais FK de trocas → itens_venda bloqueando a remoção abaixo.
    $stmt = $this->conn->prepare("
        DELETE t
          FROM trocas AS t
          JOIN itens_venda AS iv ON t.id_item = iv.id_item
         WHERE iv.id_venda = ?
    ");
    $stmt->bind_param("i", $id_venda);
    if (! $stmt->execute()) {
        return false;
    }
    $stmt->close();

    // 2) Agora sim, podemos apagar os próprios itens_venda dessa venda
    $stmt = $this->conn->prepare("DELETE FROM itens_venda WHERE id_venda = ?");
    $stmt->bind_param("i", $id_venda);
    if (! $stmt->execute()) {
        return false;
    }
    $stmt->close();

    // 3) Se houver uma tabela de parcelas ligadas à venda, delete aqui
    $stmt = $this->conn->prepare("DELETE FROM venda_parcelas WHERE id_venda = ?");
    $stmt->bind_param("i", $id_venda);
    $stmt->execute();
    $stmt->close();

    // 4) Por fim, apaga o registro da própria venda
    $stmt = $this->conn->prepare("DELETE FROM vendas WHERE id_venda = ?");
    $stmt->bind_param("i", $id_venda);
    return $stmt->execute();
}

    /**
     * Reembolsa uma venda: devolve estoque e exclui a venda.
     */
    public function refund(int $id_venda): bool {
        $this->conn->begin_transaction();
        try {
            // 1) Devolver itens ao estoque
            $stmt = $this->conn->prepare(
                "SELECT id_produto, id_variacao, quantidade FROM itens_venda WHERE id_venda = ?"
            );
            $stmt->bind_param("i", $id_venda);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($items as $it) {
                $qtd = (int)$it['quantidade'];
                if (!empty($it['id_variacao'])) {
                    $upd = $this->conn->prepare(
                        "UPDATE produto_variacoes SET estoque_atual = estoque_atual + ? WHERE id_variacao = ?"
                    );
                    $upd->bind_param("ii", $qtd, $it['id_variacao']);
                    $upd->execute();
                    $upd->close();
                } else {
                    $upd = $this->conn->prepare(
                        "UPDATE produtos SET estoque_atual = estoque_atual + ? WHERE id_produto = ?"
                    );
                    $upd->bind_param("ii", $qtd, $it['id_produto']);
                    $upd->execute();
                    $upd->close();
                }
            }

            // 2) Excluir venda e dependências
            $this->delete($id_venda);

            $this->conn->commit();
            return true;
        } catch (\Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }

    /**
     * Paginação de vendas.
     */
    public function getPaginado(int $offset, int $limite): array {
        $sql = "SELECT v.*, c.nome AS nome_cliente
                FROM vendas v
                LEFT JOIN clientes c ON v.id_cliente = c.id_cliente
                ORDER BY v.data DESC LIMIT ?, ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $offset, $limite);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Contagem total de vendas.
     */
    public function getTotalVendas(): int {
        $sql = "SELECT COUNT(*) AS total FROM vendas";
        $row = $this->conn->query($sql)->fetch_assoc();
        return (int)$row['total'];
    }

    /**
     * Estatísticas para dashboard.
     */
    public function getEstatisticasDashboard(): array {
        $hoje = date('Y-m-d');
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total_vendas, SUM(total_venda) AS total_faturado
               FROM vendas WHERE DATE(data_venda)=?"
        );
        $stmt->bind_param("s", $hoje);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $produtos = $this->conn->query("SELECT COUNT(*) AS total_produtos FROM produtos")->fetch_assoc();
        $clientes = $this->conn->query("SELECT COUNT(*) AS total_clientes FROM clientes")->fetch_assoc();
        return [
            'vendas_hoje'      => $res['total_vendas']   ?? 0,
            'faturamento_hoje' => $res['total_faturado'] ?? 0,
            'total_produtos'   => $produtos['total_produtos'] ?? 0,
            'total_clientes'   => $clientes['total_clientes'] ?? 0,
        ];
    }

    /**
     * Contagem de vendas filtradas.
     */
    public function getTotalVendasFiltradas(
        string $search, string $start_date, string $end_date
    ): int {
        $params = []; $types = '';
        $sql = "SELECT COUNT(*) AS total FROM vendas v
                LEFT JOIN clientes c ON v.id_cliente=c.id_cliente
                LEFT JOIN funcionarios f ON v.id_funcionario=f.id_funcionario
                WHERE 1=1";
        if ($search !== '') {
            $sql .= " AND (c.nome LIKE ? OR f.nome LIKE ? )";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $types .= 'ss';
        }
        if ($start_date !== '') {
            $sql .= " AND v.data>=?";
            $params[] = "$start_date 00:00:00";
            $types .= 's';
        }
        if ($end_date !== '') {
            $sql .= " AND v.data<=?";
            $params[] = "$end_date 23:59:59";
            $types .= 's';
        }
        $stmt = $this->conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_assoc()['total'];
    }

    /**
     * Retorna vendas filtradas com paginação.
     */
    public function getVendasFiltradas(
        string $search, string $start_date, string $end_date,
        int $offset, int $limit
    ): array {
        $params = []; $types = '';
        $sql = "SELECT v.*, c.nome AS nome_cliente, f.nome AS nome_funcionario
                FROM vendas v
                LEFT JOIN clientes c ON v.id_cliente=c.id_cliente
                LEFT JOIN funcionarios f ON v.id_funcionario=f.id_funcionario
                WHERE 1=1";
        if ($search !== '') {
            $sql .= " AND (c.nome LIKE ? OR f.nome LIKE ? )";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $types .= 'ss';
        }
        if ($start_date !== '') {
            $sql .= " AND v.data>=?";
            $params[] = "$start_date 00:00:00";
            $types .= 's';
        }
        if ($end_date !== '') {
            $sql .= " AND v.data<=?";
            $params[] = "$end_date 23:59:59";
            $types .= 's';
        }
        $sql .= " ORDER BY v.data_venda DESC LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= 'ii';
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

public function getSumTaxaByPeriodo(string $start_date, string $end_date): float
{
    // Garante horário de início e fim do dia
    $dtInicio = $start_date . ' 00:00:00';
    $dtFim    = $end_date   . ' 23:59:59';

    $sql = "SELECT COALESCE(SUM(taxa_maquininha), 0) AS soma
              FROM vendas
             WHERE data_venda BETWEEN ? AND ?";
    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param("ss", $dtInicio, $dtFim);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return (float)$res['soma'];
}
/**
 * Soma total_venda de todas as vendas que batem com os filtros.
 */
public function getSumVendasFiltradas($search, $start, $end)
{
    $sql = "
      SELECT COALESCE(SUM(total_venda),0) AS total
        FROM vendas v
        LEFT JOIN clientes c    ON v.id_cliente    = c.id_cliente
        LEFT JOIN funcionarios f ON v.id_funcionario = f.id_funcionario
       WHERE (c.nome LIKE ? OR f.nome LIKE ?)
         AND (? = '' OR v.data_venda >= ?)
         AND (? = '' OR v.data_venda <= ?)
    ";
    $like = "%{$search}%";
    $stmt = $this->conn->prepare($sql);
    $stmt->bind_param('ssssss', $like, $like, $start, $start, $end, $end);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return floatval($res['total'] ?? 0);
}

}
