(function ()
  {
    // Shared by hr_catalogs/create and hr_catalogs/edit: Bootstrap validation and double-submit lock.
    const form = document.querySelector("form.needs-validation");

    if (!form)
      {
        return;
      }

    const submitButton = form.querySelector("button[type='submit']");

    form.addEventListener("submit", function (event)
      {
        if (!form.checkValidity())
          {
            event.preventDefault();
            event.stopPropagation();
            form.classList.add("was-validated");

            const firstInvalid = form.querySelector(":invalid");

            if (firstInvalid)
              {
                firstInvalid.focus();
              }

            return;
          }

        if (submitButton)
          {
            submitButton.disabled = true;
          }
      });

    // Restore the button when the page comes back from the history cache.
    window.addEventListener("pageshow", function ()
      {
        if (submitButton)
          {
            submitButton.disabled = false;
          }
      });
  })();
