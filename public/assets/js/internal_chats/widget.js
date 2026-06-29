document.addEventListener("DOMContentLoaded", () => {
  const shell = document.getElementById("supportWidget");
  const root = document.getElementById("internalChatWidget");

  if (!shell || !root) {
    return;
  }

  const bootstrapUrl = root.dataset.bootstrapUrl || "";
  const chatUrlBase = (root.dataset.chatUrlBase || "").replace(/\/$/, "");
  const startUrl = root.dataset.startUrl || "";
  const sendUrl = root.dataset.sendUrl || "";
  const typingUrl = root.dataset.typingUrl || "";
  const typingStatusUrlBase = (root.dataset.typingStatusUrlBase || "").replace(/\/$/, "");
  const wsAuthUrl = root.dataset.wsAuthUrl || "";
  const storageKeys = {
    activeTab: "manager.chat.activeTab",
    activeChat: "manager.internalChat.activeChatId",
  };

  const elements = {
    supportTab: document.getElementById("supportWidgetSupportTab"),
    internalTab: document.getElementById("internalChatTab"),
    toggle: document.getElementById("supportWidgetToggle"),
    panel: document.getElementById("supportWidgetPanel"),
    supportPane: document.getElementById("supportWidgetSupportPane"),
    supportMeta: document.getElementById("supportWidgetHeaderMeta"),
    internalMeta: document.getElementById("internalChatHeaderMeta"),
    conversationList: document.getElementById("internalChatConversationList"),
    userList: document.getElementById("internalChatUserList"),
    refresh: document.getElementById("internalChatRefresh"),
    messages: document.getElementById("internalChatMessages"),
    typing: document.getElementById("internalChatTyping"),
    form: document.getElementById("internalChatComposer"),
    input: document.getElementById("internalChatInput"),
    sendButton: document.getElementById("internalChatSendButton"),
    connection: document.getElementById("internalChatConnection"),
  };

  const state = {
    bootstrap: null,
    activeChat: null,
    messages: [],
    typingUsers: [],
    activeChatId: Number(readStoredValue(storageKeys.activeChat) || 0),
    activeTab: readStoredValue(storageKeys.activeTab) === "internal" ? "internal" : "support",
    socket: null,
    socketAuthenticated: false,
    reconnectTimer: null,
    refreshTimer: null,
    typingPollTimer: null,
    typingTimer: null,
    lastTypingState: false,
    lastTypingSentAt: 0,
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

  function currentUserId() {
    return Number((state.bootstrap && state.bootstrap.currentUser && state.bootstrap.currentUser.id) || 0);
  }

  function tenantName() {
    const tenant = state.bootstrap && state.bootstrap.tenant;
    if (!tenant) {
      return "Chat interno";
    }

    return tenant.fantasy || tenant.name || ("Tenant " + tenant.database_number);
  }

  function chatTitle(chat) {
    if (!chat) {
      return "";
    }

    return chat.title || chat.participant_names || chat.participant_usernames || ("Conversa #" + Number(chat.id || 0));
  }

  function setConnectionStatus(label, isOnline) {
    elements.connection.textContent = label;
    elements.connection.classList.toggle("text-success", Boolean(isOnline));
    elements.connection.classList.toggle("text-muted", !isOnline);
  }

  function setActiveTab(tab) {
    const isInternal = tab === "internal";
    state.activeTab = isInternal ? "internal" : "support";

    elements.supportTab.classList.toggle("is-active", !isInternal);
    elements.internalTab.classList.toggle("is-active", isInternal);
    elements.supportTab.setAttribute("aria-selected", isInternal ? "false" : "true");
    elements.internalTab.setAttribute("aria-selected", isInternal ? "true" : "false");
    elements.supportPane.hidden = isInternal;
    root.hidden = !isInternal;
    elements.supportMeta.hidden = isInternal;
    elements.internalMeta.hidden = !isInternal;
    shell.classList.toggle("has-internal-active", isInternal);

    writeStoredValue(storageKeys.activeTab, state.activeTab);

    if (isInternal) {
      refreshBootstrap().catch(() => {
        setConnectionStatus("Falha ao carregar", false);
      });
      startRefreshTimer();
      syncTypingPollTimer();

      if (state.activeChatId > 0 && !elements.panel.hidden) {
        elements.input.focus();
      }
      return;
    }

    stopRefreshTimer();
    stopTypingPollTimer();
  }

  async function fetchJson(url, options) {
    const response = await fetch(url, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...((options && options.headers) || {}),
      },
      ...options,
    });

    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload.message || "Request failed");
    }

    return payload;
  }

  async function postJson(url, payload) {
    return fetchJson(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    });
  }

  function bootstrapEndpoint() {
    if (state.activeChatId > 0) {
      return `${bootstrapUrl}?chat_id=${encodeURIComponent(state.activeChatId)}`;
    }

    return bootstrapUrl;
  }

  function typingStatusEndpoint() {
    if (!typingStatusUrlBase || state.activeChatId <= 0) {
      return "";
    }

    return `${typingStatusUrlBase}/${encodeURIComponent(state.activeChatId)}`;
  }

  async function refreshBootstrap() {
    const payload = await fetchJson(bootstrapEndpoint());
    state.bootstrap = payload;
    state.activeChat = payload.activeChat || null;
    state.messages = payload.messages || [];
    state.typingUsers = payload.typingUsers || [];

    if (state.activeChat) {
      state.activeChatId = Number(state.activeChat.id || 0);
      writeStoredValue(storageKeys.activeChat, String(state.activeChatId));
    } else if (state.activeChatId > 0) {
      state.activeChatId = 0;
      writeStoredValue(storageKeys.activeChat, "");
    }

    render();
    syncTypingPollTimer();
    setConnectionStatus(state.socketAuthenticated ? "Conectado" : "Atualizando automaticamente", state.socketAuthenticated);
  }

  function render() {
    renderHeader();
    renderConversationList();
    renderUserList();
    renderMessages();
    renderTyping();
  }

  function renderHeader() {
    if (state.activeChat) {
      elements.internalMeta.textContent = `${tenantName()} | ${chatTitle(state.activeChat)}`;
      return;
    }

    elements.internalMeta.textContent = `${tenantName()} | Chat interno`;
  }

  function renderConversationList() {
    const chats = (state.bootstrap && state.bootstrap.chats) || [];

    if (!chats.length) {
      elements.conversationList.innerHTML = '<div class="internal-chat-list-empty">Nenhuma conversa interna.</div>';
      return;
    }

    elements.conversationList.innerHTML = chats.map((chat) => {
      const isActive = Number(chat.id || 0) === Number(state.activeChatId || 0);
      const unread = Number(chat.unread_count || 0);
      const activeClass = isActive ? " is-active" : "";
      const lastMessage = chat.last_message_text ? escapeHtml(chat.last_message_text) : "Sem mensagens";
      const chatDate = chat.last_message_at || chat.created;

      return `
        <button type="button" class="internal-chat-list-item${activeClass}" data-internal-chat-id="${Number(chat.id)}">
          <span class="internal-chat-list-item-title">
            <strong>${escapeHtml(chatTitle(chat))}</strong>
            ${unread > 0 ? `<span class="badge text-bg-primary">${unread}</span>` : ""}
          </span>
          <span class="internal-chat-list-item-meta">${escapeHtml(lastMessage)}</span>
          <span class="internal-chat-list-item-meta">${escapeHtml(formatDate(chatDate))}</span>
        </button>
      `;
    }).join("");

    elements.conversationList.querySelectorAll("[data-internal-chat-id]").forEach((button) => {
      button.addEventListener("click", () => {
        const chatId = Number(button.dataset.internalChatId || 0);
        if (chatId > 0) {
          openChat(chatId);
        }
      });
    });
  }

  function renderUserList() {
    const users = (state.bootstrap && state.bootstrap.users) || [];

    if (!users.length) {
      elements.userList.innerHTML = '<div class="internal-chat-list-empty">Nenhum outro usuário neste tenant.</div>';
      return;
    }

    elements.userList.innerHTML = users.map((user) => {
      return `
        <button type="button" class="internal-chat-list-item" data-internal-user-id="${Number(user.id)}">
          <span class="internal-chat-list-item-title">
            <strong>${escapeHtml(user.name || user.username)}</strong>
          </span>
          <span class="internal-chat-list-item-meta">${escapeHtml(user.username || "")} | ${escapeHtml(user.role || "user")}</span>
        </button>
      `;
    }).join("");

    elements.userList.querySelectorAll("[data-internal-user-id]").forEach((button) => {
      button.addEventListener("click", () => {
        const userId = Number(button.dataset.internalUserId || 0);
        if (userId > 0) {
          startDirectChat(userId);
        }
      });
    });
  }

  function renderMessages() {
    const messages = state.messages || [];

    elements.input.disabled = !state.activeChat;
    elements.sendButton.disabled = !state.activeChat;

    if (!state.activeChat) {
      elements.messages.innerHTML = '<div class="support-widget-empty"><p class="mb-0">Selecione um usuário para iniciar uma conversa interna.</p></div>';
      elements.typing.textContent = "";
      return;
    }

    if (!messages.length) {
      elements.messages.innerHTML = '<div class="support-widget-empty"><p class="mb-0">Nenhuma mensagem nesta conversa.</p></div>';
      return;
    }

    elements.messages.innerHTML = messages.map((message) => {
      const isSelf = Number(message.sender_user_id || 0) === currentUserId();
      const cssClass = isSelf ? "is-self" : "is-other";

      return `
        <div class="support-widget-message ${cssClass}">
          <div class="support-widget-message-meta">${escapeHtml(message.sender_name || "")} | ${escapeHtml(formatDate(message.created))}</div>
          <div class="support-widget-bubble">${escapeHtml(message.message_text || "")}</div>
        </div>
      `;
    }).join("");

    elements.messages.scrollTop = elements.messages.scrollHeight;
  }

  function renderTyping() {
    if (!state.activeChat) {
      elements.typing.textContent = "";
      return;
    }

    const typingUsers = state.typingUsers || [];
    const names = typingUsers
      .map((user) => user.name || user.username || "Usuário")
      .filter((name) => String(name || "").trim() !== "");

    if (!names.length) {
      elements.typing.textContent = "";
      return;
    }

    if (names.length === 1) {
      elements.typing.textContent = `${names[0]} está digitando...`;
      return;
    }

    elements.typing.textContent = `${names.slice(0, 2).join(", ")} estão digitando...`;
  }

  async function openChat(chatId) {
    state.activeChatId = Number(chatId);
    writeStoredValue(storageKeys.activeChat, String(state.activeChatId));
    setConnectionStatus("Carregando conversa...", false);

    try {
      const payload = await fetchJson(`${chatUrlBase}/${encodeURIComponent(chatId)}`);
      state.activeChat = payload.chat || null;
      state.messages = payload.messages || [];
      state.typingUsers = payload.typingUsers || [];
      await refreshBootstrap();
      syncTypingPollTimer();
      elements.input.focus();
    } catch (error) {
      setConnectionStatus("Falha ao abrir conversa", false);
    }
  }

  async function startDirectChat(userId) {
    setConnectionStatus("Iniciando conversa...", false);

    try {
      const payload = await postJson(startUrl, {
        target_user_id: Number(userId),
      });

      state.activeChat = payload.chat || null;
      state.activeChatId = Number((state.activeChat && state.activeChat.id) || 0);
      state.messages = payload.messages || [];
      state.typingUsers = payload.typingUsers || [];
      writeStoredValue(storageKeys.activeChat, String(state.activeChatId || ""));
      state.bootstrap = {
        ...state.bootstrap,
        chats: payload.chats || ((state.bootstrap && state.bootstrap.chats) || []),
      };
      await refreshBootstrap();
      syncTypingPollTimer();
      elements.input.focus();
    } catch (error) {
      setConnectionStatus("Falha ao iniciar conversa", false);
    }
  }

  async function sendCurrentMessage() {
    const message = elements.input.value.trim();
    if (!message || !state.activeChatId) {
      return;
    }

    elements.sendButton.disabled = true;

    try {
      const payload = await postJson(sendUrl, {
        chat_id: state.activeChatId,
        message,
      });

      elements.input.value = "";
      state.activeChat = payload.chat || state.activeChat;
      state.messages = payload.messages || state.messages;
      state.typingUsers = payload.typingUsers || [];
      state.bootstrap = {
        ...state.bootstrap,
        chats: payload.chats || ((state.bootstrap && state.bootstrap.chats) || []),
      };
      render();
      sendTyping(false);
      setConnectionStatus(state.socketAuthenticated ? "Conectado" : "Atualizando automaticamente", state.socketAuthenticated);
      syncTypingPollTimer();
    } catch (error) {
      setConnectionStatus("Falha ao enviar", false);
    } finally {
      elements.sendButton.disabled = !state.activeChat;
    }
  }

  function sendSocketMessage(payload) {
    if (!state.socket || state.socket.readyState !== WebSocket.OPEN || !state.socketAuthenticated) {
      return false;
    }

    state.socket.send(JSON.stringify(payload));
    return true;
  }

  function sendTyping(isTyping) {
    const now = Date.now();
    const shouldRefreshTyping = isTyping && state.lastTypingState === isTyping && (now - state.lastTypingSentAt) > 2500;

    if ((!shouldRefreshTyping && state.lastTypingState === isTyping) || !state.activeChatId) {
      return;
    }

    state.lastTypingState = isTyping;
    state.lastTypingSentAt = now;

    sendSocketMessage({
      type: "internal_typing",
      chat_id: state.activeChatId,
      is_typing: Boolean(isTyping),
    });

    updateTypingState(isTyping);
  }

  async function updateTypingState(isTyping) {
    if (!typingUrl || !state.activeChatId) {
      return;
    }

    try {
      const payload = await postJson(typingUrl, {
        chat_id: state.activeChatId,
        is_typing: Boolean(isTyping),
      });

      state.typingUsers = payload.typingUsers || [];
      renderTyping();
    } catch (error) {
      // Typing state is best-effort and must not interrupt messaging.
    }
  }

  async function refreshTypingStatus() {
    const url = typingStatusEndpoint();
    if (!url || state.activeTab !== "internal") {
      return;
    }

    try {
      const payload = await fetchJson(url);
      state.typingUsers = payload.typingUsers || [];
      renderTyping();
    } catch (error) {
      // Typing state is ephemeral; the next poll can recover.
    }
  }

  function scheduleReconnect() {
    if (state.reconnectTimer) {
      window.clearTimeout(state.reconnectTimer);
    }

    state.reconnectTimer = window.setTimeout(() => {
      connectSocket();
    }, 8000);
  }

  async function connectSocket() {
    if (!wsAuthUrl) {
      setConnectionStatus("Atualizando automaticamente", false);
      return;
    }

    try {
      const auth = await fetchJson(wsAuthUrl);
      const socket = new WebSocket(auth.websocketUrl);

      state.socket = socket;
      state.socketAuthenticated = false;

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
          setConnectionStatus("Conectado", true);
          return;
        }

        if (payload.type === "internal_typing" && Number(payload.chat_id || 0) === Number(state.activeChatId || 0)) {
          if (payload.is_typing && Number(payload.user_id || 0) !== currentUserId()) {
            state.typingUsers = [{
              id: Number(payload.user_id || 0),
              name: payload.user_name || payload.username || "Usuário",
              username: payload.username || "",
            }];
          } else if (!payload.is_typing) {
            state.typingUsers = [];
          }

          renderTyping();
          refreshTypingStatus();
          return;
        }

        if (payload.type === "internal_message_created" || payload.type === "internal_chat_created" || payload.type === "internal_chat_updated") {
          await refreshBootstrap();
        }
      });

      socket.addEventListener("close", () => {
        state.socketAuthenticated = false;
        setConnectionStatus("Atualizando automaticamente", false);
        scheduleReconnect();
      });

      socket.addEventListener("error", () => {
        state.socketAuthenticated = false;
        setConnectionStatus("Atualizando automaticamente", false);
      });
    } catch (error) {
      setConnectionStatus("Atualizando automaticamente", false);
      scheduleReconnect();
    }
  }

  function startRefreshTimer() {
    stopRefreshTimer();
    state.refreshTimer = window.setInterval(() => {
      if (state.activeTab === "internal") {
        refreshBootstrap().catch(() => {
          setConnectionStatus("Falha ao atualizar", false);
        });
      }
    }, 5000);
  }

  function stopRefreshTimer() {
    if (state.refreshTimer) {
      window.clearInterval(state.refreshTimer);
      state.refreshTimer = null;
    }
  }

  function syncTypingPollTimer() {
    if (state.activeTab !== "internal" || state.activeChatId <= 0) {
      stopTypingPollTimer();
      return;
    }

    if (state.typingPollTimer) {
      return;
    }

    refreshTypingStatus();
    state.typingPollTimer = window.setInterval(() => {
      refreshTypingStatus();
    }, 700);
  }

  function stopTypingPollTimer() {
    if (state.typingPollTimer) {
      window.clearInterval(state.typingPollTimer);
      state.typingPollTimer = null;
    }
  }

  elements.supportTab.addEventListener("click", () => setActiveTab("support"));
  elements.internalTab.addEventListener("click", () => setActiveTab("internal"));
  elements.toggle.addEventListener("click", () => {
    window.setTimeout(() => {
      if (state.activeTab === "internal" && state.activeChatId > 0 && !elements.panel.hidden) {
        elements.input.focus();
      }
    }, 0);
  });
  elements.refresh.addEventListener("click", () => {
    refreshBootstrap().catch(() => {
      setConnectionStatus("Falha ao atualizar", false);
    });
  });

  elements.form.addEventListener("submit", (event) => {
    event.preventDefault();
    sendCurrentMessage();
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

  setActiveTab(state.activeTab);
  refreshBootstrap().catch(() => {
    setConnectionStatus("Falha ao carregar", false);
  });
  connectSocket();
});
