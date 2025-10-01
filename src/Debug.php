<?php
declare(strict_types=1);

namespace esp\debug;

use DirectoryIterator;
use esp\error\Error;
use esp\core\Dispatcher;
use function esp\helper\esp_dump;
use function esp\helper\save_file;
use function iconv_strlen;

function for_iconv_strlen_handler(...$err)
{
    return true;
}

class Debug
{
    private Dispatcher $_dispatcher;
    private float $prevTime;
    private int $memory;
    private bool $_run;
    private array $_star;
    private float $_time;
    private array $_value = array();
    private string $_print_format = '% 9.3f';
    private array $_node = array();
    private int $_node_len = 0;
    private array $_mysql = array();
    private array $_conf;
    private string $_errorText;
    private string $_transfer_uri = '/_esp_debug_transfer';
    private string $_transfer_path = '';
    private int $_zip = 0;//压缩级别
    private int $_mysql_run = 0;//mysql执行了多少次

    private string $_domain = '/' . _DOMAIN;
    private string $_folder;
    private string $_symlink;
    private bool $_sure_symlink = false;
    private string $_root;
    private int $_ROOT_len = 0;

    private string $_path = '';
    private string $_file;
    private string $_filename;
    private bool $_hasError = false;

    private array $router = [];
    private array $response = ['type' => null, 'display' => null];

    public string $mode = 'cgi';

    public function __construct(Dispatcher $dispatcher, array $conf)
    {
        $this->_dispatcher = &$dispatcher;
        $this->_conf = $conf + ['path' => _RUNTIME, 'mode' => 'cgi', 'run' => false, 'host' => [], 'counter' => false];

        //压缩日志，若启用压缩，则运维不能直接在服务器中执行日志查找关键词
        $this->_zip = intval($this->_conf['zip'] ?? 0);
        $this->mode = $this->_conf['mode'];
        if ($this->mode === 'none') return;
        if (isset($conf['_transfer_path'])) $this->_transfer_path = $conf['_transfer_path'];

        /**
         * mode:
         * cgi:     直接保存，并不是在shutdown中执行，所以如果报错，前端可见
         * shutdown:程序结束时直接保存在本机，在shutdown中执行，若系统是分布式，需要另外想办法合并日志
         * transfer:用本地文件中转，由后台转存到实际目录，这只发生在主服务器的RPC中
         */

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
        $this->relay('START', 1);
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
        if (str_ends_with($rFile, '.mdz')) $text = gzuncompress($text);
        return $text;
    }


