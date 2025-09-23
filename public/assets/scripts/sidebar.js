const layout = document.querySelector('.layout');
const btn = document.getElementById('sidebarToggle');
const btnText = btn.querySelector('span');

btn.addEventListener('click', () => {
  const hidden = layout.classList.toggle('sidebar-hidden');

  if (hidden) {
    btn.innerHTML = "☰ <span>Show Sidebar</span>";
  } else {
    btn.innerHTML = "✖ <span>Hide Sidebar</span>";
  }
});
