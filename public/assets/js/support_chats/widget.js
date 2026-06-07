document.addEventListener("DOMContentLoaded", () => {
  const root = document.getElementById("supportWidget");

  if (!root) {
    return;
  }

  const bootstrapUrl = root.dataset.bootstrapUrl || "";
  const chatUrlBase = (root.dataset.chatUrlBase || "").replace(/\/$/, "");
  const wsAuthUrl = root.dataset.wsAuthUrl || "";
  const storageKeys = {
    panel: "manager.supportChat.panelState",
    history: "manager.supportChat.historyState",
  };

  const elements = {
    toggle: document.getElementById("supportWidgetToggle"),
    close: document.getElementById("supportWidgetClose"),
    panel: document.getElementById("supportWidgetPanel"),
    headerMeta: document.getElementById("supportWidgetHeaderMeta"),
    messages: document.getElementById("supportWidgetMessages"),
    historyList: document.getElementById("supportWidgetHistoryList"),
    historyPreview: document.getElementById("supportWidgetHistoryPreview"),
    historyToggle: document.getElementById("supportWidgetHistoryToggle"),
    typing: document.getElementById("supportWidgetTyping"),
    form: document.getElementById("supportWidgetComposer"),
    input: document.getElementById("supportWidgetInput"),
    sendButton: document.getElementById("supportWidgetSendButton"),
    connection: document.getElementById("supportWidgetConnection"),
  };

  const state = {
    bootstrap: null,
    socket: null,
    socketAuthenticated: false,
    reconnectTimer: null,
    reconnectDelay: 1200,
    typingTimer: null,
    lastTypingState: false,
    historyPreviewChatId: null,
    historyPreview: null,
    historyCollapsed: false,
  };

  function readStoredValue(key) {
    try {
      return window.localStorage.getItem(key);
    } catch (error) {
      return null;
    }
  }

  function writeStoredValue(key, value) {
    try {
      window.localStorage.setItem(key, value);
    } catch (error) {
      // localStorage can be blocked by browser privacy settings.
    }
  }

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function parseLocalDateTime(value) {
    const text = String(value || "").trim();
    if (!text) {
      return null;
    }

    if (/[zZ]|[+-]\d{2}:?\d{2}$/.test(text)) {
      return new Date(text.replace(" ", "T"));
    }

    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
    if (match) {
      return new Date(
        Number(match[1]),
        Number(match[2]) - 1,
        Number(match[3]),
        Number(match[4] || 0),
        Number(match[5] || 0),
        Number(match[6] || 0)
      );
    }

    return new Date(text.replace(" ", "T"));
  }

  function formatDate(value) {
    if (!value) {
      return "";
    }

    const date = parseLocalDateTime(value);
    if (!date || Number.isNaN(date.getTime())) {
      return value;
    }

    return new Intl.DateTimeFormat("pt-BR", {
      dateStyle: "short",
      timeStyle: "short",
    }).format(date);
  }

  function statusLabel(status) {
    switch (status) {
      case "pending":
        return "Aguardando atendente";
      case "open":
        return "Atendimento em andamento";
      case "closed":
        return "Atendimento finalizado";
      default:
        return "Suporte";
    }
  }

  function activeChat() {
    const chat = state.bootstrap && state.bootstrap.chat;
    return chat && chat.status !== "closed" ? chat : null;
  }

  function historyChats() {
    return (state.bootstrap && state.bootstrap.history) || [];
  }

  function chatId() {
    const chat = activeChat();
    return Number((chat && chat.id) || 0);
  }

  function currentUserId() {
    return Number((state.bootstrap && state.bootstrap.currentUser && state.bootstrap.currentUser.id) || 0);
  }

  function setConnectionStatus(label, isOnline) {
    elements.connection.textContent = label;
    elements.connection.classList.toggle("text-success", Boolean(isOnline));
    elements.connection.classList.toggle("text-muted", !isOnline);
  }

  function setPanelOpen(isOpen, shouldPersist = true, shouldFocus = true) {
    elements.panel.hidden = !isOpen;
    elements.toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");

    if (shouldPersist) {
      writeStoredValue(storageKeys.panel, isOpen ? "open" : "closed");
    }

    if (isOpen && shouldFocus) {
      elements.input.focus();
    }
  }

  function togglePanel(forceOpen) {
    const shouldOpen = typeof forceOpen === "boolean" ? forceOpen : elements.panel.hidden;
    setPanelOpen(shouldOpen);
  }

  function setHistoryCollapsed(isCollapsed, shouldPersist = true) {
    state.historyCollapsed = Boolean(isCollapsed);
    root.classList.toggle("is-history-collapsed", state.historyCollapsed);

    if (elements.historyToggle) {
      elements.historyToggle.setAttribute("aria-expanded", state.historyCollapsed ? "false" : "true");
      elements.historyToggle.setAttribute("aria-label", state.historyCollapsed ? "Expandir histórico" : "Recolher histórico");
      elements.historyToggle.setAttribute("title", state.historyCollapsed ? "Expandir histórico" : "Recolher histórico");

      const icon = elements.historyToggle.querySelector("i");
      if (icon) {
        icon.classList.toggle("bi-chevron-right", !state.historyCollapsed);
        icon.classList.toggle("bi-chevron-left", state.historyCollapsed);
      }
    }

    if (shouldPersist) {
      writeStoredValue(storageKeys.history, state.historyCollapsed ? "collapsed" : "expanded");
    }
  }

  function renderMessages() {
    const chat = activeChat();
    const messages = chat ? ((state.bootstrap && state.bootstrap.messages) || []) : [];
    const tenant = state.bootstrap && state.bootstrap.tenant;
    const tenantName = tenant ? (tenant.fantasy || tenant.name || ("Tenant " + tenant.database_number)) : "Suporte";

    elements.headerMeta.textContent = chat ? `${tenantName} | ${statusLabel(chat.status)}` : `${tenantName} | Sem conversa iniciada`;
    elements.typing.textContent = "";

    if (!messages.length) {
      elements.messages.innerHTML = '<div class="support-widget-empty"><p class="mb-0">Digite a primeira mensagem para iniciar o atendimento.</p></div>';
      return;
    }

    elements.messages.innerHTML = messages.map((message) => {
      const isSelf = Number(message.sender_user_id || 0) === currentUserId() && message.sender_role === "customer";
      const cssClass = isSelf ? "is-self" : "is-other";

      return `
        <div class="support-widget-message ${cssClass}">
          <div class="support-widget-message-meta">${escapeHtml(message.sender_name || message.sender_role)} | ${escapeHtml(formatDate(message.created))}</div>
          <div class="support-widget-bubble">${escapeHtml(message.message_text || "")}</div>
        </div>
      `;
    }).join("");

    elements.messages.scrollTop = elements.messages.scrollHeight;
  }

  function renderHistoryPreview() {
    if (!elements.historyPreview) {
      return;
    }

    const history = historyChats();
    const preview = state.historyPreview;

    if (!history.length) {
      elements.historyPreview.innerHTML = "";
      return;
    }

    if (!state.historyPreviewChatId) {
      elements.historyPreview.innerHTML = '<div class="support-widget-history-empty">Selecione um atendimento finalizado.</div>';
      return;
    }

    if (preview && preview.loading) {
      elements.historyPreview.innerHTML = '<div class="support-widget-history-loading">Carregando histórico...</div>';
      return;
    }

    if (preview && preview.error) {
      elements.historyPreview.innerHTML = '<div class="support-widget-history-empty">Não foi possível carregar este histórico.</div>';
      return;
    }

    if (!preview || !preview.chat) {
      elements.historyPreview.innerHTML = "";
      return;
    }

    const chat = preview.chat;
    const messages = (preview.messages || []).slice(-20);

    elements.historyPreview.innerHTML = `
      <div class="support-widget-history-preview-title">
        <strong>Atendimento #${Number(chat.id)}</strong>
        <span>Finalizado em ${escapeHtml(formatDate(chat.closed_at || chat.last_message_at || chat.created))}</span>
      </div>
      <div class="support-widget-history-messages">
        ${messages.map((message) => {
          const isSelf = Number(message.sender_user_id || 0) === currentUserId() && message.sender_role === "customer";
          const cssClass = isSelf ? "is-self" : "is-other";

          return `
            <div class="support-widget-history-message ${cssClass}">
              <div class="support-widget-history-message-meta">${escapeHtml(message.sender_name || message.sender_role)} | ${escapeHtml(formatDate(message.created))}</div>
              <div class="support-widget-history-message-text">${escapeHtml(message.message_text || "")}</div>
            </div>
          `;
        }).join("")}
      </div>
    `;
  }

  function renderHistory() {
    if (!elements.historyList) {
      return;
    }

    const history = historyChats();

    if (!history.length) {
      state.historyPreviewChatId = null;
      state.historyPreview = null;
      elements.historyList.innerHTML = '<div class="support-widget-history-empty">Nenhum histórico finalizado.</div>';
      renderHistoryPreview();
      return;
    }

    const historyIds = history.map((chat) => Number(chat.id));
    if (state.historyPreviewChatId && !historyIds.includes(Number(state.historyPreviewChatId))) {
      state.historyPreviewChatId = null;
      state.historyPreview = null;
    }

    elements.historyList.innerHTML = history.map((chat) => {
      const chatDate = chat.closed_at || chat.last_message_at || chat.created;
      const activeClass = Number(chat.id) === Number(state.historyPreviewChatId) ? " is-active" : "";
      const attendantName = chat.attendant_name || chat.attendant_username || "Sem atendente";

      return `
        <button type="button" class="support-widget-history-item${activeClass}" data-history-chat-id="${Number(chat.id)}">
          <span class="support-widget-history-item-top">
            <strong>Chat #${Number(chat.id)}</strong>
            <span>${escapeHtml(formatDate(chatDate))}</span>
          </span>
          <span class="support-widget-history-item-bottom">
            <span>${escapeHtml(attendantName)}</span>
          </span>
        </button>
      `;
    }).join("");

    elements.historyList.querySelectorAll("[data-history-chat-id]").forEach((button) => {
      button.addEventListener("click", () => {
        const chatId = Number(button.dataset.historyChatId || 0);
        if (chatId > 0) {
          loadHistoryPreview(chatId);
        }
      });
    });

    renderHistoryPreview();
  }

  async function fetchJson(url, options) {
    const response = await fetch(url, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      ...options,
    });

    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload.message || "Request failed");
    }

    return payload;
  }

  async function refreshBootstrap() {
    state.bootstrap = await fetchJson(bootstrapUrl);
    renderMessages();
    renderHistory();
  }

  async function loadHistoryPreview(chatId) {
    state.historyPreviewChatId = Number(chatId);
    state.historyPreview = { loading: true };
    renderHistory();

    try {
      state.historyPreview = await fetchJson(`${chatUrlBase}/${encodeURIComponent(chatId)}`);
    } catch (error) {
      state.historyPreview = { error: true };
    }

    renderHistory();
  }

  function sendSocketMessage(payload) {
    if (!state.socket || state.socket.readyState !== WebSocket.OPEN || !state.socketAuthenticated) {
      return false;
    }

    state.socket.send(JSON.stringify(payload));
    return true;
  }

  function scheduleReconnect() {
    if (state.reconnectTimer) {
      window.clearTimeout(state.reconnectTimer);
    }

    state.reconnectTimer = window.setTimeout(() => {
      connectSocket();
    }, state.reconnectDelay);

    state.reconnectDelay = Math.min(state.reconnectDelay * 1.7, 10000);
  }

  async function connectSocket() {
    try {
      const auth = await fetchJson(wsAuthUrl);
      const socket = new WebSocket(auth.websocketUrl);

      state.socket = socket;
      state.socketAuthenticated = false;
      setConnectionStatus("Conectando...", false);

      socket.addEventListener("open", () => {
        socket.send(JSON.stringify({
          type: "authenticate",
          token: auth.token,
        }));
      });

      socket.addEventListener("message", async (event) => {
        let payload = null;

        try {
          payload = JSON.parse(event.data);
        } catch (error) {
          return;
        }

        if (!payload || !payload.type) {
          return;
        }

        if (payload.type === "auth_ok") {
          state.socketAuthenticated = true;
          state.reconnectDelay = 1200;
          setConnectionStatus("Conectado", true);
          return;
        }

        if (payload.type === "typing") {
          if (Number(payload.chat_id) === chatId()) {
            elements.typing.textContent = payload.is_typing ? `${payload.user_name || "Atendente"} está digitando...` : "";
          }
          return;
        }

        if (payload.type === "message_created" || payload.type === "chat_created" || payload.type === "chat_assigned" || payload.type === "chat_closed") {
          await refreshBootstrap();
          return;
        }
      });

      socket.addEventListener("close", () => {
        state.socketAuthenticated = false;
        setConnectionStatus("Reconectando...", false);
        scheduleReconnect();
      });

      socket.addEventListener("error", () => {
        setConnectionStatus("Falha na conexão", false);
      });
    } catch (error) {
      setConnectionStatus("Falha na conexão", false);
      scheduleReconnect();
    }
  }

  function sendTyping(isTyping) {
    const id = chatId();
    if (state.lastTypingState === isTyping) {
      return;
    }

    state.lastTypingState = isTyping;
    sendSocketMessage({
      type: "typing",
      chat_id: id > 0 ? id : null,
      is_typing: Boolean(isTyping),
    });
  }

  elements.toggle.addEventListener("click", () => togglePanel());
  elements.close.addEventListener("click", () => togglePanel(false));

  if (elements.historyToggle) {
    elements.historyToggle.addEventListener("click", () => {
      setHistoryCollapsed(!state.historyCollapsed);
    });
  }

  elements.form.addEventListener("submit", (event) => {
    event.preventDefault();

    const message = elements.input.value.trim();
    if (!message) {
      return;
    }

    const sent = sendSocketMessage({
      type: "send_message",
      chat_id: chatId() || null,
      message,
    });

    if (!sent) {
      return;
    }

    elements.input.value = "";
    elements.typing.textContent = "";
    sendTyping(false);
  });

  elements.input.addEventListener("input", () => {
    const hasText = elements.input.value.trim() !== "";
    sendTyping(hasText);

    if (state.typingTimer) {
      window.clearTimeout(state.typingTimer);
    }

    state.typingTimer = window.setTimeout(() => {
      sendTyping(false);
    }, 900);
  });

  setHistoryCollapsed(readStoredValue(storageKeys.history) === "collapsed", false);
  setPanelOpen(readStoredValue(storageKeys.panel) === "open", false, false);

  refreshBootstrap().catch(() => {
    setConnectionStatus("Falha ao carregar", false);
  });

  connectSocket();
});
