const printButton = document.getElementById('printBankSlip');

if (printButton)
	{
	printButton.addEventListener('click', () => {
		window.print();
	});
	}
