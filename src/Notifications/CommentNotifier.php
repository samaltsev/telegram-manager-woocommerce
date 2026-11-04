<?php
declare(strict_types=1);
namespace WCTM\Notifications;

use WCTM\Integrations\WordPress\Option\BotSettingsRepository;
use WCTM\Integrations\WordPress\Option\NotificationSettingsRepository;
use WCTM\Integrations\WordPress\Option\TemplateSettingsRepository;
use WCTM\Queue\MessageQueue;
use WCTM\Telegram\ChatTarget;
use WCTM\Templates\TemplateParser;
use WCTM\Templates\TemplateTags;

if (!defined('ABSPATH')) {
    exit;
}

class CommentNotifier
{
    protected NotificationSettingsRepository $notifications;
    protected BotSettingsRepository $bots;
    protected TemplateSettingsRepository $templates;
    protected MessageQueue $queue;
    protected TemplateParser $parser;

    public function __construct(
        NotificationSettingsRepository $notifications,
        BotSettingsRepository $bots,
        TemplateSettingsRepository $templates,
        MessageQueue $queue,
        TemplateParser $parser
    ) {
        $this->notifications = $notifications;
        $this->bots = $bots;
        $this->templates = $templates;
        $this->queue = $queue;
        $this->parser = $parser;
    }

    public function queueCommentNotification($commentId, $approved)
    {
        if (!$approved || !$this->notifications->get('notify_comments', 0)) {
            return;
        }

        $comment = get_comment($commentId);
        if (!$comment) {
            return;
        }

        $raw = (string) $this->bots->get('chat_targets', '');
        if ($this->bots->get('admin_chat_id', '')) {
            $raw = trim((string) $this->bots->get('admin_chat_id', '')) . ($raw ? "\n" . $raw : '');
        }

        $targets = ChatTarget::parseMany($raw);
        $text = $this->parser->parse((string) $this->templates->get('template_comment', ''), TemplateTags::forComment($comment));
        $this->queue->enqueueForTargets('comment_notification', array('text' => $text), $targets);
    }
}
