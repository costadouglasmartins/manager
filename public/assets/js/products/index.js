(function ()
  {
  const modalElement = document.getElementById("productDeleteModal");
  const deleteForm = document.getElementById("productDeleteForm");
  const deleteName = document.getElementById("productDeleteName");
  const deleteLinks = document.querySelectorAll(".js-product-delete-link");

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

    const deleteUrl = trigger.getAttribute("data-product-delete-url") || "";
    const productName = trigger.getAttribute("data-product-name") || "";
    const productId = trigger.getAttribute("data-product-id") || "";
    const label = productName !== "" ? productName : (productId !== "" ? ("#" + productId) : "#");

    deleteForm.setAttribute("action", deleteUrl);
    deleteName.textContent = label;
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
