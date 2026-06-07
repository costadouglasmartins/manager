<link rel="stylesheet" href="<?= url('assets/css/support_chats/widget.css?v=4'); ?>">

<div class="support-widget" id="supportWidget" data-bootstrap-url="<?= url('support_chat/bootstrap'); ?>" data-chat-url-base="<?= url('support_chat/show'); ?>" data-ws-auth-url="<?= url('support_chat/wsAuth'); ?>">
  <button class="support-widget-toggle" id="supportWidgetToggle" type="button" aria-expanded="false" aria-controls="supportWidgetPanel">
    <i class="bi bi-chat-dots-fill"></i>
    <span>Suporte</span>
  </button>

  <section class="support-widget-panel" id="supportWidgetPanel" hidden>
    <header class="support-widget-header">
      <div>
        <strong>Atendimento</strong>
        <p class="mb-0" id="supportWidgetHeaderMeta">Conectando ao suporte...</p>
      </div>
      <button class="btn-close" id="supportWidgetClose" type="button" aria-label="Fechar"></button>
    </header>

    <div class="support-widget-content">
      <div class="support-widget-main">
        <div class="support-widget-body">
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
        <div class="support-widget-history-header">
          <strong id="supportWidgetHistoryTitle">Histórico</strong>
          <button class="support-widget-history-toggle" id="supportWidgetHistoryToggle" type="button" aria-label="Recolher histórico" aria-expanded="true" aria-controls="supportWidgetHistoryList" title="Recolher histórico">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
        <div id="supportWidgetHistoryList" class="support-widget-history-list"></div>
      </aside>
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

<script src="<?= url('assets/js/support_chats/widget.js?v=5'); ?>"></script>
