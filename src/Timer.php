<?php

namespace esp\debug;


final class Timer
{
    private array $time = [];
    private float $prev = 0;

    public function __construct()
    {
        $this->prev = microtime(true);
    }

    /**
     * @param string $node
     * @return void
     */
    public function node(string $node): void
    {
        $now = microtime(true);
        $this->time[] = [
            'node' => $node,
            'time' => sprintf("%.4f", $now),
            'diff' => sprintf("% 9.2f", $now - $this->prev),
        ];
        $this->prev = $now;
    }

    public function value(): array
    {
        return $this->time;
    }
}