/*
  Requirement: Populate the weekly detail page and handle the discussion forum.

  Instructions:
  1. This file is already linked to `details.html` via:
         <script src="details.js" defer></script>

  2. The following ids must exist in details.html (already listed in the
     HTML comments):
       #week-title          — <h1>
       #week-start-date     — <p>
       #week-description    — <p>
       #week-links-list     — <ul>
       #comment-list        — <div>
       #comment-form        — <form>
       #new-comment         — <textarea>

  3. Implement the TODOs below.

  API base URL: ./api/index.php
  Week object shape returned by the API:
    {
      id:          number,   // integer primary key from the weeks table
      title:       string,
      start_date:  string,   // "YYYY-MM-DD"
      description: string,
      links:       string[]  // decoded array of URL strings
    }

  Comment object shape returned by the API
  (from the comments_week table):
    {
      id:          number,
      week_id:     number,
      author:      string,
      text:        string,
      created_at:  string
    }
*/

// --- Global Data Store ---
let currentWeekId  = null;  // integer id from the weeks table
let currentComments = [];

// --- Element Selections ---
const weekTitle = document.getElementById('week-title');
const weekStartDate = document.getElementById('week-start-date');
const weekDescription = document.getElementById('week-description');
const weekLinksList = document.getElementById('week-links-list');
const commentList = document.getElementById('comment-list');
const commentForm = document.getElementById('comment-form');
const newCommentInput = document.getElementById('new-comment');


function getWeekIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}


function renderWeekDetails(week) {
  weekTitle.textContent = week.title;
  weekStartDate.textContent = "Starts on: " + week.start_date;
  weekDescription.textContent = week.description;

  weekLinksList.innerHTML = '';

  (week.links || []).forEach(url => {
    const li = document.createElement('li');
    const a = document.createElement('a');

    a.href = url;
    a.textContent = url;
    a.target = "_blank";

    li.appendChild(a);
    weekLinksList.appendChild(li);
  });
}


function createCommentArticle(comment) {
  const article = document.createElement('article');

  article.innerHTML = `
    <p>${comment.text}</p>
    <footer>Posted by: ${comment.author}</footer>
  `;

  return article;
}


function renderComments() {
  commentList.innerHTML = '';

  currentComments.forEach(comment => {
    const el = createCommentArticle(comment);
    commentList.appendChild(el);
  });
}


async function handleAddComment(event) {
  event.preventDefault();

  const commentText = newCommentInput.value.trim();

  if (!commentText) return;

  const response = await fetch('./api/index.php?action=comment', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      week_id: Number(currentWeekId),
      author: "Student",
      text: commentText
    })
  });

  const result = await response.json();

  if (result.success) {
    currentComments.push(result.data);
    renderComments();
    newCommentInput.value = '';
  }
}

async function initializePage() {
  currentWeekId = getWeekIdFromURL();

  if (!currentWeekId) {
    weekTitle.textContent = "Week not found.";
    return;
  }

  const [weekRes, commentsRes] = await Promise.all([
    fetch(`./api/index.php?id=${currentWeekId}`),
    fetch(`./api/index.php?action=comments&week_id=${currentWeekId}`)
  ]);

  const weekData = await weekRes.json();
  const commentsData = await commentsRes.json();

  if (commentsData.success) {
    currentComments = commentsData.data || [];
  }

  if (weekData.success && weekData.data) {
    renderWeekDetails(weekData.data);
    renderComments();

    commentForm.addEventListener('submit', handleAddComment);
  } else {
    weekTitle.textContent = "Week not found.";
  }
}

// --- Initial Page Load ---
initializePage();
