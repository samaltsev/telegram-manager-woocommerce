document.addEventListener('DOMContentLoaded', () => {
    const api = window.WCTMAdmin || {};
    const ajax = api.ajax;
    const toast = api.toast;
    const setButtonLoading = api.setButtonLoading;

    if (!ajax || !toast || !setButtonLoading) {
        return;
    }

    document.querySelectorAll('[data-wctm-log-action]').forEach((button) => {
        button.addEventListener('click', async (event) => {
            event.preventDefault();
            const action = button.getAttribute('data-wctm-log-action');
            if (!action) {
                return;
            }

            if (action === 'clear_logs' || action === 'clear_success_logs') {
                const ok = await (window.WCTMConfirm ? window.WCTMConfirm('This will permanently remove matching log records.') : Promise.resolve(true));
                if (!ok) {
                    return;
                }
            }

            setButtonLoading(button, true);
            try {
                const response = await ajax(action);
                if (!response?.success) {
                    throw new Error(response?.data?.message || 'Action failed.');
                }
                toast(response?.data?.message || 'Action completed.', 'success');
                if (['clear_logs', 'clear_success_logs', 'cleanup_old_records', 'retry_failed_jobs'].includes(action)) {
                    window.setTimeout(() => window.location.reload(), 900);
                }
            } catch (error) {
                toast(error instanceof Error ? error.message : 'Unknown error.', 'error');
            } finally {
                setButtonLoading(button, false);
            }
        });
    });
});
