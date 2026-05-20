(function () {
    'use strict';

    const config = window.__PROFILE_AVATAR__ || {};
    const MAX_BYTES = Number(config.maxBytes) || 2 * 1024 * 1024;
    const OUTPUT_SIZE = Number(config.outputSize) || 512;
    const MAX_QUALITY = Number(config.maxQuality) || 0.92;
    const MIN_QUALITY = Number(config.minQuality) || 0.5;

    const input = document.getElementById('avatar');
    const preview = document.getElementById('profile-avatar-preview');
    const modalEl = document.getElementById('avatar-crop-modal');
    const cropImage = document.getElementById('avatar-crop-image');
    const applyBtn = document.getElementById('avatar-crop-apply');
    const spinner = document.getElementById('avatar-crop-spinner');
    const statusEl = document.getElementById('avatar-crop-status');

    if (!input || !modalEl || !cropImage || typeof Cropper === 'undefined' || typeof bootstrap === 'undefined') {
        return;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    let cropper = null;
    let objectUrl = null;
    let appliedInSession = false;
    let pendingOpen = false;

    function revokeObjectUrl() {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
    }

    function setStatus(message) {
        if (statusEl) {
            statusEl.textContent = message || '';
        }
    }

    function destroyCropper() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    }

    function resetInput() {
        input.value = '';
    }

    function initCropper() {
        destroyCropper();
        cropper = new Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.9,
            responsive: true,
            background: false,
        });
    }

    input.addEventListener('change', function () {
        const file = input.files && input.files[0];
        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            resetInput();
            return;
        }

        appliedInSession = false;
        pendingOpen = true;
        revokeObjectUrl();
        objectUrl = URL.createObjectURL(file);
        cropImage.src = objectUrl;
        modal.show();
    });

    cropImage.addEventListener('load', function () {
        if (!pendingOpen) {
            return;
        }
        pendingOpen = false;
        initCropper();
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        destroyCropper();
        revokeObjectUrl();
        cropImage.removeAttribute('src');

        if (!appliedInSession) {
            resetInput();
        }

        setStatus('');
        applyBtn.disabled = false;
        if (spinner) {
            spinner.classList.add('d-none');
        }
    });

    applyBtn.addEventListener('click', async function () {
        if (!cropper) {
            return;
        }

        applyBtn.disabled = true;
        if (spinner) {
            spinner.classList.remove('d-none');
        }
        setStatus('Otimizando imagem…');

        try {
            const canvas = cropper.getCroppedCanvas({
                width: OUTPUT_SIZE,
                height: OUTPUT_SIZE,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
                fillColor: '#ffffff',
            });

            if (!canvas) {
                throw new Error('Não foi possível recortar a imagem.');
            }

            const blob = await compressCanvas(canvas, MAX_BYTES);
            const file = new File([blob], 'avatar.jpg', {
                type: 'image/jpeg',
                lastModified: Date.now(),
            });

            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;

            if (preview) {
                preview.src = URL.createObjectURL(blob);
            }

            appliedInSession = true;
            modal.hide();
        } catch (error) {
            setStatus(error.message || 'Erro ao processar a foto.');
            applyBtn.disabled = false;
        } finally {
            if (spinner) {
                spinner.classList.add('d-none');
            }
        }
    });

    function canvasToBlob(canvas, quality) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(
                function (blob) {
                    if (!blob) {
                        reject(new Error('Falha ao gerar a imagem.'));
                        return;
                    }
                    resolve(blob);
                },
                'image/jpeg',
                quality,
            );
        });
    }

    async function compressCanvas(canvas, maxBytes) {
        let quality = MAX_QUALITY;
        let blob = await canvasToBlob(canvas, quality);

        while (blob.size > maxBytes && quality > MIN_QUALITY) {
            quality = Math.max(MIN_QUALITY, quality - 0.08);
            blob = await canvasToBlob(canvas, quality);
        }

        if (blob.size > maxBytes) {
            throw new Error(
                'Mesmo após compressão a imagem ficou grande demais. Tente um recorte menor ou outra foto.',
            );
        }

        const kb = Math.max(1, Math.round(blob.size / 1024));
        setStatus('Pronto (' + kb + ' KB)');

        return blob;
    }
})();
