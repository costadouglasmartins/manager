document.querySelectorAll('[data-bs-toggle="popover"]').forEach((element) => {
	if (window.bootstrap && window.bootstrap.Popover)
		{
		new window.bootstrap.Popover(element);
		}
});

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
		});
	});

	remittanceForm.addEventListener('submit', (event) => {
		const submitter = event.submitter || lastRemittanceButton;
		const bankAccountId = submitter && submitter.dataset ? submitter.dataset.bankAccountId : '';
		const selectedSlips = Array.from(slipCheckboxes).filter((input) => input.checked);
		const selectedForAction = bankAccountId
			? selectedSlips.filter((input) => input.dataset.bankAccountId === bankAccountId)
			: selectedSlips;

		if (selectedForAction.length === 0)
			{
			event.preventDefault();
			window.alert('Selecione ao menos um boleto desta conta para gerar a remessa.');
			}
	});
	}
