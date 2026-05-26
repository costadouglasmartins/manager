(function ()
  {
  document.querySelectorAll(".needs-validation").forEach(function (form)
    {
    form.addEventListener("submit", function (event)
      {
      if (!form.checkValidity())
        {
        event.preventDefault();
        event.stopPropagation();
        }

      form.classList.add("was-validated");
      });
    });

  const movementType = document.querySelector("#movement_type");
  const quantityLabel = document.querySelector("[data-stock-quantity-label]");

  if (!movementType || !quantityLabel)
    {
    return;
    }

  const syncLabel = function ()
    {
    quantityLabel.textContent = movementType.value === "ADJUSTMENT" ? "Novo saldo" : "Quantidade";
    };

  movementType.addEventListener("change", syncLabel);
  syncLabel();
  })();
