(function ()
  {
    // Shared by hr_employees/create and hr_employees/edit: validation, person selection check and double-submit lock.
    const form = document.querySelector(".js-hr-employee-form");

    if (!form)
      {
        return;
      }

    const formAlert = form.querySelector(".js-form-alert");
    const personName = document.getElementById("employeePersonName");
    const personId = document.getElementById("employeePersonId");
    const submitButton = form.querySelector("button[type='submit']");

    function showAlert(message)
      {
        if (!formAlert)
          {
            return;
          }

        formAlert.textContent = message;
        formAlert.classList.remove("d-none");
      }

    function hideAlert()
      {
        if (!formAlert)
          {
            return;
          }

        formAlert.textContent = "";
        formAlert.classList.add("d-none");
      }

    // The person must come from the autocomplete list (hidden id filled), not from free text.
    function syncPersonValidity()
      {
        if (!personName || !personId)
          {
            return;
          }

        personName.setCustomValidity(personId.value === "" ? "Selecione uma pessoa da lista." : "");
      }

    if (personName)
      {
        personName.addEventListener("input", function ()
          {
            // The lib clears the hidden id on typing; flag the field until a suggestion is picked again.
            syncPersonValidity();
          });
        personName.addEventListener("autocomplete:selected", syncPersonValidity);
        syncPersonValidity();
      }

    form.addEventListener("submit", function (event)
      {
        hideAlert();
        syncPersonValidity();

        if (!form.checkValidity())
          {
            event.preventDefault();
            event.stopPropagation();
            form.classList.add("was-validated");
            showAlert("Revise os campos destacados antes de salvar.");

            const firstInvalid = form.querySelector(":invalid");

            if (firstInvalid)
              {
                firstInvalid.scrollIntoView({behavior: "smooth", block: "center"});
                firstInvalid.focus({preventScroll: true});
              }

            return;
          }

        // Prevent duplicated submissions while the request is in flight.
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
