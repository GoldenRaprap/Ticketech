document.querySelectorAll('[data-staff-bulk-form]').forEach((form) => {
	const entryList = form.querySelector('[data-staff-entry-list]');
	const entryTemplate = form.querySelector('template[data-staff-entry-template]');
	const addEntryButton = form.querySelector('[data-add-staff-entry]');
	const feedback = form.querySelector('[data-staff-entry-feedback]');
	const maxEntries = Number(form.dataset.maxEntries);

	if (
		!(entryList instanceof HTMLElement) ||
		!(entryTemplate instanceof HTMLTemplateElement) ||
		!(addEntryButton instanceof HTMLButtonElement) ||
		!(feedback instanceof HTMLElement) ||
		!Number.isInteger(maxEntries) ||
		maxEntries < 1
	) {
		throw new Error('Staff account form is missing required controls.');
	}

	let nextIndex = 0;

	entryList.querySelectorAll('input[name^="staff["]').forEach((input) => {
		const match = input.name.match(/^staff\[(\d+)\]\[name\]$/);

		if (match) {
			nextIndex = Math.max(nextIndex, Number(match[1]) + 1);
		}
	});

	const updateEntryControls = () => {
		const entries = entryList.querySelectorAll('[data-staff-entry]');

		entries.forEach((entry, index) => {
			const legend = entry.querySelector('[data-entry-legend]');
			const removeButton = entry.querySelector('[data-remove-staff-entry]');

			if (!(legend instanceof HTMLElement) || !(removeButton instanceof HTMLButtonElement)) {
				throw new Error('Staff account row is missing its label or remove button.');
			}

			legend.textContent = `Staff member ${index + 1}`;
			removeButton.disabled = entries.length === 1;
			removeButton.setAttribute('aria-label', `Remove staff member ${index + 1}`);
		});

		addEntryButton.disabled = entries.length >= maxEntries;
		feedback.textContent = `${entries.length} of ${maxEntries} staff members`;
	};

	addEntryButton.addEventListener('click', () => {
		if (entryList.querySelectorAll('[data-staff-entry]').length >= maxEntries) {
			updateEntryControls();
			return;
		}

		const fragment = entryTemplate.content.cloneNode(true);
		const entry = fragment.firstElementChild;

		if (!(entry instanceof HTMLFieldSetElement)) {
			throw new Error('Staff account row template is invalid.');
		}

		const nameInput = entry.querySelector('[data-staff-field="name"]');
		const emailInput = entry.querySelector('[data-staff-field="email"]');

		if (!(nameInput instanceof HTMLInputElement) || !(emailInput instanceof HTMLInputElement)) {
			throw new Error('Staff account row template is missing its inputs.');
		}

		nameInput.name = `staff[${nextIndex}][name]`;
		nameInput.id = `new-staff-${nextIndex}-name`;
		emailInput.name = `staff[${nextIndex}][email]`;
		emailInput.id = `new-staff-${nextIndex}-email`;
		nextIndex += 1;
		entryList.append(entry);
		updateEntryControls();
		nameInput.focus();
	});

	entryList.addEventListener('click', (event) => {
		if (!(event.target instanceof Element)) {
			return;
		}

		const removeButton = event.target.closest('[data-remove-staff-entry]');

		if (!(removeButton instanceof HTMLButtonElement)) {
			return;
		}

		const entries = entryList.querySelectorAll('[data-staff-entry]');
		const entry = removeButton.closest('[data-staff-entry]');

		if (!(entry instanceof HTMLFieldSetElement)) {
			throw new Error('Staff account remove button is not attached to a row.');
		}

		if (entries.length === 1) {
			entry.querySelectorAll('input').forEach((input) => {
				input.value = '';
			});
		} else {
			entry.remove();
		}

		updateEntryControls();
	});

	updateEntryControls();
});

