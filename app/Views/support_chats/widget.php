<?php
$resetChatPanel = !empty($_SESSION['manager']['reset_chat_panel']);
unset($_SESSION['manager']['reset_chat_panel']);
?>
<link rel="stylesheet" href="<?= url('assets/css/support_chats/widget.css?v=11'); ?>">
<link rel="stylesheet" href="<?= url('assets/css/internal_chats/widget.css?v=4'); ?>">

<div class="support-widget" id="supportWidget" data-bootstrap-url="<?= url('support_chat/bootstrap'); ?>" data-chat-url-base="<?= url('support_chat/show'); ?>" data-seen-url="<?= url('support_chat/seen'); ?>" data-ws-auth-url="<?= url('support_chat/wsAuth'); ?>" data-reset-panel="<?= $resetChatPanel ? 'true' : 'false'; ?>">
  <button class="support-widget-toggle" id="supportWidgetToggle" type="button" aria-expanded="false" aria-controls="supportWidgetPanel">
    <i class="bi bi-chat-dots-fill"></i>
    <span>Chat</span>
    <span class="chat-unread-badge support-widget-toggle-badge" id="supportWidgetUnreadCount" aria-live="polite" aria-atomic="true" hidden></span>
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
        <span class="chat-unread-badge" id="supportWidgetSupportUnreadCount" hidden></span>
      </button>
      <button class="support-widget-tab" id="internalChatTab" type="button" role="tab" aria-selected="false" aria-controls="internalChatWidget" data-chat-widget-tab="internal">
        <i class="bi bi-people-fill"></i>
        <span>Interno</span>
        <span class="chat-unread-badge" id="internalChatUnreadCount" hidden></span>
      </button>
    </div>

    <div class="support-widget-tab-pane is-active" id="supportWidgetSupportPane" role="tabpanel" aria-labelledby="supportWidgetSupportTab">
      <div class="support-widget-content">
        <aside class="chat-sidebar support-widget-sidebar" aria-label="Atendentes e atendimentos do suporte">
          <div class="chat-section-header">
            <strong id="supportWidgetAttendantsTitle">Atendentes</strong>
            <span class="chat-section-count" id="supportWidgetAttendantsCount"></span>
          </div>
          <div class="chat-people-list support-widget-attendant-list" id="supportWidgetAttendantList" aria-labelledby="supportWidgetAttendantsTitle"></div>

          <div class="support-widget-history" aria-labelledby="supportWidgetHistoryTitle">
            <div class="chat-section-header support-widget-history-header" id="supportWidgetHistoryHeader">
              <strong id="supportWidgetHistoryTitle" title="Atendimentos do último mês">Atendimentos <span class="support-widget-history-count" id="supportWidgetHistoryCount"></span></strong>
              <button class="chat-icon-button" id="supportWidgetHistoryToggle" type="button" aria-label="Recolher atendimentos" aria-expanded="true" aria-controls="supportWidgetHistoryList" title="Recolher atendimentos">
                <i class="bi bi-chevron-up"></i>
              </button>
            </div>
            <div id="supportWidgetHistoryList" class="support-widget-history-list"></div>
          </div>
        </aside>

        <div class="support-widget-main">
          <div class="chat-thread-header" id="supportWidgetThreadHeader">
            <button class="chat-icon-button chat-back" id="supportWidgetBack" type="button" aria-label="Voltar para os atendentes" title="Voltar para os atendentes">
              <i class="bi bi-arrow-left"></i>
            </button>
            <div class="chat-thread-identity" id="supportWidgetThreadIdentity"></div>
          </div>

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
            <textarea class="form-control" id="supportWidgetInput" rows="2" placeholder="Escreva sua mensagem para o suporte..."></textarea>
            <div class="support-widget-footer">
              <span id="supportWidgetConnection" class="support-widget-connection">Conectando...</span>
              <button class="btn btn-primary btn-sm" id="supportWidgetSendButton" type="submit">Enviar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="support-widget-tab-pane internal-chat-widget" id="internalChatWidget" role="tabpanel" aria-labelledby="internalChatTab" hidden data-bootstrap-url="<?= url('internal_chat/bootstrap'); ?>" data-chat-url-base="<?= url('internal_chat/show'); ?>" data-start-url="<?= url('internal_chat/start'); ?>" data-send-url="<?= url('internal_chat/send'); ?>" data-seen-url="<?= url('internal_chat/seen'); ?>" data-typing-url="<?= url('internal_chat/typing'); ?>" data-typing-status-url-base="<?= url('internal_chat/typingStatus'); ?>" data-ws-auth-url="<?= url('internal_chat/wsAuth'); ?>">
      <div class="internal-chat-content">
        <aside class="chat-sidebar internal-chat-sidebar" aria-labelledby="internalChatTeamTitle">
          <div class="chat-section-header">
            <strong id="internalChatTeamTitle">Equipe</strong>
            <span class="chat-section-count" id="internalChatTeamCount"></span>
            <button class="chat-icon-button" id="internalChatRefresh" type="button" aria-label="Atualizar" title="Atualizar">
              <i class="bi bi-arrow-clockwise"></i>
            </button>
          </div>
          <div class="chat-people-list" id="internalChatUserList"></div>
        </aside>

        <div class="internal-chat-main">
          <div class="chat-thread-header" id="internalChatThreadHeader" hidden>
            <button class="chat-icon-button chat-back" id="internalChatBack" type="button" aria-label="Voltar para a equipe" title="Voltar para a equipe">
              <i class="bi bi-arrow-left"></i>
            </button>
            <div class="chat-thread-identity" id="internalChatThreadIdentity"></div>
          </div>

          <div class="support-widget-body">
            <div id="internalChatMessages" class="support-widget-messages internal-chat-messages">
              <div class="support-widget-empty">
                <p class="mb-0">Escolha alguém da equipe para conversar.</p>
              </div>
            </div>
            <div class="support-widget-typing-wrap">
              <span id="internalChatTyping" class="support-widget-typing"></span>
            </div>
          </div>

          <form class="support-widget-composer internal-chat-composer" id="internalChatComposer">
            <textarea class="form-control" id="internalChatInput" rows="2" placeholder="Escreva uma mensagem interna..." disabled></textarea>
            <div class="support-widget-footer">
              <span id="internalChatConnection" class="support-widget-connection">Carregando...</span>
              <button class="btn btn-primary btn-sm" id="internalChatSendButton" type="submit" disabled>Enviar</button>
            </div>
          </form>
        </div>
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

<script src="<?= url('assets/js/support_chats/widget.js?v=11'); ?>"></script>
<script src="<?= url('assets/js/internal_chats/widget.js?v=6'); ?>"></script>
