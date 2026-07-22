(function ()
  {
  const category = document.querySelector(".js-product-import-category");
  const templateLink = document.querySelector(".js-product-import-template");
  if (category && templateLink)
    {
    const syncTemplateLink = function ()
      {
      const enabled = category.value !== "";
      templateLink.classList.toggle("disabled", !enabled);
      templateLink.setAttribute("aria-disabled", enabled ? "false" : "true");
      templateLink.href = enabled ? templateLink.dataset.baseUrl + "?product_category_id=" + encodeURIComponent(category.value) : "#";
      };
    category.addEventListener("change", syncTemplateLink);
    syncTemplateLink();
    }

  document.querySelectorAll(".needs-validation").forEach(function (form)
    {
    form.addEventListener("submit", function (event)
      {
      if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
      form.classList.add("was-validated");
      });
    });
  })();
