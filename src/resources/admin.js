let resources = [];

const resourceForm = document.getElementById("resource-form");
const resourcesTbody = document.getElementById("resources-tbody");

function createResourceRow(resource) {
  const tr = document.createElement("tr");

  const titleTd = document.createElement("td");
  titleTd.textContent = resource.title;

  const descriptionTd = document.createElement("td");
  descriptionTd.textContent = resource.description;

  const linkTd = document.createElement("td");
  linkTd.textContent = resource.link;

  const actionsTd = document.createElement("td");

  const editBtn = document.createElement("button");
  editBtn.className = "edit-btn";
  editBtn.dataset.id = resource.id;
  editBtn.textContent = "Edit";

  const deleteBtn = document.createElement("button");
  deleteBtn.className = "delete-btn";
  deleteBtn.dataset.id = resource.id;
  deleteBtn.textContent = "Delete";

  actionsTd.appendChild(editBtn);
  actionsTd.appendChild(deleteBtn);

  tr.appendChild(titleTd);
  tr.appendChild(descriptionTd);
  tr.appendChild(linkTd);
  tr.appendChild(actionsTd);

  return tr;
}

function renderTable() {
  resourcesTbody.innerHTML = "";

  resources.forEach((resource) => {
    resourcesTbody.appendChild(createResourceRow(resource));
  });
}

async function handleAddResource(event) {
  event.preventDefault();

  const title = document.getElementById("resource-title").value;
  const description = document.getElementById("resource-description").value;
  const link = document.getElementById("resource-link").value;

  const addButton = document.getElementById("add-resource");
  const editId = addButton.dataset.editId;

  if (editId) {
    const response = await fetch("./api/index.php", {
      method: "PUT",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({
        id: editId,
        title,
        description,
        link
      })
    });

    const result = await response.json();

    if (result.success) {
      resources = resources.map((resource) =>
        resource.id == editId
          ? { ...resource, title, description, link }
          : resource
      );

      renderTable();
      resourceForm.reset();
      addButton.textContent = "Add Resource";
      delete addButton.dataset.editId;
    }

    return;
  }

  const response = await fetch("./api/index.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
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

  if (target.classList.contains("delete-btn")) {
    const id = target.dataset.id;

    const response = await fetch(/api/index.php?id=${id}, {
  method: "DELETE"
})

    const result = await response.json();

    if (result.success) {
      resources = resources.filter((resource) => resource.id != id);
      renderTable();
    }
  }

  if (target.classList.contains("edit-btn")) {
    const id = target.dataset.id;
    const resource = resources.find((item) => item.id == id);

    if (!resource) return;

    document.getElementById("resource-title").value = resource.title;
    document.getElementById("resource-description").value = resource.description;
    document.getElementById("resource-link").value = resource.link;

    const addButton = document.getElementById("add-resource");
    addButton.textContent = "Update Resource";
    addButton.dataset.editId = id;
  }
}

async function loadAndInitialize() {
  const response = await fetch("./api/index.php");
  const result = await response.json();

  resources = result.data || [];
  renderTable();

  resourceForm.addEventListener("submit", handleAddResource);
  resourcesTbody.addEventListener("click", handleTableClick);
}

loadAndInitialize();
