<?php
declare(strict_types=1);

namespace WCTM\AdminBot;

final class AdminBotSubscriber
{
    public function __construct(
        private int $id,
        private string $chatId,
        private string $username,
        private string $status
    ) {
    }

    public function id(): int { return $this->id; }
    public function chatId(): string { return $this->chatId; }
    public function username(): string { return $this->username; }
    public function status(): string { return $this->status; }
}
