(function ()
  {
  const modalElement = document.getElementById("orphanedDeleteModal");
  const deleteForm = document.getElementById("orphanedDeleteForm");
  const deleteName = document.getElementById("orphanedDeleteName");
  const deleteUserCount = document.getElementById("orphanedUserCount");
  const deleteLinks = document.querySelectorAll(".js-orphaned-delete-link");

  if (!modalElement || !deleteForm || !deleteName || !deleteLinks.length)
    {
    return;
    }

  const syncModalData = function (trigger)
    {
    if (!trigger)
      {
      return;
      }

    const deleteUrl = trigger.getAttribute("data-orphaned-delete-url") || "";
    const ability = trigger.getAttribute("data-orphaned-ability") || "";
    const permissionId = trigger.getAttribute("data-orphaned-id") || "";
    const userCount = trigger.getAttribute("data-orphaned-user-count") || "0";
    const label = ability !== "" ? ability : (permissionId !== "" ? ("#" + permissionId) : "#");

    deleteForm.setAttribute("action", deleteUrl);
    deleteName.textContent = label;

    if (deleteUserCount)
      {
      deleteUserCount.textContent = userCount;
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
