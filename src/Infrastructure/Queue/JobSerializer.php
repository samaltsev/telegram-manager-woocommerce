<?php
declare(strict_types=1);
namespace WCTM\Infrastructure\Queue;

use WCTM\Domain\Queue\DTO\SendTelegramMessageJobData;

if (!defined('ABSPATH')) exit;

/**
 * Сериализация/десериализация данных задач.
 */
class JobSerializer
{
    public function normalizePayload(string $jobType, SendTelegramMessageJobData $data): array
    {
        return $data->toArray();
    }

    public function denormalizePayload(array $payload, string $jobType): SendTelegramMessageJobData
    {
        return SendTelegramMessageJobData::fromArray($payload);
    }
}