document.querySelectorAll('[data-priority-keyword-editor]').forEach((editor) => {
	const keywordValue = editor.querySelector('[data-keyword-value]');
	const keywordList = editor.querySelector('[data-keyword-list]');
	const keywordInput = editor.querySelector('[data-keyword-input]');
	const addKeywordButton = editor.querySelector('[data-add-keyword]');
	const keywordCount = editor.querySelector('[data-keyword-count]');
	const feedback = editor.querySelector('[data-keyword-feedback]');

	if (
		!(keywordValue instanceof HTMLInputElement) ||
		!(keywordList instanceof HTMLUListElement) ||
		!(keywordInput instanceof HTMLInputElement) ||
		!(addKeywordButton instanceof HTMLButtonElement) ||
		!(keywordCount instanceof HTMLElement) ||
		!(feedback instanceof HTMLElement)
	) {
		throw new Error('Priority keyword editor is missing required controls.');
	}

	const keywords = keywordValue.value
		.split(/[\r\n,]+/)
		.map((keyword) => keyword.trim())
		.filter(Boolean);

	const updateKeywordValue = () => {
		keywordValue.value = keywords.join(', ');
		keywordCount.textContent = `${keywords.length} ${keywords.length === 1 ? 'keyword' : 'keywords'}`;
		keywordList.replaceChildren();

		keywords.forEach((keyword, index) => {
			const item = document.createElement('li');
			item.className = 'admin-priority-keyword-chip';

			const text = document.createElement('span');
			text.textContent = keyword;

			const removeButton = document.createElement('button');
			removeButton.type = 'button';
			removeButton.className = 'admin-priority-keyword-remove';
			removeButton.setAttribute('aria-label', `Remove keyword ${keyword}`);
			removeButton.textContent = '×';
			removeButton.addEventListener('click', () => {
				keywords.splice(index, 1);
				feedback.textContent = '';
				updateKeywordValue();
			});

			item.append(text, removeButton);
			keywordList.append(item);
		});
	};

	const addKeyword = () => {
		const keyword = keywordInput.value.trim();

		if (!keyword) {
			feedback.textContent = 'Enter a keyword or phrase first.';
			keywordInput.focus();
			return;
		}

		if (keywords.some((existingKeyword) => existingKeyword.toLowerCase() === keyword.toLowerCase())) {
			feedback.textContent = 'That keyword is already in this list.';
			keywordInput.focus();
			return;
		}

		if ([...keywords, keyword].join(', ').length > 2000) {
			feedback.textContent = 'Keywords must stay within 2,000 characters.';
			keywordInput.focus();
			return;
		}

		keywords.push(keyword);
		keywordInput.value = '';
		feedback.textContent = '';
		updateKeywordValue();
		keywordInput.focus();
	};

	addKeywordButton.addEventListener('click', addKeyword);
	keywordInput.addEventListener('keydown', (event) => {
		if (event.key === 'Enter') {
			event.preventDefault();
			addKeyword();
		}
	});

	updateKeywordValue();
});

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

document.querySelectorAll('.admin-ticket-submission-switch').forEach((toggle) => {
	toggle.addEventListener('click', (event) => event.stopPropagation());
});

const createConfirmationDialog = () => {
	const dialog = document.createElement('dialog');
	dialog.className = 'app-confirmation-dialog';
	dialog.setAttribute('aria-labelledby', 'app-confirmation-title');
	dialog.setAttribute('aria-describedby', 'app-confirmation-message');
	dialog.innerHTML = `
		<div class="app-confirmation-heading">
			<span class="app-confirmation-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none">
					<path d="M12 8v4m0 4h.01M10.3 3.9 2.8 17a2 2 0 0 0 1.74 3h14.92a2 2 0 0 0 1.74-3l-7.46-13.1a2 2 0 0 0-3.48 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</span>
			<span class="app-confirmation-eyebrow">PCU HELPDESK</span>
		</div>
		<div class="app-confirmation-copy">
			<h2 id="app-confirmation-title"></h2>
			<p id="app-confirmation-message"></p>
		</div>
		<div class="app-confirmation-actions">
			<button class="app-confirmation-button app-confirmation-button--cancel" type="button" data-confirm-cancel>Cancel</button>
			<button class="app-confirmation-button app-confirmation-button--confirm" type="button" data-confirm-accept></button>
		</div>
	`;

	document.body.append(dialog);

	const title = dialog.querySelector('#app-confirmation-title');
	const message = dialog.querySelector('#app-confirmation-message');
	const cancelButton = dialog.querySelector('[data-confirm-cancel]');
	const acceptButton = dialog.querySelector('[data-confirm-accept]');

	if (
		!(title instanceof HTMLElement) ||
		!(message instanceof HTMLElement) ||
		!(cancelButton instanceof HTMLButtonElement) ||
		!(acceptButton instanceof HTMLButtonElement)
	) {
		throw new Error('Confirmation dialog is missing required content or actions.');
	}

	let resolveConfirmation = null;
	let previouslyFocusedElement = null;

	const closeDialog = (confirmed) => {
		if (!dialog.open) {
			return;
		}

		dialog.dataset.confirmed = String(confirmed);
		dialog.close();
	};

	dialog.addEventListener('close', () => {
		const confirmed = dialog.dataset.confirmed === 'true';
		delete dialog.dataset.confirmed;
		resolveConfirmation?.(confirmed);
		resolveConfirmation = null;

		if (previouslyFocusedElement instanceof HTMLElement && previouslyFocusedElement.isConnected) {
			previouslyFocusedElement.focus();
		}
	});

	dialog.addEventListener('click', (event) => {
		if (event.target === dialog) {
			closeDialog(false);
		}
	});

	cancelButton.addEventListener('click', () => closeDialog(false));
	acceptButton.addEventListener('click', () => closeDialog(true));

	return ({ titleText, messageText, confirmText, isDangerous = true }) => {
		if (dialog.open || resolveConfirmation) {
			return Promise.resolve(false);
		}

		title.textContent = titleText;
		message.textContent = messageText;
		acceptButton.textContent = confirmText;
		acceptButton.classList.toggle('app-confirmation-button--danger', isDangerous);
		previouslyFocusedElement = document.activeElement;
		resolveConfirmation = null;

		return new Promise((resolve) => {
			resolveConfirmation = resolve;
			dialog.showModal();
			cancelButton.focus();
		});
	};
};

