let currentResourceId = null;
let currentComments = [];

const resourceTitle = document.querySelector('#resource-title');
const resourceDescription = document.querySelector('#resource-description');
const resourceLink = document.querySelector('#resource-link');
const commentList = document.querySelector('#comment-list');
const commentForm = document.querySelector('#comment-form');
const newComment = document.querySelector('#new-comment');

function getResourceIdFromURL() {
  const queryString = window.location.search;
  const params = new URLSearchParams(queryString);
  return params.get('id');
}

function renderResourceDetails(resource) {
  resourceTitle.textContent = resource.title;
  resourceDescription.textContent = resource.description || '';
  resourceLink.href = resource.link;
}

function createCommentArticle(comment) {
  const article = document.createElement('article');

  const p = document.createElement('p');
  p.textContent = comment.text;

  const footer = document.createElement('footer');
  footer.textContent = `Posted by: ${comment.author}`;

  article.appendChild(p);
  article.appendChild(footer);

  return article;
}

function renderComments() {
  commentList.innerHTML = '';

  currentComments.forEach(comment => {
    const article = createCommentArticle(comment);
    commentList.appendChild(article);
  });
}

async function handleAddComment(event) {
  event.preventDefault();

  const commentText = newComment.value.trim();

  if (!commentText) return;

  const response = await fetch('./api/index.php?action=comment', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      resource_id: currentResourceId,
      author: 'Student',
      text: commentText
    })
  });

  const result = await response.json();

  if (result.success) {
    currentComments.push({
      id: result.id,
      resource_id: currentResourceId,
      author: 'Student',
      text: commentText,
      created_at: new Date().toISOString()
    });

    renderComments();
    newComment.value = '';
  }
}

async function initializePage() {
  currentResourceId = getResourceIdFromURL();

  if (!currentResourceId) {
    resourceTitle.textContent = 'Resource not found.';
    return;
  }

  try {
    const [resourceResponse, commentsResponse] = await Promise.all([
      fetch(`./api/index.php?id=${currentResourceId}`),
      fetch(`./api/index.php?resource_id=${currentResourceId}&action=comments`)
    ]);

    const resourceResult = await resourceResponse.json();
    const commentsResult = await commentsResponse.json();

    currentComments = commentsResult.success ? commentsResult.data : [];

    if (resourceResult.success && resourceResult.data) {
      renderResourceDetails(resourceResult.data);
      renderComments();
      commentForm.addEventListener('submit', handleAddComment);
    } else {
      resourceTitle.textContent = 'Resource not found.';
    }
  } catch (error) {
        resourceTitle.textContent = 'Resource not found.';

  }
}

initializePage();