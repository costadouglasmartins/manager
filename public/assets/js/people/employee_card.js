(function ()
  {
    // Shows the "Dados do funcionário" card only while the employee role is checked.
    // Hidden fields are disabled so they are not submitted and skip native validation.
    const section = document.getElementById("employeeSection");
    const employeeRole = document.getElementById("personRoleEmployee");

    if (!section || !employeeRole)
      {
        return;
      }

    function syncEmployeeSection()
      {
        const enabled = employeeRole.checked;

        section.classList.toggle("d-none", !enabled);
        section.querySelectorAll("input, select, textarea").forEach(function (field)
          {
            field.disabled = !enabled;
          });

        if (!enabled)
          {
            // Drop stale validation feedback from fields that are no longer part of the form.
            section.querySelectorAll(".is-invalid").forEach(function (field)
              {
                field.classList.remove("is-invalid");
              });
          }
      }

    employeeRole.addEventListener("change", syncEmployeeSection);
    syncEmployeeSection();
  })();
