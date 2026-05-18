const printButton = document.getElementById('printBoleto');

if (printButton)
	{
	printButton.addEventListener('click', () => {
		window.print();
	});
	}
