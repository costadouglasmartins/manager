document.querySelectorAll('[data-bs-toggle="popover"]').forEach((element) => {
	if (window.bootstrap && window.bootstrap.Popover)
		{
		new window.bootstrap.Popover(element);
		}
});

const remittanceForm = document.getElementById('bankSlipRemittanceForm');

if (remittanceForm)
	{
	remittanceForm.addEventListener('submit', (event) => {
		const selectedSlips = remittanceForm.querySelectorAll('input[name="bank_slip_ids[]"]:checked');
		if (selectedSlips.length === 0)
			{
			event.preventDefault();
			window.alert('Selecione ao menos um boleto para gerar a remessa.');
			}
	});
	}
