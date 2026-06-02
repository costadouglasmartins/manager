document.querySelectorAll('[data-bs-toggle="popover"]').forEach((element) => {
	if (window.bootstrap && window.bootstrap.Popover)
		{
		new window.bootstrap.Popover(element);
		}
});

(function ()
	{
	const modalElement = document.getElementById("bankSlipDeleteModal");
	const deleteForm = document.getElementById("bankSlipDeleteForm");
	const deleteName = document.getElementById("bankSlipDeleteName");
	let lastTrigger = null;

	if (!modalElement || !deleteForm || !deleteName)
		{
		return;
		}

	const syncModalData = function (trigger)
		{
		if (!trigger)
			{
			return;
			}

		const deleteUrl = trigger.getAttribute("data-bank-slip-delete-url") || "";
		const bankSlipId = trigger.getAttribute("data-bank-slip-id") || "";
		const deleteLabel = trigger.getAttribute("data-bank-slip-delete-label") || "";
		const label = deleteLabel !== "" ? deleteLabel : (bankSlipId !== "" ? ("#" + bankSlipId) : "#");

		deleteForm.setAttribute("action", deleteUrl);
		deleteName.textContent = label;
		};

	document.addEventListener("click", function (event)
		{
		const link = event.target instanceof Element ? event.target.closest(".js-bank-slip-delete-link") : null;
		if (!link)
			{
			return;
			}

		lastTrigger = link;
		syncModalData(link);
		});

	modalElement.addEventListener("show.bs.modal", function (event)
		{
		const related = event.relatedTarget instanceof Element ? event.relatedTarget.closest(".js-bank-slip-delete-link") : null;
		syncModalData(related || lastTrigger);
		});
	})();

(function ()
	{
	const modalElement = document.getElementById("bankSlipStatusModal");
	const statusForm = document.getElementById("bankSlipStatusForm");
	const statusName = document.getElementById("bankSlipStatusName");
	const statusSelect = document.getElementById("bankSlipStatus");
	const statusError = document.getElementById("bankSlipStatusError");
	const submitButton = document.getElementById("bankSlipStatusSubmit");
	let lastTrigger = null;

	if (!modalElement || !statusForm || !statusName || !statusSelect || !statusError || !submitButton)
		{
		return;
		}

	const hideError = function ()
		{
		statusError.textContent = "";
		statusError.classList.add("d-none");
		};

	const showError = function (message)
		{
		statusError.textContent = message;
		statusError.classList.remove("d-none");
		};

	const syncModalData = function (trigger)
		{
		if (!trigger)
			{
			return;
			}

		const bankSlipId = trigger.getAttribute("data-bank-slip-id") || "";
		const label = trigger.getAttribute("data-bank-slip-status-label") || (bankSlipId !== "" ? ("#" + bankSlipId) : "#");
		const status = trigger.getAttribute("data-bank-slip-status") || "";
		const updateUrl = trigger.getAttribute("data-bank-slip-status-url") || "";

		statusForm.setAttribute("action", updateUrl);
		statusName.textContent = label;
		statusSelect.value = status;
		hideError();
		};

	document.addEventListener("click", function (event)
		{
		const button = event.target instanceof Element ? event.target.closest(".js-bank-slip-status-button") : null;
		if (!button)
			{
			return;
			}

		lastTrigger = button;
		syncModalData(button);
		});

	modalElement.addEventListener("show.bs.modal", function (event)
		{
		const related = event.relatedTarget instanceof Element ? event.relatedTarget.closest(".js-bank-slip-status-button") : null;
		syncModalData(related || lastTrigger);
		});

	statusForm.addEventListener("submit", async function (event)
		{
		event.preventDefault();
		hideError();

		if (!statusForm.checkValidity())
			{
			statusForm.classList.add("was-validated");
			return;
			}

		submitButton.disabled = true;

		try
			{
			const response = await fetch(statusForm.getAttribute("action") || "", {
				method: "POST",
				credentials: "same-origin",
				headers: {
					"Accept": "application/json",
					"X-Requested-With": "XMLHttpRequest",
					"Content-Type": "application/x-www-form-urlencoded",
				},
				body: new URLSearchParams({ status: statusSelect.value }).toString(),
			});
			const data = await response.json().catch(function ()
				{
				return {};
				});

			if (!response.ok || data.error)
				{
				throw new Error(data.error || "Não foi possível alterar o status do boleto.");
				}

			window.location.reload();
			}
		catch (error)
			{
			showError(error.message || "Erro de comunicação ao alterar o status do boleto.");
			}
		finally
			{
			submitButton.disabled = false;
			}
		});
	})();

const remittanceForm = document.getElementById('bankSlipRemittanceForm');

if (remittanceForm)
	{
	let lastRemittanceButton = null;

	const accountCheckboxes = remittanceForm.querySelectorAll('.bank-slip-select-account');
	const slipCheckboxes = remittanceForm.querySelectorAll('.bank-slip-remittance-checkbox');

	const slipsForAccount = (bankAccountId) => {
		return Array.from(slipCheckboxes).filter((input) => input.dataset.bankAccountId === bankAccountId);
	};

	const syncAccountCheckbox = (bankAccountId) => {
		const accountCheckbox = remittanceForm.querySelector(`.bank-slip-select-account[data-bank-account-id="${bankAccountId}"]`);
		if (!accountCheckbox)
			{
			return;
			}

		const accountSlips = slipsForAccount(bankAccountId);
		const checkedSlips = accountSlips.filter((input) => input.checked);
		accountCheckbox.checked = accountSlips.length > 0 && checkedSlips.length === accountSlips.length;
		accountCheckbox.indeterminate = checkedSlips.length > 0 && checkedSlips.length < accountSlips.length;
	};

	accountCheckboxes.forEach((checkbox) => {
		checkbox.addEventListener('change', () => {
			slipsForAccount(checkbox.dataset.bankAccountId).forEach((input) => {
				input.checked = checkbox.checked;
			});
			syncAccountCheckbox(checkbox.dataset.bankAccountId);
		});
	});

	slipCheckboxes.forEach((checkbox) => {
		checkbox.addEventListener('change', () => {
			syncAccountCheckbox(checkbox.dataset.bankAccountId);
		});
	});

	remittanceForm.querySelectorAll('.bank-slip-remittance-button').forEach((button) => {
		button.addEventListener('click', () => {
			lastRemittanceButton = button;
			const bankAccountId = button.dataset.bankAccountId || '';
			const accountSlips = slipsForAccount(bankAccountId);
			const selectedForAction = accountSlips.filter((input) => input.checked);
			if (accountSlips.length > 0 && selectedForAction.length === 0)
				{
				accountSlips.forEach((input) => {
					input.checked = true;
				});
				syncAccountCheckbox(bankAccountId);
				}
		});
	});

	remittanceForm.addEventListener('submit', (event) => {
		const submitter = event.submitter || lastRemittanceButton;
		const bankAccountId = submitter && submitter.dataset ? submitter.dataset.bankAccountId : '';
		const accountSlips = bankAccountId ? slipsForAccount(bankAccountId) : [];
		const selectedSlips = Array.from(slipCheckboxes).filter((input) => input.checked);
		const selectedForAction = bankAccountId
			? selectedSlips.filter((input) => input.dataset.bankAccountId === bankAccountId)
			: selectedSlips;

		if (selectedForAction.length === 0)
			{
			if (accountSlips.length > 0)
				{
				accountSlips.forEach((input) => {
					input.checked = true;
				});
				syncAccountCheckbox(bankAccountId);
				}
			}
	});
	}
