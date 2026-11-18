<?php
$resetChatPanel = !empty($_SESSION['manager']['reset_chat_panel']);
unset($_SESSION['manager']['reset_chat_panel']);
?>
<link rel="stylesheet" href="<?= url('assets/css/support_chats/widget.css?v=8'); ?>">
<link rel="stylesheet" href="<?= url('assets/css/internal_chats/widget.css?v=2'); ?>">

<div class="support-widget" id="supportWidget" data-bootstrap-url="<?= url('support_chat/bootstrap'); ?>" data-chat-url-base="<?= url('support_chat/show'); ?>" data-ws-auth-url="<?= url('support_chat/wsAuth'); ?>" data-reset-panel="<?= $resetChatPanel ? 'true' : 'false'; ?>">
  <button class="support-widget-toggle" id="supportWidgetToggle" type="button" aria-expanded="false" aria-controls="supportWidgetPanel">
    <i class="bi bi-chat-dots-fill"></i>
    <span>Chat</span>
  </button>

  <section class="support-widget-panel" id="supportWidgetPanel" hidden>
    <header class="support-widget-header">
      <div>
        <strong>Chat</strong>
        <p class="mb-0" id="supportWidgetHeaderMeta">Conectando ao suporte...</p>
        <p class="mb-0" id="internalChatHeaderMeta" hidden>Carregando conversas internas...</p>
      </div>
      <button class="btn-close" id="supportWidgetClose" type="button" aria-label="Fechar"></button>
    </header>

    <div class="support-widget-tabs" role="tablist" aria-label="Canais do chat">
      <button class="support-widget-tab is-active" id="supportWidgetSupportTab" type="button" role="tab" aria-selected="true" aria-controls="supportWidgetSupportPane" data-chat-widget-tab="support">
        <i class="bi bi-headset"></i>
        <span>Suporte</span>
      </button>
      <button class="support-widget-tab" id="internalChatTab" type="button" role="tab" aria-selected="false" aria-controls="internalChatWidget" data-chat-widget-tab="internal">
        <i class="bi bi-people-fill"></i>
        <span>Interno</span>
      </button>
    </div>

    <div class="support-widget-tab-pane is-active" id="supportWidgetSupportPane" role="tabpanel" aria-labelledby="supportWidgetSupportTab">
      <div class="support-widget-content">
        <div class="support-widget-main">
          <div class="support-widget-body">
            <div class="alert alert-warning mb-2" id="supportWidgetNotice" role="alert" aria-live="polite" aria-atomic="true" hidden></div>
            <div id="supportWidgetMessages" class="support-widget-messages">
              <div class="support-widget-empty">
                <p class="mb-0">Digite a primeira mensagem para iniciar o atendimento.</p>
              </div>
            </div>
            <div class="support-widget-typing-wrap">
              <span id="supportWidgetTyping" class="support-widget-typing"></span>
            </div>
          </div>

          <form class="support-widget-composer" id="supportWidgetComposer">
            <textarea class="form-control" id="supportWidgetInput" rows="3" placeholder="Escreva sua mensagem para o suporte..."></textarea>
            <div class="support-widget-footer">
              <span id="supportWidgetConnection" class="support-widget-connection">Conectando...</span>
              <button class="btn btn-primary btn-sm" id="supportWidgetSendButton" type="submit">Enviar</button>
            </div>
          </form>
        </div>

        <aside class="support-widget-history" aria-labelledby="supportWidgetHistoryTitle">
          <div class="support-widget-history-header" id="supportWidgetHistoryHeader">
            <strong id="supportWidgetHistoryTitle">Histórico <span class="support-widget-history-count" id="supportWidgetHistoryCount"></span></strong>
            <button class="support-widget-history-toggle" id="supportWidgetHistoryToggle" type="button" aria-label="Recolher histórico" aria-expanded="true" aria-controls="supportWidgetHistoryList" title="Recolher histórico">
              <i class="bi bi-chevron-right"></i>
            </button>
          </div>
          <div id="supportWidgetHistoryList" class="support-widget-history-list"></div>
        </aside>
      </div>
    </div>

    <div class="support-widget-tab-pane internal-chat-widget" id="internalChatWidget" role="tabpanel" aria-labelledby="internalChatTab" hidden data-bootstrap-url="<?= url('internal_chat/bootstrap'); ?>" data-chat-url-base="<?= url('internal_chat/show'); ?>" data-start-url="<?= url('internal_chat/start'); ?>" data-send-url="<?= url('internal_chat/send'); ?>" data-typing-url="<?= url('internal_chat/typing'); ?>" data-typing-status-url-base="<?= url('internal_chat/typingStatus'); ?>" data-ws-auth-url="<?= url('internal_chat/wsAuth'); ?>">
      <div class="internal-chat-content">
        <aside class="internal-chat-sidebar" aria-label="Conversas internas">
          <div class="internal-chat-section-header">
            <strong>Conversas</strong>
            <button class="internal-chat-icon-button" id="internalChatRefresh" type="button" aria-label="Atualizar conversas" title="Atualizar conversas">
              <i class="bi bi-arrow-clockwise"></i>
            </button>
          </div>
          <div class="internal-chat-list" id="internalChatConversationList"></div>
        </aside>

        <div class="internal-chat-main">
          <div class="internal-chat-thread-header" id="internalChatThreadHeader" hidden>
            <button class="internal-chat-icon-button internal-chat-back" id="internalChatBack" type="button" aria-label="Voltar para as conversas" title="Voltar para as conversas">
              <i class="bi bi-arrow-left"></i>
            </button>
            <div class="internal-chat-thread-identity" id="internalChatThreadIdentity"></div>
          </div>

          <div class="support-widget-body">
            <div id="internalChatMessages" class="support-widget-messages internal-chat-messages">
              <div class="support-widget-empty">
                <p class="mb-0">Escolha uma conversa ou alguém da Equipe para conversar.</p>
              </div>
            </div>
            <div class="support-widget-typing-wrap">
              <span id="internalChatTyping" class="support-widget-typing"></span>
            </div>
          </div>

          <form class="support-widget-composer internal-chat-composer" id="internalChatComposer">
            <textarea class="form-control" id="internalChatInput" rows="3" placeholder="Escreva uma mensagem interna..." disabled></textarea>
            <div class="support-widget-footer">
              <span id="internalChatConnection" class="support-widget-connection">Carregando...</span>
              <button class="btn btn-primary btn-sm" id="internalChatSendButton" type="submit" disabled>Enviar</button>
            </div>
          </form>
        </div>

        <aside class="internal-chat-team" aria-labelledby="internalChatTeamTitle">
          <div class="internal-chat-section-header">
            <strong id="internalChatTeamTitle">Equipe</strong>
            <span class="internal-chat-team-count" id="internalChatTeamCount"></span>
          </div>
          <div class="internal-chat-team-list" id="internalChatUserList"></div>
        </aside>
      </div>
    </div>
  </section>
</div>

<div class="modal fade support-widget-history-modal" id="supportWidgetHistoryModal" tabindex="-1" aria-labelledby="supportWidgetHistoryModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="supportWidgetHistoryModalTitle">Histórico</h5>
          <p class="support-widget-history-modal-meta mb-0" id="supportWidgetHistoryModalMeta"></p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div id="supportWidgetHistoryModalMessages" class="support-widget-history-modal-messages"></div>
      </div>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/support_chats/widget.js?v=8'); ?>"></script>
<script src="<?= url('assets/js/internal_chats/widget.js?v=3'); ?>"></script>
