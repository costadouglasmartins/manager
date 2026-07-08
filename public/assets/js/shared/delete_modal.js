(function ()
  {
  const modalElement = document.getElementById("recordDeleteModal");
  const deleteForm = document.getElementById("recordDeleteForm");
  const deleteName = document.getElementById("recordDeleteName");
  const deleteDescription = document.getElementById("recordDeleteDescription");
  const deleteLinks = document.querySelectorAll(".js-record-delete-link");

  if (!modalElement || !deleteForm || !deleteName || !deleteLinks.length)
    {
    return;
    }

  const defaultDescription = deleteDescription ? deleteDescription.textContent : "este registro";

  const syncModalData = function (trigger)
    {
    if (!trigger)
      {
      return;
      }

    const deleteUrl = trigger.getAttribute("data-record-delete-url") || "";
    const recordName = trigger.getAttribute("data-record-name") || "";
    const recordId = trigger.getAttribute("data-record-id") || "";
    const description = trigger.getAttribute("data-record-delete-description") || defaultDescription;
    const label = recordName !== "" ? recordName : (recordId !== "" ? ("#" + recordId) : "#");

    deleteForm.setAttribute("action", deleteUrl);
    deleteName.textContent = label;

    if (deleteDescription)
      {
      deleteDescription.textContent = description;
      }
    };

  deleteLinks.forEach(function (link)
    {
    link.addEventListener("click", function ()
      {
      syncModalData(link);
      });
    });

  modalElement.addEventListener("show.bs.modal", function (event)
    {
    syncModalData(event.relatedTarget || document.activeElement);
    });
  })();
