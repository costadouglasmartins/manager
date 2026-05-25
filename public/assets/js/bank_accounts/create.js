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

  const bankSelect = document.getElementById("bank_id");
  const bankSlipFields = document.querySelectorAll(".js-bank-slip-field");
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

      const text = field.value.replace(/\D+/g, "");

      if (text === "")
        {
        return "";
        }

      const value = Number(text);

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

  function toggleBankSlipFields()
    {
    const selectedOption = bankSelect ? bankSelect.options[bankSelect.selectedIndex] : null;
    const bankCnab = selectedOption ? String(selectedOption.dataset.bankCnab || "") : "";
    const shouldEnableBankSlips = bankCnab === "240" || bankCnab === "400";

    bankSlipFields.forEach(function (fieldWrapper)
      {
      fieldWrapper.classList.toggle("d-none", !shouldEnableBankSlips);

      fieldWrapper.querySelectorAll("[name^='bank_slip_']").forEach(function (field)
        {
        field.disabled = !shouldEnableBankSlips;
        });
      });

    toggleBankSlipProtestDays(shouldEnableBankSlips);
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

  if (bankSelect && bankSlipFields.length > 0)
    {
    bankSelect.addEventListener("change", toggleBankSlipFields);
    toggleBankSlipFields();
    }

  if (bankSlipProtestSelect)
    {
    bankSlipProtestSelect.addEventListener("change", function ()
      {
      const selectedOption = bankSelect ? bankSelect.options[bankSelect.selectedIndex] : null;
      const bankCnab = selectedOption ? String(selectedOption.dataset.bankCnab || "") : "";

      toggleBankSlipProtestDays(bankCnab === "240" || bankCnab === "400");
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
