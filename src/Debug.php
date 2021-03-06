<?php
declare(strict_types=1);

namespace esp\debug;

use function esp\helper\mk_dir;
use function esp\helper\save_file;
use esp\http\Http;

class Debug extends \esp\core\Debug
{
    private $prevTime;
    private $isMaster;
    private $memory;
    private $_run;
    private $_star;
    private $_time;
    private $_value = array();
    private $_print_format = '% 9.3f';
    private $_node = array();
    private $_node_len = 0;
    private $_mysql = array();
    private $_conf;
    private $_errorText;
    private $_ROOT_len = 0;
    private $_rpc = [];
    private $_transfer_uri = '/_esp_debug_transfer';
    private $_transfer_path = '';
    private $_zip = 0;


    public function __construct(array $conf)
    {
        $this->_conf = $conf + ['path' => _RUNTIME, 'run' => false, 'host' => [], 'counter' => false];

        //压缩日志，若启用压缩，则运维不能直接在服务器中执行日志查找关键词
        $this->_zip = intval($this->_conf['zip'] ?? 0);

        if (defined('_RPC')) {
            $this->_rpc = _RPC;
            $this->mode = 'rpc';

            $this->isMaster = is_file(_RUNTIME . '/master.lock');

            //当前是主服务器，还继续判断保存方式
            if ($this->isMaster) {
                $this->mode = 'shutdown';
                if (isset($conf['master'])) $this->mode = $conf['master'];
                if (isset($conf['transfer'])) $this->_transfer_path = $conf['transfer'];

                //保存节点服务器发来的日志
                if (_VIRTUAL === 'rpc' && _URI === $this->_transfer_uri) {
                    $save = $this->transferDebug();
                    exit(getenv('SERVER_ADDR') . ";Length={$save};Time:" . microtime(true));
                }
            }
        }

        $this->_star = [$_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true), memory_get_usage()];

