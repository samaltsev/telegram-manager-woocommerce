(function () {
    'use strict';

    const config = window.wctmAdmin || {};

    const ajax = async (action, data = {}) => {
        const body = new URLSearchParams({
            action: `wctm_${action}`,
            nonce: config.nonce || '',
            ...data
        });
        const response = await fetch(window.ajaxurl || config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        });
        const raw = await response.text();
        let payload;

        try {
            payload = JSON.parse(raw);
        } catch (error) {
            const plainText = raw
                .replace(/<style[\s\S]*?<\/style>/gi, ' ')
                .replace(/<script[\s\S]*?<\/script>/gi, ' ')
                .replace(/<[^>]+>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();

            if (response.status === 400 && (plainText === '' || plainText === '0')) {
                throw new Error('WordPress rejected the AJAX action. Refresh the admin page and sign in again if needed.');
            }

            throw new Error(plainText || `Server returned HTTP ${response.status} instead of JSON.`);
        }

        if (!response.ok && !payload?.data?.message) {
            throw new Error(`Server returned HTTP ${response.status}.`);
        }

        return payload;
    };

    const toast = (message, type = 'success') => {
        const node = document.createElement('div');
        node.className = `tcc-toast is-${type === 'error' ? 'error' : 'success'}`;
        node.textContent = String(message || '');
        document.body.appendChild(node);
        window.setTimeout(() => node.remove(), 3500);
    };

    const showInlineResult = (node, message, type = 'success') => {
        if (!node) return;
        node.textContent = String(message || '');
        node.className = `tcc-inline-result is-${type}`;
        node.style.display = 'block';
    };

    const hideInlineResult = (node) => {
        if (!node) return;
        node.style.display = 'none';
        node.textContent = '';
        node.className = 'tcc-inline-result';
    };

    const setButtonLoading = (button, loading, text = 'Working...') => {
        if (!button) return;
        if (loading) {
            button.dataset.tccLabel = button.textContent;
            button.textContent = text;
        } else if (button.dataset.tccLabel) {
            button.textContent = button.dataset.tccLabel;
            delete button.dataset.tccLabel;
        }
        button.disabled = Boolean(loading);
    };

    window.WCTMAdmin = {ajax, toast, showInlineResult, hideInlineResult, setButtonLoading};

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-wctm-ajax-action]').forEach((button) => {
            button.addEventListener('click', async (event) => {
                event.preventDefault();

                const action = button.getAttribute('data-wctm-ajax-action');
                const result = document.getElementById('wctm-dashboard-action-result');

                if (!action) {
                    return;
                }

                setButtonLoading(button, true);

                try {
                    const response = await ajax(action);
                    if (!response?.success) {
                        throw new Error(response?.data?.message || 'Action failed.');
                    }

                    const message = response?.data?.message || 'Action completed.';
                    showInlineResult(result, message, 'success');
                    toast(message, 'success');
                } catch (error) {
                    const message = error instanceof Error ? error.message : 'Unknown error.';
                    showInlineResult(result, message, 'error');
                    toast(message, 'error');
                } finally {
                    setButtonLoading(button, false);
                }
            });
        });

        const tokenToggle = document.getElementById('wctm-toggle-token');
        const tokenInput = document.getElementById('bot-token');
        if (tokenToggle && tokenInput) {
            tokenToggle.addEventListener('click', (event) => {
                event.preventDefault();
                const showing = tokenInput.type === 'text';
                tokenInput.type = showing ? 'password' : 'text';
                tokenToggle.setAttribute('aria-pressed', showing ? 'false' : 'true');
            });
        }

        document.querySelectorAll('[data-wctm-preview]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const target = document.getElementById(button.getAttribute('data-wctm-preview') || '');
                if (!target) return;

                const isOpen = !target.hasAttribute('hidden');
                if (isOpen) {
                    target.setAttribute('hidden', 'hidden');
                    button.textContent = 'Preview v';
                } else {
                    target.removeAttribute('hidden');
                    button.textContent = 'Preview ^';
                }
            });
        });

        document.querySelectorAll('.tcc-notification-toggle').forEach((checkbox) => {
            const refresh = () => {
                const card = checkbox.closest('.tcc-notif-card');
                if (card) {
                    card.classList.toggle('is-enabled', checkbox.checked);
                    card.classList.toggle('is-disabled', !checkbox.checked);
                }
            };
            checkbox.addEventListener('change', refresh);
            refresh();
        });

        const copySystemButton = document.getElementById('wctm-copy-system-report');
        if (copySystemButton) {
            copySystemButton.addEventListener('click', async (event) => {
                event.preventDefault();
                const report = copySystemButton.getAttribute('data-report') || '';
                const original = copySystemButton.textContent;

                try {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        await navigator.clipboard.writeText(report);
                    } else {
                        const textarea = document.createElement('textarea');
                        textarea.value = report;
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        textarea.remove();
                    }
                    copySystemButton.textContent = 'Copied ✓';
                    toast('System report copied.', 'success');
                } catch (error) {
                    toast('Could not copy system report.', 'error');
                }

                window.setTimeout(() => {
                    copySystemButton.textContent = original || 'Copy system report';
                }, 1600);
            });
        }
    });
})();
