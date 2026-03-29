
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