        $this->_ROOT_len = strlen(_ROOT);
        $this->_run = boolval($this->_conf['auto'] ?? 1);
        $this->_time = microtime(true);
        $this->prevTime = microtime(true) - $this->_star[0];
        $this->memory = memory_get_usage();
        $this->_node[0] = [
            't' => sprintf($this->_print_format, $this->prevTime * 1000),
            'm' => sprintf($this->_print_format, ($this->memory - $this->_star[1]) / 1024),
            'n' => sprintf($this->_print_format, ($this->memory) / 1024),
            'g' => ''];
        $this->prevTime = microtime(true);
        $this->relay('START', []);
    }

    /**
     * 将节点发来的日志保存到指定目录，或者直接保存
     * 当前只会在master中执行
     *
     * @return bool|int|string
     */
    private function transferDebug()
    {
        $input = file_get_contents("php://input");
        if (empty($input)) return 'null';

        $array = json_decode($input, true);
        if (empty($array['data'])) $array['data'] = 'NULL Data';
        else {
            $array['data'] = base64_decode($array['data']);
            if (!$this->_zip) $array['data'] = gzuncompress($array['data']);
        }

        if (is_array($array['data'])) $array['data'] = print_r($array['data'], true);

        //临时中转文件
        if ($this->mode === 'transfer') {
            $move = $this->_transfer_path . '/' . urlencode(base64_encode($array['filename']));
            return save_file($move, $array['data'], false);
        }

        return save_file($array['filename'], $array['data'], false);
    }


    /**
     * 读取日志
     * @param string $file
     * @return string
     */
    public function read(string $file): string
    {
        $rFile = realpath($file);
        if (!$rFile or !is_readable($rFile)) return "## 日志文件不存在或无权限读取：\n{$file}";
        $text = file_get_contents($rFile);
        if (substr($rFile, -4) === '.mdz') $text = gzuncompress($text);
        return $text;
    }


    /**
     * 将move里的临时文件移入真实目录
     * 在并发较大时，需要将日志放入临时目录，由后台移到目标目录中
     * 因为在大并发时，创建新目录的速度可能跟不上系统请求速度，有时候发生目录已存在的错误
     *
     * @param bool $show
     * @param string|null $path
     */
    public static function move(bool $show = false, string $path = null)
    {
        if (!_CLI) throw new \Error('debug->move() 只能运行于CLI环境');

        if (is_null($path)) $path = _RUNTIME . '/debug/move';
        $time = 0;

        reMove:
        $time++;
        $dir = new \DirectoryIterator($path);
        $array = array();
        foreach ($dir as $i => $f) {
            if ($i > 100) break;
            if ($f->isFile()) $array[] = $f->getFilename();
        }
        if (empty($array)) return;

        if ($show) echo date('Y-m-d H:i:s') . "\tmoveDEBUG({$time}):\t" . json_encode($array, 256 | 64) . "\n";

        foreach ($array as $file) {
            try {
                $move = base64_decode(urldecode($file));
                if (empty($move) or $move[0] !== '/') {
                    @unlink("{$path}/{$file}");
                    continue;
                }
                mk_dir($move);
                rename("{$path}/{$file}", $move);
            } catch (\Error $e) {
                print_r(['moveDebug' => $e]);
            }
        }
        goto reMove;
    }


    public function error($error, $tract = null)
    {
        if (is_null($tract)) {
            $tract = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        } else if (is_int($tract)) {
            $tract = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $tract + 1)[$tract] ?? [];
        }
        $info = [
            'time' => date('Y-m-d H:i:s'),
            'HOST' => getenv('SERVER_ADDR'),
            'Url' => _HTTP_ . _DOMAIN . _URI,
            'Referer' => getenv("HTTP_REFERER"),
            'Debug' => $this->filename(),
            'Trace' => $tract,
            'Error' => $error,
            'Server' => $_SERVER,
        ];
        if (is_array($error)) $error = json_encode($error, 256 | 64 | 128);
        $this->relay("[red;{$error}]");
        $conf = ['filename' => 'YmdHis', 'path' => $this->_conf['error'] ?? (_RUNTIME . '/error')];
        $filename = $conf['path'] . "/" . date($conf['filename']) . mt_rand() . '.md';
        return $this->save_file($filename, json_encode($info, 64 | 128 | 256));
    }

    public function warn($error, $tract = null)
    {
        if (is_null($tract)) {
            $tract = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        } else if (is_int($tract)) {
            $tract = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $tract + 1)[$tract] ?? [];
        }

        $info = [
            'time' => date('Y-m-d H:i:s'),
            'HOST' => getenv('SERVER_ADDR'),
            'Url' => _HTTP_ . _DOMAIN . _URI,
            'Referer' => getenv("HTTP_REFERER"),
            'Debug' => $this->filename(),
            'Trace' => $tract,
            'Error' => $error,
            'Server' => $_SERVER,
        ];
        $conf = ['filename' => 'YmdHis', 'path' => $this->_conf['warn'] ?? (_RUNTIME . '/warn')];
        $filename = $conf['path'] . "/" . date($conf['filename']) . mt_rand() . '.md';
        return $this->save_file($filename, json_encode($info, 64 | 128 | 256));
    }

    /**
     * @param string $filename
     * @param string $data
     * @return string
     */
    public function save_file(string $filename, string $data)
    {
        //这是从Error中发来的保存错误日志
        if ($filename[0] !== '/') {
            $path = $this->_conf['error'] ?? (_RUNTIME . '/error');
            $filename = "{$path}/{$filename}";
        }

        if ($this->_zip and $filename[-1] !== 'z') $filename .= 'z';

        $send = null;

        if ($this->mode === 'transfer') {
            //当前发生在master中，若有定义transfer，则直接发到中转目录
            if ($this->_zip > 0) $data = gzcompress($data, $this->_zip);
            return save_file($this->_transfer_path . '/' . urlencode(base64_encode($filename)), $data, false);

        } else if ($this->mode === 'rpc' and $this->_rpc) {

            /**
             * 发到RPC，写入move专用目录，然后由后台移到实际目录
             */
            $post = json_encode([
                'filename' => $filename,
                'data' => base64_encode(gzcompress($data, $this->_zip ?: 5))
            ], 256 | 64);

            $http = new Http();
            $send = $http->rpc($this->_rpc)
                ->encode('html')
                ->data($post)
                ->post($this->_transfer_uri)
                ->html();
            return "Rpc:{$send}";
        }

        if ($this->_zip > 0) $data = gzcompress($data, $this->_zip);

        return save_file($filename, $data, false);
    }

    private $router = [];
    private $response = ['type' => null, 'display' => null];

    public function setRouter(array $request): void
    {
        $this->router = $request + [
                'virtual' => null,
                'method' => null,
                'module' => null,
                'controller' => null,
                'action' => null,
                'exists' => null,
                'params' => [],
            ];
    }

    public function setResponse(array $result): void
    {
        $this->response = $result + [
                'type' => null,
                'display' => null,
            ];
    }

    /**
     * 保存记录到的数据
     * @param string $pre
     * @return string
     */
    public function save_logs(string $pre = '')
    {
        if (empty($this->_node)) return 'empty node';
        else if ($this->_run === false) return 'debug not star or be stop';

        $filename = $this->filename();
        if (empty($filename)) return 'null filename';

        //长耗时间记录
        if (($limitTime = ($this->_conf['limit'] ?? 0)) and ($u = microtime(true) - $this->_time) > $limitTime / 1000) {
            $this->error("耗时过长：总用时{$u}秒，超过限制{$limitTime}ms");
        }

        //其他未通过类，而是直接通过公共变量送入的日志
        $this->relay('END:save_logs', []);
        $rq = $this->router;
        $data = array();
        $data[] = "## 请求数据\n```\n";
        $data[] = " - SaveBy:\t{$pre}\n";
        $data[] = " - METHOD:\t{$rq['method']}\n";
        $data[] = " - GET_URL:\t" . (defined('_URL') ? _URL : '') . "\n";
        $data[] = " - SERV_IP:\t" . ($_SERVER['HTTP_X_SERV_IP'] ?? ($_SERVER['SERVER_ADDR'] ?? '')) . "\n";
        $data[] = " - USER_IP:\t" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
        $data[] = " - REAL_IP:\t" . _CIP . "\n";
        $data[] = " - DATETIME:\t" . date('Y-m-d H:i:s', intval($this->_time)) . "\n";
        $data[] = " - PHP_VER:\t" . phpversion() . "\n";
        $data[] = " - AGENT:\t" . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n";
        $data[] = " - ROOT:\t" . _ROOT . "\n";
        $data[] = " - Router:\t" . json_encode($rq, 256 | 64) . "\n```\n";
        if (!$rq['exists']) goto save;//请求了不存在的控制器

        if (!empty($this->_value)) {
            $data[] = "\n## 程序附加\n```\n";
            foreach ($this->_value as $k => &$v) $data[] = " - {$k}:\t{$v}\n";
            $data[] = "```\n";
        }

        $data[] = "\n## 执行顺序\n```\n\t\t耗时\t\t耗内存\t\t占内存\t\n";
        if (isset($this->_node[0])) {
            $data[] = "  {$this->_node[0]['t']}\t{$this->_node[0]['m']}\t{$this->_node[0]['n']}\t{$this->_node[0]['g']}进程启动到Debug被创建的消耗总量\n";
            unset($this->_node[0]);
        }
        $data[] = "" . (str_repeat('-', 100)) . "\n";
        //具体监控点
        $len = min($this->_node_len + 3, 50);
        foreach ($this->_node as $i => &$row) {
            $data[] = "  {$row['t']}\t{$row['m']}\t{$row['n']}\t" . sprintf("%-{$len}s", $row['g']) . "\t{$row['f']}\n";
        }

        $data[] = "" . (str_repeat('-', 100)) . "\n";
        $time = sprintf($this->_print_format, (microtime(true) - $this->_star[0]) * 1000);
        $memo = sprintf($this->_print_format, (memory_get_usage() - $this->_star[1]) / 1024);
        $total = sprintf($this->_print_format, (memory_get_usage()) / 1024);
        $data[] = "  {$time}\t{$memo}\t{$total}\t进程启动到Debug结束时的消耗总量\n```\n";

        if (!empty($this->_errorText)) {
            $data[] = "\n\n##程序出错1：\n```\n{$this->_errorText}\n```\n";
        }
        $e = error_get_last();
        if (!empty($e)) {
            $data[] = "\n\n##程序出错0：\n```\n" . print_r($e, true) . "\n```\n";
        }

        if (!empty($print = $this->_conf['print'])) {

            if (($print['mysql'] ?? 0) and !empty($this->_mysql)) {
                $slow = array();
                foreach ($this->_mysql as $i => $sql) {
                    if (intval($sql['wait']) > 20) $slow[] = $i;
                }
                $data[] = "\n## Mysql 顺序：\n";
                $data[] = " - 当前共执行MYSQL：\t" . count($this->_mysql) . " 次\n";
                if (!empty($slow)) $data[] = " - 超过20Ms的语句有：\t" . implode(',', $slow) . "\n";
                $data[] = "```\n" . print_r($this->_mysql, true) . "\n```";
            }

            if (($print['post'] ?? 0) and ($rq['method'] === 'POST')) {
                $data[] = "\n## Post原始数据：\n```\n" . file_get_contents("php://input") . "\n```\n";
            }

            if ($print['html'] ?? 0) {
                $data[] = "\n## 页面实际响应： \n";
                $headers = headers_list();
                headers_sent($hFile, $hLin);
                $headers[] = "HeaderSent: {$hFile}($hLin)";
                $data[] = "\n### Headers\n```\n" . json_encode($headers, 256 | 128 | 64) . "\n```\n";
                if ($this->response['type']) {
                    $data[] = "\n### Content-Type:{$this->response['type']}\n```\n" . $this->response['display'] . "\n```\n";
                } else {
                    $data[] = "\n### Write:\n```\n" . ob_get_contents() . "\n```\n";
                }
            }

            if ($print['server'] ?? 0) {
                $data['_SERVER'] = "\n## _SERVER\n```\n" . print_r($_SERVER, true) . "\n```\n";
            }
        }

        $data[] = "\n## 最后保存：" . microtime(true) . "\n";

        save:
        $this->_run = false;
        $this->_node = [];

        return $this->save_file($filename, implode($data));
    }


    /**
     * 设置是否记录几个值
     * @param string $type
     * @param bool $val
     * @return $this
     */
    public function setPrint(string $type, bool $val = null)
    {
        if ($type === 'null') {
            $this->_conf['print'] = [];
        } else {
            $this->_conf['print'][$type] = $val;
        }
        return $this;
    }

    /**
     * 禁用debug
     * @param int $mt 禁用几率，
     * 0    =完全禁用
     * 1-99 =1/x几率启用
     * 1    =1/2机会
     * 99   =1%的机会启用
     * 100  =启用
     * @return $this
     */
    public function disable(int $mt = 0)
    {
        if ($mt === 100) {
            $this->_run = true;
            return $this;
        }
        if ($mt > 0 && mt_rand(0, $mt) === 1) return $this;
        $this->_run = false;
        return $this;
    }

    /**
     * 启动，若程序入口已经启动，这里则不需要执行
     * @param null $pre
     * @return $this
     */
    public function star($pre = null)
    {
        $this->_run = true;
        $pre = $pre ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $this->relay('STAR BY HANDer', $pre);//创建起点
        return $this;
    }


    /**
     * 停止记录，只是停止记录，不是禁止
     * @param null $pre
     * @return $this|null
     */
    public function stop($pre = null)
    {
        if (!$this->_run) return null;
        if (!empty($this->_node)) {
            $pre = $pre ?: debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            $this->relay('STOP BY HANDer', $pre);//创建一个结束点
        }
        $this->_run = null;
        return $this;
    }

    public function __set(string $name, $value)
    {
        $this->_value[$name] = $value;
    }

    public function __get(string $name)
    {
        return $this->_value[$name] ?? null;
    }

    public function mysql_log($val, $pre = null)
    {
        if ($this->_run === false or !($this->_conf['print']['mysql'] ?? 0)) return $this;
        static $count = 0;
        if (is_null($pre)) $pre = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        $this->relay("Mysql[" . (++$count) . '] = ' . print_r($val, true) . str_repeat('-', 10) . '>', $pre);
        return $this;
    }


    /**
     * 创建一个debug点
     *
     * @param $msg
     * @param array|null $prev 调用的位置，若是通过中间件调用，请在调用此函数时提供下面的内容：
     * @return $this|bool
     */
    public function relay($msg, array $prev = null): Debug
    {
        if (!$this->_run) return $this;
        if (is_null($prev)) $prev = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        if (isset($prev['file'])) {
            $file = substr($prev['file'], $this->_ROOT_len) . " [{$prev['line']}]";
        } else {
            $file = null;
        }
        if (is_array($msg)) $msg = "\n" . print_r($msg, true);
        elseif (is_object($msg)) $msg = "\n" . print_r($msg, true);
        elseif (is_null($msg)) $msg = "\n" . var_export($msg, true);
        elseif (is_bool($msg)) $msg = "\n" . var_export($msg, true);
        elseif (!is_string($msg)) $msg = strval($msg);

        $this->_node_len = max(\iconv_strlen($msg), $this->_node_len);
        $nowMemo = memory_get_usage();
        $time = sprintf($this->_print_format, (microtime(true) - $this->prevTime) * 1000);
        $memo = sprintf($this->_print_format, ($nowMemo - $this->memory) / 1024);
        $now = sprintf($this->_print_format, ($nowMemo) / 1024);
        $this->prevTime = microtime(true);
        $this->memory = $nowMemo;
        $this->_node[] = ['t' => $time, 'm' => $memo, 'n' => $now, 'g' => $msg, 'f' => $file];
        return $this;
    }

    private $_folder;
    private $_root;
    private $_path = '';
    private $_file;
    private $_filename;
    private $_hasError = false;

    /**
     * 设置或读取debug文件保存的根目录
     * @param $path
     * @return $this|string
     */
    public function root(string $path = null)
    {
        if (is_null($path)) {
            if (is_null($this->_root))
                return $this->_root = str_replace(
                    ['{RUNTIME}', '{ROOT}', '{VIRTUAL}', '{DATE}'],
                    [_RUNTIME, _ROOT, _VIRTUAL, date('Y_m_d')],
                    $this->_conf['path']);
            return $this->_root;
        }
        $this->_root = '/' . trim($path, '/');
        if (!in_array(_HOST, $this->_conf['host'])) $this->_root .= "/hackers";
        return $this;
    }

    /**
     * 修改前置目录，前置目录从域名或module之后开始
     * @param string|null $path
     * @return $this|string
     */
    public function folder(string $path = null)
    {
        $m = $this->router['module'];
        if (!empty($m)) $m = strtoupper($m) . "/";

        if (is_null($path)) {
            if (is_null($this->_folder)) {
                return $this->_folder = '/' . _DOMAIN . "/{$m}{$this->router['controller']}/{$this->router['action']}" . ucfirst($this->router['method']);
            }
            return $this->_folder;
        }
        $path = trim($path, '/');
        $this->_folder = '/' . _DOMAIN . "/{$m}{$path}/{$this->router['controller']}/{$this->router['action']}" . ucfirst($this->router['method']);
        return $this;
    }

    /**
     * 修改后置目录
     * @param string|null $path
     * @param bool $append
     * @return $this|string
     */
    public function path(string $path = null, bool $append = false)
    {
        if (is_null($path)) return $this->_path;
        if ($append) {
            $this->_path .= '/' . trim($path, '/');
        } else {
            $this->_path = '/' . trim($path, '/');
        }
        return $this;
    }


    /**
     * 指定完整的目录，也就是不采用控制器名称
     * @param string|null $path
     * @return $this|string
     */
    public function fullPath(string $path = null)
    {
        if (is_null($path)) return $this->folder() . $this->path();
        $m = $this->router['module'];
        if ($m) $m = "/{$m}";
        $path = trim($path, '/');
        $this->_folder = '/' . _DOMAIN . "{$m}/{$path}";
        $this->_path = '';
        return $this;
    }

    /**
     * 设置文件名
     * @param $file
     * @return $this|string
     */
    public function file(string $file = null)
    {
        if (is_null($file)) {
            if (is_null($this->_file)) {
                list($s, $c) = explode('.', microtime(true) . '.0');
                return date($this->_conf['rules']['filename'], intval($s)) . "_{$c}_" . mt_rand(100, 999);
            }
            return $this->_file;
        }
        $this->_file = trim(trim($file, '.md'), '/');
        return $this;
    }

    /**
     * 设置，或读取完整的保存文件地址和名称
     * 如果运行一次后，第二次运行时不会覆盖之前的值，也就是只以第一次取得的值为准
     * @param string|null $file
     * @return null|string
     */
    public function filename(string $file = null): string
    {
        if (empty($this->router['controller'])) return '';
        if ($file) return $this->file($file);

        if (is_null($this->_filename)) {
            $root = $this->root();
            $folder = $this->folder();
            $file = $this->file();
            if ($this->_hasError) $file .= '_Error';
            $p = "{$root}{$folder}{$this->_path}";
            $this->_filename = "{$p}/{$file}.md";
            if ($this->_zip) $this->_filename .= 'z';
        }
        return $this->_filename;
    }

}