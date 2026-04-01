/**
 * Connections store — GitHub and Troy credential status.
 */
import { atom } from 'nanostores';

export const $connections = atom({
	hasGithubApp: false,
	hasGithubPat: false,
	hasTroyUrl: false,
	hasTroyCreds: false,
});

export function initConnections(data) {
	$connections.set(data.connections || {});
}

export function updateConnections(updates) {
	$connections.set({ ...$connections.get(), ...updates });
}
