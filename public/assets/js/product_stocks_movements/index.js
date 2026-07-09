(function ()
  {
  const modalElement = document.getElementById("productStockMovementDeleteModal");
  const deleteForm = document.getElementById("productStockMovementDeleteForm");
  const deleteName = document.getElementById("productStockMovementDeleteName");
  let lastTrigger = null;

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

    const deleteUrl = trigger.getAttribute("data-movement-delete-url") || "";
    const movementId = trigger.getAttribute("data-movement-id") || "";
    const label = movementId !== "" ? ("#" + movementId) : "#";

    deleteForm.setAttribute("action", deleteUrl);
    deleteName.textContent = label;
    };

  document.addEventListener("click", function (event)
    {
    const link = event.target instanceof Element ? event.target.closest(".js-stock-movement-delete-link") : null;
    if (!link)
      {
      return;
      }

    lastTrigger = link;
    syncModalData(link);
    });

  modalElement.addEventListener("show.bs.modal", function (event)
    {
    const related = event.relatedTarget instanceof Element ? event.relatedTarget.closest(".js-stock-movement-delete-link") : null;
    syncModalData(related || lastTrigger);
    });
  })();
