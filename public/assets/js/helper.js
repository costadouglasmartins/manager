(function ()
  {
  function digitsOnly(value, maxLength)
    {
    let digits = String(value || "").replace(/\D+/g, "");

    if (typeof maxLength === "number" && maxLength > -1)
      {
      digits = digits.slice(0, maxLength);
      }

    return digits;
    }

  function allowOnlyNumbers(input)
    {
    input.value = digitsOnly(input.value, input.maxLength);
    }

  window.digitsOnly = digitsOnly;

  document.addEventListener("input", function (event)
    {
    if (event.target.matches(".only-numbers"))
      {
      allowOnlyNumbers(event.target);
      }
    });

  document.querySelectorAll(".only-numbers").forEach(allowOnlyNumbers);
  })();
