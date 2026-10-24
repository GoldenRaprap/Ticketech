document.querySelectorAll('[data-ticket-row]').forEach((row) => {
	const ticketUrl = row.dataset.ticketUrl;

	if (!ticketUrl) {
		console.error('Ticket row is missing its destination URL.');
		return;
	}

	row.addEventListener('click', (event) => {
		if (event.target instanceof Element && event.target.closest('a, button, input, select, textarea, label')) {
			return;
		}

		window.location.assign(ticketUrl);
	});

	row.addEventListener('keydown', (event) => {
		if (event.target !== row || (event.key !== 'Enter' && event.key !== ' ')) {
			return;
		}

		event.preventDefault();
		window.location.assign(ticketUrl);
	});
});

document.querySelectorAll('[data-notification-row]').forEach((row) => {
	const form = row.querySelector('[data-notification-action]');

	if (!(form instanceof HTMLFormElement)) {
		console.error('Clickable notification is missing its read action form.');
		return;
	}

	const openNotification = () => form.requestSubmit();

	row.addEventListener('click', openNotification);
	row.addEventListener('keydown', (event) => {
		if (event.key !== 'Enter' && event.key !== ' ') {
			return;
		}

		event.preventDefault();
		openNotification();
	});
});

const attachmentPreviewDialog = document.querySelector('[data-attachment-preview-dialog]');