    /**
     * 将move里的临时文件移入真实目录
     * 在并发较大时，需要将日志放入临时目录，由后台移到目标目录中
     * 因为在大并发时，创建新目录的速度可能跟不上系统请求速度，有时候发生目录已存在的错误
     *
     * @param bool $show
     * @param string|null $path
     * @throws Error
     */
    public function moveTransfer(bool $show = false, string $path = null)
    {
        if (!_CLI) throw new Error('debug->moveTransfer() 只能运行于CLI环境');

        if (is_null($path)) $path = $this->_transfer_path;
        $time = 0;

        reMove:
        $time++;
        $dir = new DirectoryIterator($path);
        $array = array();
        foreach ($dir as $i => $f) {
            if ($f->isDot()) continue;
            if ($f->isFile()) {
                $array[] = $f->getFilename();
                if ($i > 100) break;//每次只移100个文件，防止文件太多卡死
            }
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
                $path = dirname($move);
                if (!file_exists($path)) @mkdir($path, 0740, true);
                rename("{$path}/{$file}", $move);
            } catch (\Error|\Exception $e) {
                print_r(['moveDebug' => $e]);
            }
        }
        goto reMove;
    }


    /**
     * @param $error
     * @param int $preLev
     * @return bool
     */
    public function error($error, int $preLev = 1): bool
    {
        $tract = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $preLev + 1)[0];
        $info = [
            'time' => date('Y-m-d H:i:s'),
            'HOST' => getenv('SERVER_ADDR'),
            'Url' => _URL,
            'Referer' => getenv("HTTP_REFERER"),
            'Debug' => $this->filename(),
            'Trace' => $tract,
            'Error' => $error,
            'Server' => $_SERVER,
        ];
        if (is_array($error)) $error = json_encode($error, 256 | 64 | 128);
        $this->relay("[red;{$error}]", $preLev + 1);
        $conf = ['filename' => 'YmdHis', 'path' => $this->_conf['error'] ?? (_RUNTIME . '/error')];
        $filename = $conf['path'] . "/" . date($conf['filename']) . mt_rand() . '.json';
        return $this->save_debug_file($filename, json_encode($info, 64 | 128 | 256));
    }

    /**
     * @param string $filename
     * @param string $data
     * @return bool
     */
    public function save_debug_file(string $filename, string $data): bool
    {
        if ($this->mode === 'none') return false;

        //这是从Error中发来的保存错误日志
        if ($filename[0] !== '/') {
            $path = $this->_conf['error'] ?? (_RUNTIME . '/error');
            $filename = "{$path}/{$filename}";
        }

        if ($this->_zip and $filename[-1] !== 'z') $filename .= 'z';
        if ($this->_zip > 0) $data = gzcompress($data, $this->_zip);

        if ($this->mode === 'transfer') {
            $filename = $this->_transfer_path . '/' . urlencode(base64_encode($filename));
        }

        return $this->save_md_file($filename, $data);
    }

    private function save_md_file(string $file, $content): bool
    {

        $save = (boolean)save_file($file, $content);


        /**
         * $this->_sure_symlink
         * 是指在这之前曾指定过其他目录，则可以按需创建软链接
         * 若之前没指定过目录，则不需要创建连接
         * 若symlink被禁用，需要在php.ini中解除禁用
         */
        if (isset($this->_symlink) and $this->_sure_symlink) {
            if (!empty($this->_symlink) and $this->_symlink[0] === '/') {
                $fileLink = $this->_symlink;
            } else {
                $fileLink = $this->realDebugFile();
            }
            try {
                $lPath = dirname($fileLink);
                if (!file_exists($lPath)) @mkdir($lPath, 0740, true);
            } catch (\Throwable $error) {

            }
            if (function_exists('symlink')) {
                \symlink($file, $fileLink);
            } else {
                file_put_contents($fileLink, $file);
            }
        }

        return $save;
    }

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

    public function setController(string $cont)
    {
        $this->router['entrance'] = "{$cont}->{$this->router['action']}{$this->router['method']}(...params)";
    }

    public function setResponse(array $result): void
    {
        $this->response = $result + ['type' => null, 'display' => null];
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
        else if ($this->mode === 'none') return 'debug not save';

        $filename = $this->filename();
        if (empty($filename)) return 'null filename';

        //长耗时间记录
        if (($limitTime = ($this->_conf['limit'] ?? 0)) and ($u = microtime(true) - $this->_time) > $limitTime / 1000) {
            $this->error("耗时过长，超过限制{$limitTime}ms");
            $this->relay("[blue;总用时{$u}秒]");
        }

        if ($this->_mysql_run > ($this->_conf['mysql_limit'] ?? 10000000)) {
            $this->error("连续执行{$this->_mysql_run}次SQL");
        }

        //其他未通过类，而是直接通过公共变量送入的日志
        $this->relay('END:save_logs', -1);
        $rq = &$this->router;
        $rq['params'] = json_encode($rq['params'], 320);

        $data = array();
        $data[] = "## 请求数据\n```\n";
        $data[] = " - SaveBy:\t{$pre}\n";
        $data[] = " - METHOD:\t{$rq['method']}\n";
        $data[] = " - GET_URL:\t" . (defined('_URL') ? _URL : '') . "\n";
        $data[] = " - SERV_IP:\t" . ($_SERVER['SERVER_ADDR'] ?? '') . "\n";
        $data[] = " - USER_IP:\t" . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
        $data[] = " - REAL_IP:\t" . _CIP . "\n";
        if (isset($_SERVER['HTTP_X_SERV_IP'])) $data[] = " - AGENT_IP:\t" . $_SERVER['HTTP_X_SERV_IP'] . "\n";
        $data[] = " - DATETIME:\t" . date('Y-m-d H:i:s', intval($this->_time)) . "\n";
        $data[] = " - PHP_VER:\t" . phpversion() . "\n";
        $data[] = " - AGENT:\t" . ($_SERVER['HTTP_USER_AGENT'] ?? '') . "\n";
        $data[] = " - ROOT:\t" . _ROOT . "\n";
        $data[] = " - Router:\t" . json_encode($rq, 256 | 64 | 128) . "\n```\n";
        if (!$rq['exists']) goto save;//请求了不存在的控制器

        if (!empty($this->_value)) {
            $data[] = "\n## 程序附加\n```\n";
            foreach ($this->_value as $k => $v) $data[] = " - {$k}:\t{$v}\n";
            $data[] = "```\n";
        }

        $data[] = "\n## 执行顺序\n```\n\t\t耗时\t\t耗内存\t\t占内存\t\n";
        if (isset($this->_node[0])) {
            $data[] = "  {$this->_node[0]['t']}\t{$this->_node[0]['m']}\t{$this->_node[0]['n']}\t{$this->_node[0]['g']}进程启动到Debug被创建的消耗总量\n";
            unset($this->_node[0]);
        }
        $data[] = str_repeat('-', 100) . "\n";
        //具体监控点
        $len = min($this->_node_len + 3, 50);
        foreach ($this->_node as $i => $row) {
            $data[] = "  {$row['t']}\t{$row['m']}\t{$row['n']}\t" . sprintf("%-{$len}s", $row['g']) . "\t{$row['f']}\n";
        }

        $data[] = str_repeat('-', 100) . "\n";
        $time = sprintf($this->_print_format, (microtime(true) - $this->_star[0]) * 1000);
        $memo = sprintf($this->_print_format, (memory_get_usage() - $this->_star[1]) / 1024);
        $total = sprintf($this->_print_format, (memory_get_usage()) / 1024);
        $data[] = "  {$time}\t{$memo}\t{$total}\t进程启动到Debug结束时的消耗总量\n```\n";

        if (isset($this->_errorText)) {
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
        $data[] = "\n## 实际运行：" . (microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? 0)) . "秒\n";

        if (isset($this->_dispatcher->_timer)) {
            $timer = $this->_dispatcher->_timer->value();
            $data[] = "```\n";
            foreach ($timer as $time) {
                $data[] = "{$time['time']}\t{$time['diff']}\t{$time['node']}\n";
            }
            $data[] = "```\n";
        }

        save:
        $this->_run = false;
        $this->_node = [];

        return $this->save_debug_file($filename, implode($data));
    }

    public function setErrorText(string $text): Debug
    {
        $this->_errorText = $text;
        return $this;
    }

    /**
     * 设置是否记录几个值
     * @param string $type
     *      可设置项有：mysql,post,server,html
     *      若设为null，则以上几项全部不记录
     *
     * @param bool $val
     * @return $this
     */
    public function setPrint(string $type, bool $val = null): Debug
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
    public function disable(int $mt = 0): Debug
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
     * @param int $pre
     * @return $this
     */
    public function star(int $pre = 1): Debug
    {
        $this->_run = true;
        $this->relay('STAR BY HANDer', $pre + 1);//创建起点
        return $this;
    }


    /**
     * 停止记录，只是停止记录，不是禁止
     * @param int $pre
     * @return Debug
     */
    public function stop(int $pre = 1): Debug
    {
        if (!$this->_run) return $this;
        if (!empty($this->_node)) {
            $this->relay('STOP BY HANDer', $pre + 1);//创建一个结束点
        }
        $this->_run = false;
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

    /**
     * @param $val
     * @param int $pre
     * @return Debug
     */
    public function mysql_log($val, int $pre = 1): Debug
    {
        if ($this->_run === false or !($this->_conf['print']['mysql'] ?? 0)) return $this;
        $this->relay("Mysql[" . (++$this->_mysql_run) . '] = ' . print_r($val, true) . str_repeat('-', 10) . '>', $pre);
        return $this;
    }


    /**
     * 创建一个debug点
     *
     * @param $msg
     * @param int $preLev 调用的位置，若是通过中间件调用，请在调用此函数时提供下面的内容：
     * @param array|null $prevTrace
     * @return Debug
     */
    public function relay($msg, int $preLev = 0, array $prevTrace = null): Debug
    {
        if (!$this->_run) return $this;
        else if ($this->mode === 'none') return $this;

        $prev = [];
        if ($preLev >= 0) {
            $prev = array_reverse(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $preLev))[0] ?? [];
        }
        if (empty($prev) and !empty($prevTrace)) $prev = $prevTrace;

        $file = null;
        if (is_array($prev) and isset($prev['file'])) {
            $file = substr($prev['file'], $this->_ROOT_len) . " [{$prev['line']}]";
        }

        if (is_null($msg)) $msg = 'NULL';
        if (is_array($msg)) $msg = "\n" . json_encode($msg, 256 | 64 | 128);
        elseif (is_object($msg)) $msg = "\n" . print_r($msg, true);
        elseif (!is_string($msg)) $msg = esp_dump($msg);

        try {
            $this->_node_len = max(iconv_strlen($msg), $this->_node_len);
        } catch (\Throwable $e) {
            $this->_node_len = 0;
        }

        $nowMemo = memory_get_usage();
        $time = sprintf($this->_print_format, (microtime(true) - $this->prevTime) * 1000);
        $memo = sprintf($this->_print_format, ($nowMemo - $this->memory) / 1024);
        $now = sprintf($this->_print_format, ($nowMemo) / 1024);
        $this->prevTime = microtime(true);
        $this->memory = $nowMemo;
        $this->_node[] = ['t' => $time, 'm' => $memo, 'n' => $now, 'g' => $msg, 'f' => $file];
        return $this;
    }

    /**
     * 设置或读取debug文件保存的根目录
     * @param string|null $path
     * @return $this|string
     */
    public function root(string $path = null)
    {
        if (is_null($path)) {
            if (!isset($this->_root))
                return $this->_root = str_replace(
                    ['{RUNTIME}', '{ROOT}', '{VIRTUAL}', '{DATE}'],
                    [_RUNTIME, _ROOT, _VIRTUAL, date('Y_m_d')],
                    $this->_conf['path']);
            return $this->_root;
        }
        $this->_root = '/' . trim($path, '/');
        if (!in_array(_HOST, $this->_conf['host'])) $this->_root .= "/hackers";
        $this->_sure_symlink = true;
        return $this;
    }

    public function setDomainPath(string $path)
    {
        $this->_domain = "/{$path}";
        if (!$path) $this->_domain = "";
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
            if (!isset($this->_folder)) {
                return $this->_folder = "{$this->_domain}/{$m}{$this->router['controller']}/{$this->router['action']}" . ucfirst($this->router['method']);
            }
            return $this->_folder;
        }
        $path = trim($path, '/');
        $this->_folder = "{$this->_domain}/{$m}{$path}/{$this->router['controller']}/{$this->router['action']}" . ucfirst($this->router['method']);
        $this->_sure_symlink = true;
        return $this;
    }

    /**
     * 将正常的日志文件创建一个软链接
     *
     * @param string $path 若为空则链接到原始规则文件
     * @return $this
     */
    public function symlink(string $path = ''): Debug
    {
        $this->_symlink = trim($path, '/');
        return $this;
    }

    /**
     * 原始路径
     * @return string
     */
    private function realDebugFile()
    {
        $root = str_replace(
            ['{RUNTIME}', '{ROOT}', '{VIRTUAL}', '{DATE}'],
            [_RUNTIME, _ROOT, _VIRTUAL, date('Y_m_d')],
            $this->_conf['path']);
        $m = $this->router['module'];
        if (!empty($m)) $m = strtoupper($m) . "/";

        $folder = "{$this->_domain}/{$m}{$this->router['controller']}/{$this->router['action']}" . ucfirst($this->router['method']);
        list($s, $c) = explode('.', microtime(true) . '.0');
        $file = date($this->_conf['rules']['filename'], intval($s)) . "_{$c}_" . mt_rand(100, 999);

        if ($this->_hasError) $file .= '_Error';
        return "{$root}{$folder}{$this->_path}/{$file}.md";
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
        $this->_sure_symlink = true;
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
        $force = ($path[0] === '/');//  以/开头，强制完整目录，不带域名
        $path = trim($path, '/');
        $this->_folder = "{$this->_domain}{$m}/{$path}";
        if ($force) $this->_folder = "{$m}/{$path}";
        $this->_path = '';
        $this->_sure_symlink = true;
        return $this;
    }

    /**
     * 设置文件名
     * @param string|null $file
     * @return $this|string
     */
    public function file(string $file = null)
    {
        if (is_null($file)) {
            if (!isset($this->_file)) {
                list($s, $c) = explode('.', microtime(true) . '.0');
                return date($this->_conf['rules']['filename'], intval($s)) . "_{$c}_" . mt_rand(100, 999);
            }
            return $this->_file;
        }
        $this->_sure_symlink = true;
        $this->_file = trim(trim($file, '.md'), '/');
        return $this;
    }

    /**
     * 设置，或读取完整的保存文件地址和名称
     * 如果运行一次后，第二次运行时不会覆盖之前的值，也就是只以第一次取得的值为准
     * @param string|null $file
     * @return string
     */
    public function filename(string $file = null): string
    {
        if (empty($this->router['controller'])) return '';
        if ($file) return $this->file($file);

        if (!isset($this->_filename)) {
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