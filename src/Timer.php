<?php

namespace esp\debug;


final class Timer
{
    private array $time = [];
    private float $prev = 0;
    private float $start = 0;

    public function __construct()
    {
        $nginx = floatval(getenv('REQUEST_TIME_FLOAT'));
        $now = microtime(true);
        $this->time[] = [
            'node' => 'nginx Start',
            'time' => sprintf("%.4f", $nginx),
            'diff' => sprintf("% 9.2f", ($now - $nginx) * 1000),
            'total' => sprintf("% 9.2f", ($now - $nginx) * 1000),
        ];
        $this->prev = $now;
        $this->start = $nginx;
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
            'total' => sprintf("% 9.2f", ($now - $this->start) * 1000),
        ];
        $this->prev = $now;
    }

    public function value(): array
    {
        $data = [];
        foreach ($this->time as $time) {
            $data[] = "{$time['time']}\t{$time['diff']}ms\t{$time['total']}ms\t{$time['node']}\n";
        }
        return $data;
    }
}