<?php
class VendaProduto
{
    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function adicionarItem($id_venda, $id_variacao, $quantidade, $preco_unitario)
    {
        // Busca a variação e o nome do produto associado
        $stmt = $this->mysqli->prepare("
            SELECT pv.cor, pv.tamanho, p.nome AS nome_produto 
            FROM produto_variacoes pv
            JOIN produtos p ON pv.id_produto = p.id_produto
            WHERE pv.id_variacao = ?
        ");
        $stmt->bind_param("i", $id_variacao);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Formar o nome completo combinando o nome do produto, cor e tamanho
        $nomeCompleto = $result['nome_produto'];
        
        if (!empty($result['cor'])) {
            $nomeCompleto .= '_' . $result['cor'];
        }

        if (!empty($result['tamanho'])) {
            $nomeCompleto .= '_' . $result['tamanho'];
        }

        $stmt = $this->mysqli->prepare("
            INSERT INTO itens_venda 
            (id_venda, id_variacao, quantidade, preco_unitario, total_item, nome_variacao) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $total_item = $quantidade * $preco_unitario;
        $stmt->bind_param("iiidss", $id_venda, $id_variacao, $quantidade, $preco_unitario, $total_item, $nomeCompleto);
        $stmt->execute();
        $stmt->close();
    }

    public function adicionarItemSemVariacao($id_venda, $id_produto, $quantidade, $preco_unitario)
    {
        // Busca o nome do produto
        $stmt = $this->mysqli->prepare("SELECT nome FROM produtos WHERE id_produto = ?");
        $stmt->bind_param("i", $id_produto);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $nomeCompleto = $result['nome'];

        $stmt = $this->mysqli->prepare("
            INSERT INTO itens_venda 
            (id_venda, id_produto, quantidade, preco_unitario, total_item, nome_variacao) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $total_item = $quantidade * $preco_unitario;
        $stmt->bind_param("iiidss", $id_venda, $id_produto, $quantidade, $preco_unitario, $total_item, $nomeCompleto);
        $stmt->execute();
        $stmt->close();
    }
}
