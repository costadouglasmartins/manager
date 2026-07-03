(function ()
  {
  const CHUNK_SIZE = 512 * 1024;
  const MAX_FILE_SIZE = 8 * 1024 * 1024;
  const ALLOWED_IMAGE_TYPES = ["image/jpeg", "image/png", "image/webp"];

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

  const form = document.querySelector("form[data-product-chunk-upload-url]");
  const input = document.querySelector("[data-product-images-input]");
  const preview = document.querySelector("[data-product-upload-preview]");
  const progress = document.querySelector("[data-product-upload-progress]");
  const progressBar = document.querySelector("[data-product-upload-progress-bar]");
  const progressStatus = document.querySelector("[data-product-upload-status]");
  const progressDetails = document.querySelector("[data-product-upload-details]");
  const errorBox = document.querySelector("[data-product-upload-error]");
  let uploadInProgress = false;

  if (!input || !preview)
    {
    return;
    }

  input.addEventListener("change", function ()
    {
    hideUploadError();
    resetProgress();
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

  if (!form)
    {
    return;
    }

  form.addEventListener("submit", function (event)
    {
    if (form.dataset.productChunkUploadReady === "1")
      {
      return;
      }

    if (uploadInProgress)
      {
      event.preventDefault();
      return;
      }

    if (!form.checkValidity())
      {
      return;
      }

    const files = selectedFiles();
    if (files.length === 0)
      {
      return;
      }

    event.preventDefault();
    uploadInProgress = true;
    setSubmittingState(true);
    hideUploadError();
    clearUploadTokens();

    uploadFiles(files)
      .then(function (tokens)
        {
        appendUploadTokens(tokens);
        form.dataset.productChunkUploadReady = "1";
        syncRichEditors();
        setSubmittingState(false);
        input.disabled = true;
        submitForm();
        })
      .catch(function (error)
        {
        uploadInProgress = false;
        input.disabled = false;
        form.dataset.productChunkUploadReady = "";
        setSubmittingState(false);
        showUploadError(error.message || "Não foi possível enviar as imagens.");
        });
    });

  function selectedFiles()
    {
    return Array.from(input.files || []).filter(function (file)
      {
      return file.size > 0;
      });
    }

  async function uploadFiles(files)
    {
    validateFiles(files);

    const uploadUrl = form.getAttribute("data-product-chunk-upload-url") || "";
    const totalBytes = files.reduce(function (total, file)
      {
      return total + file.size;
      }, 0);
    const tokens = [];
    let uploadedBytes = 0;

    showProgress();
    updateProgress(0, totalBytes, files[0], 0, files.length);

    for (let fileIndex = 0; fileIndex < files.length; fileIndex++)
      {
      const file = files[fileIndex];
      const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
      const uploadId = createUploadId(fileIndex);
      let completedToken = "";

      for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++)
        {
        const start = chunkIndex * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, file.size);
        const chunk = file.slice(start, end);
        const response = await postChunk(
          uploadUrl,
          uploadId,
          file,
          chunk,
          chunkIndex,
          totalChunks,
          function (chunkUploadedBytes)
            {
            updateProgress(uploadedBytes + chunkUploadedBytes, totalBytes, file, fileIndex, files.length);
            }
        );

        uploadedBytes += chunk.size;
        updateProgress(uploadedBytes, totalBytes, file, fileIndex, files.length);

        if (response.complete)
          {
          completedToken = response.token || "";
          }
        }

      if (!completedToken)
        {
        throw new Error("Não foi possível concluir o upload de " + file.name + ".");
        }

      tokens.push(completedToken);
      }

    updateProgress(totalBytes, totalBytes, files[files.length - 1], files.length - 1, files.length);
    return tokens;
    }

  function validateFiles(files)
    {
    files.forEach(function (file)
      {
      if (file.size > MAX_FILE_SIZE)
        {
        throw new Error("Cada imagem deve ter no máximo 8 MB.");
        }

      if (file.type && !ALLOWED_IMAGE_TYPES.includes(file.type))
        {
        throw new Error("Envie somente imagens JPG, PNG ou WebP.");
        }
      });
    }

  function postChunk(uploadUrl, uploadId, file, chunk, chunkIndex, totalChunks, onProgress)
    {
    return new Promise(function (resolve, reject)
      {
      const request = new XMLHttpRequest();
      const formData = new FormData();

      formData.append("upload_id", uploadId);
      formData.append("file_name", file.name);
      formData.append("file_size", String(file.size));
      formData.append("file_type", file.type || "");
      formData.append("chunk_index", String(chunkIndex));
      formData.append("total_chunks", String(totalChunks));
      formData.append("chunk", chunk, file.name + ".part");

      request.open("POST", uploadUrl, true);
      request.setRequestHeader("Accept", "application/json");
      request.setRequestHeader("X-Requested-With", "XMLHttpRequest");

      request.upload.addEventListener("progress", function (event)
        {
        if (event.lengthComputable)
          {
          onProgress(Math.min(chunk.size, event.loaded));
          }
        });

      request.addEventListener("load", function ()
        {
        const response = parseJson(request.responseText);
        if (request.status >= 200 && request.status < 300 && response)
          {
          resolve(response);
          return;
          }

        reject(new Error((response && response.error) || "Falha ao enviar uma parte do arquivo."));
        });

      request.addEventListener("error", function ()
        {
        reject(new Error("Falha de rede durante o upload das imagens."));
        });

      request.addEventListener("abort", function ()
        {
        reject(new Error("Upload cancelado."));
        });

      request.send(formData);
      });
    }

  function parseJson(value)
    {
    try
      {
      return JSON.parse(value);
      }
    catch (error)
      {
      return null;
      }
    }

  function createUploadId(fileIndex)
    {
    if (window.crypto && typeof window.crypto.randomUUID === "function")
      {
      return window.crypto.randomUUID();
      }

    return [
      Date.now(),
      fileIndex,
      Math.random().toString(16).slice(2),
    ].join("-");
    }

  function appendUploadTokens(tokens)
    {
    tokens.forEach(function (token)
      {
      const hiddenInput = document.createElement("input");
      hiddenInput.type = "hidden";
      hiddenInput.name = "uploaded_images[]";
      hiddenInput.value = token;
      hiddenInput.dataset.productUploadToken = "1";
      form.appendChild(hiddenInput);
      });
    }

  function clearUploadTokens()
    {
    form.querySelectorAll("[data-product-upload-token]").forEach(function (hiddenInput)
      {
      hiddenInput.remove();
      });
    }

  function showProgress()
    {
    if (progress)
      {
      progress.classList.remove("d-none");
      }
    }

  function resetProgress()
    {
    updateProgress(0, 0, null, 0, 0);

    if (progress)
      {
      progress.classList.add("d-none");
      }
    }

  function updateProgress(uploadedBytes, totalBytes, file, fileIndex, fileCount)
    {
    const percent = totalBytes > 0 ? Math.min(100, Math.round((uploadedBytes / totalBytes) * 100)) : 0;

    if (progressBar)
      {
      progressBar.style.width = percent + "%";
      progressBar.textContent = percent + "%";
      }

    if (progress)
      {
      const progressElement = progress.querySelector(".progress");
      if (progressElement)
        {
        progressElement.setAttribute("aria-valuenow", String(percent));
        }
      }

    if (progressStatus)
      {
      progressStatus.textContent = file ? "Enviando " + file.name : "Preparando upload...";
      }

    if (progressDetails)
      {
      const fileCounter = fileCount > 0 ? " (" + (fileIndex + 1) + "/" + fileCount + ")" : "";
      progressDetails.textContent = formatBytes(uploadedBytes) + " de " + formatBytes(totalBytes) + fileCounter;
      }
    }

  function formatBytes(bytes)
    {
    if (bytes >= 1048576)
      {
      return formatNumber(bytes / 1048576) + " MB";
      }

    if (bytes >= 1024)
      {
      return formatNumber(bytes / 1024) + " KB";
      }

    return bytes + " B";
    }

  function formatNumber(value)
    {
    return value.toLocaleString("pt-BR", {
      maximumFractionDigits: 1,
      minimumFractionDigits: 0,
    });
    }

  function setSubmittingState(isSubmitting)
    {
    input.disabled = isSubmitting;

    form.querySelectorAll('button[type="submit"]').forEach(function (button)
      {
      button.disabled = isSubmitting;
      button.setAttribute("aria-busy", isSubmitting ? "true" : "false");
      });
    }

  function showUploadError(message)
    {
    if (!errorBox)
      {
      return;
      }

    errorBox.textContent = message;
    errorBox.classList.remove("d-none");
    }

  function hideUploadError()
    {
    if (!errorBox)
      {
      return;
      }

    errorBox.textContent = "";
    errorBox.classList.add("d-none");
    }

  function syncRichEditors()
    {
    form.querySelectorAll("[data-rich-editor]").forEach(function (root)
      {
      const surface = root.querySelector("[data-rich-editor-surface]");
      const source = root.querySelector("[data-rich-editor-source]");

      if (surface && source)
        {
        source.value = surface.innerHTML.trim();
        }
      });
    }

  function submitForm()
    {
    if (typeof form.requestSubmit === "function")
      {
      form.requestSubmit();
      return;
      }

    HTMLFormElement.prototype.submit.call(form);
    }
  })();
