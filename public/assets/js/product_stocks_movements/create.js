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
	const product = document.querySelector("#product_id");
	const variation = document.querySelector("#variation_id");
	const dataElement = document.getElementById("productStockOptions");
	let products = [];
	try { products = JSON.parse(dataElement ? dataElement.textContent : "[]"); }
	catch (error) { products = []; }

	if (product && variation)
		{
		const syncTargetOptions = function ()
			{
			const selected = variation.value || variation.dataset.selectedValue || "";
			const productRow = products.find(function (row) { return String(row.id) === String(product.value); });
			variation.innerHTML = "";
			if (!productRow)
				{
				variation.disabled = true;
				variation.required = true;
				const option = document.createElement("option"); option.value = ""; option.textContent = "Selecione primeiro o produto"; variation.appendChild(option);
				return;
				}
			const parentOption = document.createElement("option");
			parentOption.value = "";
			parentOption.textContent = Number(productRow.track_stock || 0) === 1 ? "Produto principal — estoque: " + formatQuantity(productRow.stock_quantity) : "Produto principal — sem controle de estoque";
			parentOption.disabled = Number(productRow.track_stock || 0) !== 1;
			parentOption.selected = selected === "" && !parentOption.disabled;
			variation.appendChild(parentOption);
			(productRow.variations || []).forEach(function (row)
				{
				const option = document.createElement("option");
				option.value = row.id;
				option.textContent = row.name + (row.sku ? " — " + row.sku : "") + " — estoque: " + formatQuantity(row.stock_quantity);
				option.selected = String(selected) === String(row.id);
				variation.appendChild(option);
				});
			variation.disabled = false;
			variation.required = parentOption.disabled;
			variation.dataset.selectedValue = "";
			};
		product.addEventListener("change", syncTargetOptions);
		syncTargetOptions();
		}

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

	function formatQuantity(value)
		{
		return Number(value || 0).toLocaleString("pt-BR", { minimumFractionDigits: 0, maximumFractionDigits: 3 });
		}
  })();
