
// --- Copy Code ---
function copyCode(button, codeId) {
	const code = document.getElementById(codeId);
	if (!code) return;

	navigator.clipboard.writeText(code.textContent).then(() => {
		button.classList.add('copied');
		button.textContent = 'Copied!';
		setTimeout(() => {
			button.classList.remove('copied');
			button.textContent = 'Copy';
		}, 2000);
	});
}

// --- Scroll Reveal ---
const observer = new IntersectionObserver((entries) => {
	entries.forEach(e => {
		if (e.isIntersecting) {
			e.target.classList.add('visible');
			observer.unobserve(e.target);
		}
	});
}, { threshold: 0.1, rootMargin: '0px 0px -20px 0px' });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));