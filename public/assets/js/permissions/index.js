(() => {
  const toggleAll = document.getElementById("permToggleAll");
  const checkboxes = document.querySelectorAll('input[name="permission_ids[]"]');
  const groupToggles = document.querySelectorAll(".perm-group-toggle");

  if (!checkboxes.length)
    {
    return;
    }

  function groupCheckboxes(group)
    {
    return document.querySelectorAll('input[name="permission_ids[]"][data-group="' + group + '"]');
    }

  function allCheckedIn(list)
    {
    for (let i = 0; i < list.length; i++)
      {
      if (!list[i].checked)
        {
        return false;
        }
      }
    return list.length > 0;
    }

  function someCheckedIn(list)
    {
    for (let i = 0; i < list.length; i++)
      {
      if (list[i].checked)
        {
        return true;
        }
      }
    return false;
    }

  function syncToggle(toggle, list)
    {
    const allOn = allCheckedIn(list);
    toggle.checked = allOn;
    toggle.indeterminate = (!allOn && someCheckedIn(list));
    }

  function updateGlobalToggle()
    {
    if (toggleAll)
      {
      syncToggle(toggleAll, checkboxes);
      }
    }

  function updateGroupToggles()
    {
    for (let i = 0; i < groupToggles.length; i++)
      {
      syncToggle(groupToggles[i], groupCheckboxes(groupToggles[i].getAttribute("data-group")));
      }
    }

  function refresh()
    {
    updateGlobalToggle();
    updateGroupToggles();
    }

  if (toggleAll)
    {
    toggleAll.addEventListener("change", function () {
      for (let i = 0; i < checkboxes.length; i++)
        {
        checkboxes[i].checked = this.checked;
        }
      refresh();
    });
    }

  for (let i = 0; i < groupToggles.length; i++)
    {
    groupToggles[i].addEventListener("change", function () {
      const list = groupCheckboxes(this.getAttribute("data-group"));
      for (let j = 0; j < list.length; j++)
        {
        list[j].checked = this.checked;
        }
      refresh();
    });
    }

  for (let i = 0; i < checkboxes.length; i++)
    {
    checkboxes[i].addEventListener("change", refresh);
    }

  refresh();
})();
