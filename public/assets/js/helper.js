(function ()
  {
  function allowOnlyNumbers(input)
    {
    let value = input.value.replace(/\D+/g, "");

    if (input.maxLength > -1)
      {
      value = value.slice(0, input.maxLength);
      }

    input.value = value;
    }

  document.addEventListener("input", function (event)
    {
    if (event.target.matches(".only-numbers"))
      {
      allowOnlyNumbers(event.target);
      }
    });

  document.querySelectorAll(".only-numbers").forEach(allowOnlyNumbers);
  })();
