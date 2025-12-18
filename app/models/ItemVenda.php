<?php 
class ItemVenda
{
    private $mysqli;

    public function __construct($mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function getByVenda($id_venda)
    {
        $stmt = $this->mysqli->prepare("SELECT *, preco_unitario as preco_venda FROM itens_venda WHERE id_venda = ?");
        $stmt->bind_param("i", $id_venda);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    
        $items = [];
        foreach ($result as $item) {
            $nome_completo = $item['nome_variacao'];
            $partes = explode('_', $nome_completo);
            
            $produto_nome = $partes[0];  // Nome do Produto
            
            $cor = isset($partes[1]) ? $partes[1] : null;
            $tamanho = isset($partes[2]) ? $partes[2] : null;
            
            $item['produto_nome'] = $produto_nome;
            $item['cor'] = $cor;
            $item['tamanho'] = $tamanho;
            
            $items[] = $item;
        }
        return $items;
    }

    // pega um único item
public function getById($id_item){
    $stmt = $this->mysqli->prepare("SELECT * FROM itens_venda WHERE id_item = ?");
    $stmt->bind_param("i",$id_item);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// atualizar um item trocado
public function update($id_item, $data){
    $stmt = $this->mysqli->prepare("
      UPDATE itens_venda 
         SET id_produto    = ?, 
             id_variacao   = ?, 
             quantidade    = ?, 
             preco_unitario= ?, 
             total_item    = ?, 
             nome_variacao = ?
       WHERE id_item = ?");
    $stmt->bind_param("iiiddsi",
      $data['id_produto'],
      $data['id_variacao'],
      $data['quantidade'],
      $data['preco_unitario'],
      $data['total_item'],
      $data['nome_variacao'],
      $id_item
    );
    return $stmt->execute();
}


}
