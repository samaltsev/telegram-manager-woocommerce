<?php
declare(strict_types=1);

namespace WCTM\Bootstrap;

use RuntimeException;

class Container
{
    protected array $instances = [];
    protected array $resolvers = [];

    public function get(string $id)
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (array_key_exists($id, $this->resolvers)) {
            $this->instances[$id] = ($this->resolvers[$id])($this);
            return $this->instances[$id];
        }

        throw new RuntimeException("Service '{$id}' is not registered in the container.");
    }

    public function set(string $id, $resolver): void
    {
        unset($this->instances[$id]);

        if (is_callable($resolver)) {
            $this->resolvers[$id] = $resolver;
            return;
        }

        $this->instances[$id] = $resolver;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances)
            || array_key_exists($id, $this->resolvers);
    }
}
