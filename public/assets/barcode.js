/** Enables local camera barcode capture without sending video to a server. */
document.addEventListener('DOMContentLoaded', () => {
    const startButton = document.querySelector('#scan-barcode');
    const stopButton = document.querySelector('#stop-barcode');
    const scanner = document.querySelector('#barcode-scanner');
    const video = document.querySelector('#barcode-video');
    const input = document.querySelector('#barcode-input');
    const status = document.querySelector('#barcode-status');
    if (!startButton || !scanner || !video || !input || !status) return;

    let stream = null;
    let scanning = false;
    const stop = () => {
        scanning = false;
        if (stream) stream.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        scanner.hidden = true;
    };

    startButton.addEventListener('click', async () => {
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
            status.textContent = 'Camera scanning is not supported in this browser. Enter the barcode manually.';
            scanner.hidden = false;
            return;
        }
        try {
            const detector = new BarcodeDetector({formats: ['ean_13','ean_8','upc_a','upc_e','code_128','itf']});
            stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: {ideal: 'environment'}}, audio: false});
            video.srcObject = stream;
            await video.play();
            scanner.hidden = false;
            scanning = true;
            status.textContent = 'Point the camera at a barcode.';
            const detect = async () => {
                if (!scanning) return;
                try {
                    const matches = await detector.detect(video);
                    if (matches.length > 0) {
                        input.value = matches[0].rawValue.replace(/\D/g, '');
                        input.dispatchEvent(new Event('change', {bubbles: true}));
                        stop();
                        input.focus();
                        return;
                    }
                } catch (_) {}
                requestAnimationFrame(detect);
            };
            requestAnimationFrame(detect);
        } catch (_) {
            status.textContent = 'Camera access was unavailable. Check permission or enter the barcode manually.';
            scanner.hidden = false;
        }
    });
    stopButton?.addEventListener('click', stop);
    window.addEventListener('pagehide', stop);
});