const requestConfirmation = createConfirmationDialog();

document.querySelectorAll('[data-public-feature-toggle]').forEach((toggle) => {
	if (!(toggle instanceof HTMLInputElement)) {
		console.error('Public feature availability control is not an input.');
		return;
	}

	const details = toggle.closest('details');
	const form = toggle.form;
	const state = details?.querySelector('[data-public-feature-state]');
	const description = details?.querySelector('[data-public-feature-description]');

	if (!(form instanceof HTMLFormElement) || !(state instanceof HTMLElement) || !(description instanceof HTMLElement)) {
		console.error('Public feature availability control is missing its form, status, or description.');
		return;
	}

	const updateFeatureAvailability = () => {
		state.textContent = toggle.checked ? 'Enabled' : 'Disabled';
		state.classList.toggle('admin-access-state--inactive', !toggle.checked);
		description.textContent = toggle.checked
			? toggle.dataset.enabledDescription || ''
			: toggle.dataset.disabledDescription || '';
	};

	toggle.addEventListener('change', () => {
		const isEnabled = toggle.checked;

		const saveAvailability = () => {
			updateFeatureAvailability();
			form.requestSubmit();
		};

		if (!isEnabled) {
			void requestConfirmation({
				titleText: toggle.dataset.disableTitle || 'Disable this feature?',
				messageText: toggle.dataset.disableConfirmation || 'Are you sure you want to disable this feature?',
				confirmText: 'Disable feature',
			}).then((confirmed) => {
				if (!confirmed) {
					toggle.checked = true;

					return;
				}

				toggle.checked = false;
				saveAvailability();
			});

			return;
		}

		saveAvailability();
	});
});

document.querySelectorAll('[data-confirm-logout]').forEach((form) => {
	if (!(form instanceof HTMLFormElement)) {
		console.error('Logout confirmation target is not a form.');
		return;
	}

	form.addEventListener('submit', (event) => {
		event.preventDefault();

		void requestConfirmation({
			titleText: 'Log out of Ticketech?',
			messageText: 'Are you sure you want to end your session?',
			confirmText: 'Log out',
			isDangerous: false,
		}).then((confirmed) => {
			if (confirmed) {
				HTMLFormElement.prototype.submit.call(form);
			}
		});
	});
});

document.querySelectorAll('[data-confirm-delete]').forEach((form) => {
	if (!(form instanceof HTMLFormElement)) {
		console.error('Delete confirmation target is not a form.');
		return;
	}

	form.addEventListener('submit', (event) => {
		event.preventDefault();

		void requestConfirmation({
			titleText: form.dataset.confirmTitle || 'Remove this record?',
			messageText: form.dataset.confirmMessage || 'This action cannot be undone.',
			confirmText: 'Remove permanently',
		}).then((confirmed) => {
			if (confirmed) {
				HTMLFormElement.prototype.submit.call(form);
			}
		});
	});
});

