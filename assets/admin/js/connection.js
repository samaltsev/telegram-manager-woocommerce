document.addEventListener('DOMContentLoaded', () => {
    const api = window.WCTMAdmin || {};
    const ajax = api.ajax;
    const toast = api.toast;
    const showInlineResult = api.showInlineResult;
    const hideInlineResult = api.hideInlineResult;
    const setButtonLoading = api.setButtonLoading;

    if (!ajax || !toast || !showInlineResult || !hideInlineResult || !setButtonLoading) {
        return;
    }

    const domain = 'telegram-manager-woocommerce';
    const __ = window.wp?.i18n?.__ || ((text) => text);

    const tokenInput = document.getElementById('bot-token');
    const botNameInput = document.getElementById('bot-name');
    const usernameInput = document.getElementById('bot-username');
    const verifyButton = document.getElementById('wctm-verify-bot');
    const verifyResult = document.getElementById('wctm-verify-bot-result');
    const botInfoPanel = document.getElementById('wctm-bot-info-panel');
    const botNameLink = document.getElementById('wctm-bot-name-link');
    const botUsernameLink = document.getElementById('wctm-bot-username-link');
    const botStatusBadge = document.getElementById('wctm-bot-status-badge');
    const chatIdInput = document.getElementById('telegram-chat-id') || document.getElementById('admin-chat-id');
    const getChatIdButton = document.getElementById('wctm-get-chat-id');
    const testButton = document.getElementById('wctm-admin-bot-test');
    const saveButton = document.getElementById('wctm-apply-admin-bot');
    const chatIdResult = document.getElementById('wctm-chat-id-result');
    const testResult = document.getElementById('wctm-admin-bot-test-result');
    const tokenStep = document.querySelector('[data-step="token"]');
    const chatStep = document.querySelector('[data-step="chat_id"]');
    const testStep = document.querySelector('[data-step="test_message"]');

    const hasValue = (input) => String(input?.value || '').trim() !== '';

    const refreshSteps = () => {
        tokenStep?.classList.toggle('is-done', hasValue(tokenInput));
        chatStep?.classList.toggle('is-done', hasValue(chatIdInput));
    };

    const requireToken = () => {
        if (hasValue(tokenInput)) {
            return true;
        }

        showInlineResult(testResult || chatIdResult, __('Add Bot Token first.', domain), 'error');
        tokenInput?.focus();
        return false;
    };

    const updateBotInfo = (data = {}) => {
        const name = String(data.bot_name || '').trim();
        const username = String(data.username || '').replace(/^@+/, '').trim();
        const link = data.link || (username ? `https://t.me/${encodeURIComponent(username)}` : '');

        if (botNameInput && name) {
            botNameInput.value = name;
        }
        if (usernameInput && username) {
            usernameInput.value = username;
        }

        if (botNameLink) {
            botNameLink.textContent = name || botNameLink.textContent || 'Telegram bot';
            if (link && botNameLink.tagName.toLowerCase() === 'a') {
                botNameLink.href = link;
            } else if (link && botNameLink.tagName.toLowerCase() !== 'a') {
                const anchor = document.createElement('a');
                anchor.id = botNameLink.id;
                anchor.href = link;
                anchor.target = '_blank';
                anchor.rel = 'noopener noreferrer';
                anchor.textContent = botNameLink.textContent;
                botNameLink.replaceWith(anchor);
            }
        }

        const usernameText = username ? `@${username}` : '—';
        const currentUsernameNode = document.getElementById('wctm-bot-username-link');
        if (currentUsernameNode) {
            currentUsernameNode.textContent = usernameText;
            if (link && currentUsernameNode.tagName.toLowerCase() === 'a') {
                currentUsernameNode.href = link;
            } else if (link && currentUsernameNode.tagName.toLowerCase() !== 'a') {
                const anchor = document.createElement('a');
                anchor.id = currentUsernameNode.id;
                anchor.href = link;
                anchor.target = '_blank';
                anchor.rel = 'noopener noreferrer';
                anchor.textContent = usernameText;
                currentUsernameNode.replaceWith(anchor);
            }
        }

        if (botStatusBadge) {
            botStatusBadge.textContent = username || name ? 'Connected' : 'Not verified yet';
            botStatusBadge.classList.toggle('tcc-badge-success', Boolean(username || name));
            botStatusBadge.classList.toggle('tcc-badge-warning', !Boolean(username || name));
        }
        if (botInfoPanel) {
            botInfoPanel.classList.toggle('is-connected', Boolean(username || name));
            botInfoPanel.classList.toggle('is-muted', !Boolean(username || name));
        }
    };

    const saveBotSettings = async () => {
        if (!tokenInput || !chatIdInput) {
            return false;
        }

        const payload = {
            token: tokenInput.value.trim(),
            bot_name: botNameInput?.value?.trim() || '',
            username: usernameInput?.value?.trim() || '',
            chat_id: chatIdInput.value.trim(),
        };

        const response = await ajax('save_bot_settings', payload);
        if (!response?.success) {
            throw new Error(response?.data?.message || __('Could not save settings.', domain));
        }

        refreshSteps();
        return response;
    };

    verifyButton?.addEventListener('click', async (event) => {
        event.preventDefault();

        if (!requireToken()) {
            return;
        }

        hideInlineResult(verifyResult);
        setButtonLoading(verifyButton, true, __('Verifying...', domain));

        try {
            const response = await ajax('verify_bot', {
                token: tokenInput.value.trim(),
            });

            if (!response?.success) {
                throw new Error(response?.data?.message || __('Could not verify bot.', domain));
            }

            updateBotInfo(response.data || {});
            refreshSteps();
            showInlineResult(verifyResult, response.data?.message || __('Bot verified successfully.', domain), 'success');
            toast(response.data?.message || __('Bot verified successfully.', domain), 'success');
        } catch (error) {
            showInlineResult(verifyResult, error instanceof Error ? error.message : __('Unknown error.', domain), 'error');
        } finally {
            setButtonLoading(verifyButton, false);
        }
    });

    getChatIdButton?.addEventListener('click', async (event) => {
        event.preventDefault();

        if (!requireToken()) {
            return;
        }

        hideInlineResult(chatIdResult);
        setButtonLoading(getChatIdButton, true, __('Getting...', domain));

        try {
            const response = await ajax('get_chat_id', {
                token: tokenInput.value.trim(),
            });

            if (!response?.success) {
                throw new Error(response?.data?.message || __('Could not get Chat ID.', domain));
            }

            if (response.data?.chat_id) {
                chatIdInput.value = response.data.chat_id;
            }

            refreshSteps();
            showInlineResult(chatIdResult, response.data?.message || __('Telegram Chat ID found.', domain), 'success');
        } catch (error) {
            showInlineResult(chatIdResult, error instanceof Error ? error.message : __('Unknown error.', domain), 'error');
        } finally {
            setButtonLoading(getChatIdButton, false);
        }
    });

    testButton?.addEventListener('click', async (event) => {
        event.preventDefault();

        if (!requireToken()) {
            return;
        }

        hideInlineResult(testResult);
        setButtonLoading(testButton, true, __('Sending...', domain));

        try {
            await saveBotSettings();

            const response = await ajax('test_connection', {
                token: tokenInput.value.trim(),
                chat_id: chatIdInput?.value?.trim() || '',
            });

            if (!response?.success) {
                throw new Error(response?.data?.message || __('Could not send test message.', domain));
            }

            if (response.data) {
                updateBotInfo(response.data);
            }
            if (response.data?.username && usernameInput) {
                usernameInput.value = response.data.username;
            }
            if (response.data?.chat_id && chatIdInput) {
                chatIdInput.value = response.data.chat_id;
            }

            testStep?.classList.add('is-done');
            refreshSteps();
            showInlineResult(testResult, __('Telegram is ready', domain), 'success');
            toast(__('Telegram is ready', domain), 'success');
        } catch (error) {
            showInlineResult(testResult, error instanceof Error ? error.message : __('Unknown error.', domain), 'error');
        } finally {
            setButtonLoading(testButton, false);
        }
    });

    saveButton?.addEventListener('click', async (event) => {
        event.preventDefault();
        setButtonLoading(saveButton, true, __('Saving...', domain));

        try {
            const response = await saveBotSettings();
            toast(response?.data?.message || __('Settings saved.', domain), 'success');
        } catch (error) {
            toast(error instanceof Error ? error.message : __('Unknown error.', domain), 'error');
        } finally {
            setButtonLoading(saveButton, false);
        }
    });

    tokenInput?.addEventListener('input', refreshSteps);
    chatIdInput?.addEventListener('input', refreshSteps);
    refreshSteps();
});
