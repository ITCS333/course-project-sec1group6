let resources = [];
let editMode = false;
let currentEditId = null;

const resourceForm = document.querySelector('#resource-form');
const resourcesTbody = document.querySelector('#resources-tbody');

function createResourceRow(resource) {
  const tr = document.createElement('tr');

  tr.innerHTML = `
    <td>${resource.title}</td>
    <td>${resource.description || ''}</td>
    <td><a href="${resource.link}" target="_blank">${resource.link}</a></td>
    <td>
      <button class="edit-btn" data-id="${resource.id}">Edit</button>
      <button class="delete-btn" data-id="${resource.id}">Delete</button>
    </td>
  `;

  return tr;
}

function renderTable() {
  resourcesTbody.innerHTML = '';

  resources.forEach((resource) => {
    const row = createResourceRow(resource);
    resourcesTbody.appendChild(row);
  });
}

async function handleAddResource(event) {
  event.preventDefault();

  const titleInput = document.querySelector('#resource-title');
  const descriptionInput = document.querySelector('#resource-description');
  const linkInput = document.querySelector('#resource-link');
  const submitButton = document.querySelector('#add-resource');

  const title = titleInput.value.trim();
  const description = descriptionInput.value.trim();
  const link = linkInput.value.trim();

  if (!title || !link) return;

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
      resources = resources.map((resource) =>
        Number(resource.id) === Number(currentEditId)
          ? { ...resource, title, description, link }
          : resource
      );

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
    body: JSON.stringify({ title, description, link })
  });

  const result = await response.json();

  if (result.success) {
    resources.push({
      id: result.id,
      title,
      description,
      link
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
      resources = resources.filter(
        (resource) => Number(resource.id) !== Number(id)
      );
      renderTable();
    }
  }

  if (target.classList.contains('edit-btn')) {
    const id = target.dataset.id;
    const resource = resources.find(
      (resource) => Number(resource.id) === Number(id)
    );

    if (!resource) return;

    document.querySelector('#resource-title').value = resource.title;
    document.querySelector('#resource-description').value = resource.description || '';
    document.querySelector('#resource-link').value = resource.link;
    document.querySelector('#add-resource').textContent = 'Update Resource';

    editMode = true;
    currentEditId = id;
  }
}

async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result = await response.json();

  if (result.success) {
    resources = result.data;
    renderTable();
  }

  resourceForm.addEventListener('submit', handleAddResource);
  resourcesTbody.addEventListener('click', handleTableClick);
}

loadAndInitialize();