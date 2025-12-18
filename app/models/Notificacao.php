<?php
// app/models/Notificacao.php

class Notificacao
{
    /** @var mysqli */
    private $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    /**
     * Insere uma nova notificação no banco.
     *
     * @param string      $titulo
     * @param string      $mensagem
     * @param string      $tipo       one of ['info','success','warning','danger']
     * @param int         $criadorId
     * @param string|null $expiraEm   formato 'Y-m-d H:i:s' ou null
     * @param bool|int    $paraTodos  1 = enviar para todos; 0 = não
     *
     * @return int  ID da notificação criada
     * @throws Exception em caso de erro no SQL
     */
    public function criar(string $titulo, string $mensagem, string $tipo, int $criadorId, ?string $expiraEm = null, $paraTodos = 0): int
    {
        $sql = "
            INSERT INTO notificacoes
                (titulo, mensagem, tipo, criador_id, data_criacao, expira_em, para_todos)
            VALUES
                (?,       ?,       ?,    ?,          NOW(),       ?,         ?)
        ";

        if (! $stmt = $this->mysqli->prepare($sql)) {
            throw new Exception("Prepare failed: " . $this->mysqli->error);
        }

        $stmt->bind_param(
            'sssisi',
            $titulo,
            $mensagem,
            $tipo,
            $criadorId,
            $expiraEm,
            $paraTodos
        );

        if (! $stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $newId = $stmt->insert_id;
        $stmt->close();
        return $newId;
    }
}
