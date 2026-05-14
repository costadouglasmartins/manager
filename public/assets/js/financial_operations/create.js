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

      if (!form.checkValidity())
        {
        event.preventDefault();
        event.stopPropagation();
        }

      form.classList.add("was-validated");
      });
    });
  })();
