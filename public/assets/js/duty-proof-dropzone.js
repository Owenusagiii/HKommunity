document.addEventListener('DOMContentLoaded', () => {
    const allowedExtensions = /\.(pdf|jpe?g|png)$/i;
    const maxFileSize = 5 * 1024 * 1024;

    document.querySelectorAll('[data-proof-dropzone]').forEach((dropzone) => {
        if (!(dropzone instanceof HTMLElement)) {
            return;
        }

        const input = dropzone.querySelector('input[type="file"]');
        const fileList = dropzone.querySelector('.duty-proof-file-list');
        const errorMessage = dropzone.querySelector('[data-proof-file-error]');
        const maxFiles = Number.parseInt(dropzone.dataset.maxFiles || '5', 10);

        if (!(input instanceof HTMLInputElement) || !(fileList instanceof HTMLUListElement) ||
            !(errorMessage instanceof HTMLElement) || !Number.isInteger(maxFiles) || maxFiles < 0) {
            return;
        }
        const existingFiles = dropzone.querySelector('.duty-submission-proof-list-in-dropzone');
        const limitMessage = dropzone.querySelector('[data-proof-file-limit]');
        let selectedFiles = [];
        let activeExistingFiles = existingFiles instanceof HTMLUListElement
            ? existingFiles.querySelectorAll('[data-existing-file-id]').length
            : 0;

        const getValidationError = (files) => {
            if (activeExistingFiles + files.length > maxFiles) {
                const availableSlots = Math.max(0, maxFiles - activeExistingFiles);
                return `You can add up to ${availableSlots} more file${availableSlots === 1 ? '' : 's'}. Remove an existing file to make room.`;
            }

            if (files.some((file) => !allowedExtensions.test(file.name))) {
                return 'Only PDF, JPG, and PNG files are accepted.';
            }

            if (files.some((file) => file.size < 1 || file.size > maxFileSize)) {
                return 'Each file must be larger than 0 and no more than 5 MB.';
            }

            return '';
        };

        const mergeFiles = (...fileGroups) => {
            const files = [];
            const seenFiles = new Set();

            fileGroups.flat().forEach((file) => {
                const key = `${file.name}\0${file.size}\0${file.lastModified}\0${file.type}`;
                if (seenFiles.has(key)) {
                    return;
                }
                seenFiles.add(key);
                files.push(file);
            });

            return files;
        };

        const showError = (message) => {
            errorMessage.textContent = message;
            errorMessage.hidden = message === '';
        };

        const updateDropzone = () => {
            const hasFiles = activeExistingFiles + selectedFiles.length > 0;
            dropzone.classList.toggle('has-files', hasFiles);

            if (limitMessage instanceof HTMLElement) {
                const availableSlots = Math.max(0, maxFiles - activeExistingFiles - selectedFiles.length);
                limitMessage.textContent = availableSlots > 0
                    ? `${availableSlots} additional file${availableSlots === 1 ? '' : 's'} available · 5 MB each`
                    : 'Remove a proof file to add another · 5 MB each';
            }
        };

        const renderFiles = () => {
            fileList.querySelectorAll('[data-selected-file-index]').forEach((item) => item.remove());

            selectedFiles.forEach((file, index) => {
                const item = document.createElement('li');
                const icon = document.createElement('i');
                const name = document.createElement('span');
                const size = document.createElement('small');
                const removeButton = document.createElement('button');

                item.dataset.selectedFileIndex = String(index);
                icon.className = 'fa-regular fa-image duty-proof-file-icon';
                icon.setAttribute('aria-hidden', 'true');
                name.className = 'duty-proof-file-name';
                name.textContent = file.name;
                size.textContent = `${(file.size / (1024 * 1024)).toFixed(2)} MB`;
                removeButton.type = 'button';
                removeButton.className = 'duty-proof-file-remove';
                removeButton.dataset.fileIndex = String(index);
                removeButton.setAttribute('aria-label', `Remove ${file.name}`);
                removeButton.textContent = '×';
                item.append(icon, name, size, removeButton);
                fileList.append(item);
            });

            fileList.hidden = activeExistingFiles + selectedFiles.length === 0;
            updateDropzone();
        };

        renderFiles();

        const applyFiles = (files) => {
            const validationError = getValidationError(files);
            if (validationError) {
                showError(validationError);
                const transfer = new DataTransfer();
                selectedFiles.forEach((file) => transfer.items.add(file));
                input.files = transfer.files;
                return false;
            }

            selectedFiles = files;
            const transfer = new DataTransfer();
            selectedFiles.forEach((file) => transfer.items.add(file));
            input.files = transfer.files;
            showError('');
            renderFiles();
            return true;
        };

        dropzone.addEventListener('duty-proof-draft-restore', (event) => {
            const restoredFiles = event instanceof CustomEvent && Array.isArray(event.detail)
                ? event.detail.filter((file) => file instanceof File)
                : [];
            applyFiles(restoredFiles);
        });

        input.addEventListener('change', () => {
            const newlySelectedFiles = Array.from(input.files || []);
            applyFiles(mergeFiles(selectedFiles, newlySelectedFiles));
        });

        fileList.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            const existingRemoveButton = target.closest('[data-remove-existing-proof]');
            if (existingRemoveButton instanceof HTMLButtonElement) {
                const fileId = existingRemoveButton.dataset.fileId;
                const fileRow = existingRemoveButton.closest('[data-existing-file-id]');
                const form = dropzone.closest('form');
                if (!fileId || !fileRow || !(form instanceof HTMLFormElement)) {
                    return;
                }

                const removalInput = document.createElement('input');
                removalInput.type = 'hidden';
                removalInput.name = 'remove_file_ids[]';
                removalInput.value = fileId;
                form.append(removalInput);
                fileRow.remove();
                activeExistingFiles = Math.max(0, activeExistingFiles - 1);
                showError('');
                renderFiles();
                return;
            }

            const removeButton = target.closest('.duty-proof-file-remove');
            if (!(removeButton instanceof HTMLButtonElement)) {
                return;
            }

            const removeIndex = Number(removeButton.dataset.fileIndex);
            const remainingFiles = selectedFiles.filter((_, index) => index !== removeIndex);
            applyFiles(remainingFiles);
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.add('is-dragging');
            });
        });

        ['dragleave', 'dragend'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                if (eventName === 'dragend' || !dropzone.contains(event.relatedTarget)) {
                    dropzone.classList.remove('is-dragging');
                }
            });
        });

        dropzone.addEventListener('drop', (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragging');

            const droppedFiles = Array.from(event.dataTransfer?.files || []);
            if (droppedFiles.length) {
                applyFiles(mergeFiles(selectedFiles, droppedFiles));
            }
        });

        if (existingFiles instanceof HTMLUListElement) {
            const form = dropzone.closest('form');
            form?.addEventListener('submit', (event) => {
                if (activeExistingFiles + selectedFiles.length > 0) {
                    return;
                }

                event.preventDefault();
                showError('Keep or add at least one proof file before saving.');
            });
        }
    });
});
