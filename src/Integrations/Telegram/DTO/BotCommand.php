<?php
declare(strict_types=1);

namespace WCTM\Integrations\Telegram\DTO;

final class BotCommand
{
    public function __construct(
        private string $command,
        private string $description
    ) {
        $this->command = ltrim(trim($this->command), '/');

        if ($this->command === '' || !preg_match('/^[a-zA-Z0-9_]{1,32}$/', $this->command)) {
            throw new \InvalidArgumentException('Invalid Telegram command.');
        }

        $this->description = trim($this->description);

        if ($this->description === '') {
            throw new \InvalidArgumentException('Command description cannot be empty.');
        }

        if (mb_strlen($this->description) > 256) {
            throw new \InvalidArgumentException('Command description is too long.');
        }
    }

    public function command(): string
    {
        return $this->command;
    }

    public function description(): string
    {
        return $this->description;
    }

    /**
     * @return array{command:string, description:string}
     */
    public function toArray(): array
    {
        return [
            'command' => $this->command,
            'description' => $this->description,
        ];
    }
}