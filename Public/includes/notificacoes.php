<?php

$id_usuario = $_SESSION['id_usuario'] ?? 0; // ID do usuário logado (0 se não estiver logado)
/* Nome: notificacoes.php | Caminho: /Public/includes/notificacoes.php */
if ($id_usuario == 0) {
    require_once '../login/verificar_sessao.php';
}

require_once __DIR__ . '/../../app/config/config.php'; // cria $mysqli

// Calcula baseUrl para links
$currentFile    = $_SERVER['PHP_SELF'];
$parts          = explode('/', $currentFile);
$directoryDepth = count($parts);
$baseUrl        = str_repeat('../', $directoryDepth);

// Busca todas as notificações ativas
function getNotificacoes()
{
    global $mysqli;
    $notificacoes = [];

    $sql = "
      SELECT n.*, u.nome AS criador_nome
        FROM notificacoes n
        JOIN usuarios u ON n.criador_id = u.id_usuario
       WHERE (n.expira_em IS NULL OR n.expira_em > NOW())
       ORDER BY n.data_criacao DESC
       LIMIT 10
    ";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $row['lida'] = true; // ou false, mas sem controle individual
            $notificacoes[] = $row;
        }
        $stmt->close();
    }

    return $notificacoes;
}

// Conta quantas notificações ainda estão “ativas”
function contarNotificacoesAtivas()
{
    global $mysqli;
    $count = 0;

    $sql = "
      SELECT COUNT(*) AS cnt
        FROM notificacoes n
       WHERE (n.expira_em IS NULL OR n.expira_em > NOW())
    ";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $count = (int) ($row['cnt'] ?? 0);
        $stmt->close();
    }

    return $count;
}

$notificacoes_nao_lidas = contarNotificacoesAtivas();
$notificacoes          = getNotificacoes();
?>

<div class="notificacao-dropdown">
  <button class="action-btn" id="notificacoesDropdown">
    <i class="bi bi-bell"></i>
    <?php if ($notificacoes_nao_lidas > 0): ?>
      <span class="badge"><?= $notificacoes_nao_lidas ?></span>
    <?php endif; ?>
  </button>

  <div class="dropdown-menu notifications-dropdown" id="notificacoesMenu">
  <div class="dropdown-header">
    <h6 class="mb-0">Notificações</h6>
    <button class="btn btn-sm btn-outline-primary" id="btnAbrirModalNotificacao">
      <i class="bi bi-plus-lg"></i> Nova
    </button>
  </div>

  <div class="dropdown-body" id="notificacoesList">
    <?php if (count($notificacoes) > 0): ?>
      <?php foreach ($notificacoes as $n): ?>
        <div class="notification-item<?= $n['lida'] ? '' : ' unread' ?>">
          <div class="notification-icon">
            <?php
            switch ($n['tipo']) {
              case 'warning': $icon = 'exclamation-triangle'; $color = 'warning'; break;
              case 'success': $icon = 'check-circle';      $color = 'success'; break;
              case 'danger':  $icon = 'exclamation-circle'; $color = 'danger';  break;
              default:        $icon = 'info-circle';        $color = 'primary'; break;
            }
            ?>
            <div class="icon-circle bg-<?= $color ?>-light">
              <i class="bi bi-<?= $icon ?> text-<?= $color ?>"></i>
            </div>
          </div>
          <div class="notification-content">
            <h6><?= htmlspecialchars($n['titulo']) ?></h6>
            <p><?= htmlspecialchars($n['mensagem']) ?></p>
            <div class="notification-meta">
              <span class="time"><?= date('d/m/Y H:i', strtotime($n['data_criacao'])) ?></span>
              <span class="author">Por: <?= htmlspecialchars($n['criador_nome']) ?></span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="no-notifications">
        <div class="empty-icon"><i class="bi bi-bell-slash"></i></div>
        <p>Nenhuma notificação disponível</p>
      </div>
    <?php endif; ?>
  </div>

  <div class="dropdown-footer">
  </div>
</div>
</div>
<!-- Modal para Nova Notificação (apenas para administradores) -->
    <div class="modal fade p-4 mb-5" id="novaNotificacaoModal" tabindex="-1" aria-labelledby="novaNotificacaoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="novaNotificacaoModalLabel">Nova Notificação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="notificacaoFormModal">
                        <div class="mb-1">
                            <label for="tituloModal" class="form-label">Título</label>
                            <input type="text" class="form-control" id="tituloModal" name="titulo" required>
                        </div>

                        <div class="mb-1">
                            <label for="mensagemModal" class="form-label">Mensagem</label>
                            <textarea class="form-control" id="mensagemModal" name="mensagem" rows="3" required></textarea>
                        </div>

                        <div class="mb-1">
                            <label for="tipoModal" class="form-label">Tipo</label>
                            <select class="form-select" id="tipoModal" name="tipo">
                                <option value="info">Informação</option>
                                <option value="success">Sucesso</option>
                                <option value="warning">Aviso</option>
                                <option value="danger">Alerta</option>
                            </select>
                        </div>

                        <div class="mb-1">
                            <label for="expira_emModal" class="form-label">Expira em (opcional)</label>
                            <input type="datetime-local" class="form-control" id="expira_emModal" name="expira_em">
                        </div>

                        <div class="">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="para_todosModal" name="para_todos" checked>
                                <label class="form-check-label" for="para_todosModal">
                                    Enviar para todos os usuários
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="enviarNotificacaoModal">Enviar Notificação</button>
                </div>
            </div>
        </div>
    </div>


