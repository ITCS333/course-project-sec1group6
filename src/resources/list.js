const resourceListSection = document.querySelector('#resource-list-section');

function createResourceArticle(resource) {

  const article = document.createElement('article');

  article.innerHTML = `
    <h2>${resource.title}</h2>
    <p>${resource.description || ''}</p>
    <a href="details.html?id=${resource.id}">
      View Resource & Discussion
    </a>
  `;

  return article;
}

async function loadResources() {

  try {

    const response = await fetch('./api/index.php');
    const result = await response.json();

    resourceListSection.innerHTML = '';

    if (result.success && result.data) {

      result.data.forEach(resource => {

        const article = createResourceArticle(resource);

        resourceListSection.appendChild(article);

      });

    }

  } catch (error) {

    resourceListSection.innerHTML =
      '<p>Failed to load resources.</p>';

  }

}

loadResources();