(function ()
  {
  function normalizeMoneyValue(value)
    {
    let text = String(value || "").trim().replace(/[R$\s]/g, "");

    if (text === "")
      {
      return "";
      }

    if (text.includes(","))
      {
      text = text.replace(/\./g, "").replace(",", ".");
      }
    else if (/^-?\d{1,3}(\.\d{3})+$/.test(text))
      {
      text = text.replace(/\./g, "");
      }

    text = text.replace(/[^0-9.-]/g, "");
    const amount = Number.parseFloat(text);

    if (!Number.isFinite(amount))
      {
      return "";
      }

    return amount.toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

  function formatMoneyInput(input)
    {
    input.value = normalizeMoneyValue(input.value);
    }

  const bankSlipToggle = document.querySelector(".js-bank-slip-toggle");
  const bankSelect = document.getElementById("bank_id");
  const bankSlipFields = document.querySelectorAll(".js-bank-slip-field");
  const cnab240OnlyFieldWrappers = document.querySelectorAll(".js-bank-slip-cnab240-field");
  const bankSlipProtestSelect = document.getElementById("bank_slip_protest");
  const bankSlipProtestDaysInput = document.getElementById("bank_slip_protest_number_of_days");
  const fieldValidationRules = [
    {
    selector: "#bank_slip_protest_number_of_days",
    validate: function (field)
      {
      if (field.disabled || field.value.trim() === "")
        {
        return "";
        }

      const value = Number(field.value);
      return Number.isFinite(value) && value >= 5 ? "" : "Informe no mínimo 5 dias para protesto.";
      },
    },
  ];

  function validateField(field)
    {
    field.setCustomValidity("");

    const rule = fieldValidationRules.find(function (validationRule)
      {
      return field.matches(validationRule.selector);
      });

    if (!rule)
      {
      return true;
      }

    if (field.disabled || field.closest(".d-none"))
      {
      return true;
      }

    const message = rule.validate(field);
    field.setCustomValidity(message);

    return message === "";
    }

  function validateFormFields(form)
    {
    let isValid = true;

    fieldValidationRules.forEach(function (rule)
      {
      form.querySelectorAll(rule.selector).forEach(function (field)
        {
        if (!validateField(field))
          {
          isValid = false;
          }
        });
      });

    return isValid;
    }

  function registerFieldValidations()
    {
    fieldValidationRules.forEach(function (rule)
      {
      document.querySelectorAll(rule.selector).forEach(function (field)
        {
        field.addEventListener("input", function ()
          {
          validateField(field);
          });

        field.addEventListener("change", function ()
          {
          validateField(field);
          });

        validateField(field);
        });
      });
    }

  function selectedBankConfig()
    {
    if (!bankSelect)
      {
      return { number: "", cnab: "" };
      }

    const selectedOption = bankSelect.options[bankSelect.selectedIndex] || null;

    return {
      number: selectedOption ? String(selectedOption.dataset.bankNumber || "").trim() : "",
      cnab: selectedOption ? String(selectedOption.dataset.bankCnab || "").trim() : "",
    };
    }

  function toggleBankSlipLayoutByBank(shouldEnableBankSlips)
    {
    const bankConfig = selectedBankConfig();
    const shouldShowCnab240OnlyFields = shouldEnableBankSlips && bankConfig.number === "341" && bankConfig.cnab === "240";

    cnab240OnlyFieldWrappers.forEach(function (fieldWrapper)
      {
      fieldWrapper.classList.toggle("d-none", !shouldShowCnab240OnlyFields);

      fieldWrapper.querySelectorAll("input, select, textarea").forEach(function (field)
        {
        field.disabled = !shouldShowCnab240OnlyFields;
        field.required = false;
        field.setCustomValidity("");
        });
      });

    toggleBankSlipProtestDays(shouldShowCnab240OnlyFields);
    }

  function toggleBankSlipProtestDays(shouldEnableBankSlips)
    {
    if (!bankSlipProtestSelect || !bankSlipProtestDaysInput)
      {
      return;
      }

    bankSlipProtestDaysInput.disabled = !shouldEnableBankSlips || bankSlipProtestSelect.value !== "1";
    validateField(bankSlipProtestDaysInput);
    }

  function toggleBankSlipFields()
    {
    const shouldEnableBankSlips = Boolean(bankSlipToggle && bankSlipToggle.checked);

    bankSlipFields.forEach(function (fieldWrapper)
      {
      fieldWrapper.classList.toggle("d-none", !shouldEnableBankSlips);

      fieldWrapper.querySelectorAll("[name^='bank_slip_']").forEach(function (field)
        {
        field.disabled = !shouldEnableBankSlips;
        });
      });

    if (bankSelect)
      {
      bankSelect.required = shouldEnableBankSlips;
      }

    toggleBankSlipLayoutByBank(shouldEnableBankSlips);
    }

  if (bankSlipToggle && bankSlipFields.length > 0)
    {
    bankSlipToggle.addEventListener("change", toggleBankSlipFields);
    toggleBankSlipFields();
    }

  if (bankSelect)
    {
    bankSelect.addEventListener("change", toggleBankSlipFields);
    }

  if (bankSlipProtestSelect)
    {
    bankSlipProtestSelect.addEventListener("change", function ()
      {
      toggleBankSlipLayoutByBank(Boolean(bankSlipToggle && bankSlipToggle.checked));
      });
    }

  registerFieldValidations();

  document.querySelectorAll(".js-money").forEach(function (input)
    {
    input.addEventListener("input", function ()
      {
      input.value = input.value.replace(/[^0-9,.-]/g, "");
      });

    input.addEventListener("blur", function ()
      {
      formatMoneyInput(input);
      });
    });

  document.querySelectorAll(".needs-validation").forEach(function (form)
    {
    form.addEventListener("submit", function (event)
      {
      form.querySelectorAll(".js-money").forEach(formatMoneyInput);
      validateFormFields(form);

      if (!form.checkValidity())
        {
        event.preventDefault();
        event.stopPropagation();
        }

      form.classList.add("was-validated");
      });
    });
  })();
