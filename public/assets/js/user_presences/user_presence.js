(function (window, document) {
  "use strict";

  function clearStatusClasses(element)
    {
    ["text-bg-success", "text-bg-warning", "text-bg-danger", "text-bg-secondary"].forEach(function (className) {
      element.classList.remove(className);
    });
    }

  function applyPresence(root, presence)
    {
    const badge = root.querySelector("[data-user-presence-badge]");
    const label = root.querySelector("[data-user-presence-label]");
    const icon = root.querySelector("[data-user-presence-icon]");
    const status = presence.status || "offline";
    const selectedOption = root.querySelector('[data-user-presence-status="' + status + '"]');

    if (badge)
      {
      clearStatusClasses(badge);
      badge.classList.add(presence.class || "text-bg-secondary");
      }

    if (label)
      {
      label.textContent = selectedOption ? selectedOption.getAttribute("data-user-presence-name") : (presence.label || "Offline");
      }

    if (icon)
      {
      icon.className = "bi " + (presence.icon || "bi-circle") + " app-presence-current-icon";
      }

    root.querySelectorAll("[data-user-presence-status]").forEach(function (button) {
      const isActive = button.getAttribute("data-user-presence-status") === status;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-pressed", isActive ? "true" : "false");
    });
    }

  function setLoading(root, isLoading)
    {
    root.setAttribute("aria-busy", isLoading ? "true" : "false");
    root.querySelectorAll("[data-user-presence-status]").forEach(function (button) {
      button.disabled = isLoading;
    });
    }

  function setFeedback(root, message, isError)
    {
    const feedback = root.querySelector("[data-user-presence-feedback]");
    if (!feedback)
      {
      return;
      }

    if (root.presenceFeedbackTimeout)
      {
      window.clearTimeout(root.presenceFeedbackTimeout);
      }

    feedback.textContent = message || "";
    feedback.classList.toggle("is-error", Boolean(isError));

    if (message && !isError)
      {
      root.presenceFeedbackTimeout = window.setTimeout(function () {
        feedback.textContent = "";
      }, 1800);
      }
    }

  function updateStatus(root, status)
    {
    const endpoint = root.getAttribute("data-status-url");
    const body = new FormData();
    body.append("status", status);

    setLoading(root, true);
    setFeedback(root, "Atualizando presença...", false);

    fetch(endpoint, {
      method: "POST",
      body: body,
      headers: {
        "Accept": "application/json",
        "X-Requested-With": "XMLHttpRequest"
      }
    })
      .then(function (response) {
        return response.json().then(function (payload) {
          if (!response.ok)
            {
            throw new Error(payload.message || "Status could not be updated.");
            }

          return payload;
        });
      })
      .then(function (payload) {
        if (payload.presence)
          {
          applyPresence(root, payload.presence);
          setFeedback(root, "Presença atualizada.", false);
          }
      })
      .catch(function (error) {
        const message = error.message === "The tenant online user limit was reached."
          ? "O limite de usuários online deste tenant foi atingido."
          : (error.message || "Não foi possível atualizar a presença.");
        setFeedback(root, message, true);
      })
      .finally(function () {
        setLoading(root, false);
      });
    }

  function bind(root)
    {
    root.querySelectorAll("[data-user-presence-status]").forEach(function (button) {
      button.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        updateStatus(root, button.getAttribute("data-user-presence-status") || "offline");
      });
    });
    }

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[data-user-presence]").forEach(bind);
  });
})(window, document);