if (attachmentPreviewDialog instanceof HTMLDialogElement) {
	const previewImage = attachmentPreviewDialog.querySelector('[data-attachment-preview-image]');
	const previewViewport = attachmentPreviewDialog.querySelector('.attachment-preview-viewport');
	const zoomInButton = attachmentPreviewDialog.querySelector('[data-preview-zoom-in]');
	const zoomOutButton = attachmentPreviewDialog.querySelector('[data-preview-zoom-out]');
	const zoomResetButton = attachmentPreviewDialog.querySelector('[data-preview-zoom-reset]');
	const closePreviewButton = attachmentPreviewDialog.querySelector('[data-preview-close]');
	let imageScale = 1;

	if (
		previewImage instanceof HTMLImageElement
		&& previewViewport instanceof HTMLElement
		&& zoomInButton instanceof HTMLButtonElement
		&& zoomOutButton instanceof HTMLButtonElement
		&& zoomResetButton instanceof HTMLButtonElement
		&& closePreviewButton instanceof HTMLButtonElement
	) {
		const updateImageScale = () => {
			previewImage.style.transform = `scale(${imageScale})`;
			previewViewport.classList.toggle('is-zoomed', imageScale > 1);
		};

		document.querySelectorAll('[data-image-preview]').forEach((button) => {
			button.addEventListener('click', () => {
				const imageUrl = button.dataset.previewSrc;

				if (!imageUrl) {
					console.error('Image attachment preview is missing its URL.');
					return;
				}

				previewImage.src = imageUrl;
				previewImage.alt = button.dataset.previewAlt || 'Image attachment';
				imageScale = 1;
				updateImageScale();
				attachmentPreviewDialog.showModal();
			});
		});

		zoomInButton.addEventListener('click', () => {
			imageScale = Math.min(imageScale + 0.5, 4);
			updateImageScale();
		});

		zoomOutButton.addEventListener('click', () => {
			imageScale = Math.max(imageScale - 0.5, 1);
			updateImageScale();
		});

		zoomResetButton.addEventListener('click', () => {
			imageScale = 1;
			updateImageScale();
		});

		closePreviewButton.addEventListener('click', () => attachmentPreviewDialog.close());
		attachmentPreviewDialog.addEventListener('click', (event) => {
			if (event.target === attachmentPreviewDialog) {
				attachmentPreviewDialog.close();
			}
		});
		attachmentPreviewDialog.addEventListener('close', () => {
			previewImage.removeAttribute('src');
			previewImage.alt = '';
		});
	}
}

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
	const attachmentLabel = input.closest('.attachment-label');
	const list = attachmentLabel?.querySelector('[data-attachment-list]');
	const count = attachmentLabel?.querySelector('[data-attachment-count] span');
	const limitMessage = attachmentLabel?.querySelector('[data-attachment-limit-message]');

	if (!box || !list || !count || !limitMessage) {
		return;
	}

	const allowedExtensions = new Set(['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);
	const maxFiles = 5;
	const maxFileSize = 5 * 1024 * 1024;
	let queuedFiles = Array.from(input.files);
	const getFileFeedback = (file) => {
		const extension = file.name.split('.').pop()?.toLowerCase();

		if (!extension || !allowedExtensions.has(extension)) {
			return { valid: false, message: 'Unsupported file type. Choose a PDF, JPG, PNG, DOC, or DOCX file.' };
		}

		if (file.size > maxFileSize) {
			return { valid: false, message: 'This file is larger than the 5 MB limit.' };
		}

		return { valid: true, message: `Ready to upload · ${formatFileSize(file.size)}` };
	};

	const formatFileSize = (size) => {
		if (size < 1024) {
			return `${size} bytes`;
		}

		return `${(size / 1024 / 1024 >= 1 ? size / 1024 / 1024 : size / 1024).toFixed(1)} ${size / 1024 / 1024 >= 1 ? 'MB' : 'KB'}`;
	};

	const renderFiles = () => {
		const files = Array.from(input.files);
		list.replaceChildren();
		count.textContent = String(files.length);

		files.forEach((file, index) => {
			const feedback = getFileFeedback(file);
			const item = document.createElement('li');
			const details = document.createElement('div');
			const fileName = document.createElement('span');
			const status = document.createElement('small');
			const removeButton = document.createElement('button');

			item.className = `attachment-file${feedback.valid ? '' : ' is-invalid'}`;
			details.className = 'attachment-file-details';
			fileName.className = 'attachment-file-name';
			fileName.textContent = file.name;
			status.className = 'attachment-file-status';
			status.textContent = feedback.message;
			removeButton.className = 'attachment-file-remove';
			removeButton.type = 'button';
			removeButton.textContent = 'Remove';
			removeButton.setAttribute('aria-label', `Remove ${file.name}`);
			removeButton.addEventListener('click', () => {
				const remainingFiles = queuedFiles.filter((_, fileIndex) => fileIndex !== index);
				setFiles(remainingFiles);
			});

			details.append(fileName, status);
			item.append(details, removeButton);
			list.append(item);
		});

		input.setAttribute('aria-invalid', String(files.some((file) => !getFileFeedback(file).valid)));
	};

	const setFiles = (selectedFiles, append = false) => {
		const incomingFiles = Array.from(selectedFiles);
		const files = append ? [...queuedFiles, ...incomingFiles] : incomingFiles;
		const transfer = new DataTransfer();
		const hasExceededLimit = files.length > maxFiles;

		files.slice(0, maxFiles).forEach((file) => transfer.items.add(file));
		queuedFiles = files.slice(0, maxFiles);
		input.files = transfer.files;
		limitMessage.hidden = !hasExceededLimit;
		limitMessage.textContent = hasExceededLimit
			? 'Only 5 files can be attached. Files beyond the 5-file limit were not added.'
			: '';
		renderFiles();
	};

	input.addEventListener('change', () => setFiles(input.files, true));
	box.addEventListener('dragover', (event) => {
		event.preventDefault();
		box.classList.add('is-dragging');
	});
	box.addEventListener('dragleave', () => box.classList.remove('is-dragging'));
	box.addEventListener('drop', (event) => {
		event.preventDefault();
		box.classList.remove('is-dragging');

		if (event.dataTransfer.files.length > 0) {
			setFiles(event.dataTransfer.files, true);
		}
	});

	input.closest('form')?.addEventListener('submit', (event) => {
		if (Array.from(input.files).some((file) => !getFileFeedback(file).valid)) {
			event.preventDefault();
			input.focus();
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
