document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
	const input = document.getElementById(toggle.getAttribute('aria-controls'));

	if (!input) {
		return;
	}

	toggle.addEventListener('click', () => {
		const isVisible = input.type === 'text';
		input.type = isVisible ? 'password' : 'text';
		toggle.textContent = isVisible ? 'Show' : 'Hide';
		toggle.setAttribute('aria-pressed', String(!isVisible));
		toggle.setAttribute('aria-label', `${isVisible ? 'Show' : 'Hide'} password`);
	});
});

document.querySelectorAll('[data-message-counter]').forEach((textarea) => {
	const counter = document.querySelector('[data-message-count]');

	if (!counter) {
		return;
	}

	const updateCount = () => {
		counter.textContent = String(textarea.value.length);
	};

	textarea.addEventListener('input', updateCount);
	updateCount();
});

document.querySelectorAll('.attachment-box input[type="file"]').forEach((input) => {
	const box = input.closest('.attachment-box');
	const name = box?.querySelector('.attachment-inner span:last-child');

	if (!box || !name) {
		return;
	}

	const showFileName = (file) => {
		name.textContent = file ? file.name : 'Choose a file or drop it here';
	};

	input.addEventListener('change', () => showFileName(input.files[0]));
	box.addEventListener('dragover', (event) => {
		event.preventDefault();
		box.classList.add('is-dragging');
	});
	box.addEventListener('dragleave', () => box.classList.remove('is-dragging'));
	box.addEventListener('drop', (event) => {
		event.preventDefault();
		box.classList.remove('is-dragging');

		if (event.dataTransfer.files.length > 0) {
			input.files = event.dataTransfer.files;
			showFileName(input.files[0]);
		}
	});
});
