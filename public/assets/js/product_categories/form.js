(function ()
  {
  const list = document.querySelector(".js-new-product-attributes");
  const empty = document.querySelector(".js-new-product-attributes-empty");
  const addButton = document.querySelector(".js-add-product-attribute");
  const template = document.getElementById("productAttributeTemplate");
  let nextIndex = 0;

  document.querySelectorAll(".js-product-variation-axis").forEach(function (axis)
    {
    axis.addEventListener("change", function ()
      {
      if (!axis.checked) return;
      const row = axis.closest("tr");
      const specific = row ? row.querySelector(".js-product-variation-specific") : null;
      if (specific) specific.checked = true;
      });
    });

  const saveTemplate = document.querySelector(".js-save-category-template");
  if (saveTemplate)
    {
    const syncTemplateFields = function ()
      {
      document.querySelectorAll(".js-category-template-field").forEach(function (field)
        {
        field.disabled = !saveTemplate.checked;
        field.required = saveTemplate.checked && field.id === "template_name";
        });
      };
    saveTemplate.addEventListener("change", syncTemplateFields);
    syncTemplateFields();
    }

  document.querySelectorAll(".needs-validation").forEach(function (form)
    {
    form.addEventListener("submit", function (event)
      {
      if (!form.checkValidity()) { event.preventDefault(); event.stopPropagation(); }
      form.classList.add("was-validated");
      });
    });

  document.querySelectorAll(".js-product-condition-operator").forEach(function (operator)
    {
    operator.addEventListener("change", function () { syncConditionRow(operator); });
    syncConditionRow(operator);
    });

  if (!list || !addButton || !template) return;
  addButton.addEventListener("click", addAttribute);

  function addAttribute()
    {
    const fragment = template.content.cloneNode(true);
    const row = fragment.querySelector(".js-new-product-attribute");
    const index = nextIndex++;
    row.querySelectorAll("[data-field]").forEach(function (control)
      {
      control.name = "new_attributes[" + index + "][" + control.dataset.field + "]";
      });
    row.querySelector("[data-field='name']").required = true;
    row.querySelector(".js-remove-product-attribute").addEventListener("click", function () { row.remove(); syncEmpty(); });
    row.querySelector(".js-product-attribute-type").addEventListener("change", function () { syncRow(row); });
    row.querySelector(".js-new-variation-axis").addEventListener("change", function ()
      {
      if (row.querySelector(".js-new-variation-axis").checked) row.querySelector(".js-new-variation-specific").checked = true;
      });
    list.appendChild(fragment);
    syncRow(row);
    syncEmpty();
    row.querySelector("[data-field='name']").focus();
    }

  function syncRow(row)
    {
    const type = row.querySelector(".js-product-attribute-type").value;
    const measurement = row.querySelector(".js-product-attribute-measurement");
    const options = row.querySelector(".js-product-attribute-options");
    const optionsInput = options.querySelector("textarea");
    const variationAxis = row.querySelector(".js-new-variation-axis");
    const measured = type === "INTEGER" || type === "DECIMAL";
    measurement.disabled = !measured;
    if (!measured) measurement.value = "";
    options.classList.toggle("d-none", type !== "OPTION");
    optionsInput.required = type === "OPTION";
    variationAxis.disabled = type !== "OPTION";
    if (type !== "OPTION") variationAxis.checked = false;
    }

  function syncEmpty() { empty.classList.toggle("d-none", list.children.length > 0); }

  function syncConditionRow(operator)
    {
    const row = operator.closest(".row");
    if (!row) return;
    const value = row.querySelector(".js-product-condition-value");
    const source = row.querySelector(".js-product-condition-source");
    if (!value || !source) return;
    const noValueNeeded = operator.value === "FILLED" || operator.value === "EMPTY" || source.value === "";
    value.disabled = noValueNeeded;
    value.required = !noValueNeeded && source.value !== "";
    if (noValueNeeded) value.value = "";
    source.addEventListener("change", function () { syncConditionRow(operator); }, { once: true });
    }
  })();
