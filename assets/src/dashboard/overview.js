/**
 * Dashboard — Overview tab: welcome cards and navigation cards.
 *
 * Navigation cards become <a> links that point to the correct subpage
 * when the admin is split. For now they still use data-tab-target for
 * tabs on the same page.
 */
import { activateTab } from '../lib/tabs.js';

export function renderOverview() {
	document.querySelectorAll('.ep-overview-card[data-tab-target]').forEach(card => {
		card.setAttribute('role', 'link');
		card.addEventListener('click', () => activateTab(card.dataset.tabTarget));
	});
}