document.querySelectorAll('[data-confirm-submit]').forEach((form) => {
	if (!(form instanceof HTMLFormElement)) {
		console.error('Confirmation target is not a form.');
		return;
	}

	form.addEventListener('submit', (event) => {
		event.preventDefault();

		void requestConfirmation({
			titleText: form.dataset.confirmTitle || 'Confirm this account access change?',
			messageText: form.dataset.confirmMessage || 'The account access status will be changed.',
			confirmText: form.dataset.confirmButton || 'Continue',
			isDangerous: form.dataset.confirmDanger !== 'false',
		}).then((confirmed) => {
			if (confirmed) {
				HTMLFormElement.prototype.submit.call(form);
			}
		});
	});
});

document.querySelectorAll('[data-auto-submit-on-change]').forEach((form) => {
	if (!(form instanceof HTMLFormElement)) {
		console.error('Auto-submit filter is not a form.');
		return;
	}

	form.addEventListener('change', (event) => {
		if (
			event.target instanceof HTMLSelectElement ||
			(event.target instanceof HTMLInputElement && event.target.type === 'date')
		) {
			form.requestSubmit();
		}
	});

	form.addEventListener('keydown', (event) => {
		if (event.target instanceof HTMLInputElement && event.target.name === 'search' && event.key === 'Enter') {
			event.preventDefault();
			form.requestSubmit();
		}
	});
});

document.querySelectorAll('[data-ticket-assignee]').forEach((assigneeSelect) => {
	if (!(assigneeSelect instanceof HTMLSelectElement)) {
		console.error('Ticket assignee control is not a select element.');
		return;
	}

	const form = assigneeSelect.form;
	const priorityField = form?.querySelector('[data-priority-field]');
	const prioritySelect = priorityField?.querySelector('select[name="priority"]');
	const automaticPriorityNote = form?.querySelector('[data-automatic-priority-note]');

	if (
		!(priorityField instanceof HTMLElement) ||
		!(prioritySelect instanceof HTMLSelectElement) ||
		!(automaticPriorityNote instanceof HTMLElement)
	) {
		console.error('Ticket assignment form is missing priority controls.');
		return;
	}

	const updatePriorityControls = () => {
		const isAssigned = assigneeSelect.value !== '';

		prioritySelect.disabled = !isAssigned;
		automaticPriorityNote.hidden = isAssigned;
	};

	assigneeSelect.addEventListener('change', updatePriorityControls);
	updatePriorityControls();
});

document.querySelectorAll('.public-review-choices').forEach((choices) => {
	const ratings = choices.querySelectorAll('input[name="rating"]');

	const updateLitStars = () => {
		const selectedRating = Number(choices.querySelector('input[name="rating"]:checked')?.value || 0);

		choices.querySelectorAll('[data-star-value]').forEach((star) => {
			star.classList.toggle('is-lit', Number(star.dataset.starValue) <= selectedRating);
		});
	};

	ratings.forEach((rating) => rating.addEventListener('change', updateLitStars));
	updateLitStars();
});

