(function ()
  {
  document.querySelectorAll(".needs-validation").forEach(function (form)
    {
    form.addEventListener("submit", function (event)
      {
      if (!form.checkValidity())
        {
        event.preventDefault();
        event.stopPropagation();
        }

      form.classList.add("was-validated");
      });
    });

  const input = document.querySelector("[data-product-images-input]");
  const preview = document.querySelector("[data-product-upload-preview]");

  if (!input || !preview)
    {
    return;
    }

  input.addEventListener("change", function ()
    {
    preview.innerHTML = "";

    Array.from(input.files || []).forEach(function (file)
      {
      if (!file.type.startsWith("image/"))
        {
        return;
        }

      const image = document.createElement("img");
      image.alt = file.name;
      image.src = URL.createObjectURL(file);
      image.addEventListener("load", function ()
        {
        URL.revokeObjectURL(image.src);
        });
      preview.appendChild(image);
      });
    });
  })();
