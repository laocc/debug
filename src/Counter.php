<?php
declare(strict_types=1);

namespace esp\debug;

use esp\core\db\Redis;
use esp\core\Request;
use function esp\helper\mk_dir;

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
            $redis->hIncrBy($conf['concurrent'] . '_concurrent_' . date('Y_m_d'), strval(time()), 1);
        }
    }


    /**
     * 记录mysql并发
     *
     * @param string $action
     * @param string $sql
     * @param int $traceLevel
     * @throws \ErrorException
     */
    public function recodeMysql(string $action, string $sql, int $traceLevel)
    {
        $key = $this->conf['mysql'] ?? null;
        if (!$key) return;
        $time = time();
        $this->redis->hIncrBy("{$key}_mysql_" . date('Y_m_d', $time), $action . '.' . strval($time), 1);
        if ($traceLevel === 0) return;


        $logPath = strval($this->conf['mysql_log'] ?? '');
        if ($logPath) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, ($traceLevel + 1));
            $trace = $trace[$traceLevel] ?? [];

            $log = [
                'time' => date('H:i:s', $time),
                'sql' => $sql,
                'file' => str_replace(_ROOT, '', $trace['file'] ?? ''),
                'line' => $trace['line'] ?? '0',
                'url' => _URL,
            ];
            $fil = rtrim($logPath, '/') . date('/Y-m-d/Hi', $time) . '.log';
            mk_dir($fil);

            file_put_contents($fil, json_encode($log, 256 | 64) . "\n\n", FILE_APPEND);
        }

        $key = $this->conf['mysql_count'] ?? null;
        if ($key) {
            $log = [
                'sql' => preg_replace(['/\:\w+/', '/\([\d\.]+,[\d\.]+\)/', '/\d{2,}/'], '%s', $sql),
                'file' => str_replace(_ROOT, '', $trace['file'] ?? ''),
                'line' => $trace['line'] ?? '0',
            ];
            $sqlMd5 = md5($log['sql'] . $log['file'] . $log['line']);
            $fil = _RUNTIME . '/mysql_md5/' . date('Y-m-d/', $time) . $sqlMd5 . '.log';
            mk_dir($fil);
            if (!is_file($fil)) {
                file_put_contents($fil, json_encode($log, 256 | 64 | 128));
            }

            $this->redis->hIncrBy($key . '_run_' . date('Y_m_d', $time), $sqlMd5, 1);
        }


    }

    public function getTopMysql(int $time = 0, int $limit = 100, int $minRun = 1)
    {
        if (!$this->conf['mysql_count']) return [];
        if (!$time) $time = time();
        $key = "{$this->conf['mysql_count']}_run_" . date('Y_m_d', $time);
        $value = $this->redis->hGetAlls($key);
        arsort($value);
        $topValue = [];
        $i = 0;
        foreach ($value as $k => $val) {
            if ($val < $minRun) break;
            $fil = _RUNTIME . '/mysql_md5/' . date('Y-m-d/', $time) . $k . '.log';
            if (!is_readable($fil)) {
                $sql = [
                    'key' => $k,
                    'run' => $val,
                    'sql' => 'undefined',
                    'file' => 'undefined',
                    'line' => '',
                ];
            } else {
                $js = file_get_contents($fil);
                $sql = json_decode($js, true);
                $sql['key'] = $k;
                $sql['run'] = $val;
            }
            $topValue[] = $sql;
            if ($limit > 0 and $i++ >= $limit) break;
        }
        return $topValue;
    }

    /**
     * 记录各控制器请求计数 若是非法请求，不记录
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
     * 获取最大并发数统计
     *
     * @param int $time
     * @param int $step 全天1440分钟，分成多少段，
     *      若=1，则返回数据是1440个，
     *      若=15则返回96段，即每15分钟数据合并成一段，
     *      若=0则不处理直接返回原始数据
     * @return array
     */
    public function getConcurrent(int $time = 0, int $step = 1)
    {
        if (!$this->conf['concurrent']) return [];
        if (!$time) $time = time();
        $key = "{$this->conf['concurrent']}_concurrent_" . date('Y_m_d', $time);
        $value = $this->redis->hGetAlls($key);
        if ($step === 0) return $value;
        $dTime = strtotime(date('Ymd'));
        arsort($value);
        $maxCont = [];
        $sumCont = [];
        $max = 0;

        foreach ($value as $tm => $val) {
            $m = date('H:i', intval($tm));
            $max = max($max, $val);

            if (!isset($maxCont[$m])) {
                $maxCont[$m] = $val;
            } else if ($maxCont[$m] < $val) {
                $maxCont[$m] = $val;
            }

            if (isset($sumCont[$m])) {
                $sumCont[$m] += $val;
            } else {
                $sumCont[$m] = $val;
            }
        }
        for ($h = 0; $h < 1440; $h++) {
            $s = date('H:i', $dTime + $h * 60);
            if (!isset($sumCont[$s])) $sumCont[$s] = 0;
            if (!isset($maxCont[$s])) $maxCont[$s] = 0;
        }
        ksort($maxCont);
        ksort($sumCont);
        $sumCont = array_values($sumCont);
        $maxCont = array_values($maxCont);

        $labels = [];
        for ($h = 0; $h < 1440 / $step; $h++) {
            $labels[] = date('H:i', $dTime + $h * 60 * $step);
        }

        $average = $maximum = [];
        for ($h = 0; $h < 1440; $h++) {
            $a = intval($h / $step);
            if (!isset($average[$a])) {
                $average[$a] = $sumCont[$h];
            } else {
                $average[$a] += $sumCont[$h];
            }
            if (!isset($maximum[$a])) {
                $maximum[$a] = $maxCont[$h];
            } else if ($maximum[$a] < $maxCont[$h]) {
                $maximum[$a] = $maxCont[$h];
            }
        }
        foreach ($average as $t => $v) {
            $average[$t] = ceil($v / (60 * $step));
        }

        /**
         * 假设$step=60，相当于返回24节，即每小时一段，返回数据如下：
         * $labels= ["00:00","01:00"..."22:00","23:00"];
         * $average=[1,3,5,6...83,32];共24段
         * $maximum结构与$average相同
         */

        return [
            'max' => $max,//当天最高并发值
            'label' => $labels,
            'average' => $average,
            'maximum' => $maximum,
        ];
    }

    public function getMysql(int $time = 0, int $step = 1, string $action = null)
    {
        if (!$this->conf['mysql']) return [];
        if (!$time) $time = time();
        $key = "{$this->conf['mysql']}_mysql_" . date('Y_m_d', $time);
        $value = $this->redis->hGetAlls($key);
        if ($step === 0) return $value;
        $dTime = strtotime(date('Ymd'));
        arsort($value);
        $maxCont = [];
        $sumCont = [];
        $max = 0;

        foreach ($value as $tm => $val) {
            $tk = explode('.', $tm);
            if ($action and $action !== $tk[0]) continue;

            $m = date('H:i', intval($tk[1]));
            $max = max($max, $val);

            if (!isset($maxCont[$m])) {
                $maxCont[$m] = $val;
            } else if ($maxCont[$m] < $val) {
                $maxCont[$m] = $val;
            }

            if (isset($sumCont[$m])) {
                $sumCont[$m] += $val;
            } else {
                $sumCont[$m] = $val;
            }
        }
        for ($h = 0; $h < 1440; $h++) {
            $s = date('H:i', $dTime + $h * 60);
            if (!isset($sumCont[$s])) $sumCont[$s] = 0;
            if (!isset($maxCont[$s])) $maxCont[$s] = 0;
        }
        ksort($maxCont);
        ksort($sumCont);
        $sumCont = array_values($sumCont);
        $maxCont = array_values($maxCont);

        $labels = [];
        for ($h = 0; $h < 1440 / $step; $h++) {
            $labels[] = date('H:i', $dTime + $h * 60 * $step);
        }

        $average = $maximum = [];
        for ($h = 0; $h < 1440; $h++) {
            $a = intval($h / $step);
            if (!isset($average[$a])) {
                $average[$a] = $sumCont[$h];
            } else {
                $average[$a] += $sumCont[$h];
            }
            if (!isset($maximum[$a])) {
                $maximum[$a] = $maxCont[$h];
            } else if ($maximum[$a] < $maxCont[$h]) {
                $maximum[$a] = $maxCont[$h];
            }
        }
        foreach ($average as $t => $v) {
            $average[$t] = ceil($v / (60 * $step));
        }

        /**
         * 假设$step=60，相当于返回24节，即每小时一段，返回数据如下：
         * $labels= ["00:00","01:00"..."22:00","23:00"];
         * $average=[1,3,5,6...83,32];共24段
         * $maximum结构与$average相同
         */

        return [
            'max' => $max,//当天最高并发值
            'label' => $labels,
            'average' => $average,
            'maximum' => $maximum,
        ];
    }

    /**
     * 读取（控制器、方法）计数器值表
     * @param int $time
     * @param bool|null $method
     * @return array|array[]
     */
    public function getCounter(int $time = 0, bool $method = null)
    {
        if ($time === 0) $time = time();
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
                if (!isset($sum[$h])) {
                    $sum[$h] = array_sum($val);
                } else {
                    $sum[$h] += array_sum($val);
                }
            }
        }
        $data['_count_'] = $sum;
        return $data;
    }


}