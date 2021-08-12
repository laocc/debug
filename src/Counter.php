<?php

namespace esp\debug;


use esp\core\db\Redis;
use esp\core\Request;

class Counter
{
    private $conf;
    private $redis;
    private $request;

    public function __construct(array $conf, Redis $redis, Request $request = null)
    {
        $this->conf = $conf;
        $this->redis = $redis;
        $this->request = $request;

        //统计最大并发
        if ($conf['concurrent'] ?? 0) {
            $redis->hIncrBy($conf['concurrent'] . '_concurrent_' . date('Y_m_d'), '' . _TIME, 1);
        }
    }

    /**
     *
     * 统计最大并发
     * 记录各控制器请求计数 若是非法请求，不记录
     *
     */
    public function recodeCounter()
    {
        if (!$this->request->exists or !$this->conf['counter']) return;

        //记录各控制器请求计数
        $counter = $this->conf['counter'];

        $key = sprintf('%s/%s/%s/%s/%s/%s', date('H'),
            $this->request->method, $this->request->virtual, $this->request->module ?: 'auto',
            $this->request->controller, $this->request->action);
        if (is_array($counter)) {
            $counter += ['key' => 'DEBUG', 'params' => 0];
            $hKey = "{$counter['key']}_counter_" . date('Y_m_d');
            if ($counter['params'] and $this->request->params[0] ?? null) {
                $key .= "/{$this->request->params[0]}";
            }

        } else {
            $hKey = "{$counter}_counter_" . date('Y_m_d');
        }
        $this->redis->hIncrBy($hKey, $key, 1);

    }

    /**
     * 获取最大并发数
     * @param int $time
     * @return array
     */
    public function getConcurrent(int $time = _TIME)
    {
        if (!$this->conf['concurrent']) return [];
        $key = "{$this->conf['concurrent']}_concurrent_" . date('Y_m_d', $time);
        $all = $this->redis->hGetAlls($key);
//        arsort($all);
        return $all;
    }

    /**
     * 读取（控制器、方法）计数器值表
     * @param int $time
     * @param bool|null $method
     * @return array|array[]
     */
    public function getCounter(int $time = 0, bool $method = null)
    {
        if ($time === 0) $time = _TIME;
        $conf = $this->conf['counter'];
        if (!$conf) return [];
        if (is_array($conf)) {
            if (!isset($conf['key'])) throw new \Error("counter.key未定义");
            $key = "{$conf['key']}_counter_" . date('Y_m_d', $time);
        } else {
            $key = "{$conf}_counter_" . date('Y_m_d', $time);
        }

        $all = $this->redis->hGetAlls($key);
        if (empty($all)) return ['data' => [], 'action' => []];

        $data = [];
        foreach ($all as $hs => $hc) {
            //实际这里是7段，分为5段就行，后三段连起来
            $key = explode('/', $hs, 5);

            $hour = (intval($key[0]) + 1);
            $ca = "/{$key[4]}";
            switch ($method) {
                case true:
                    $ca = "{$key[1]}:{$ca}";
                    break;
                case false;
                    break;
                default:
                    $ca .= ucfirst($key[1]);
                    break;
            }
            $vm = "{$key[2]}.{$key[2]}";
            if (!isset($data[$vm])) $data[$vm] = ['action' => [], 'data' => []];
            if (!isset($data[$vm]['data'][$hour])) $data[$vm]['data'][$hour] = [];
            $data[$vm]['data'][$hour][$ca] = $hc;
            if (!in_array($ca, $data[$vm]['action'])) $data[$vm]['action'][] = $ca;
            sort($data[$vm]['action']);
        }
        $sum = [];
        foreach ($data as $vm => $vml) {
            foreach ($vml['data'] as $h => $val) {
                $sum[$h] = array_sum($val);
            }
        }
        $data['_count_'] = $sum;
        return $data;
    }


}