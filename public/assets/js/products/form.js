(function ()
  {
  const category = document.querySelector(".js-product-category");
  const container = document.querySelector(".js-product-attributes");
  const section = document.querySelector(".js-product-attributes-section");
  const empty = document.querySelector(".js-product-attributes-empty");
  const trackStock = document.querySelector(".js-product-track-stock");
  const variationList = document.querySelector(".js-product-variations");
  const variationEmpty = document.querySelector(".js-product-variations-empty");
  const variationTemplate = document.getElementById("productVariationTemplate");
  const addVariationButton = document.querySelector(".js-add-product-variation");
  const schemas = readJson("productCategorySchemas");
  const storedValues = readJson("productAttributeValues");
  let nextVariationIndex = variationList ? variationList.children.length : 0;

  if (category && container && section)
    {
    category.addEventListener("change", renderAttributes);
    container.addEventListener("input", applyAttributeConditions);
    container.addEventListener("change", applyAttributeConditions);
    renderAttributes();
    }

  if (trackStock)
    {
    trackStock.addEventListener("change", syncStockFields);
    syncStockFields();
    }

  if (variationList)
    {
    variationList.querySelectorAll(".js-product-variation-row").forEach(prepareVariationRow);
    syncVariationEmpty();
    }

  if (addVariationButton && variationTemplate && variationList)
    {
    addVariationButton.addEventListener("click", addVariation);
    }

  function readJson(id)
    {
    const element = document.getElementById(id);
    if (!element) return {};
    try { return JSON.parse(element.textContent || "{}"); }
    catch (error) { return {}; }
    }

  function syncStockFields()
    {
    document.querySelectorAll(".js-product-stock-field input").forEach(function (input)
      {
      input.disabled = !trackStock.checked;
      });
    }

  function renderAttributes()
    {
    const attributes = schemas[String(category.value)] || [];
    container.innerHTML = "";
    section.classList.remove("d-none");
    empty.classList.toggle("d-none", attributes.length > 0);

    attributes.forEach(function (attribute)
      {
      container.appendChild(buildAttribute(attribute));
      });
    applyAttributeConditions();
    }

  function buildAttribute(attribute)
    {
    const wrapper = document.createElement("div");
    wrapper.className = "col-12 col-md-6 col-xl-4";
    wrapper.dataset.attributeId = String(attribute.product_attribute_id);

    const label = document.createElement("label");
    const attributeId = String(attribute.product_attribute_id);
    const inputId = "product_attribute_" + attributeId;
    label.className = "form-label";
    label.htmlFor = inputId;
    label.dataset.baseLabel = attribute.name;
    label.textContent = attribute.name + (Number(attribute.required) === 1 ? " *" : "");
    wrapper.appendChild(label);

    const group = document.createElement("div");
    group.className = attribute.units && attribute.units.length ? "input-group" : "";
    const control = buildControl(attribute, inputId);
    group.appendChild(control);

    if (attribute.units && attribute.units.length)
      {
      const unit = document.createElement("select");
      unit.className = "form-select product-attribute-unit";
      unit.name = "attributes[" + attributeId + "][unit_id]";
      unit.setAttribute("aria-label", "Unidade de " + attribute.name);
      const currentUnit = valueFor(attributeId).product_unit_id || attribute.product_unit_id || "";
      attribute.units.forEach(function (optionData)
        {
        const option = document.createElement("option");
        option.value = optionData.id;
        option.textContent = optionData.symbol;
        option.selected = String(currentUnit) === String(optionData.id);
        unit.appendChild(option);
        });
      group.appendChild(unit);
      }

    wrapper.appendChild(group);
    if (Number(attribute.required) === 1)
      {
      const feedback = document.createElement("div");
      feedback.className = "invalid-feedback";
      feedback.textContent = "Informe " + attribute.name.toLowerCase() + ".";
      wrapper.appendChild(feedback);
      }
    return wrapper;
    }

  function buildControl(attribute, inputId)
    {
    const attributeId = String(attribute.product_attribute_id);
    const type = String(attribute.data_type || "TEXT").toUpperCase();
    const saved = valueFor(attributeId);
    const name = "attributes[" + attributeId + "][value]";
    let control;

    if (type === "OPTION" || type === "SELECT")
      {
      control = document.createElement("select");
      control.className = "form-select";
      const blank = document.createElement("option");
      blank.value = "";
      blank.textContent = "Selecione";
      control.appendChild(blank);
      (attribute.options || []).forEach(function (optionData)
        {
        const option = document.createElement("option");
        option.value = optionData.id;
        option.textContent = optionData.label;
        option.selected = String(savedValue(saved, type)) === String(optionData.id);
        control.appendChild(option);
        });
      }
    else if (type === "BOOLEAN" || type === "BOOL")
      {
      control = document.createElement("select");
      control.className = "form-select";
      [["", "Selecione"], ["1", "Sim"], ["0", "Não"]].forEach(function (pair)
        {
        const option = document.createElement("option");
        option.value = pair[0];
        option.textContent = pair[1];
        option.selected = String(savedValue(saved, type)) === pair[0];
        control.appendChild(option);
        });
      }
    else
      {
      control = document.createElement("input");
      control.className = "form-control";
      control.type = type === "DATE" ? "date" : "text";
      if (["INTEGER", "INT", "DECIMAL", "NUMBER"].includes(type)) control.inputMode = type === "INTEGER" || type === "INT" ? "numeric" : "decimal";
      control.value = savedValue(saved, type);
      }

    control.id = inputId;
    control.name = name;
    control.dataset.baseRequired = Number(attribute.required) === 1 ? "1" : "0";
    control.required = Number(attribute.required) === 1;
    return control;
    }

  function applyAttributeConditions()
    {
    const attributes = schemas[String(category.value)] || [];
    const byId = {};
    attributes.forEach(function (attribute) { byId[String(attribute.product_attribute_id)] = attribute; });

    container.querySelectorAll("[data-attribute-id]").forEach(function (wrapper)
      {
      const attribute = byId[String(wrapper.dataset.attributeId)];
      if (!attribute) return;
      let visible = true;
      let requiredByCondition = false;
      (attribute.conditions || []).forEach(function (condition)
        {
        const matches = conditionMatches(condition, byId);
        const action = String(condition.action || "SHOW").toUpperCase();
        if (action === "SHOW" && !matches) visible = false;
        if (action === "REQUIRE" && matches) requiredByCondition = true;
        });
      wrapper.classList.toggle("d-none", !visible);
      wrapper.querySelectorAll("input, select, textarea").forEach(function (control)
        {
        control.disabled = !visible;
        if (control.name && control.name.indexOf("[value]") !== -1)
          {
          control.required = visible && (control.dataset.baseRequired === "1" || requiredByCondition);
          }
        });
      const label = wrapper.querySelector(".form-label");
      const valueControl = wrapper.querySelector("[name*='[value]']");
      if (label && valueControl)
        {
        label.textContent = label.dataset.baseLabel + (valueControl.required ? " *" : "");
        }
      });
    }

  function conditionMatches(condition, attributesById)
    {
    const sourceId = String(condition.source_product_attribute_id || "");
    const source = attributesById[sourceId];
    if (!source) return true;
    const control = container.querySelector("[name='attributes[" + sourceId + "][value]']");
    const rawValue = control && !control.disabled ? control.value : "";
    const filled = rawValue !== "";
    const operator = String(condition.operator || "EQUALS").toUpperCase();
    if (operator === "FILLED") return filled;
    if (operator === "EMPTY") return !filled;

    const expected = normalizeComparable(condition.value_text || "");
    const values = comparableValues(source, rawValue);
    const matches = values.some(function (value) { return normalizeComparable(value) === expected; });
    return operator === "NOT_EQUALS" ? !matches : matches;
    }

  function comparableValues(attribute, rawValue)
    {
    const values = [rawValue];
    const type = String(attribute.data_type || "TEXT").toUpperCase();
    if ((type === "OPTION" || type === "SELECT") && rawValue !== "")
      {
      (attribute.options || []).forEach(function (option)
        {
        if (String(option.id) === String(rawValue))
          {
          values.push(option.value || "");
          values.push(option.label || "");
          }
        });
      }
    if (type === "BOOLEAN" || type === "BOOL")
      {
      values.push(rawValue === "1" ? "sim" : "não");
      values.push(rawValue === "1" ? "yes" : "no");
      }
    return values;
    }

  function normalizeComparable(value)
    {
    return String(value == null ? "" : value).trim().toLowerCase();
    }

  function valueFor(attributeId)
    {
    return storedValues[attributeId] || storedValues[Number(attributeId)] || {};
    }

  function savedValue(value, type)
    {
    if (Object.prototype.hasOwnProperty.call(value, "submitted_value")) return value.submitted_value == null ? "" : String(value.submitted_value);
    if (type === "OPTION" || type === "SELECT") return value.product_attribute_option_id || "";
    if (type === "INTEGER" || type === "INT") return value.value_integer == null ? "" : String(value.value_integer);
    if (type === "DECIMAL" || type === "NUMBER") return value.value_decimal == null ? "" : String(value.value_decimal).replace(".", ",");
    if (type === "BOOLEAN" || type === "BOOL") return value.value_boolean == null ? "" : String(value.value_boolean);
    if (type === "DATE") return value.value_date || "";
    return value.value_text || "";
    }

  function addVariation()
    {
    const fragment = variationTemplate.content.cloneNode(true);
    const row = fragment.querySelector(".js-product-variation-row");
    const index = nextVariationIndex++;
    row.querySelectorAll("[data-variation-field]").forEach(function (control)
      {
      const field = control.dataset.variationField === "track_stock_hidden" ? "track_stock" : control.dataset.variationField;
      control.name = "variations[" + index + "][" + field + "]";
      });
    variationList.appendChild(fragment);
    prepareVariationRow(row);
    syncVariationEmpty();
    row.querySelector("[data-variation-field='name']").focus();
    }

  function prepareVariationRow(row)
    {
    const remove = row.querySelector(".js-remove-product-variation");
    const stock = row.querySelector(".js-product-variation-track-stock");
    if (remove) remove.addEventListener("click", function () { row.remove(); syncVariationEmpty(); });
    if (stock)
      {
      stock.addEventListener("change", function () { syncVariationStock(row); });
      syncVariationStock(row);
      }
    }

  function syncVariationStock(row)
    {
    const track = row.querySelector(".js-product-variation-track-stock");
    row.querySelectorAll(".js-product-variation-stock-field input").forEach(function (input)
      {
      input.disabled = !track.checked;
      });
    }

  function syncVariationEmpty()
    {
    if (variationEmpty) variationEmpty.classList.toggle("d-none", variationList.children.length > 0);
    }
  })();
