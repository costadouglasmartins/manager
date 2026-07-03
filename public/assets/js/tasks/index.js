(function ()
  {
  const modalElement = document.getElementById("taskDeleteModal");
  const deleteForm = document.getElementById("taskDeleteForm");
  const deleteName = document.getElementById("taskDeleteName");
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

    const deleteUrl = trigger.getAttribute("data-task-delete-url") || "";
    const taskTitle = trigger.getAttribute("data-task-title") || "";
    const taskId = trigger.getAttribute("data-task-id") || "";
    const label = taskTitle !== "" ? taskTitle : (taskId !== "" ? ("#" + taskId) : "#");

    deleteForm.setAttribute("action", deleteUrl);
    deleteName.textContent = label;
    };

  document.addEventListener("click", function (event)
    {
    const button = event.target instanceof Element ? event.target.closest(".js-task-delete-button") : null;
    if (!button)
      {
      return;
      }

    lastTrigger = button;
    syncModalData(button);
    });

  modalElement.addEventListener("show.bs.modal", function (event)
    {
    const related = event.relatedTarget instanceof Element ? event.relatedTarget.closest(".js-task-delete-button") : null;
    syncModalData(related || lastTrigger);
    });
  })();
