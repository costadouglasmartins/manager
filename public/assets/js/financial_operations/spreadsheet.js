(function ()
	{
	function initialize()
		{
	const modalElement = document.getElementById("financialSpreadsheetCellModal");
	const form = document.getElementById("financialSpreadsheetCellForm");
	const errorBox = document.getElementById("financialSpreadsheetCellError");
	const submitButton = document.getElementById("financialSpreadsheetCellSubmit");
	const categoryIdInput = document.getElementById("financialSpreadsheetCategoryId");
	const subcategoryIdInput = document.getElementById("financialSpreadsheetSubcategoryId");
	const yearInput = document.getElementById("financialSpreadsheetYear");
	const monthInput = document.getElementById("financialSpreadsheetMonth");
	const categoryLabelInput = document.getElementById("financialSpreadsheetCategoryLabel");
	const monthLabelInput = document.getElementById("financialSpreadsheetMonthLabel");
	const valueInput = document.getElementById("financialSpreadsheetValue");

	if (!modalElement || !form || !errorBox || !submitButton || !categoryIdInput || !subcategoryIdInput || !yearInput || !monthInput || !categoryLabelInput || !monthLabelInput || !valueInput || !window.bootstrap)
		{
		return;
		}

	const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);

	function hideError()
		{
		errorBox.textContent = "";
		errorBox.classList.add("d-none");
		}

	function showError(message)
		{
		errorBox.textContent = message;
		errorBox.classList.remove("d-none");
		}

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

	function openCellModal(cell)
		{
		categoryIdInput.value = cell.dataset.categoryId || "";
		subcategoryIdInput.value = cell.dataset.subcategoryId || "";
		yearInput.value = cell.dataset.year || "";
		monthInput.value = cell.dataset.month || "";
		categoryLabelInput.value = cell.dataset.rowLabel || "";
		monthLabelInput.value = ((cell.dataset.monthLabel || "") + " / " + (cell.dataset.year || "")).trim();
		valueInput.value = cell.dataset.value || "0,00";
		form.classList.remove("was-validated");
		hideError();
		modal.show();
		setTimeout(function ()
			{
			valueInput.focus();
			valueInput.select();
			}, 150);
		}

	document.addEventListener("click", function (event)
		{
		const cell = event.target instanceof Element ? event.target.closest(".js-financial-spreadsheet-cell") : null;
		if (!cell)
			{
			return;
			}

		openCellModal(cell);
		});

	valueInput.addEventListener("input", function ()
		{
		valueInput.value = valueInput.value.replace(/[^0-9,.-]/g, "");
		});

	valueInput.addEventListener("blur", function ()
		{
		formatMoneyInput(valueInput);
		});

	form.addEventListener("submit", async function (event)
		{
		event.preventDefault();
		hideError();
		formatMoneyInput(valueInput);

		if (!form.checkValidity())
			{
			form.classList.add("was-validated");
			return;
			}

		submitButton.disabled = true;

		try
			{
			const response = await fetch(form.getAttribute("action") || "", {
				method: "POST",
				credentials: "same-origin",
				headers: {
					"Accept": "application/json",
					"Content-Type": "application/x-www-form-urlencoded",
					"X-Requested-With": "XMLHttpRequest",
				},
				body: new URLSearchParams(new FormData(form)).toString(),
			});
			const data = await response.json().catch(function ()
				{
				return {};
				});

			if (!response.ok || data.error)
				{
				throw new Error(data.message || data.error || "Nao foi possivel salvar a operacao financeira.");
				}

			window.location.reload();
			}
		catch (error)
			{
			showError(error.message || "Erro de comunicacao ao salvar a operacao financeira.");
			}
		finally
			{
			submitButton.disabled = false;
			}
		});
		}

	if (document.readyState === "loading")
		{
		document.addEventListener("DOMContentLoaded", initialize);
		return;
		}

	initialize();
	})();
