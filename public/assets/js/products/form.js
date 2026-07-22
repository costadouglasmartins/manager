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
  const generator = document.querySelector(".js-product-variation-generator");
  const generateButton = document.querySelector(".js-generate-product-variations");
  const priceRuleList = document.querySelector(".js-product-price-rules");
  const priceRuleEmpty = document.querySelector(".js-product-price-rules-empty");
  const priceRuleTemplate = document.getElementById("productPriceRuleTemplate");
  const addPriceRuleButton = document.querySelector(".js-add-product-price-rule");
  const schemas = readJson("productCategorySchemas");
  const storedValues = readJson("productAttributeValues");
  let nextVariationIndex = variationList ? variationList.children.length : 0;
  let nextPriceRuleIndex = priceRuleList ? priceRuleList.children.length : 0;

  if (category && container && section)
    {
    category.addEventListener("change", function ()
      {
      renderAttributes();
      renderGenerator();
      variationList.querySelectorAll(".js-product-variation-row").forEach(function (row)
        {
        renderVariationAttributes(row, Number(row.dataset.variationIndex || 0), {}, {});
        });
      });
    container.addEventListener("input", applyAllAttributeConditions);
    container.addEventListener("change", applyAllAttributeConditions);
    renderAttributes();
    renderGenerator();
    }

  if (trackStock)
    {
    trackStock.addEventListener("change", syncStockFields);
    syncStockFields();
    }

  if (variationList)
    {
    variationList.querySelectorAll(".js-product-variation-row").forEach(function (row, position)
      {
      const attributeContainer = row.querySelector(".js-product-variation-attributes");
      const values = attributeContainer ? parseJsonText(attributeContainer.dataset.variationValues || "{}") : {};
      const index = Number(attributeContainer && attributeContainer.dataset.variationIndex !== undefined ? attributeContainer.dataset.variationIndex : position);
      row.dataset.variationIndex = String(index);
      prepareVariationRow(row, index, values, {});
      nextVariationIndex = Math.max(nextVariationIndex, index + 1);
      });
    syncVariationEmpty();
    syncPriceRuleTargets();
    }

  if (addVariationButton && variationTemplate && variationList) addVariationButton.addEventListener("click", function () { addVariation({}); });
  if (generateButton) generateButton.addEventListener("click", generateVariations);

  if (priceRuleList)
    {
    priceRuleList.querySelectorAll(".js-product-price-rule-row").forEach(preparePriceRuleRow);
    syncPriceRuleEmpty();
    }
  if (addPriceRuleButton && priceRuleTemplate && priceRuleList) addPriceRuleButton.addEventListener("click", addPriceRule);

  function readJson(id)
    {
    const element = document.getElementById(id);
    return element ? parseJsonText(element.textContent || "{}") : {};
    }

  function parseJsonText(value)
    {
    try { return JSON.parse(value || "{}"); }
    catch (error) { return {}; }
    }

  function currentSchema()
    {
    return schemas[String(category ? category.value : "")] || [];
    }

  function syncStockFields()
    {
    document.querySelectorAll(".js-product-stock-field input").forEach(function (input) { input.disabled = !trackStock.checked; });
    }

  function renderAttributes()
    {
    const attributes = currentSchema().filter(function (attribute) { return Number(attribute.variation_specific || 0) !== 1; });
    container.innerHTML = "";
    section.classList.remove("d-none");
    empty.classList.toggle("d-none", attributes.length > 0);
    attributes.forEach(function (attribute)
      {
      container.appendChild(buildAttribute(attribute, "attributes", valueFor(attribute.product_attribute_id), "product_attribute_" + attribute.product_attribute_id));
      });
    applyBaseAttributeConditions();
    }

  function buildAttribute(attribute, prefix, saved, inputId)
    {
    const wrapper = document.createElement("div");
    wrapper.className = "col-12 col-md-6 col-xl-4";
    wrapper.dataset.attributeId = String(attribute.product_attribute_id);
    const label = document.createElement("label");
    label.className = "form-label";
    label.htmlFor = inputId;
    label.dataset.baseLabel = String(attribute.name || "Característica");
    label.textContent = label.dataset.baseLabel + (Number(attribute.required) === 1 ? " *" : "");
    wrapper.appendChild(label);

    const group = document.createElement("div");
    group.className = attribute.units && attribute.units.length ? "input-group" : "";
    const control = buildControl(attribute, prefix, saved || {}, inputId);
    group.appendChild(control);
    if (attribute.units && attribute.units.length)
      {
      const unit = document.createElement("select");
      unit.className = "form-select product-attribute-unit";
      unit.name = prefix + "[" + attribute.product_attribute_id + "][unit_id]";
      unit.setAttribute("aria-label", "Unidade de " + attribute.name);
      const currentUnit = saved.product_unit_id || attribute.product_unit_id || "";
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
    const feedback = document.createElement("div");
    feedback.className = "invalid-feedback";
    feedback.textContent = "Informe " + String(attribute.name || "a característica").toLowerCase() + ".";
    wrapper.appendChild(feedback);
    return wrapper;
    }

  function buildControl(attribute, prefix, saved, inputId)
    {
    const attributeId = String(attribute.product_attribute_id);
    const type = String(attribute.data_type || "TEXT").toUpperCase();
    const name = prefix + "[" + attributeId + "][value]";
    let control;
    if (type === "OPTION" || type === "SELECT")
      {
      control = document.createElement("select");
      control.className = "form-select";
      appendOption(control, "", "Selecione", savedValue(saved, type) === "");
      (attribute.options || []).forEach(function (optionData)
        {
        appendOption(control, optionData.id, optionData.label, String(savedValue(saved, type)) === String(optionData.id));
        });
      }
    else if (type === "BOOLEAN" || type === "BOOL")
      {
      control = document.createElement("select");
      control.className = "form-select";
      [["", "Selecione"], ["1", "Sim"], ["0", "Não"]].forEach(function (pair) { appendOption(control, pair[0], pair[1], String(savedValue(saved, type)) === pair[0]); });
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

  function appendOption(select, value, label, selected)
    {
    const option = document.createElement("option");
    option.value = value;
    option.textContent = label;
    option.selected = selected;
    select.appendChild(option);
    }

  function applyBaseAttributeConditions()
    {
    applyScopedConditions(container, currentSchema().filter(function (attribute) { return Number(attribute.variation_specific || 0) !== 1; }), "attributes");
    }

  function applyAllAttributeConditions()
    {
    applyBaseAttributeConditions();
    if (!variationList) return;
    variationList.querySelectorAll(".js-product-variation-row").forEach(function (row)
      {
      const target = row.querySelector(".js-product-variation-attributes");
      const index = Number(row.dataset.variationIndex || 0);
      const attributes = currentSchema().filter(function (attribute) { return Number(attribute.variation_specific || 0) === 1; });
      applyScopedConditions(target, attributes, "variations[" + index + "][attributes]");
      });
    }

  function applyScopedConditions(scope, attributes, prefix)
    {
    if (!scope) return;
    const byId = {};
    attributes.forEach(function (attribute) { byId[String(attribute.product_attribute_id)] = attribute; });
    scope.querySelectorAll("[data-attribute-id]").forEach(function (wrapper)
      {
      const attribute = byId[String(wrapper.dataset.attributeId)];
      if (!attribute) return;
      let visible = true;
      let requiredByCondition = false;
      (attribute.conditions || []).forEach(function (condition)
        {
        const matches = conditionMatches(condition, byId, scope, prefix);
        const action = String(condition.action || "SHOW").toUpperCase();
        if (action === "SHOW" && !matches) visible = false;
        if (action === "REQUIRE" && matches) requiredByCondition = true;
        });
      wrapper.classList.toggle("d-none", !visible);
      wrapper.querySelectorAll("input, select, textarea").forEach(function (control)
        {
        control.disabled = !visible;
        if (control.name && control.name.indexOf("[value]") !== -1) control.required = visible && (control.dataset.baseRequired === "1" || requiredByCondition);
        });
      const label = wrapper.querySelector(".form-label");
      const valueControl = wrapper.querySelector("[name*='[value]']");
      if (label && valueControl) label.textContent = label.dataset.baseLabel + (valueControl.required ? " *" : "");
      });
    }

  function conditionMatches(condition, attributesById, scope, prefix)
    {
    const sourceId = String(condition.source_product_attribute_id || "");
    const source = attributesById[sourceId] || currentSchema().find(function (attribute) { return String(attribute.product_attribute_id) === sourceId; });
    if (!source) return true;
    let control = scope.querySelector("[name='" + prefix + "[" + sourceId + "][value]']");
    if (!control && container && scope !== container) control = container.querySelector("[name='attributes[" + sourceId + "][value]']");
    const rawValue = control && !control.disabled ? control.value : "";
    const filled = rawValue !== "";
    const operator = String(condition.operator || "EQUALS").toUpperCase();
    if (operator === "FILLED") return filled;
    if (operator === "EMPTY") return !filled;
    const expected = normalizeComparable(condition.value_text || "");
    const matches = comparableValues(source, rawValue).some(function (value) { return normalizeComparable(value) === expected; });
    return operator === "NOT_EQUALS" ? !matches : matches;
    }

  function comparableValues(attribute, rawValue)
    {
    const values = [rawValue];
    const type = String(attribute.data_type || "TEXT").toUpperCase();
    if ((type === "OPTION" || type === "SELECT") && rawValue !== "")
      {
      (attribute.options || []).forEach(function (option) { if (String(option.id) === String(rawValue)) values.push(option.value || "", option.label || ""); });
      }
    if (type === "BOOLEAN" || type === "BOOL") values.push(rawValue === "1" ? "sim" : "não", rawValue === "1" ? "yes" : "no");
    return values;
    }

  function normalizeComparable(value) { return String(value == null ? "" : value).trim().toLowerCase(); }
  function valueFor(attributeId) { return storedValues[attributeId] || storedValues[Number(attributeId)] || {}; }

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

  function renderGenerator()
    {
    if (!generator || !generateButton) return;
    const axes = currentSchema().filter(function (attribute) { return Number(attribute.variation_axis || 0) === 1 && (attribute.options || []).length > 0; });
    generator.innerHTML = "";
    generator.classList.toggle("d-none", axes.length === 0);
    generateButton.classList.toggle("d-none", axes.length === 0);
    axes.forEach(function (attribute)
      {
      const fieldset = document.createElement("fieldset");
      fieldset.className = "mb-3";
      const legend = document.createElement("legend");
      legend.className = "fs-6 fw-semibold mb-2";
      legend.textContent = attribute.name;
      fieldset.appendChild(legend);
      const options = document.createElement("div");
      options.className = "d-flex flex-wrap gap-3";
      (attribute.options || []).forEach(function (option)
        {
        const label = document.createElement("label");
        label.className = "form-check";
        const checkbox = document.createElement("input");
        checkbox.className = "form-check-input";
        checkbox.type = "checkbox";
        checkbox.checked = true;
        checkbox.value = option.id;
        checkbox.dataset.axisId = attribute.product_attribute_id;
        checkbox.dataset.optionLabel = option.label;
        checkbox.dataset.optionValue = option.value;
        const text = document.createElement("span");
        text.className = "form-check-label";
        text.textContent = option.label;
        label.append(checkbox, text);
        options.appendChild(label);
        });
      fieldset.appendChild(options);
      generator.appendChild(fieldset);
      });
    const help = document.createElement("small");
    help.className = "text-muted";
    help.textContent = "Marque as opções desejadas e gere somente as combinações que ainda não existem.";
    generator.appendChild(help);
    }

  function generateVariations()
    {
    const axes = currentSchema().filter(function (attribute) { return Number(attribute.variation_axis || 0) === 1; });
    const selected = axes.map(function (attribute)
      {
      return Array.from(generator.querySelectorAll("[data-axis-id='" + attribute.product_attribute_id + "']:checked")).map(function (checkbox)
        {
        return { attribute: attribute, optionId: checkbox.value, label: checkbox.dataset.optionLabel || checkbox.value, value: checkbox.dataset.optionValue || checkbox.value };
        });
      });
    if (selected.some(function (options) { return options.length === 0; })) { window.alert("Selecione ao menos uma opção em cada característica geradora."); return; }
    const combinations = selected.reduce(function (rows, options)
      {
      const result = [];
      rows.forEach(function (row) { options.forEach(function (option) { result.push(row.concat([option])); }); });
      return result;
      }, [[]]);
    if (combinations.length > 200) { window.alert("A combinação geraria mais de 200 variações. Reduza as opções selecionadas."); return; }

    const existing = existingCombinationSignatures();
    combinations.forEach(function (combination)
      {
      const signature = combination.map(function (item) { return item.attribute.product_attribute_id + "=" + item.optionId; }).sort().join("|");
      if (existing.has(signature)) return;
      const attributes = {};
      combination.forEach(function (item) { attributes[item.attribute.product_attribute_id] = { value: item.optionId }; });
      const name = combination.map(function (item) { return item.label; }).join(" / ");
      const baseSku = (document.getElementById("sku") || {}).value || "";
      const suffix = combination.map(function (item) { return slug(item.value || item.label); }).filter(Boolean).join("-");
      addVariation({ name: name, sku: [baseSku, suffix].filter(Boolean).join("-"), attributes: attributes });
      existing.add(signature);
      });
    }

  function existingCombinationSignatures()
    {
    const signatures = new Set();
    variationList.querySelectorAll(".js-product-variation-row").forEach(function (row)
      {
      const parts = [];
      currentSchema().filter(function (attribute) { return Number(attribute.variation_axis || 0) === 1; }).forEach(function (attribute)
        {
        const control = row.querySelector("[name$='[attributes][" + attribute.product_attribute_id + "][value]']");
        if (control && control.value !== "") parts.push(attribute.product_attribute_id + "=" + control.value);
        });
      if (parts.length) signatures.add(parts.sort().join("|"));
      });
    return signatures;
    }

  function addVariation(seed)
    {
    const fragment = variationTemplate.content.cloneNode(true);
    const row = fragment.querySelector(".js-product-variation-row");
    const index = nextVariationIndex++;
    const clientKey = "new-" + Date.now().toString(36) + "-" + index + "-" + Math.random().toString(36).slice(2, 8);
    row.dataset.clientKey = clientKey;
    row.dataset.variationIndex = String(index);
    row.querySelectorAll("[data-variation-field]").forEach(function (control)
      {
      const field = control.dataset.variationField === "track_stock_hidden" ? "track_stock" : control.dataset.variationField;
      control.name = "variations[" + index + "][" + field + "]";
      if (field === "client_key") control.value = clientKey;
      if (Object.prototype.hasOwnProperty.call(seed, field)) control.value = seed[field];
      });
    variationList.appendChild(fragment);
    prepareVariationRow(row, index, {}, seed.attributes || {});
    syncVariationEmpty();
    syncPriceRuleTargets();
    const name = row.querySelector("[data-variation-field='name']");
    if (name) name.focus();
    }

  function prepareVariationRow(row, index, values, seededAttributes)
    {
    row.dataset.variationIndex = String(index);
    const remove = row.querySelector(".js-remove-product-variation");
    const stock = row.querySelector(".js-product-variation-track-stock");
    const name = row.querySelector("[name$='[name]'], [data-variation-field='name']");
    if (remove) remove.addEventListener("click", function () { row.remove(); syncVariationEmpty(); syncPriceRuleTargets(); });
    if (stock) { stock.addEventListener("change", function () { syncVariationStock(row); }); syncVariationStock(row); }
    if (name) name.addEventListener("input", syncPriceRuleTargets);
    renderVariationAttributes(row, index, values, seededAttributes);
    }

  function renderVariationAttributes(row, index, values, seededAttributes)
    {
    const target = row.querySelector(".js-product-variation-attributes");
    if (!target) return;
    target.innerHTML = "";
    const attributes = currentSchema().filter(function (attribute) { return Number(attribute.variation_specific || 0) === 1; });
    const prefix = "variations[" + index + "][attributes]";
    attributes.forEach(function (attribute)
      {
      const saved = values[attribute.product_attribute_id] || values[String(attribute.product_attribute_id)] || {};
      const seed = seededAttributes[attribute.product_attribute_id] || seededAttributes[String(attribute.product_attribute_id)] || null;
      if (seed) saved.submitted_value = seed.value;
      target.appendChild(buildAttribute(attribute, prefix, saved, "variation_" + index + "_attribute_" + attribute.product_attribute_id));
      });
    target.addEventListener("input", function () { applyScopedConditions(target, attributes, prefix); });
    target.addEventListener("change", function () { applyScopedConditions(target, attributes, prefix); });
    applyScopedConditions(target, attributes, prefix);
    }

  function syncVariationStock(row)
    {
    const stock = row.querySelector(".js-product-variation-track-stock");
    row.querySelectorAll(".js-product-variation-stock-field input").forEach(function (input) { input.disabled = !stock.checked; });
    }

  function syncVariationEmpty() { if (variationEmpty) variationEmpty.classList.toggle("d-none", variationList.children.length > 0); }

  function addPriceRule()
    {
    const fragment = priceRuleTemplate.content.cloneNode(true);
    const row = fragment.querySelector(".js-product-price-rule-row");
    const index = nextPriceRuleIndex++;
    row.querySelectorAll("[data-price-rule-field]").forEach(function (control)
      {
      let field = control.dataset.priceRuleField;
      if (field === "available_hidden") field = "available";
      if (field === "active_hidden") field = "active";
      control.name = "price_rules[" + index + "][" + field + "]";
      });
    priceRuleList.appendChild(fragment);
    preparePriceRuleRow(row);
    syncPriceRuleTargets();
    syncPriceRuleEmpty();
    }

  function preparePriceRuleRow(row)
    {
    const remove = row.querySelector(".js-remove-product-price-rule");
    if (remove) remove.addEventListener("click", function () { row.remove(); syncPriceRuleEmpty(); });
    }

  function syncPriceRuleTargets()
    {
    if (!priceRuleList || !variationList) return;
    const variations = Array.from(variationList.querySelectorAll(".js-product-variation-row")).map(function (row)
      {
      const keyControl = row.querySelector("[name$='[client_key]']");
      const nameControl = row.querySelector("[name$='[name]'], [data-variation-field='name']");
      return { key: keyControl ? keyControl.value : row.dataset.clientKey, name: nameControl && nameControl.value ? nameControl.value : "Variação sem nome" };
      }).filter(function (variation) { return variation.key; });
    priceRuleList.querySelectorAll(".js-price-rule-variation").forEach(function (select)
      {
      const selected = select.value || select.dataset.selectedValue || "";
      select.innerHTML = "";
      appendOption(select, "", "Produto principal", selected === "");
      variations.forEach(function (variation) { appendOption(select, variation.key, variation.name, selected === variation.key); });
      select.dataset.selectedValue = "";
      });
    }

  function syncPriceRuleEmpty() { if (priceRuleEmpty) priceRuleEmpty.classList.toggle("d-none", priceRuleList.children.length > 0); }
  function slug(value) { return String(value || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, ""); }
  })();
