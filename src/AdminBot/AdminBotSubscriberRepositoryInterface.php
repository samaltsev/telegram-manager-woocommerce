<?php
declare(strict_types=1);

namespace WCTM\AdminBot;


if (!defined('ABSPATH')) exit;

interface AdminBotSubscriberRepositoryInterface
{
    public function addSubscriber(string $chatId, ?string $username = null, array $meta = []): bool;

    public function updateStatusByChatId(string $chatId, string $status): bool;

    public function findByChatId(string $chatId): ?AdminBotSubscriber;

    public function all(string $search = ''): array;
}
