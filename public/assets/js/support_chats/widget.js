document.addEventListener("DOMContentLoaded", () => {
  const root = document.getElementById("supportWidget");

  if (!root) {
    return;
  }

  const bootstrapUrl = root.dataset.bootstrapUrl || "";
  const seenUrl = root.dataset.seenUrl || bootstrapUrl.replace(/bootstrap$/, "seen");
  const chatUrlBase = (root.dataset.chatUrlBase || "").replace(/\/$/, "");
  const wsAuthUrl = root.dataset.wsAuthUrl || "";
  const storageKeys = {
    panel: "manager.supportChat.panelState",
  };

  const elements = {
    toggle: document.getElementById("supportWidgetToggle"),
    close: document.getElementById("supportWidgetClose"),
    panel: document.getElementById("supportWidgetPanel"),
    supportPane: document.getElementById("supportWidgetSupportPane"),
    threadIdentity: document.getElementById("supportWidgetThreadIdentity"),
    back: document.getElementById("supportWidgetBack"),
    chatList: document.getElementById("supportWidgetChatList"),
    chatsCount: document.getElementById("supportWidgetChatsCount"),
    newChat: document.getElementById("supportWidgetNewChat"),
    messages: document.getElementById("supportWidgetMessages"),
    typing: document.getElementById("supportWidgetTyping"),
    notice: document.getElementById("supportWidgetNotice"),
    form: document.getElementById("supportWidgetComposer"),
    input: document.getElementById("supportWidgetInput"),
    sendButton: document.getElementById("supportWidgetSendButton"),
    resumeButton: document.getElementById("supportWidgetResumeButton"),
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
    // 0 shows the active chat (or a new one); a closed chat id shows it read-only.
    viewedChatId: 0,
    viewedPayload: null,
    compactThreadOpen: false,
    refreshPromise: null,
    seenRequests: new Set(),
    acknowledgedMessages: new Map(),
    renderedMessagesKey: "",
    renderedChatId: 0,
    renderedHeaderHtml: "",
    renderedListHtml: "",
  };

  const presenceLabels = {
    online: "Online",
    away: "Ausente",
    busy: "Ocupado",
    offline: "Offline",
  };
  const defaultPlaceholder = elements.input.getAttribute("placeholder") || "";
  // Consecutive messages from one sender inside this window share one name/time line.
  const messageGroupWindowMs = 5 * 60 * 1000;

  // Must match the compact breakpoint in support_chats/widget.css.
  const compactQuery = window.matchMedia("(max-width: 767.98px)");
  // Touch devices: focusing the textarea would pop the virtual keyboard over the chat.
  const coarsePointerQuery = window.matchMedia("(pointer: coarse)");

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

  function timestamp(value) {
    const date = parseLocalDateTime(value);
    return date && !Number.isNaN(date.getTime()) ? date.getTime() : 0;
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

  // Full date and time, e.g. "19/11/2026 19:17" (when a chat was closed).
  function formatFullDateTime(value) {
    const date = parseLocalDateTime(value);
    if (!date || Number.isNaN(date.getTime())) {
      return String(value || "");
    }

    const day = new Intl.DateTimeFormat("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric" }).format(date);
    const time = new Intl.DateTimeFormat("pt-BR", { hour: "2-digit", minute: "2-digit" }).format(date);
    return `${day} ${time}`;
  }

  // Today: "21:10"; this year: "19/11 21:10"; older: "19/11/2025 21:10".
  function formatMessageTime(value) {
    const date = parseLocalDateTime(value);
    if (!date || Number.isNaN(date.getTime())) {
      return String(value || "");
    }

    const now = new Date();
    const sameYear = date.getFullYear() === now.getFullYear();
    const sameDay = sameYear && date.getMonth() === now.getMonth() && date.getDate() === now.getDate();
    const time = new Intl.DateTimeFormat("pt-BR", { hour: "2-digit", minute: "2-digit" }).format(date);
    if (sameDay) {
      return time;
    }

    const day = new Intl.DateTimeFormat("pt-BR", sameYear
      ? { day: "2-digit", month: "2-digit" }
      : { day: "2-digit", month: "2-digit", year: "numeric" }).format(date);
    return `${day} ${time}`;
  }

  // Renders a thread, showing the sender line only when the sender changes or
  // a few minutes passed; the full date stays available on hover.
  function messagesHtml(messages, isSelfMessage) {
    let previous = null;

    return messages.map((message) => {
      const isSelf = isSelfMessage(message);
      const senderKey = `${message.sender_role || ""}:${Number(message.sender_user_id || 0)}`;
      const time = timestamp(message.created) || null;
      const isContinued = Boolean(previous) && previous.senderKey === senderKey
        && time !== null && previous.time !== null
        && time - previous.time >= 0 && time - previous.time < messageGroupWindowMs;
      previous = { senderKey, time };

      const when = escapeHtml(formatMessageTime(message.created));
      const meta = isSelf ? when : `${escapeHtml(message.sender_name || message.sender_role)} · ${when}`;

      return `
        <div class="support-widget-message ${isSelf ? "is-self" : "is-other"}${isContinued ? " is-continued" : ""}">
          ${isContinued ? "" : `<div class="support-widget-message-meta">${meta}</div>`}
          <div class="support-widget-bubble" title="${escapeHtml(formatDate(message.created))}">${escapeHtml(message.message_text || "")}</div>
        </div>
      `;
    }).join("");
  }

  function presenceStatus(value) {
    const status = String(value || "offline");
    return Object.prototype.hasOwnProperty.call(presenceLabels, status) ? status : "offline";
  }

  function initials(name) {
    const parts = String(name || "").trim().split(/\s+/).filter(Boolean);
    if (!parts.length) {
      return "?";
    }

    const first = Array.from(parts[0]);
    const last = parts.length > 1 ? Array.from(parts[parts.length - 1]) : [];
    return ((first[0] || "") + (last[0] || first[1] || "")).toUpperCase();
  }

  // contentHtml must already be escaped.
  function avatarHtml(contentHtml, status) {
    return `<span class="chat-avatar" data-presence="${status}" aria-hidden="true">${contentHtml}<span class="chat-presence-dot"></span></span>`;
  }

  function onlineAttendants() {
    return (state.bootstrap && state.bootstrap.attendants) || [];
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

  function attendantName(chat) {
    return Number((chat && chat.attendant_user_id) || 0) > 0
      ? (chat.attendant_name || chat.attendant_username || "")
      : "";
  }

  // A closed chat is shown read-only: it is never reopened from the widget.
  function isReadOnly() {
    return state.viewedChatId > 0;
  }

  // The closed chat on screen: the list entry first (available at once), then
  // the loaded payload.
  function viewedClosedChat() {
    if (!isReadOnly()) {
      return null;
    }

    return historyChats().find((chat) => Number(chat.id) === state.viewedChatId)
      || (state.viewedPayload && state.viewedPayload.chat)
      || null;
  }

  function viewedChat() {
    return isReadOnly() ? viewedClosedChat() : activeChat();
  }

  function viewedMessages() {
    if (isReadOnly()) {
      return (state.viewedPayload && state.viewedPayload.messages) || [];
    }

    return activeChat() ? ((state.bootstrap && state.bootstrap.messages) || []) : [];
  }

  function setConnectionStatus(label, isOnline) {
    elements.connection.textContent = label;
    elements.connection.classList.toggle("text-success", Boolean(isOnline));
    elements.connection.classList.toggle("text-muted", !isOnline);
  }

  function showNotice(message) {
    if (!elements.notice) {
      return;
    }

    const text = String(message || "").trim();
    elements.notice.textContent = text;
    elements.notice.hidden = text === "";
  }

  function clearNotice() {
    showNotice("");
  }

  function setPanelOpen(isOpen, shouldPersist = true, shouldFocus = true) {
    elements.panel.hidden = !isOpen;
    root.classList.toggle("is-open", isOpen);
    elements.toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");

    if (shouldPersist) {
      writeStoredValue(storageKeys.panel, isOpen ? "open" : "closed");
    }

    if (isOpen && shouldFocus && !coarsePointerQuery.matches && !elements.input.disabled) {
      elements.input.focus();
    }

    root.dispatchEvent(new CustomEvent("chat:visibility"));
  }

  function togglePanel(forceOpen) {
    const shouldOpen = typeof forceOpen === "boolean" ? forceOpen : elements.panel.hidden;
    setPanelOpen(shouldOpen);
  }

  // Compact panels show either the chat list or the conversation.
  function isConversationVisible() {
    return !compactQuery.matches || state.compactThreadOpen;
  }

  function setCompactThreadOpen(isOpen) {
    state.compactThreadOpen = Boolean(isOpen);
    root.classList.toggle("is-support-thread-open", state.compactThreadOpen);

    if (state.compactThreadOpen) {
      elements.messages.scrollTop = elements.messages.scrollHeight;
      scheduleReadAcknowledgement();
    }
  }

  function focusInput() {
    if (!coarsePointerQuery.matches && !elements.input.disabled) {
      elements.input.focus();
    }
  }

  function render() {
    renderMessages();
    renderComposer();
    renderHeader();
    renderChatList();
  }

  function renderMessages() {
    const chat = viewedChat();
    const messages = viewedMessages();
    const id = Number((chat && chat.id) || state.viewedChatId || 0);

    if (isReadOnly() || !activeChat()) {
      elements.typing.textContent = "";
    }

    if (!messages.length) {
      let text = "Digite a primeira mensagem para iniciar o atendimento.";
      if (isReadOnly()) {
        text = state.viewedPayload ? "Nenhuma mensagem neste atendimento." : "Carregando atendimento...";
      }

      state.renderedMessagesKey = "";
      state.renderedChatId = id;
      elements.messages.innerHTML = `<div class="support-widget-empty"><p class="mb-0">${text}</p></div>`;
      return;
    }

    const lastMessage = messages[messages.length - 1] || {};
    const messagesKey = `${id}:${messages.length}:${lastMessage.id || ""}`;
    if (messagesKey === state.renderedMessagesKey) {
      return;
    }

    const shouldScroll = state.renderedChatId !== id
      || elements.messages.scrollHeight - elements.messages.scrollTop - elements.messages.clientHeight < 60;
    state.renderedMessagesKey = messagesKey;
    state.renderedChatId = id;

    elements.messages.innerHTML = messagesHtml(messages, (message) => (
      Number(message.sender_user_id || 0) === currentUserId() && message.sender_role === "customer"
    ));

    if (shouldScroll) {
      elements.messages.scrollTop = elements.messages.scrollHeight;
    }
  }

  // A closed chat disables typing; the user starts a new atendimento (or goes
  // back to the open one) with the button that replaces "Enviar".
  function renderComposer() {
    const readOnly = isReadOnly();
    const hasActive = Boolean(activeChat());

    elements.input.disabled = readOnly;
    elements.input.setAttribute("placeholder", readOnly ? "Atendimento finalizado." : defaultPlaceholder);
    elements.sendButton.hidden = readOnly;

    if (elements.resumeButton) {
      elements.resumeButton.hidden = !readOnly;
      elements.resumeButton.textContent = hasActive ? "Atendimento atual" : "Novo atendimento";
    }

    if (elements.newChat) {
      elements.newChat.hidden = hasActive;
    }
  }

  // The panel header names the support chat on screen.
  function renderHeader() {
    if (!elements.threadIdentity) {
      return;
    }

    const chat = viewedChat();
    const name = attendantName(chat);
    let status = "offline";
    let avatar = '<i class="bi bi-headset"></i>';
    let title = "Suporte";
    let detail = "";

    if (isReadOnly()) {
      const closedAt = chat && (chat.closed_at || chat.last_message_at || chat.created);
      status = presenceStatus(chat && chat.attendant_presence_status);
      title = name || "Suporte";
      avatar = name ? escapeHtml(initials(name)) : avatar;
      detail = `${name ? `<span class="chat-thread-presence">${presenceLabels[status]}</span> · ` : ""}${escapeHtml(closedAt ? `Finalizado em ${formatFullDateTime(closedAt)}` : "Atendimento finalizado")}`;
    } else if (chat && name) {
      status = presenceStatus(chat.attendant_presence_status);
      title = name;
      avatar = escapeHtml(initials(name));
      detail = `<span class="chat-thread-presence">${presenceLabels[status]}</span> · Atendimento em andamento`;
    } else if (chat) {
      status = onlineAttendants().length ? "online" : "offline";
      detail = "Aguardando atendente";
    } else {
      const count = onlineAttendants().length;
      status = count ? "online" : "offline";
      detail = count
        ? `${count} ${count === 1 ? "atendente online" : "atendentes online"} · escreva para iniciar`
        : "Nenhum atendente online · sua mensagem fica na fila";
    }

    const html = `
      ${avatarHtml(avatar, status)}
      <span class="chat-thread-text">
        <span class="chat-thread-name">${escapeHtml(title)}</span>
        <span class="chat-thread-status">${detail}</span>
      </span>
    `;

    elements.threadIdentity.dataset.presence = status;
    if (html !== state.renderedHeaderHtml) {
      state.renderedHeaderHtml = html;
      elements.threadIdentity.innerHTML = html;
    }
  }

  // Support chats of the last month, newest first, closed ones included. Each
  // row is the root attendant of that chat, tinted with their presence.
  function supportChatEntries() {
    const active = activeChat();
    const entries = active ? [{ chat: active, isOpen: true, at: timestamp(active.last_message_at || active.created) }] : [];

    historyChats().forEach((chat) => {
      if (!active || Number(chat.id) !== Number(active.id)) {
        entries.push({ chat, isOpen: false, at: timestamp(chat.closed_at || chat.last_message_at || chat.created) });
      }
    });

    return entries.sort((a, b) => (b.at - a.at) || (Number(b.chat.id) - Number(a.chat.id)));
  }

  function renderChatList() {
    if (!elements.chatList) {
      return;
    }

    const entries = supportChatEntries();
    const viewedId = isReadOnly() ? state.viewedChatId : chatId();
    let html = "";

    if (elements.chatsCount) {
      elements.chatsCount.textContent = entries.length ? String(entries.length) : "";
    }

    if (!entries.length) {
      html = '<div class="chat-people-empty">Nenhum atendimento no último mês.</div>';
    } else {
      html = entries.map(({ chat, isOpen }) => {
        const id = Number(chat.id);
        const name = attendantName(chat) || "Aguardando atendente";
        const status = attendantName(chat) ? presenceStatus(chat.attendant_presence_status) : "offline";
        const unread = Math.max(0, Number(chat.unread_count || 0));
        let meta = `Finalizado · ${formatFullDateTime(chat.closed_at || chat.last_message_at || chat.created)}`;
        if (isOpen) {
          meta = `${chat.status === "pending" ? "Na fila" : "Em andamento"} · ${formatMessageTime(chat.last_message_at || chat.created)}`;
        }

        const classes = ["chat-person", "chat-person-chat", isOpen ? "is-open" : "is-closed"];
        if (id === viewedId) {
          classes.push("is-active");
        }
        if (unread > 0) {
          classes.push("has-unread");
        }
        const details = [presenceLabels[status], meta];
        if (unread > 0) {
          details.push(`${unread} ${unread === 1 ? "mensagem não lida" : "mensagens não lidas"}`);
        }
        const label = `${name} (${details.join(", ")})`;

        return `
          <button type="button" class="${classes.join(" ")}" data-support-chat-id="${id}" data-presence="${status}" title="${escapeHtml(label)}" aria-label="${escapeHtml(`Atendimento #${id}: ${label}`)}">
            <span class="chat-person-dot" aria-hidden="true"></span>
            <span class="chat-person-text">
              <span class="chat-person-name">${escapeHtml(name)}</span>
              <span class="chat-person-meta">${escapeHtml(meta)}</span>
            </span>
            ${unread > 0 ? `<span class="chat-unread-badge" aria-hidden="true">${unread}</span>` : ""}
          </button>
        `;
      }).join("");
    }

    // The 5s poll re-renders constantly; identical markup would drop hover and focus.
    if (html === state.renderedListHtml) {
      return;
    }

    state.renderedListHtml = html;
    elements.chatList.innerHTML = html;
    elements.chatList.querySelectorAll("[data-support-chat-id]").forEach((button) => {
      button.addEventListener("click", () => {
        const id = Number(button.dataset.supportChatId || 0);
        if (id > 0) {
          showChat(id);
        }
      });
    });
  }

  // The active chat opens for typing; a closed one opens read-only.
  async function showChat(id, openThread = true) {
    if (openThread) {
      setCompactThreadOpen(true);
    }

    if (id === chatId()) {
      showActiveChat();
      return;
    }

    state.viewedChatId = id;
    state.viewedPayload = null;
    render();

    try {
      const payload = await fetchJson(`${chatUrlBase}/${encodeURIComponent(id)}`);
      if (state.viewedChatId !== id) {
        return;
      }

      state.viewedPayload = payload;
      render();
      scheduleReadAcknowledgement();
    } catch (error) {
      if (state.viewedChatId === id) {
        elements.messages.innerHTML = '<div class="support-widget-empty"><p class="mb-0">Não foi possível carregar este atendimento.</p></div>';
      }
    }
  }

  // Back to the open chat, or to a blank one that starts a new atendimento.
  function showActiveChat() {
    state.viewedChatId = 0;
    state.viewedPayload = null;
    setCompactThreadOpen(true);
    render();
    focusInput();
    scheduleReadAcknowledgement();
  }

  async function fetchJson(url, options) {
    const response = await fetch(url, {
      ...options,
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...((options && options.headers) || {}),
      },
    });

    const payload = await response.json();
    if (!response.ok) {
      throw new Error(payload.message || "Request failed");
    }

    return payload;
  }

  async function refreshBootstrap() {
    if (state.refreshPromise) {
      return state.refreshPromise;
    }

    state.refreshPromise = fetchJson(bootstrapUrl).then((payload) => {
      const previousActiveId = chatId();
      state.bootstrap = payload;

      // An attendant closed the chat on screen: keep it visible, read-only.
      if (!isReadOnly() && previousActiveId > 0 && chatId() !== previousActiveId
        && historyChats().some((chat) => Number(chat.id) === previousActiveId)) {
        showChat(previousActiveId, false);
      }

      render();
      publishUnreadCount();
      scheduleReadAcknowledgement();
    }).finally(() => {
      state.refreshPromise = null;
    });

    return state.refreshPromise;
  }

  function publishUnreadCount() {
    root.dispatchEvent(new CustomEvent("chat:unread", {
      detail: {
        channel: "support",
        count: Math.max(0, Number((state.bootstrap && state.bootstrap.unread_count) || 0)),
      },
    }));
  }

  function scheduleReadAcknowledgement() {
    window.requestAnimationFrame(() => {
      markVisibleMessagesSeen();
    });
  }

  async function markVisibleMessagesSeen() {
    if (document.visibilityState !== "visible" || elements.panel.hidden
      || !elements.supportPane || elements.supportPane.hidden || !isConversationVisible()) {
      return;
    }

    let chat = viewedChat();
    if (isReadOnly()) {
      chat = state.viewedPayload && state.viewedPayload.chat;
    }
    const messages = viewedMessages();

    if (!chat || Number(chat.unread_count || 0) <= 0 || !messages.length) {
      return;
    }

    const id = Number(chat.id || 0);
    const lastMessageId = Math.max(...messages.map((message) => Number(message.id || 0)));
    const key = `${id}:${lastMessageId}`;
    if (!id || !lastMessageId || state.seenRequests.has(key) || state.acknowledgedMessages.get(id) >= lastMessageId) {
      return;
    }

    state.seenRequests.add(key);
    try {
      await fetchJson(seenUrl, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ chat_id: id, last_message_id: lastMessageId }),
      });
      state.acknowledgedMessages.set(id, lastMessageId);
      await refreshBootstrap();
    } catch (error) {
      // Read receipts are retried by the next refresh without interrupting chat.
    } finally {
      state.seenRequests.delete(key);
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
          await refreshBootstrap();
          return;
        }

        if (payload.type === "typing") {
          if (!isReadOnly() && Number(payload.chat_id) === chatId()) {
            elements.typing.textContent = payload.is_typing ? `${payload.user_name || "Atendente"} está digitando...` : "";
          }
          return;
        }

        if (payload.type === "error") {
          if (payload.code === "support_unavailable") {
            try {
              await refreshBootstrap();
            } catch (error) {
              // The persisted message will be loaded on the next successful refresh.
            }
            showNotice(payload.message || "Não há atendentes disponíveis no momento. Sua mensagem foi registrada e ficará na fila.");
            return;
          }

          showNotice("Não foi possível concluir a operação no chat.");
          return;
        }

        if (payload.type === "message_created" || payload.type === "chat_created" || payload.type === "chat_assigned" || payload.type === "chat_closed") {
          await refreshBootstrap();

          if (payload.type === "chat_assigned" && Number(payload.chat_id) === chatId() && Number(payload.attendant_user_id) > 0) {
            clearNotice();
          }

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

  root.addEventListener("chat:visibility", () => {
    scheduleReadAcknowledgement();
  });

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
      refreshBootstrap().catch(() => {
        setConnectionStatus("Falha ao atualizar", false);
      });
      scheduleReadAcknowledgement();
    }
  });

  if (elements.newChat) {
    elements.newChat.addEventListener("click", () => showActiveChat());
  }

  if (elements.resumeButton) {
    elements.resumeButton.addEventListener("click", () => showActiveChat());
  }

  if (elements.back) {
    elements.back.addEventListener("click", () => setCompactThreadOpen(false));
  }

  // Leaving the compact layout shows both columns; coming back starts at the list.
  const onCompactChange = () => setCompactThreadOpen(false);
  if (typeof compactQuery.addEventListener === "function") {
    compactQuery.addEventListener("change", onCompactChange);
  } else if (typeof compactQuery.addListener === "function") {
    compactQuery.addListener(onCompactChange);
  }

  elements.form.addEventListener("submit", (event) => {
    event.preventDefault();

    const message = elements.input.value.trim();
    if (!message || isReadOnly()) {
      return;
    }

    // Without an open chat the server creates a new one; closed chats are never reused.
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

  const shouldResetPanel = root.dataset.resetPanel === "true";
  setPanelOpen(!shouldResetPanel && readStoredValue(storageKeys.panel) === "open", shouldResetPanel, false);

  refreshBootstrap().catch(() => {
    setConnectionStatus("Falha ao carregar", false);
  });

  window.setInterval(() => {
    refreshBootstrap().catch(() => {
      setConnectionStatus("Falha ao atualizar", false);
    });
  }, 5000);

  connectSocket();
});
