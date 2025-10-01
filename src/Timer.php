<?php

namespace esp\debug;


final class Timer
{
    private array $time = [];
    private float $prev = 0;

    public function __construct()
    {
        $nginx = floatval(getenv('REQUEST_TIME_FLOAT'));
        $now = microtime(true);
        $this->time[] = [
            'node' => 'nginx Start',
            'time' => sprintf("%.4f", $nginx),
            'diff' => sprintf("% 9.2f", ($now - $nginx) * 1000),
        ];
        $this->prev = $now;
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
            'diff' => sprintf("% 9.2f", ($now - $this->prev) * 1000),
        ];
        $this->prev = $now;
    }

    public function value(): array
    {
        return $this->time;
    }
}