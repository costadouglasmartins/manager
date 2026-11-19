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
    history: "manager.supportChat.historyState",
  };

  const elements = {
    toggle: document.getElementById("supportWidgetToggle"),
    close: document.getElementById("supportWidgetClose"),
    panel: document.getElementById("supportWidgetPanel"),
    supportPane: document.getElementById("supportWidgetSupportPane"),
    headerMeta: document.getElementById("supportWidgetHeaderMeta"),
    threadIdentity: document.getElementById("supportWidgetThreadIdentity"),
    back: document.getElementById("supportWidgetBack"),
    attendantList: document.getElementById("supportWidgetAttendantList"),
    attendantsCount: document.getElementById("supportWidgetAttendantsCount"),
    messages: document.getElementById("supportWidgetMessages"),
    historyList: document.getElementById("supportWidgetHistoryList"),
    historyHeader: document.getElementById("supportWidgetHistoryHeader"),
    historyCount: document.getElementById("supportWidgetHistoryCount"),
    historyToggle: document.getElementById("supportWidgetHistoryToggle"),
    historyModal: document.getElementById("supportWidgetHistoryModal"),
    historyModalTitle: document.getElementById("supportWidgetHistoryModalTitle"),
    historyModalMeta: document.getElementById("supportWidgetHistoryModalMeta"),
    historyModalMessages: document.getElementById("supportWidgetHistoryModalMessages"),
    typing: document.getElementById("supportWidgetTyping"),
    notice: document.getElementById("supportWidgetNotice"),
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
    historyModalChatId: null,
    historyCollapsed: false,
    compactThreadOpen: false,
    historyModalPayload: null,
    historyModalOpen: false,
    refreshPromise: null,
    seenRequests: new Set(),
    acknowledgedMessages: new Map(),
    renderedMessagesKey: "",
    renderedChatId: 0,
    renderedThreadHeaderHtml: "",
    renderedAttendantsHtml: "",
    renderedHistoryHtml: "",
  };

  const presenceLabels = {
    online: "Online",
    away: "Ausente",
    busy: "Ocupado",
    offline: "Offline",
  };
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
      const date = parseLocalDateTime(message.created);
      const time = date && !Number.isNaN(date.getTime()) ? date.getTime() : null;
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

  function tenantName() {
    const tenant = state.bootstrap && state.bootstrap.tenant;
    return tenant ? (tenant.fantasy || tenant.name || ("Tenant " + tenant.database_number)) : "Suporte";
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

    if (isOpen && shouldFocus && !coarsePointerQuery.matches) {
      elements.input.focus();
    }

    root.dispatchEvent(new CustomEvent("chat:visibility"));
  }

  function togglePanel(forceOpen) {
    const shouldOpen = typeof forceOpen === "boolean" ? forceOpen : elements.panel.hidden;
    setPanelOpen(shouldOpen);
  }

  // The chat list is a section below the attendants; its collapsed state is
  // persisted for every layout.
  function syncHistoryToggle() {
    const isExpanded = !state.historyCollapsed;
    const label = isExpanded ? "Recolher atendimentos" : "Expandir atendimentos";

    if (!elements.historyToggle) {
      return;
    }

    elements.historyToggle.setAttribute("aria-expanded", isExpanded ? "true" : "false");
    elements.historyToggle.setAttribute("aria-label", label);
    elements.historyToggle.setAttribute("title", label);

    const icon = elements.historyToggle.querySelector("i");
    if (icon) {
      icon.classList.remove("bi-chevron-up", "bi-chevron-down");
      icon.classList.add(isExpanded ? "bi-chevron-up" : "bi-chevron-down");
    }
  }

  // Compact panels show either the attendant list or the conversation.
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

  // Assignment stays automatic on the server: any attendant opens the
  // customer's single support conversation.
  function openSupportConversation() {
    setCompactThreadOpen(true);

    if (!coarsePointerQuery.matches) {
      elements.input.focus();
    }
  }

  function setHistoryCollapsed(isCollapsed, shouldPersist = true) {
    state.historyCollapsed = Boolean(isCollapsed);
    root.classList.toggle("is-history-collapsed", state.historyCollapsed);
    syncHistoryToggle();

    if (shouldPersist) {
      writeStoredValue(storageKeys.history, state.historyCollapsed ? "collapsed" : "expanded");
    }
  }

  function toggleHistory() {
    setHistoryCollapsed(!state.historyCollapsed);
  }

  function renderMessages() {
    const chat = activeChat();
    const messages = chat ? ((state.bootstrap && state.bootstrap.messages) || []) : [];

    elements.headerMeta.textContent = tenantName();
    elements.typing.textContent = "";

    if (!messages.length) {
      state.renderedMessagesKey = "";
      state.renderedChatId = Number((chat && chat.id) || 0);
      elements.messages.innerHTML = '<div class="support-widget-empty"><p class="mb-0">Digite a primeira mensagem para iniciar o atendimento.</p></div>';
      return;
    }

    const id = Number(chat.id || 0);
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

  // Who answers this chat: the assigned attendant with their presence, or
  // "Suporte" with the queue state while nobody has taken it.
  function renderThreadHeader() {
    if (!elements.threadIdentity) {
      return;
    }

    const chat = activeChat();
    const attendantName = chat && Number(chat.attendant_user_id || 0) > 0
      ? (chat.attendant_name || chat.attendant_username || "")
      : "";
    let status = "offline";
    let html = "";

    if (attendantName) {
      status = presenceStatus(chat.attendant_presence_status);
      html = `
        ${avatarHtml(escapeHtml(initials(attendantName)), status)}
        <span class="chat-thread-text">
          <span class="chat-thread-name">${escapeHtml(attendantName)}</span>
          <span class="chat-thread-status"><span class="chat-thread-presence">${presenceLabels[status]}</span> · ${escapeHtml(statusLabel(chat.status))}</span>
        </span>
      `;
    } else {
      const hasAttendants = onlineAttendants().length > 0;
      let detail = statusLabel(chat && chat.status);
      if (!chat) {
        detail = hasAttendants ? "Envie uma mensagem para iniciar o atendimento" : "Nenhum atendente online no momento";
      }

      status = hasAttendants ? "online" : "offline";
      html = `
        ${avatarHtml('<i class="bi bi-headset"></i>', status)}
        <span class="chat-thread-text">
          <span class="chat-thread-name">Suporte</span>
          <span class="chat-thread-status">${escapeHtml(detail)}</span>
        </span>
      `;
    }

    elements.threadIdentity.dataset.presence = status;
    if (html !== state.renderedThreadHeaderHtml) {
      state.renderedThreadHeaderHtml = html;
      elements.threadIdentity.innerHTML = html;
    }
  }

  // Only roots that can take a chat right now (active, presence online).
  // Each one opens the support conversation; the server picks who answers.
  function renderAttendants() {
    if (!elements.attendantList) {
      return;
    }

    const attendants = onlineAttendants();
    const chat = activeChat();
    const assignedId = Number((chat && chat.attendant_user_id) || 0);
    let html = "";

    if (elements.attendantsCount) {
      elements.attendantsCount.textContent = attendants.length ? `${attendants.length} online` : "";
    }

    if (!attendants.length) {
      // The queue still accepts messages, so a new conversation must stay
      // reachable; a current one is already listed under Atendimentos.
      html = `
        <div class="chat-people-empty">Nenhum atendente online. Sua mensagem fica na fila.</div>
        ${chat ? "" : '<button type="button" class="chat-people-action" data-support-open-chat="true">Deixar mensagem</button>'}
      `;
    } else {
      html = attendants.map((attendant) => {
        const name = attendant.name || attendant.username || "Atendente";
        const status = presenceStatus(attendant.presence_status);
        const isAssigned = Number(attendant.id) === assignedId;
        const details = `${presenceLabels[status]}${isAssigned ? ", seu atendente" : ""}`;

        return `
          <button type="button" class="chat-person${isAssigned ? " is-active" : ""}" data-support-open-chat="true" data-presence="${status}" title="${escapeHtml(name)} (${escapeHtml(details)})" aria-label="Abrir atendimento: ${escapeHtml(name)}, ${escapeHtml(details)}">
            <span class="chat-person-dot" aria-hidden="true"></span>
            <span class="chat-person-name">${escapeHtml(name)}</span>
          </button>
        `;
      }).join("");
    }

    if (html !== state.renderedAttendantsHtml) {
      state.renderedAttendantsHtml = html;
      elements.attendantList.innerHTML = html;
    }
  }

  function historyModalInstance() {
    if (!elements.historyModal || !window.bootstrap || !window.bootstrap.Modal) {
      return null;
    }

    return window.bootstrap.Modal.getOrCreateInstance(elements.historyModal);
  }

  function showHistoryModal() {
    const modal = historyModalInstance();
    if (modal) {
      modal.show();
    }
  }

  function renderHistoryModalLoading(chatId) {
    if (!elements.historyModalTitle || !elements.historyModalMeta || !elements.historyModalMessages) {
      return;
    }

    elements.historyModalTitle.textContent = `Atendimento #${Number(chatId)}`;
    elements.historyModalMeta.textContent = "";
    elements.historyModalMessages.innerHTML = '<div class="support-widget-history-modal-empty">Carregando conversa...</div>';
  }

  function renderHistoryModalError() {
    if (!elements.historyModalMessages) {
      return;
    }

    elements.historyModalMessages.innerHTML = '<div class="support-widget-history-modal-empty">Não foi possível carregar este histórico.</div>';
  }

  function renderHistoryModal(payload) {
    if (!elements.historyModalTitle || !elements.historyModalMeta || !elements.historyModalMessages) {
      return;
    }

    const chat = payload.chat || {};
    const messages = payload.messages || [];
    const attendantName = chat.attendant_name || chat.attendant_username || "Sem atendente";
    const closedAt = chat.closed_at || chat.last_message_at || chat.created;

    elements.historyModalTitle.textContent = `Atendimento #${Number(chat.id || 0)}`;
    elements.historyModalMeta.textContent = `${attendantName} | Finalizado em ${formatDate(closedAt)}`;

    if (!messages.length) {
      elements.historyModalMessages.innerHTML = '<div class="support-widget-history-modal-empty">Nenhuma mensagem neste atendimento.</div>';
      return;
    }

    elements.historyModalMessages.innerHTML = messages.map((message) => {
      const isSelf = Number(message.sender_user_id || 0) === currentUserId() && message.sender_role === "customer";
      const cssClass = isSelf ? "is-self" : "is-other";

      return `
        <div class="support-widget-history-modal-message ${cssClass}">
          <div class="support-widget-history-modal-message-meta">${escapeHtml(message.sender_name || message.sender_role)} | ${escapeHtml(formatDate(message.created))}</div>
          <div class="support-widget-history-modal-message-bubble">${escapeHtml(message.message_text || "")}</div>
        </div>
      `;
    }).join("");
  }

  // Support chats of the last month: the current one first (it opens the
  // conversation), then the closed ones (read-only, in the modal). The server
  // stops sending chats closed more than a month ago; they stay in the database.
  function renderHistory() {
    if (!elements.historyList) {
      return;
    }

    const current = activeChat();
    const history = historyChats();
    const total = history.length + (current ? 1 : 0);
    let html = "";

    if (elements.historyCount) {
      elements.historyCount.textContent = total ? `(${total})` : "";
    }

    const historyIds = history.map((chat) => Number(chat.id));
    if (state.historyModalChatId && !historyIds.includes(Number(state.historyModalChatId))) {
      state.historyModalChatId = null;
    }

    if (!total) {
      html = '<div class="support-widget-history-empty">Nenhum atendimento no último mês.</div>';
    } else {
      if (current) {
        const unread = Math.max(0, Number(current.unread_count || 0));
        const attendantName = current.attendant_name || current.attendant_username || "Aguardando atendente";
        html += `
          <button type="button" class="support-widget-history-item is-current" data-support-open-chat="true" title="Abrir o atendimento atual">
            <span class="support-widget-history-item-top">
              <strong>Chat #${Number(current.id)}</strong>
              <span class="support-widget-history-item-status">${current.status === "pending" ? "Na fila" : "Em andamento"}</span>
            </span>
            <span class="support-widget-history-item-bottom">
              <span>${escapeHtml(attendantName)}</span>
              ${unread > 0 ? `<span class="chat-unread-badge" aria-label="${unread} mensagens não lidas">${unread}</span>` : ""}
            </span>
          </button>
        `;
      }

      html += history.map((chat) => {
        const chatDate = chat.closed_at || chat.last_message_at || chat.created;
        const activeClass = Number(chat.id) === Number(state.historyModalChatId) ? " is-active" : "";
        const attendantName = chat.attendant_name || chat.attendant_username || "Sem atendente";
        const unread = Math.max(0, Number(chat.unread_count || 0));

        return `
          <button type="button" class="support-widget-history-item${activeClass}" data-history-chat-id="${Number(chat.id)}" title="Encerrado em ${escapeHtml(formatDate(chatDate))}">
            <span class="support-widget-history-item-top">
              <strong>Chat #${Number(chat.id)}</strong>
              <span>${escapeHtml(formatMessageTime(chatDate))}</span>
            </span>
            <span class="support-widget-history-item-bottom">
              <span>${escapeHtml(attendantName)}</span>
              ${unread > 0 ? `<span class="chat-unread-badge" aria-label="${unread} mensagens não lidas">${unread}</span>` : ""}
            </span>
          </button>
        `;
      }).join("");
    }

    // The 5s poll re-renders constantly; identical markup would drop hover and focus.
    if (html === state.renderedHistoryHtml) {
      return;
    }

    state.renderedHistoryHtml = html;
    elements.historyList.innerHTML = html;
    elements.historyList.querySelectorAll("[data-history-chat-id]").forEach((button) => {
      button.addEventListener("click", () => {
        const chatId = Number(button.dataset.historyChatId || 0);
        if (chatId > 0) {
          openHistoryModal(chatId);
        }
      });
    });
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
      state.bootstrap = payload;
      renderMessages();
      renderThreadHeader();
      renderAttendants();
      renderHistory();
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
    if (document.visibilityState !== "visible") {
      return;
    }

    let chat = null;
    let messages = [];

    if (state.historyModalOpen) {
      if (!elements.historyModal || !elements.historyModal.classList.contains("show") || !state.historyModalPayload) {
        return;
      }

      chat = state.historyModalPayload.chat;
      messages = state.historyModalPayload.messages || [];
    } else if (!elements.panel.hidden && elements.supportPane && !elements.supportPane.hidden && isConversationVisible()) {
      chat = activeChat();
      messages = (state.bootstrap && state.bootstrap.messages) || [];
    }

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

  async function openHistoryModal(chatId) {
    state.historyModalChatId = Number(chatId);
    state.historyModalPayload = null;
    state.historyModalOpen = true;
    renderHistoryModalLoading(chatId);
    renderHistory();
    showHistoryModal();

    try {
      const payload = await fetchJson(`${chatUrlBase}/${encodeURIComponent(chatId)}`);
      if (state.historyModalChatId !== Number(chatId)) {
        return;
      }

      state.historyModalPayload = payload;
      renderHistoryModal(payload);
      scheduleReadAcknowledgement();
    } catch (error) {
      renderHistoryModalError();
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
          if (Number(payload.chat_id) === chatId()) {
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

  if (elements.historyModal) {
    elements.historyModal.addEventListener("shown.bs.modal", () => {
      scheduleReadAcknowledgement();
    });
    elements.historyModal.addEventListener("hidden.bs.modal", () => {
      state.historyModalOpen = false;
      state.historyModalPayload = null;
      scheduleReadAcknowledgement();
    });
  }

  if (elements.historyToggle) {
    elements.historyToggle.addEventListener("click", () => {
      toggleHistory();
    });
  }

  // The whole history header is a tap target, not only the chevron.
  if (elements.historyHeader) {
    elements.historyHeader.addEventListener("click", (event) => {
      if (!event.target.closest("button")) {
        toggleHistory();
      }
    });
  }

  // Attendants and the current chat in the list open the conversation.
  [elements.attendantList, elements.historyList].forEach((list) => {
    if (!list) {
      return;
    }

    list.addEventListener("click", (event) => {
      if (event.target.closest("[data-support-open-chat]")) {
        openSupportConversation();
      }
    });
  });

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
