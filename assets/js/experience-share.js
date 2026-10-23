const shareButton = document.querySelector('[data-experience-share]');
const shareFeedback = document.querySelector('[data-share-feedback]');

if (shareButton instanceof HTMLButtonElement && shareFeedback instanceof HTMLElement) {
    shareButton.addEventListener('click', async () => {
        const shareData = {
            title: shareButton.dataset.shareTitle ?? document.title,
            url: window.location.href,
        };

        if (typeof navigator.share === 'function') {
            try {
                await navigator.share(shareData);
                shareFeedback.textContent = 'تم فتح خيارات مشاركة الخبرة.';
                return;
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }
            }
        }

        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(shareData.url);
            } else {
                const temporaryInput = document.createElement('textarea');
                temporaryInput.value = shareData.url;
                temporaryInput.setAttribute('readonly', '');
                temporaryInput.style.position = 'fixed';
                temporaryInput.style.opacity = '0';
                document.body.append(temporaryInput);
                temporaryInput.select();
                const copied = document.execCommand('copy');
                temporaryInput.remove();

                if (!copied) {
                    throw new Error('تعذر نسخ رابط الخبرة.');
                }
            }

            shareFeedback.textContent = 'تم نسخ رابط الخبرة لمشاركته.';
        } catch {
            shareFeedback.textContent = 'تعذرت مشاركة الخبرة أو نسخ رابطها. انسخي الرابط من شريط العنوان.';
        }
    });
}