document.querySelectorAll('[data-article-content-editor]').forEach((editor) => {
	if (!(editor instanceof HTMLElement)) {
		console.error('Article content editor is not an HTML element.');
		return;
	}

	const form = editor.closest('form');
	const contentInput = form?.querySelector('[data-article-content-value]');
	const toolbar = form?.querySelector('[role="toolbar"]');
	const errorMessage = form?.querySelector('[data-article-content-error]');

	if (
		!(form instanceof HTMLFormElement)
		|| !(contentInput instanceof HTMLTextAreaElement)
		|| !(toolbar instanceof HTMLElement)
		|| !(errorMessage instanceof HTMLElement)
	) {
		console.error('Article content editor is missing its form controls.');
		return;
	}

	const toolbarButtons = toolbar.querySelectorAll('[data-editor-command], [data-editor-link]');
	const updateToolbarState = () => {
		const selection = window.getSelection();
		const hasEditorSelection = selection
			&& selection.rangeCount > 0
			&& editor.contains(selection.getRangeAt(0).commonAncestorContainer);

		toolbarButtons.forEach((button) => {
			if (!button.hasAttribute('aria-pressed')) {
				return;
			}

			let isActive = false;

			if (hasEditorSelection) {
				const command = button.dataset.editorCommand;

				if (command === 'formatBlock') {
					const activeBlock = document.queryCommandValue(command)
						.replace(/[<>]/g, '')
						.toLowerCase();
					isActive = activeBlock === button.dataset.editorValue;
				} else if (command) {
					isActive = document.queryCommandState(command);
				}
			}

			button.setAttribute('aria-pressed', String(isActive));
		});
	};

	toolbarButtons.forEach((button) => {
		button.addEventListener('mousedown', (event) => event.preventDefault());
		button.addEventListener('click', () => {
			editor.focus();

			if (button.hasAttribute('data-editor-link')) {
				const url = window.prompt('Enter a link URL');

				if (url) {
					document.execCommand('createLink', false, url);
				}

				updateToolbarState();
				return;
			}

			const command = button.dataset.editorCommand;

			if (!command) {
				console.error('Article formatting control is missing its command.');
				return;
			}

			const commandValue = button.dataset.editorValue;
			const value = command === 'formatBlock' && commandValue
				? `<${commandValue}>`
				: commandValue;

			document.execCommand(command, false, value || undefined);
			updateToolbarState();
		});
	});

	editor.addEventListener('input', () => {
		editor.removeAttribute('aria-invalid');
		errorMessage.hidden = true;
		updateToolbarState();
	});
	editor.addEventListener('keyup', updateToolbarState);
	editor.addEventListener('mouseup', updateToolbarState);
	document.addEventListener('selectionchange', updateToolbarState);

	form.addEventListener('submit', (event) => {
		contentInput.value = editor.innerHTML;

		if (!editor.textContent?.trim()) {
			event.preventDefault();
			editor.setAttribute('aria-invalid', 'true');
			errorMessage.hidden = false;
			editor.focus();
		}
	});
});

document.querySelectorAll('[data-report-period-form]').forEach((form) => {
	if (!(form instanceof HTMLFormElement)) {
		console.error('Report period filter is not a form.');
		return;
	}

	const periodSelect = form.querySelector('[data-report-period]');
	const departmentSelect = form.querySelector('[data-report-department]');
	const referenceField = form.querySelector('[data-report-reference-field]');
	const referenceDateInput = form.querySelector('[data-report-reference-input]');
	const referenceDateLabel = form.querySelector('[data-report-reference-label]');
	const customRange = form.querySelector('[data-report-custom-range]');
	const startDateInput = form.querySelector('[data-report-start-date]');
	const endDateInput = form.querySelector('[data-report-end-date]');

	if (
		!(periodSelect instanceof HTMLSelectElement)
		|| !(referenceField instanceof HTMLElement)
		|| !(referenceDateInput instanceof HTMLInputElement)
		|| !(referenceDateLabel instanceof HTMLElement)
		|| !(customRange instanceof HTMLElement)
		|| !(startDateInput instanceof HTMLInputElement)
		|| !(endDateInput instanceof HTMLInputElement)
	) {
		console.error('Report period filter is missing a required control.');
		return;
	}

	const updateReportPeriodControls = () => {
		const isCustom = periodSelect.value === 'custom';

		referenceField.hidden = isCustom;
		referenceDateInput.disabled = isCustom;
		customRange.hidden = !isCustom;
		startDateInput.disabled = !isCustom;
		endDateInput.disabled = !isCustom;
		startDateInput.required = isCustom;
		endDateInput.required = isCustom;
		endDateInput.min = isCustom ? startDateInput.value : '';

		referenceDateLabel.textContent = periodSelect.value === 'weekly'
			? 'Date in week'
			: periodSelect.value === 'monthly'
				? 'Date in month'
				: periodSelect.value === 'yearly'
					? 'Date in year'
					: 'Report date';
	};

	const submitCustomRangeIfValid = () => {
		if (
			periodSelect.value === 'custom'
			&& startDateInput.value
			&& endDateInput.value
			&& startDateInput.value <= endDateInput.value
		) {
			form.requestSubmit();
		}
	};

	periodSelect.addEventListener('change', () => {
		updateReportPeriodControls();

		if (periodSelect.value !== 'custom') {
			form.requestSubmit();
		}
	});
	departmentSelect?.addEventListener('change', () => form.requestSubmit());
	referenceDateInput.addEventListener('change', () => {
		if (periodSelect.value !== 'custom') {
			form.requestSubmit();
		}
	});
	startDateInput.addEventListener('change', () => {
		endDateInput.min = startDateInput.value;
		submitCustomRangeIfValid();
	});
	endDateInput.addEventListener('change', submitCustomRangeIfValid);
	updateReportPeriodControls();
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
