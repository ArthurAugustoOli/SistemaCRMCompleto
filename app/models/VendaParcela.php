<?php 
namespace App\Models;

class VendaParcela {
  private $conn;
  public function __construct($mysqli){ $this->conn = $mysqli; }

  public function criarParcela($idVenda, $num, $valor, $venc, $taxa){
    $stmt = $this->conn->prepare("
      INSERT INTO venda_parcelas
        (id_venda, numero_parcela, valor_parcela, data_vencimento, taxa_maquininha)
      VALUES(?,?,?,?,?)
    ");
    $stmt->bind_param("iidsd",
      $idVenda, $num, $valor, $venc, $taxa
    );
    $stmt->execute();
  }
}
?>