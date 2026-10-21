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

document.querySelectorAll('[data-ticket-comment-check]').forEach((conversation) => {
	const notice = document.querySelector('[data-new-reply-notice]');
	const refreshButton = notice?.querySelector('[data-refresh-ticket]');

	if (!notice || !refreshButton) {
		return;
	}

	let latestCommentId = Number(conversation.dataset.lastCommentId);
	const updatesUrl = conversation.dataset.updatesUrl;

	if (!updatesUrl || !Number.isFinite(latestCommentId)) {
		console.error('Ticket conversation updates are missing required polling data.');
		return;
	}

	refreshButton.addEventListener('click', () => window.location.reload());

	const checkForNewReplies = async () => {
		if (document.hidden || notice.hidden) {
			return;
		}

		try {
			const response = await fetch(updatesUrl, {
				headers: { Accept: 'application/json' },
				credentials: 'same-origin',
			});

			if (!response.ok) {
				throw new Error(`Ticket update check failed with status ${response.status}.`);
			}

			const data = await response.json();
			const latestServerCommentId = Number(data.latest_comment_id);

			if (!Number.isFinite(latestServerCommentId)) {
				throw new Error('Ticket update check returned an invalid comment ID.');
			}

			if (latestServerCommentId > latestCommentId) {
				notice.hidden = false;
			}
		} catch (error) {
			console.error('Unable to check for new ticket replies.', error);
		}
	};

	window.setInterval(checkForNewReplies, 15000);
});
