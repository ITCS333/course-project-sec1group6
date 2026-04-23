

/*
  Requirement: Make the "Manage Resources" page interactive.

  Instructions:
  1. Link this file to admin.html using:
     <script src="admin.js" defer></script>
  
  2. In admin.html, add id="resources-tbody" to the <tbody> element
     inside your resources-table. This id is required by this script.
  
  3. Implement the TODOs below.
*/

// --- Global Data Store ---
// This will hold the resources loaded from the API.
let resources = [];
let editId = null;

// --- Element Selections ---
// TODO: Select the resource form ('#resource-form').
const resourceForm = document.getElementById('resource-form');
// TODO: Select the resources table body ('#resources-tbody').
const resourceTable = document.getElementById('resources-tbody');

// --- Functions ---

/**
 * TODO: Implement the createResourceRow function.
 * It takes one resource object { id, title, description, link }.
 * It should return a <tr> element with the following <td>s:
 * 1. A <td> for the title.
 * 2. A <td> for the description.
 * 3. A <td> for the link.
 * 4. A <td> containing two buttons:
 *    - An "Edit" button with class="edit-btn" and data-id="${id}".
 *    - A "Delete" button with class="delete-btn" and data-id="${id}".
 */
function createResourceRow(resource) {
  // ... your implementation here ...
const tr = document.createElement('tr');

    const tdTitle = document.createElement('td');
    tdTitle.textContent = resource.title;
    tr.appendChild(tdTitle);

    const tdDesc = document.createElement('td');
    tdDesc.textContent = resource.description;
    tr.appendChild(tdDesc);

    const tdLink = document.createElement('td');
    tdLink.textContent = resource.link;
    tr.appendChild(tdLink);

    const tdActions = document.createElement('td');

    const editBtn = document.createElement('button');
    editBtn.textContent = 'Edit';
    editBtn.className = 'edit-btn';
    editBtn.setAttribute('data-id', resource.id); 

    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = 'Delete';
    deleteBtn.className = 'delete-btn';
    deleteBtn.setAttribute('data-id', resource.id);

    tdActions.appendChild(editBtn);
    tdActions.appendChild(deleteBtn);
    tr.appendChild(tdActions);

    return tr;
}
/**
 * TODO: Implement the renderTable function.
 * It should:
 * 1. Clear the resources table body ('#resources-tbody').
 * 2. Loop through the global resources array.
 * 3. For each resource, call createResourceRow() and
 *    append the returned <tr> to the table body.
 */
function renderTable(data = resources) {
    const tableBody = document.getElementById('resources-tbody');
    if (!tableBody) return;

    tableBody.innerHTML = '';

    data.forEach(resource => {
        const row = createResourceRow(resource);
        tableBody.appendChild(row);
    });
}
/**
 * TODO: Implement the handleAddResource function.
 * This is the event handler for the form's 'submit' event.
 * It should:
 * 1. Prevent the form's default submission.
 * 2. Get the values from the title (id="resource-title"),
 *    description (id="resource-description"), and
 *    link (id="resource-link") inputs.
 * 3. Use fetch() to POST the new resource to the API:
 *    - URL: './api/index.php'
 *    - Method: POST
 *    - Headers: { 'Content-Type': 'application/json' }
 *    - Body: JSON.stringify({ title, description, link })
 * 4. The API returns { success: true, id: <new id> }.
 *    Add the new resource object (including the id returned by the API)
 *    to the global resources array.
 * 5. Call renderTable() to refresh the list.
 * 6. Reset the form.
 */
async function handleAddResource(event) {
  // ... your implementation here ...
event.preventDefault(); 
    
    const title = document.getElementById('resource-title').value;
    const description = document.getElementById('resource-description').value;
    const link = document.getElementById('resource-link').value;
    const submitBtn = document.getElementById('add-resource');

    const method = editId ? 'PUT' : 'POST';
    const bodyData = editId ? { id: editId, title, description, link } : { title, description, link };

    try {
        const response = await fetch('./api/index.php', {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(bodyData) 
        });
        const result = await response.json();

        if (result.success) {
            if (editId) {
                const index = resources.findIndex(r => r.id == editId);
                resources[index] = { ...resources[index], title, description, link };
                editId = null;
                submitBtn.textContent = "Add Resource";
            } else {
                resources.push({ id: result.id, title, description, link });
            }
            renderTable();
            resourceForm.reset();
        }
    } catch (error) {
        console.error("Error saving resource:", error);
    }
}

/**
 * TODO: Implement the handleTableClick function.
 * This handles click events on the table body using event delegation.
 * It should:
 *
 * If the clicked element has class "delete-btn":
 * 1. Get the resource id from the button's data-id attribute.
 * 2. Use fetch() to DELETE the resource via the API:
 *    - URL: ./api/index.php?id=${id}
 *    - Method: DELETE
 * 3. On success, remove the resource from the global resources array
 *    by filtering out the entry with the matching id.
 * 4. Call renderTable() to refresh the list.
 *
 * If the clicked element has class "edit-btn":
 * 1. Get the resource id from the button's data-id attribute.
 * 2. Find the matching resource in the global resources array.
 * 3. Populate the form fields (id="resource-title", id="resource-description",
 *    id="resource-link") with the resource's current values so the admin
 *    can edit them.
 * 4. Change the submit button (id="add-resource") text to "Update Resource"
 *    to indicate edit mode.
 * 5. On form submit, use fetch() to PUT the updated resource to the API:
 *    - URL: './api/index.php'
 *    - Method: PUT
 *    - Headers: { 'Content-Type': 'application/json' }
 *    - Body: JSON.stringify({ id, title, description, link })
 * 6. On success, update the matching resource in the global resources array.
 * 7. Call renderTable() and reset the form back to "Add" mode,
 *    restoring the submit button text to "Add Resource".
 */
async function handleTableClick(event) {
const id = event.target.dataset.id;
    if (!id) return;

    if (event.target.classList.contains('delete-btn')) {
        const response = await fetch(`./api/index.php?id=${id}`, { method: 'DELETE' });
        const result = await response.json();
        if (result.success) {
            resources = resources.filter(r => r.id != id);
            renderTable();
        }
    }

    if (event.target.classList.contains('edit-btn')) {
        const resource = resources.find(r => r.id == id);
        document.getElementById('resource-title').value = resource.title;
        document.getElementById('resource-description').value = resource.description;
        document.getElementById('resource-link').value = resource.link;
        
        editId = id;
        document.getElementById('add-resource').textContent = "Update Resource";
    }
}
      
/**
 * TODO: Implement the loadAndInitialize function.
 * This function must be 'async'.
 * It should:
 * 1. Use fetch() to GET all resources from the API:
 *    - URL: './api/index.php'
 *    - The API returns { success: true, data: [...] }
 * 2. Store the resources array (from data) in the global resources variable.
 * 3. Call renderTable() to populate the table for the first time.
 * 4. Add the 'submit' event listener to the resource form (id="resource-form"),
 *    calling handleAddResource.
 * 5. Add the 'click' event listener to the table body (id="resources-tbody"),
 *    calling handleTableClick.
 */
async function loadAndInitialize() {
  // ... your implementation here ...
try {
        const response = await fetch('./api/index.php');
        const result = await response.json();
        resources = result.data || [];
        
        renderTable();

        resourceForm.addEventListener('submit', handleAddResource);
        resourceTable.addEventListener('click', handleTableClick);
    } catch (error) {
        console.error("Failed to load:", error);
    }
}
 
// --- Initial Page Load ---
loadAndInitialize()