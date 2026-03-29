/**
 * Site Editor — hide Templates, Template Parts, Patterns, and Pages
 * from the sidebar navigation.
 *
 * There's no PHP filter for site editor navigation panels (Gutenberg #49640),
 * so a MutationObserver is the only available approach. The observer runs
 * permanently because the site editor dynamically re-renders panels.
 */
wp.domReady(() => {
	const hideSidebarItems = () => {
		document.querySelectorAll('.edit-site-sidebar-navigation-item').forEach((item) => {
			const text = item.textContent?.trim().toLowerCase();
			if (text === 'templates' || text === 'template parts') {
				item.style.display = 'none';
			}
			const id = item.getAttribute('id');
			if (id === 'patterns-navigation-item' || id === 'page-navigation-item') {
				item.style.display = 'none';
			}
		});
	};

	const observeSidebar = () => {
		const sidebar = document.querySelector('.edit-site-sidebar__screen-wrapper');
		if (!sidebar) {
			// Try again shortly if sidebar not yet present
			setTimeout(observeSidebar, 250);
			return;
		}
		const observer = new MutationObserver(hideSidebarItems);
		observer.observe(sidebar, { childList: true, subtree: true });
		// Initial run
		hideSidebarItems();
	};

	observeSidebar();
});
