(() => {
	const form = document.getElementById("bankSlipEditForm");
	if (!form)
		{
		return;
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

		return amount.toLocaleString("pt-BR", {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2,
		});
		}

	function formatMoneyInput(input)
		{
		input.value = normalizeMoneyValue(input.value);
		}

	form.querySelectorAll(".js-money").forEach((input) => {
		formatMoneyInput(input);
		input.addEventListener("input", () => {
			input.value = input.value.replace(/[^0-9,.-]/g, "");
		});
		input.addEventListener("blur", () => formatMoneyInput(input));
	});

	form.querySelectorAll(".js-uppercase").forEach((input) => {
		input.value = input.value.toUpperCase();
		input.addEventListener("input", () => {
			input.value = input.value.toUpperCase();
		});
	});

	const zipCode = document.getElementById("payer_zip_code");
	if (zipCode)
		{
		const formatZipCode = () => {
			const digits = zipCode.value.replace(/\D/g, "").slice(0, 8);
			zipCode.value = digits.length > 5 ? `${digits.slice(0, 5)}-${digits.slice(5)}` : digits;
			};
		formatZipCode();
		zipCode.addEventListener("input", formatZipCode);
		}

	const payerDocument = document.getElementById("payer_document");
	if (payerDocument)
		{
		const formatPayerDocument = () => payerDocument.dispatchEvent(new Event("input", { bubbles: true }));
		if (document.readyState === "loading")
			{
			document.addEventListener("DOMContentLoaded", formatPayerDocument, { once: true });
			}
		else
			{
			formatPayerDocument();
			}
		}

	form.addEventListener("submit", (event) => {
		form.querySelectorAll(".js-money").forEach(formatMoneyInput);

		if (!form.checkValidity())
			{
			event.preventDefault();
			event.stopPropagation();
			}

		form.classList.add("was-validated");
	});
})();
