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
  const seenUrl = root.dataset.seenUrl || "";
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
    unread: document.getElementById("internalChatUnreadCount"),
    supportUnread: document.getElementById("supportWidgetSupportUnreadCount"),
    totalUnread: document.getElementById("supportWidgetUnreadCount"),
    supportPane: document.getElementById("supportWidgetSupportPane"),
    supportMeta: document.getElementById("supportWidgetHeaderCurrent"),
    internalMeta: document.getElementById("internalChatHeaderCurrent"),
    userList: document.getElementById("internalChatUserList"),
    teamCount: document.getElementById("internalChatTeamCount"),
    refresh: document.getElementById("internalChatRefresh"),
    threadIdentity: document.getElementById("internalChatThreadIdentity"),
    back: document.getElementById("internalChatBack"),
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
    renderedMessagesKey: "",
    renderedChatId: 0,
    forceScrollToBottom: false,
    renderedUserHtml: "",
    renderedHeaderHtml: "",
    // Compact panels show the team list or the conversation, never both.
    compactThreadOpen: false,
    refreshVersion: 0,
    seenRequests: new Set(),
    seenMessages: new Map(),
  };

  const unreadCounts = { support: 0, internal: 0 };

  function renderUnreadBadge(element, count) {
    if (!element) {
      return;
    }

    element.textContent = count > 0 ? String(count) : "";
    element.hidden = count === 0;
    element.setAttribute("aria-label", `${count} ${count === 1 ? "mensagem não lida" : "mensagens não lidas"}`);
  }

  shell.addEventListener("chat:unread", (event) => {
    const detail = event.detail || {};
    if (!Object.prototype.hasOwnProperty.call(unreadCounts, detail.channel)) {
      return;
    }

    unreadCounts[detail.channel] = Math.max(0, Math.trunc(Number(detail.count) || 0));
    const total = unreadCounts.support + unreadCounts.internal;
    renderUnreadBadge(elements.totalUnread, total);
    renderUnreadBadge(elements.supportUnread, unreadCounts.support);
    renderUnreadBadge(elements.unread, unreadCounts.internal);
    elements.toggle.setAttribute("aria-label", total > 0
      ? `Chat, ${total} ${total === 1 ? "mensagem não lida" : "mensagens não lidas"}`
      : "Chat");
  });

  function publishUnreadCount(count) {
    shell.dispatchEvent(new CustomEvent("chat:unread", {
      detail: { channel: "internal", count },
    }));
  }

  function isThreadVisible() {
    return !elements.panel.hidden && !root.hidden && state.activeTab === "internal"
      && document.visibilityState === "visible" && (!compactQuery.matches || state.compactThreadOpen);
  }

  // Back keeps the conversation selected (header and list still show it),
  // exactly like the Suporte tab.
  function setCompactThreadOpen(isOpen) {
    state.compactThreadOpen = Boolean(isOpen);
    root.classList.toggle("is-thread-open", state.compactThreadOpen);
    // The back button lives in the panel header, outside this pane.
    shell.classList.toggle("has-internal-thread", state.compactThreadOpen);

    if (state.compactThreadOpen) {
      elements.messages.scrollTop = elements.messages.scrollHeight;
      markVisibleMessagesSeen();
    }
  }

  async function markVisibleMessagesSeen() {
    const lastMessage = state.messages[state.messages.length - 1];
    const id = state.activeChatId;
    const messageId = Number((lastMessage && lastMessage.id) || 0);
    const requestKey = `${id}:${messageId}`;
    if (!seenUrl || !isThreadVisible() || !state.activeChat || !messageId
      || state.seenMessages.get(id) >= messageId || state.seenRequests.has(requestKey)) {
      return;
    }

    state.seenRequests.add(requestKey);
    try {
      // Let the browser paint the thread before acknowledging its messages.
      await new Promise((resolve) => window.requestAnimationFrame(resolve));
      if (!isThreadVisible() || state.activeChatId !== id) {
        return;
      }

      await postJson(seenUrl, { chat_id: id, last_message_id: messageId });
      state.seenMessages.set(id, Math.max(state.seenMessages.get(id) || 0, messageId));
      await refreshBootstrap();
    } catch (error) {
      // Keep the count until the acknowledgement succeeds on a later refresh.
    } finally {
      state.seenRequests.delete(requestKey);
    }
  }

  // Must match the compact breakpoint in support_chats/widget.css.
  const compactQuery = window.matchMedia("(max-width: 767.98px)");
  // Touch devices: focusing the textarea would pop the virtual keyboard over the chat.
  const coarsePointerQuery = window.matchMedia("(pointer: coarse)");
  const presenceLabels = {
    online: "Online",
    away: "Ausente",
    busy: "Ocupado",
    offline: "Offline",
  };
  const presenceOrder = ["online", "away", "busy", "offline"];
  // Consecutive messages from one sender inside this window share one name/time line.
  const messageGroupWindowMs = 5 * 60 * 1000;

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

  function timestamp(value) {
    const date = parseLocalDateTime(value);
    return date && !Number.isNaN(date.getTime()) ? date.getTime() : 0;
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

  function presenceStatus(user) {
    const status = String((user && user.presence_status) || "offline");
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

  function avatarHtml(name, status) {
    return `<span class="chat-avatar" data-presence="${status}" aria-hidden="true">${escapeHtml(initials(name))}<span class="chat-presence-dot"></span></span>`;
  }

  function tenantUsers() {
    return (state.bootstrap && state.bootstrap.users) || [];
  }

  // Chats are direct (one other participant) and only expose usernames, so
  // the partner's presence is looked up in the tenant user list.
  function chatPartner(chat) {
    const usernames = String((chat && chat.participant_usernames) || "")
      .split(",")
      .map((username) => username.trim())
      .filter(Boolean);

    if (usernames.length !== 1) {
      return null;
    }

    return tenantUsers().find((user) => user.username === usernames[0]) || null;
  }

  function focusInput() {
    if (!coarsePointerQuery.matches && !elements.panel.hidden) {
      elements.input.focus();
    }
  }

  function currentUserId() {
    return Number((state.bootstrap && state.bootstrap.currentUser && state.bootstrap.currentUser.id) || 0);
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
    shell.dispatchEvent(new CustomEvent("chat:visibility"));

    if (isInternal) {
      refreshBootstrap().catch(() => {
        setConnectionStatus("Falha ao carregar", false);
      });
      syncTypingPollTimer();

      if (state.activeChatId > 0) {
        focusInput();
      }
      return;
    }

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
    const version = ++state.refreshVersion;
    const requestedChatId = state.activeChatId;
    const payload = await fetchJson(bootstrapEndpoint());

    // The user opened or left a conversation while this request was in flight;
    // applying it would switch the thread back. The newer action refreshes itself.
    if (requestedChatId !== state.activeChatId || version !== state.refreshVersion) {
      return;
    }

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
    publishUnreadCount(payload.unread_count || 0);
    markVisibleMessagesSeen();
    syncTypingPollTimer();
    setConnectionStatus(state.socketAuthenticated ? "Conectado" : "Atualizando automaticamente", state.socketAuthenticated);
  }

  function render() {
    renderHeader();
    renderUserList();
    renderMessages();
    renderTyping();
  }

  // The panel header names the internal conversation on screen.
  function renderHeader() {
    if (!elements.threadIdentity) {
      return;
    }

    let html = "";
    let status = "offline";

    if (!state.activeChat) {
      html = `
        <span class="chat-avatar" data-presence="offline" aria-hidden="true"><i class="bi bi-people-fill"></i></span>
        <span class="chat-thread-text">
          <span class="chat-thread-name">Chat interno</span>
          <span class="chat-thread-status">Escolha alguém da equipe para conversar</span>
        </span>
      `;
    } else {
      const partner = chatPartner(state.activeChat);
      const title = chatTitle(state.activeChat);
      status = partner ? presenceStatus(partner) : "offline";
      html = `
        ${avatarHtml(title, status)}
        <span class="chat-thread-text">
          <span class="chat-thread-name">${escapeHtml(title)}</span>
          <span class="chat-thread-status"><span class="chat-thread-presence">${escapeHtml(presenceLabels[status])}</span></span>
        </span>
      `;
    }

    elements.threadIdentity.dataset.presence = status;
    if (html !== state.renderedHeaderHtml) {
      state.renderedHeaderHtml = html;
      elements.threadIdentity.innerHTML = html;
    }
  }

  // One list for the whole team: each tenant user is a row tinted with the
  // presence color, with the presence and the time of the last message below
  // the name, like the support list. Clicking opens the conversation (or starts it).
  function teamRows() {
    const users = tenantUsers();
    const chats = (state.bootstrap && state.bootstrap.chats) || [];
    const chatByUserId = new Map();
    const rows = [];

    chats.forEach((chat) => {
      const partner = chatPartner(chat);
      if (partner && !chatByUserId.has(Number(partner.id))) {
        chatByUserId.set(Number(partner.id), chat);
      } else if (!partner) {
        // A conversation whose partner left the tenant stays reachable.
        rows.push({ userId: 0, name: chatTitle(chat), status: "offline", chat });
      }
    });

    users.forEach((user) => {
      rows.push({
        userId: Number(user.id),
        name: user.name || user.username || "Usuário",
        status: presenceStatus(user),
        chat: chatByUserId.get(Number(user.id)) || null,
      });
    });

    // Conversations by date (newest first), then the others by presence; the
    // server order (by name) breaks ties.
    return rows
      .map((row, index) => ({
        ...row,
        index,
        at: row.chat ? timestamp(row.chat.last_message_at || row.chat.created) : 0,
        unread: Math.max(0, Number((row.chat && row.chat.unread_count) || 0)),
      }))
      .sort((a, b) => (b.at - a.at)
        || (presenceOrder.indexOf(a.status) - presenceOrder.indexOf(b.status))
        || (a.index - b.index));
  }

  function renderUserList() {
    const rows = teamRows();
    const onlineCount = tenantUsers().filter((user) => presenceStatus(user) === "online").length;
    let html = "";

    if (elements.teamCount) {
      elements.teamCount.textContent = tenantUsers().length ? `${onlineCount} online` : "";
    }

    if (!rows.length) {
      html = '<div class="chat-people-empty">Nenhum outro usuário neste tenant.</div>';
    } else {
      // A conversation without messages yet is not listed by the server, so
      // the selected row is also matched by the partner.
      const partner = chatPartner(state.activeChat);
      html = rows.map((row) => {
        const label = presenceLabels[row.status];
        const meta = row.chat ? `${label} · ${formatMessageTime(row.chat.last_message_at || row.chat.created)}` : label;
        const isActive = row.chat
          ? Number(row.chat.id) === Number(state.activeChatId || 0)
          : Boolean(partner) && Number(partner.id) === row.userId;
        const classes = ["chat-person", "chat-person-chat"];
        if (isActive) {
          classes.push("is-active");
        }
        if (row.unread > 0) {
          classes.push("has-unread");
        }
        const details = [meta];
        if (row.unread > 0) {
          details.push(`${row.unread} ${row.unread === 1 ? "mensagem não lida" : "mensagens não lidas"}`);
        }

        return `
          <button type="button" class="${classes.join(" ")}" data-internal-user-id="${row.userId}" data-internal-chat-id="${row.chat ? Number(row.chat.id) : 0}" data-presence="${row.status}" title="${escapeHtml(row.name)} (${escapeHtml(details.join(", "))})" aria-label="Conversar com ${escapeHtml(row.name)}, ${escapeHtml(details.join(", "))}">
            <span class="chat-person-dot" aria-hidden="true"></span>
            <span class="chat-person-text">
              <span class="chat-person-name">${escapeHtml(row.name)}</span>
              <span class="chat-person-meta">${escapeHtml(meta)}</span>
            </span>
            ${row.unread > 0 ? `<span class="chat-unread-badge" aria-hidden="true">${row.unread}</span>` : ""}
          </button>
        `;
      }).join("");
    }

    // Re-rendering identical markup every poll would drop focus and hover.
    if (html !== state.renderedUserHtml) {
      state.renderedUserHtml = html;
      elements.userList.innerHTML = html;
    }
  }

  function renderMessages() {
    const messages = state.messages || [];

    elements.input.disabled = !state.activeChat;
    elements.sendButton.disabled = !state.activeChat;

    if (!state.activeChat) {
      state.renderedMessagesKey = "";
      state.renderedChatId = 0;
      elements.messages.innerHTML = '<div class="support-widget-empty"><p class="mb-0">Escolha alguém da equipe para conversar.</p></div>';
      elements.typing.textContent = "";
      return;
    }

    if (!messages.length) {
      state.renderedMessagesKey = "";
      state.renderedChatId = state.activeChatId;
      elements.messages.innerHTML = '<div class="support-widget-empty"><p class="mb-0">Nenhuma mensagem nesta conversa.</p></div>';
      return;
    }

    // The 5s poll re-renders constantly: skip unchanged threads and only follow
    // new messages when the reader is already at the bottom, so scrolling back
    // through older messages is not interrupted.
    const lastMessage = messages[messages.length - 1] || {};
    const messagesKey = `${state.activeChatId}:${messages.length}:${lastMessage.id || ""}`;
    if (messagesKey === state.renderedMessagesKey && !state.forceScrollToBottom) {
      return;
    }

    const container = elements.messages;
    const isSameChat = state.renderedChatId === state.activeChatId;
    const wasNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 60;
    const shouldScroll = !isSameChat || wasNearBottom || state.forceScrollToBottom;

    state.renderedMessagesKey = messagesKey;
    state.renderedChatId = state.activeChatId;
    state.forceScrollToBottom = false;

    // The sender line is shown only when the sender changes or a few minutes
    // passed; the full date stays available on hover.
    let previous = null;
    elements.messages.innerHTML = messages.map((message) => {
      const senderId = Number(message.sender_user_id || 0);
      const isSelf = senderId === currentUserId();
      const date = parseLocalDateTime(message.created);
      const time = date && !Number.isNaN(date.getTime()) ? date.getTime() : null;
      const isContinued = Boolean(previous) && previous.senderId === senderId
        && time !== null && previous.time !== null
        && time - previous.time >= 0 && time - previous.time < messageGroupWindowMs;
      previous = { senderId, time };

      const when = escapeHtml(formatMessageTime(message.created));
      const meta = isSelf ? when : `${escapeHtml(message.sender_name || "")} · ${when}`;

      return `
        <div class="support-widget-message ${isSelf ? "is-self" : "is-other"}${isContinued ? " is-continued" : ""}">
          ${isContinued ? "" : `<div class="support-widget-message-meta">${meta}</div>`}
          <div class="support-widget-bubble" title="${escapeHtml(formatDate(message.created))}">${escapeHtml(message.message_text || "")}</div>
        </div>
      `;
    }).join("");

    if (shouldScroll) {
      elements.messages.scrollTop = elements.messages.scrollHeight;
    }
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
    setCompactThreadOpen(true);
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
      focusInput();
    } catch (error) {
      setConnectionStatus("Falha ao abrir conversa", false);
    }
  }

  // Compact back button: returns to the team list, keeping the conversation selected.
  function leaveThread() {
    sendTyping(false);
    setCompactThreadOpen(false);
  }

  async function startDirectChat(userId) {
    setCompactThreadOpen(true);
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
      focusInput();
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
      state.forceScrollToBottom = true;
      render();
      await refreshBootstrap();
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
          await refreshBootstrap();
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
      refreshBootstrap().catch(() => {
        setConnectionStatus("Falha ao atualizar", false);
      });
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
      if (state.activeTab === "internal" && state.activeChatId > 0) {
        focusInput();
      }
    }, 0);
  });

  shell.addEventListener("chat:visibility", () => {
    markVisibleMessagesSeen();
    if (isThreadVisible()) {
      refreshBootstrap().catch(() => setConnectionStatus("Falha ao atualizar", false));
    }
  });

  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") {
      refreshBootstrap().catch(() => setConnectionStatus("Falha ao atualizar", false));
    }
  });

  // An existing conversation opens directly; otherwise one is started.
  elements.userList.addEventListener("click", (event) => {
    const button = event.target.closest("[data-internal-user-id]");
    if (!button) {
      return;
    }

    const chatId = Number(button.dataset.internalChatId || 0);
    const userId = Number(button.dataset.internalUserId || 0);
    if (chatId > 0) {
      openChat(chatId);
    } else if (userId > 0) {
      startDirectChat(userId);
    }
  });

  if (elements.back) {
    elements.back.addEventListener("click", () => leaveThread());
  }

  // Leaving the compact layout shows both columns; coming back starts at the list.
  const onCompactChange = () => setCompactThreadOpen(false);
  if (typeof compactQuery.addEventListener === "function") {
    compactQuery.addEventListener("change", onCompactChange);
  } else if (typeof compactQuery.addListener === "function") {
    compactQuery.addListener(onCompactChange);
  }
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
  startRefreshTimer();
  refreshBootstrap().catch(() => {
    setConnectionStatus("Falha ao carregar", false);
  });
  connectSocket();
});