<style>
    /* Notificações Dropdown */
    .notifications-dropdown {
        width: 350px;
        max-height: 500px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .dropdown-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        background-color: #f8f9fa;
    }

    .dropdown-header h6 {
        font-weight: 600;
        color: #333;
    }

    .dropdown-body {
        overflow-y: auto;
        max-height: 350px;
        padding: 0;
    }

    .notification-item {
        display: flex;
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .notification-item:hover {
        background-color: #f8f9fa;
    }

    .notification-item.unread {
        background-color: rgba(78, 115, 223, 0.05);
        border-left: 3px solid #4e73df;
    }

    .notification-icon {
        margin-right: 15px;
        flex-shrink: 0;
    }

    .icon-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .bg-primary-light {
        background-color: rgba(78, 115, 223, 0.1);
    }

    .bg-success-light {
        background-color: rgba(28, 200, 138, 0.1);
    }

    .bg-warning-light {
        background-color: rgba(246, 194, 62, 0.1);
    }

    .bg-danger-light {
        background-color: rgba(231, 74, 59, 0.1);
    }

    .notification-content {
        flex-grow: 1;
        min-width: 0;
    }

    .notification-content h6 {
        margin: 0 0 5px;
        font-size: 14px;
        font-weight: 600;
        color: #333;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .notification-content p {
        margin: 0 0 5px;
        font-size: 13px;
        color: #6c757d;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .notification-meta {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        color: #adb5bd;
    }

    .no-notifications {
        padding: 30px 15px;
        text-align: center;
        color: #6c757d;
    }

    .empty-icon {
        font-size: 40px;
        color: #dee2e6;
        margin-bottom: 10px;
    }

    .dropdown-footer {
        padding: 10px 15px;
        text-align: center;
        border-top: 1px solid #f0f0f0;
        background-color: #f8f9fa;
        margin-top: auto;
    }

    .dropdown-footer a {
        color: #4e73df;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
    }

    .dropdown-footer a:hover {
        text-decoration: underline;
    }

    /* Animações */
    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .notification-item {
        animation: fadeIn 0.3s ease-in-out;
    }

    /* Responsividade */
    @media (max-width: 575.98px) {
        .notifications-dropdown {
            width: 300px;
        }

        .notification-item {
            padding: 10px;
        }

        .icon-circle {
            width: 35px;
            height: 35px;
        }

        /* Ajustes para o modal em telas pequenas */
        .modal-dialog {
            margin: 0.5rem;
        }

        .modal-content {
            border-radius: 0.5rem;
        }
    }

    /* Estilos para o toast de notificação */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1060;
    }

    .toast {
        background-color: #fff;
        border-radius: 0.5rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        overflow: hidden;
        max-width: 350px;
    }

    .toast-header {
        display: flex;
        align-items: center;
        padding: 0.5rem 0.75rem;
        background-color: rgba(255, 255, 255, 0.85);
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .toast-body {
        padding: 0.75rem;
    }

    .modal {
        z-index: 1050;
        /* Garante que o modal fique na frente */
    }

    .modal-backdrop {
        z-index: 1040;
        /* Mantém o fundo escurecido atrás do modal */
    }
    
    @media (max-width: 767.98px) {
    .modal-content {
        height: auto; !important
    }
}
    /* wrapper relativo */
.notificacao-dropdown {
  position: relative;
  display: inline-block;
}

/* posiciona o dropdown */
#notificacoesMenu {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 0.5rem;
  border-radius: 0.5rem;
  box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
  overflow: hidden;
  opacity: 0;
  visibility: hidden;
  transform: translateY(-10px);
  transition: opacity 0.2s ease, transform 0.2s ease;
  z-index: 2000;
}

/* quando aberto */
#notificacoesMenu.show {
  opacity: 1;
  visibility: visible;
  transform: translateY(0);
}
/* Botão de notificações */
.notificacao-dropdown .action-btn {
  position: relative;
  width: 42px;
  height: 42px;
  border: none;
  background: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 0.2s ease, box-shadow 0.2s ease;
}

/* Sombra e fundo no hover/focus */
.notificacao-dropdown .action-btn:hover,
.notificacao-dropdown .action-btn:focus {
  background-color: #f0f0f0;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  outline: none;
}

/* Ícone da campainha */
.notificacao-dropdown .action-btn i {
  font-size: 20px;
  color: #555;
}

