/*
  Requirement: Make the "Manage Resources" page interactive.
*/

var resources = [];
var editMode = false;
var currentEditId = null;

function createResourceRow(resource) {
  const tr = document.createElement('tr');

  const titleTd = document.createElement('td');
  titleTd.textContent = resource.title;

  const descriptionTd = document.createElement('td');
  descriptionTd.textContent = resource.description || '';

  const linkTd = document.createElement('td');
  const anchor = document.createElement('a');
  anchor.href = resource.link;
  anchor.target = '_blank';
  anchor.textContent = resource.link;
  linkTd.appendChild(anchor);

  const actionsTd = document.createElement('td');

  const editButton = document.createElement('button');
  editButton.className = 'edit-btn';
  editButton.dataset.id = resource.id;
  editButton.textContent = 'Edit';

  const deleteButton = document.createElement('button');
  deleteButton.className = 'delete-btn';
  deleteButton.dataset.id = resource.id;
  deleteButton.textContent = 'Delete';

  actionsTd.appendChild(editButton);
  actionsTd.appendChild(deleteButton);

  tr.appendChild(titleTd);
  tr.appendChild(descriptionTd);
  tr.appendChild(linkTd);
  tr.appendChild(actionsTd);

  return tr;
}

function renderTable() {
  const tbody = document.querySelector('#resources-tbody');

  if (!tbody) {
    return;
  }

  while (tbody.firstChild) {
    tbody.removeChild(tbody.firstChild);
  }

  for (let i = 0; i < resources.length; i += 1) {
    tbody.appendChild(createResourceRow(resources[i]));
  }
}

async function handleAddResource(event) {
  event.preventDefault();

  const resourceForm = document.querySelector('#resource-form');
  const titleInput = document.querySelector('#resource-title');
  const descriptionInput = document.querySelector('#resource-description');
  const linkInput = document.querySelector('#resource-link');
  const submitButton = document.querySelector('#add-resource');

  const title = titleInput.value.trim();
  const description = descriptionInput.value.trim();
  const link = linkInput.value.trim();

  if (!title || !link) {
    return;
  }

  if (editMode && currentEditId !== null) {
    const response = await fetch('./api/index.php', {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        id: currentEditId,
        title,
        description,
        link
      })
    });

    const result = await response.json();

    if (result.success) {
      resources = resources.map(function (resource) {
        if (String(resource.id) === String(currentEditId)) {
          return {
            id: currentEditId,
            title: title,
            description: description,
            link: link
          };
        }
        return resource;
      });

      renderTable();
      resourceForm.reset();
      editMode = false;
      currentEditId = null;
      submitButton.textContent = 'Add Resource';
    }

    return;
  }

  const response = await fetch('./api/index.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      title: title,
      description: description,
      link: link
    })
  });

  const result = await response.json();

  if (result.success) {
    resources.push({
      id: result.id,
      title: title,
      description: description,
      link: link
    });

    renderTable();
    resourceForm.reset();
  }
}

async function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains('delete-btn')) {
    const id = target.dataset.id;

    const response = await fetch(`./api/index.php?id=${id}`, {
      method: 'DELETE'
    });

    const result = await response.json();

    if (result.success) {
      resources = resources.filter(function (resource) {
        return String(resource.id) !== String(id);
      });
      renderTable();
    }

    return;
  }

  if (target.classList.contains('edit-btn')) {
    const id = target.dataset.id;

    const resource = resources.find(function (item) {
      return String(item.id) === String(id);
    });

    if (!resource) {
      return;
    }

    document.querySelector('#resource-title').value = resource.title;
    document.querySelector('#resource-description').value = resource.description || '';
    document.querySelector('#resource-link').value = resource.link;
    document.querySelector('#add-resource').textContent = 'Update Resource';

    editMode = true;
    currentEditId = id;
  }
}

async function loadAndInitialize() {
  const resourceForm = document.querySelector('#resource-form');
  const tbody = document.querySelector('#resources-tbody');

  const response = await fetch('./api/index.php');
  const result = await response.json();

  if (result.success && Array.isArray(result.data)) {
    resources = result.data;
  } else {
    resources = [];
  }

  renderTable();

  if (resourceForm) {
    resourceForm.addEventListener('submit', handleAddResource);
  }

  if (tbody) {
    tbody.addEventListener('click', handleTableClick);
  }
}

loadAndInitialize();