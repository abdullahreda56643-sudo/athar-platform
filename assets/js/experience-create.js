const form = document.querySelector('#experience-form');
const contentField = document.querySelector('#experience-content');
const contentCount = document.querySelector('#content-count');
const attachmentInput = document.querySelector('#experience-attachments');
const attachmentList = document.querySelector('#attachment-list');
const uploadZone = document.querySelector('.compose-upload-zone');

if (
    form instanceof HTMLFormElement
    && contentField instanceof HTMLTextAreaElement
    && contentCount instanceof HTMLElement
    && attachmentInput instanceof HTMLInputElement
    && attachmentList instanceof HTMLUListElement
    && uploadZone instanceof HTMLElement
) {
    const updateCount = () => {
        contentCount.textContent = String(contentField.value.length);
    };

    const insertAtCursor = (before, after = '') => {
        const start = contentField.selectionStart;
        const end = contentField.selectionEnd;
        const selectedText = contentField.value.slice(start, end);
        contentField.setRangeText(`${before}${selectedText || (after ? 'نص مهم' : '')}${after}`, start, end, 'select');
        contentField.focus();
        updateCount();
    };

    document.querySelectorAll('.compose-editor-toolbar [data-insert]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const action = button.dataset.insert;
            if (action === 'bold') {
                insertAtCursor('**', '**');
            } else if (action === 'heading') {
                insertAtCursor('\n\n### ', '\n');
            } else if (action === 'bullet') {
                insertAtCursor('\n• ');
            } else if (action === 'number') {
                insertAtCursor('\n1. ');
            } else if (action === 'quote') {
                insertAtCursor('«', '»');
            }
        });
    });

    contentField.addEventListener('input', updateCount);

    const formatFileSize = (size) => {
        return size < 1024 * 1024
            ? `${Math.ceil(size / 1024)} كيلوبايت`
            : `${(size / (1024 * 1024)).toFixed(1)} ميجابايت`;
    };

    const updateFileList = () => {
        attachmentList.replaceChildren();
        Array.from(attachmentInput.files ?? []).forEach((file, index) => {
            const item = document.createElement('li');
            const details = document.createElement('span');
            details.textContent = `${file.name} · ${formatFileSize(file.size)}`;
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.textContent = 'إزالة';
            removeButton.setAttribute('aria-label', `إزالة الملف ${file.name}`);
            removeButton.addEventListener('click', () => {
                const files = new DataTransfer();
                Array.from(attachmentInput.files ?? []).forEach((selectedFile, fileIndex) => {
                    if (fileIndex !== index) {
                        files.items.add(selectedFile);
                    }
                });
                attachmentInput.files = files.files;
                attachmentInput.dispatchEvent(new Event('change'));
            });
            item.append(details, removeButton);
            attachmentList.append(item);
        });

        const selectedFiles = Array.from(attachmentInput.files ?? []);
        const invalidCount = selectedFiles.length > 5;
        const invalidSize = selectedFiles.some((file) => file.size > 10 * 1024 * 1024 || file.size === 0);
        attachmentInput.setCustomValidity(
            invalidCount
                ? 'يمكن إرفاق خمسة ملفات بحد أقصى.'
                : invalidSize
                    ? 'يجب ألا يتجاوز حجم الملف الواحد 10 ميجابايت.'
                    : '',
        );
    };

    attachmentInput.addEventListener('change', updateFileList);
    uploadZone.addEventListener('dragover', (event) => {
        event.preventDefault();
        uploadZone.classList.add('is-dragging');
    });
    uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('is-dragging'));
    uploadZone.addEventListener('drop', (event) => {
        event.preventDefault();
        uploadZone.classList.remove('is-dragging');
        if (event instanceof DragEvent && event.dataTransfer !== null) {
            attachmentInput.files = event.dataTransfer.files;
            attachmentInput.dispatchEvent(new Event('change'));
        }
    });
}