/* Badge vermelhinho sobrescrito */
.notificacao-dropdown .action-btn .badge {
  position: absolute;
  top: 4px;
  right: 4px;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  font-size: 12px;
  font-weight: 600;
  line-height: 20px;
  color: #fff;
  background-color: #dc3545; /* vermelho Bootstrap */
  border: 2px solid #fff;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

/* Animação suave ao atualizar o número */
.notificacao-dropdown .action-btn .badge {
  transition: transform 0.3s ease;
}
.notificacao-dropdown .action-btn .badge.updated {
  transform: scale(1.2);
}

</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const notificacoesDropdown = document.getElementById('notificacoesDropdown');
        const notificacoesMenu = document.getElementById('notificacoesMenu');

        // Toggle dropdown
        notificacoesDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            notificacoesMenu.classList.toggle('show');
        });

        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function(e) {
            if (!notificacoesMenu.contains(e.target) && !notificacoesDropdown.contains(e.target)) {
                notificacoesMenu.classList.remove('show');
            }
        });

        // Abrir modal de nova notificação
        const btnAbrirModal = document.getElementById('btnAbrirModalNotificacao');
        if (btnAbrirModal) {
            btnAbrirModal.addEventListener('click', function(e) {
                e.stopPropagation();
                notificacoesMenu.classList.remove('show');

                // Verificar se o Bootstrap está disponível
                if (typeof bootstrap !== 'undefined') {
                    // Abrir o modal de nova notificação
                    const novaNotificacaoModal = new bootstrap.Modal(document.getElementById('novaNotificacaoModal'));
                    novaNotificacaoModal.show();
                    document.querySelectorAll(".modal-backdrop").forEach(backdrop => backdrop.remove());

                } else {
                    console.error('Bootstrap não está disponível. Certifique-se de que o script do Bootstrap está carregado.');
                }
            });
        }

        // Marcar notificação como lida ao clicar
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const notificacaoId = this.getAttribute('data-id');

                fetch('<?= $baseUrl ?>api/notificacoes/marcar_lida.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `notificacao_id=${notificacaoId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.classList.remove('unread');

                            // Atualizar contador de notificações
                            const badge = document.querySelector('#notificacoesDropdown .badge');
                            if (badge) {
                                const count = parseInt(badge.textContent) - 1;
                                if (count > 0) {
                                    badge.textContent = count;
                                } else {
                                    badge.remove();
                                }
                            }
                        }
                    })
                    .catch(error => console.error('Erro ao marcar notificação como lida:', error));
            });
        });

        // Enviar nova notificação do modal
        const enviarNotificacaoBtn = document.getElementById('enviarNotificacaoModal');
        if (enviarNotificacaoBtn) {
            enviarNotificacaoBtn.addEventListener('click', function() {
                const form = document.getElementById('notificacaoFormModal');
                const formData = new FormData(form);

                // Converter para formato URL encoded
                const urlEncoded = new URLSearchParams();
                for (const [key, value] of formData) {
                    if (key === 'para_todos') {
                        urlEncoded.append(key, '1');
                    } else {
                        urlEncoded.append(key, value);
                    }
                }

                // Se para_todos não estiver marcado, adicionar com valor 0
                if (!formData.has('para_todos')) {
                    urlEncoded.append('para_todos', '0');
                }

                fetch('../includes/notificacoes/criar.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: urlEncoded.toString()
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Fechar modal
                            if (typeof bootstrap !== 'undefined') {
                                const modal = bootstrap.Modal.getInstance(document.getElementById('novaNotificacaoModal'));
                                modal.hide();
                            }

                            // Mostrar mensagem de sucesso
                            mostrarToast('Sucesso', 'Notificação enviada com sucesso!', 'success');

                            // Limpar formulário
                            form.reset();

                            // Recarregar as notificações após um breve delay
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            mostrarToast('Erro', 'Erro ao enviar notificação: ' + data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao enviar notificação:', error);
                        mostrarToast('Erro', 'Erro ao enviar notificação. Verifique o console para mais detalhes.', 'danger');
                    });
            });
        }

        // Função para mostrar toast de notificação
        function mostrarToast(titulo, mensagem, tipo) {
            // Verificar se o container de toast já existe
            let toastContainer = document.querySelector('.toast-container');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.className = 'toast-container';
                document.body.appendChild(toastContainer);
            }

            // Criar o toast
            const toastId = 'toast-' + Date.now();
            const toastHTML = `
            <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header bg-${tipo}-subtle text-${tipo}">
                    <strong class="me-auto">${titulo}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${mensagem}
                </div>
            </div>
        `;

            toastContainer.insertAdjacentHTML('beforeend', toastHTML);

            // Mostrar o toast
            if (typeof bootstrap !== 'undefined') {
                const toastElement = document.getElementById(toastId);
                const toast = new bootstrap.Toast(toastElement, {
                    autohide: true,
                    delay: 5000
                });
                toast.show();

                // Remover o toast após ser escondido
                toastElement.addEventListener('hidden.bs.toast', function() {
                    toastElement.remove();
                });
            } else {
                // Fallback se o Bootstrap não estiver disponível
                const toastElement = document.getElementById(toastId);
                toastElement.style.display = 'block';

                // Adicionar botão de fechar
                const closeBtn = toastElement.querySelector('.btn-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        toastElement.remove();
                    });
                }

                // Auto-esconder após 5 segundos
                setTimeout(() => {
                    toastElement.remove();
                }, 5000);
            }
        }
    });
</script>