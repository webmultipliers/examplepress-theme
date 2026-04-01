/**
 * Health checks store.
 */
import { atom } from 'nanostores';

export const $healthChecks = atom({
	env: [],
	theme: [],
	router: [],
	security: [],
});

export function initHealth(data) {
	$healthChecks.set(data.healthChecks || {});
}
