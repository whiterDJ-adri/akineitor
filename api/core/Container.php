<?php

namespace Core;

class Container
{
    private array $factories = [];
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (!isset($this->factories[$id])) {
            throw new \RuntimeException("No provider for {$id}");
        }
        $this->instances[$id] = ($this->factories[$id])($this);
        return $this->instances[$id];
    }
}