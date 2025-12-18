<?php
namespace App\Models;

class Etiqueta
{
    private $conn;

    public function __construct(\mysqli $mysqli)
    {
        $this->conn = $mysqli;
    }

    public function getAllProdutos(): array
    {
        $sql = "SELECT id_produto, nome, codigo_barras FROM produtos ORDER BY nome";
        return $this->conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    public function getVariacoesByProduto(int $id): array
    {
        $stmt = $this->conn->prepare("
            SELECT id_variacao, cor, tamanho, sku 
            FROM produto_variacoes 
            WHERE id_produto = ?
            ORDER BY cor, tamanho
        ");
        $stmt->bind_param("i",$id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** retorna produtos + suas variações em um só array */
    public function getAllWithVariacoes(): array
    {
        $prods = $this->getAllProdutos();
        foreach($prods as &$p){
            $p['variacoes'] = $this->getVariacoesByProduto($p['id_produto']);
        }
        return $prods;
    }
}
