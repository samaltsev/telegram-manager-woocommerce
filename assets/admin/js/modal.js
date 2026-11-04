window.WCTMConfirm = function (message) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'tcc-confirm-overlay';
        overlay.innerHTML = `
            <div class="tcc-confirm-modal" role="dialog" aria-modal="true">
                <h2>Confirm action</h2>
                <p>${String(message || 'Are you sure?')}</p>
                <div class="tcc-confirm-actions">
                    <button type="button" class="tcc-btn tcc-btn-secondary" data-result="0">Cancel</button>
                    <button type="button" class="tcc-btn tcc-btn-danger" data-result="1">Confirm</button>
                </div>
            </div>`;
        document.body.appendChild(overlay);
        overlay.addEventListener('click', (event) => {
            const resultButton = event.target.closest('[data-result]');
            if (event.target === overlay || resultButton) {
                const result = resultButton?.getAttribute('data-result') === '1';
                overlay.remove();
                resolve(result);
            }
        });
    });
};
