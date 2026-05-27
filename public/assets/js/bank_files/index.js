(function ()
  {
  const modalElement = document.getElementById("bankFileDeleteModal");
  const deleteForm = document.getElementById("bankFileDeleteForm");
  const deleteName = document.getElementById("bankFileDeleteName");
  let lastTrigger = null;

  document.querySelectorAll('[data-bs-toggle="popover"]').forEach((element) => {
    if (window.bootstrap && window.bootstrap.Popover)
      {
      new window.bootstrap.Popover(element);
      }
  });

  if (!modalElement || !deleteForm || !deleteName)
    {
    return;
    }

  const syncModalData = function (trigger)
    {
    if (!trigger)
      {
      return;
      }

    const deleteUrl = trigger.getAttribute("data-bank-file-delete-url") || "";
    const fileName = trigger.getAttribute("data-bank-file-name") || "";
    const fileId = trigger.getAttribute("data-bank-file-id") || "";
    const label = fileName !== "" ? fileName : (fileId !== "" ? ("#" + fileId) : "#");

    deleteForm.setAttribute("action", deleteUrl);
    deleteName.textContent = label;
    };

  document.addEventListener("click", function (event)
    {
    const link = event.target instanceof Element ? event.target.closest(".js-bank-file-delete-link") : null;
    if (!link)
      {
      return;
      }

    lastTrigger = link;
    syncModalData(link);
    });

  modalElement.addEventListener("show.bs.modal", function (event)
    {
    const related = event.relatedTarget instanceof Element ? event.relatedTarget.closest(".js-bank-file-delete-link") : null;
    syncModalData(related || lastTrigger);
    });
  })();
