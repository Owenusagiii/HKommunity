document.addEventListener('DOMContentLoaded', () => {
    const draftStates = new WeakMap();
    const submitDialog = document.getElementById('duty-submit-confirm-dialog');
    const submitCancelButton = submitDialog?.querySelector('[data-duty-submit-cancel]');
    const submitConfirmButton = submitDialog?.querySelector('[data-duty-submit-confirm]');
    let pendingSubmitForm = null;
    let confirmedSubmitForm = null;
    let submitTrigger = null;

    if (
        submitDialog instanceof HTMLDialogElement
        && submitCancelButton instanceof HTMLButtonElement
        && submitConfirmButton instanceof HTMLButtonElement
    ) {
        document.querySelectorAll('form[data-confirm-duty-submit]').forEach((form) => {
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            form.addEventListener('submit', (event) => {
                if (event.defaultPrevented) {
                    return;
                }

                if (confirmedSubmitForm === form) {
                    confirmedSubmitForm = null;
                    return;
                }

                event.preventDefault();
                pendingSubmitForm = form;
                submitTrigger = event.submitter instanceof HTMLButtonElement
                    ? event.submitter
                    : form.querySelector('button[type="submit"]');
                submitDialog.showModal();
                submitCancelButton.focus();
            });
        });

        submitCancelButton.addEventListener('click', () => {
            submitDialog.close();
        });

        submitConfirmButton.addEventListener('click', () => {
            if (!(pendingSubmitForm instanceof HTMLFormElement)) {
                console.error('Unable to confirm duty submission: pending form was not found.');
                return;
            }

            const form = pendingSubmitForm;
            pendingSubmitForm = null;
            confirmedSubmitForm = form;
            submitDialog.close();
            if (submitTrigger instanceof HTMLButtonElement) {
                form.requestSubmit(submitTrigger);
            } else {
                form.requestSubmit();
            }
        });

        submitDialog.addEventListener('click', (event) => {
            if (event.target === submitDialog) {
                submitDialog.close();
            }
        });

        submitDialog.addEventListener('cancel', () => {
            pendingSubmitForm = null;
        });

        submitDialog.addEventListener('close', () => {
            submitTrigger?.focus();
            pendingSubmitForm = null;
            submitTrigger = null;
        });
    } else if (document.querySelector('form[data-confirm-duty-submit]')) {
        console.error('Unable to initialize duty submission confirmation: dialog or buttons were not found.');
    }

    const getEditForm = (dialog) => dialog.querySelector('.duty-submission-edit-form');

    const saveDraft = (dialog) => {
        const form = getEditForm(dialog);
        if (!(form instanceof HTMLFormElement) || form.classList.contains('is-locked')) {
            return;
        }

        const fields = {};
        form.querySelectorAll('input:not([type="hidden"]):not([type="file"]), select, textarea').forEach((field) => {
            if (field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')) {
                fields[field.name] = field.checked;
            } else if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
                fields[field.name] = field.value;
            }
        });

        const fileInput = form.querySelector('input[type="file"]');
        draftStates.set(dialog, {
            fields,
            files: fileInput instanceof HTMLInputElement ? Array.from(fileInput.files || []) : [],
        });
    };

    const restoreDraft = (dialog) => {
        const state = draftStates.get(dialog);
        const form = getEditForm(dialog);
        if (!state || !(form instanceof HTMLFormElement)) {
            return;
        }

        form.querySelectorAll('input:not([type="hidden"]):not([type="file"]), select, textarea').forEach((field) => {
            if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
                return;
            }

            if (!Object.prototype.hasOwnProperty.call(state.fields, field.name)) {
                return;
            }
            if (field instanceof HTMLInputElement && (field.type === 'checkbox' || field.type === 'radio')) {
                field.checked = Boolean(state.fields[field.name]);
            } else {
                field.value = String(state.fields[field.name]);
            }
        });

        const dropzone = form.querySelector('[data-proof-dropzone]');
        if (dropzone) {
            dropzone.dispatchEvent(new CustomEvent('duty-proof-draft-restore', { detail: state.files }));
        }
    };

    const deleteDialog = document.getElementById('duty-delete-confirm-dialog');
    const deleteCancelButton = deleteDialog?.querySelector('[data-duty-delete-cancel]');
    const deleteConfirmButton = deleteDialog?.querySelector('[data-duty-delete-confirm]');
    let pendingDeleteForm = null;
    let deleteTrigger = null;

    if (
        deleteDialog instanceof HTMLDialogElement
        && deleteCancelButton instanceof HTMLButtonElement
        && deleteConfirmButton instanceof HTMLButtonElement
    ) {
        document.querySelectorAll('[data-confirm-duty-delete]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                pendingDeleteForm = form;
                deleteTrigger = event.submitter instanceof HTMLElement ? event.submitter : form.querySelector('button[type="submit"]');
                deleteDialog.showModal();
                deleteCancelButton.focus();
            });
        });

        deleteCancelButton.addEventListener('click', () => {
            deleteDialog.close();
        });

        deleteConfirmButton.addEventListener('click', () => {
            if (!(pendingDeleteForm instanceof HTMLFormElement)) {
                console.error('Unable to delete duty submission: pending form was not found.');
                return;
            }
            pendingDeleteForm.submit();
        });

        deleteDialog.addEventListener('click', (event) => {
            if (event.target === deleteDialog) {
                deleteDialog.close();
            }
        });

        deleteDialog.addEventListener('close', () => {
            deleteTrigger?.focus();
            pendingDeleteForm = null;
            deleteTrigger = null;
        });
    } else {
        console.error('Unable to initialize duty submission deletion confirmation: dialog or buttons were not found.');
    }

    const editConfirmDialog = document.getElementById('duty-edit-confirm-dialog');
    const editConfirmButton = editConfirmDialog?.querySelector('[data-duty-edit-confirm]');
    const editCancelButton = editConfirmDialog?.querySelector('[data-duty-edit-cancel]');
    let pendingEditForm = null;
    let pendingEditSubmitter = null;
    let confirmedEditForm = null;
    let editConfirmTrigger = null;

    if (
        editConfirmDialog instanceof HTMLDialogElement
        && editConfirmButton instanceof HTMLButtonElement
        && editCancelButton instanceof HTMLButtonElement
    ) {
        document.querySelectorAll('.duty-submission-edit-form:not(.is-locked)').forEach((form) => {
            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            form.addEventListener('submit', (event) => {
                if (confirmedEditForm === form) {
                    confirmedEditForm = null;
                    return;
                }

                event.preventDefault();
                event.stopImmediatePropagation();
                pendingEditForm = form;
                pendingEditSubmitter = event.submitter instanceof HTMLButtonElement
                    ? event.submitter
                    : document.querySelector(`button[form="${form.id}"]`);
                editConfirmTrigger = pendingEditSubmitter;
                editConfirmDialog.showModal();
                editCancelButton.focus();
            });
        });

        editConfirmButton.addEventListener('click', () => {
            if (!(pendingEditForm instanceof HTMLFormElement)) {
                console.error('Unable to confirm duty submission changes: pending form was not found.');
                return;
            }

            const form = pendingEditForm;
            const submitter = pendingEditSubmitter;
            pendingEditForm = null;
            pendingEditSubmitter = null;
            confirmedEditForm = form;
            editConfirmDialog.close();
            if (submitter instanceof HTMLButtonElement && !submitter.disabled) {
                form.requestSubmit(submitter);
            } else {
                form.requestSubmit();
            }
        });

        const cancelEditConfirmation = () => {
            pendingEditForm = null;
            pendingEditSubmitter = null;
            editConfirmDialog.close();
        };

        editCancelButton.addEventListener('click', cancelEditConfirmation);
        editConfirmDialog.addEventListener('click', (event) => {
            if (event.target === editConfirmDialog) {
                cancelEditConfirmation();
            }
        });
        editConfirmDialog.addEventListener('cancel', () => {
            pendingEditForm = null;
            pendingEditSubmitter = null;
        });
        editConfirmDialog.addEventListener('close', () => {
            if (editConfirmTrigger instanceof HTMLElement) {
                editConfirmTrigger.focus();
            }
            editConfirmTrigger = null;
            pendingEditSubmitter = null;
        });
    } else if (document.querySelector('.duty-submission-edit-form:not(.is-locked)')) {
        console.error('Unable to initialize duty submission save confirmation: dialog or buttons were not found.');
    }

    document.querySelectorAll('.duty-submission-edit-form:not(.is-locked)').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('.duty-submission-edit-fields input[name]').forEach((input) => {
                if (input instanceof HTMLInputElement && input.value === input.defaultValue) {
                    input.disabled = true;
                }
            });
        });
    });

    document.querySelectorAll('.duty-submission-row').forEach((row) => {
        const openDialog = () => {
            const dialogId = row.getAttribute('data-popout-dialog');
            const dialog = dialogId ? document.getElementById(dialogId) : null;

            if (!(dialog instanceof HTMLDialogElement)) {
                console.error('Unable to open duty submission: dialog was not found.');
                return;
            }

            if (dialog.open) {
                return;
            }

            dialog.returnFocusTo = row;
            dialog.showModal();
            restoreDraft(dialog);
            document.documentElement.classList.add('duty-submission-dialog-open');
            document.body.classList.add('duty-submission-dialog-open');
            requestAnimationFrame(() => dialog.classList.add('is-open'));
        };

        row.addEventListener('click', openDialog);
        row.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openDialog();
            }
        });
    });

    document.querySelectorAll('.duty-submission-dialog').forEach((dialog) => {
        let closeTimer = 0;

        const closeDialog = () => {
            if (!dialog.open || closeTimer) {
                return;
            }

            dialog.classList.remove('is-open');
            closeTimer = window.setTimeout(() => {
                closeTimer = 0;
                dialog.close();
            }, 280);
        };

        dialog.querySelector('.duty-submission-dialog-close')?.addEventListener('click', () => {
            closeDialog();
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                closeDialog();
            }
        });

        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            closeDialog();
        });

        dialog.addEventListener('close', () => {
            saveDraft(dialog);
            if (closeTimer) {
                window.clearTimeout(closeTimer);
                closeTimer = 0;
            }
            dialog.classList.remove('is-open');
            dialog.returnFocusTo?.focus();
            delete dialog.returnFocusTo;
            if (!document.querySelector('.duty-submission-dialog[open]')) {
                document.documentElement.classList.remove('duty-submission-dialog-open');
                document.body.classList.remove('duty-submission-dialog-open');
            }
        });
    });
});
