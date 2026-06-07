document.addEventListener("DOMContentLoaded", () => {
  const root = document.getElementById("supportWidget");

  if (!root) {
    return;
  }

  const bootstrapUrl = root.dataset.bootstrapUrl || "";
  const chatUrlBase = (root.dataset.chatUrlBase || "").replace(/\/$/, "");
  const wsAuthUrl = root.dataset.wsAuthUrl || "";

  const elements = {
    toggle: document.getElementById("supportWidgetToggle"),
    close: document.getElementById("supportWidgetClose"),
    panel: document.getElementById("supportWidgetPanel"),
    headerMeta: document.getElementById("supportWidgetHeaderMeta"),
    messages: document.getElementById("supportWidgetMessages"),
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
  };

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

  function chatId() {
    return Number((state.bootstrap && state.bootstrap.chat && state.bootstrap.chat.id) || 0);
  }

  function currentUserId() {
    return Number((state.bootstrap && state.bootstrap.currentUser && state.bootstrap.currentUser.id) || 0);
  }

  function setConnectionStatus(label, isOnline) {
    elements.connection.textContent = label;
    elements.connection.classList.toggle("text-success", Boolean(isOnline));
    elements.connection.classList.toggle("text-muted", !isOnline);
  }

  function renderMessages() {
    const chat = state.bootstrap && state.bootstrap.chat;
    const messages = (state.bootstrap && state.bootstrap.messages) || [];
    const tenant = state.bootstrap && state.bootstrap.tenant;
    const tenantName = tenant ? (tenant.fantasy || tenant.name || ("Tenant " + tenant.database_number)) : "Suporte";

    elements.headerMeta.textContent = chat ? `${tenantName} | ${statusLabel(chat.status)}` : `${tenantName} | Sem conversa iniciada`;

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
  }

  function togglePanel(forceOpen) {
    const shouldOpen = typeof forceOpen === "boolean" ? forceOpen : elements.panel.hidden;
    elements.panel.hidden = !shouldOpen;
    elements.toggle.setAttribute("aria-expanded", shouldOpen ? "true" : "false");

    if (shouldOpen) {
      elements.input.focus();
    }
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

  refreshBootstrap().catch(() => {
    setConnectionStatus("Falha ao carregar", false);
  });

  connectSocket();
});
