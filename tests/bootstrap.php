<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/../');
require_once dirname(__DIR__) . '/src/Integrations/WordPress/Option/QueueSettingsRepository.php';
require_once dirname(__DIR__) . '/src/Queue/RetryHandler.php';
require_once dirname(__DIR__) . '/src/Integrations/WordPress/Option/BotOptionRepository.php';
require_once dirname(__DIR__) . '/src/Integrations/Telegram/Webhook/TelegramWebhookVerifier.php';
