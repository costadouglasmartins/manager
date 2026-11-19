<?php
$resetChatPanel = !empty($_SESSION['manager']['reset_chat_panel']);
unset($_SESSION['manager']['reset_chat_panel']);
?>
<link rel="stylesheet" href="<?= url('assets/css/support_chats/widget.css?v=14'); ?>">
<link rel="stylesheet" href="<?= url('assets/css/internal_chats/widget.css?v=5'); ?>">

<div class="support-widget" id="supportWidget" data-bootstrap-url="<?= url('support_chat/bootstrap'); ?>" data-chat-url-base="<?= url('support_chat/show'); ?>" data-seen-url="<?= url('support_chat/seen'); ?>" data-ws-auth-url="<?= url('support_chat/wsAuth'); ?>" data-reset-panel="<?= $resetChatPanel ? 'true' : 'false'; ?>">
  <button class="support-widget-toggle" id="supportWidgetToggle" type="button" aria-expanded="false" aria-controls="supportWidgetPanel">
    <i class="bi bi-chat-dots-fill"></i>
    <span>Chat</span>
    <span class="chat-unread-badge support-widget-toggle-badge" id="supportWidgetUnreadCount" aria-live="polite" aria-atomic="true" hidden></span>
  </button>

  <section class="support-widget-panel" id="supportWidgetPanel" hidden>
    <!-- The header names the conversation on screen: one block per tab. -->
    <header class="support-widget-header">
      <div class="chat-header-current" id="supportWidgetHeaderCurrent">
        <button class="chat-icon-button chat-back" id="supportWidgetBack" type="button" aria-label="Voltar para os atendimentos" title="Voltar para os atendimentos">
          <i class="bi bi-arrow-left"></i>
        </button>
        <div class="chat-thread-identity" id="supportWidgetThreadIdentity">
          <span class="chat-thread-text">
            <span class="chat-thread-name">Suporte</span>
            <span class="chat-thread-status">Conectando ao suporte...</span>
          </span>
        </div>
      </div>
      <div class="chat-header-current" id="internalChatHeaderCurrent" hidden>
        <button class="chat-icon-button chat-back" id="internalChatBack" type="button" aria-label="Voltar para a equipe" title="Voltar para a equipe">
          <i class="bi bi-arrow-left"></i>
        </button>
        <div class="chat-thread-identity" id="internalChatThreadIdentity">
          <span class="chat-thread-text">
            <span class="chat-thread-name">Chat interno</span>
            <span class="chat-thread-status">Carregando conversas internas...</span>
          </span>
        </div>
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
            <textarea class="form-control" id="supportWidgetInput" rows="2" placeholder="Escreva sua mensagem para o suporte..."></textarea>
            <div class="support-widget-footer">
              <span id="supportWidgetConnection" class="support-widget-connection">Conectando...</span>
              <button class="btn btn-outline-primary btn-sm" id="supportWidgetResumeButton" type="button" hidden>Novo atendimento</button>
              <button class="btn btn-primary btn-sm" id="supportWidgetSendButton" type="submit">Enviar</button>
            </div>
          </form>
        </div>

        <aside class="chat-sidebar support-widget-sidebar" aria-labelledby="supportWidgetChatsTitle">
          <div class="chat-section-header">
            <strong id="supportWidgetChatsTitle" title="Atendimentos do último mês">Atendimentos</strong>
            <span class="chat-section-count" id="supportWidgetChatsCount"></span>
            <button class="chat-icon-button" id="supportWidgetNewChat" type="button" aria-label="Novo atendimento" title="Novo atendimento" hidden>
              <i class="bi bi-plus-lg"></i>
            </button>
          </div>
          <div class="chat-people-list" id="supportWidgetChatList"></div>
        </aside>
      </div>
    </div>

    <div class="support-widget-tab-pane internal-chat-widget" id="internalChatWidget" role="tabpanel" aria-labelledby="internalChatTab" hidden data-bootstrap-url="<?= url('internal_chat/bootstrap'); ?>" data-chat-url-base="<?= url('internal_chat/show'); ?>" data-start-url="<?= url('internal_chat/start'); ?>" data-send-url="<?= url('internal_chat/send'); ?>" data-seen-url="<?= url('internal_chat/seen'); ?>" data-typing-url="<?= url('internal_chat/typing'); ?>" data-typing-status-url-base="<?= url('internal_chat/typingStatus'); ?>" data-ws-auth-url="<?= url('internal_chat/wsAuth'); ?>">
      <div class="internal-chat-content">
        <div class="internal-chat-main">
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
      </div>
    </div>
  </section>
</div>

<script src="<?= url('assets/js/support_chats/widget.js?v=12'); ?>"></script>
<script src="<?= url('assets/js/internal_chats/widget.js?v=7'); ?>"></script>
