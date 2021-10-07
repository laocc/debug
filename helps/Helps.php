<?php
declare(strict_types=1);

namespace esp\debug_helps;

use function esp\helper\root;
use esp\core\Controller;
use esp\library\Input;
use esp\library\ext\Markdown;
use esp\library\request\Get;

class Helps extends Controller
{
    private $_root;
    private $_error;
    private $_warn;

    public function _init()
    {
//        parent::_init();
        $this->_root = $this->debug()->root();
        $this->_root = dirname($this->_root);
        $this->_error = _RUNTIME . '/error';
        $this->_warn = _RUNTIME . '/warn';
        $this->debug()->disable();
        $this->setViewPath(__DIR__ . '/views');
        $this->setLayout(__DIR__ . '/views/layout');
    }

    public function ordAction($path)
    {
        $key = Input::get('key', '');
        $this->assign('path', $path);
        $this->assign('key', $key);
        $path = urldecode($path);
        $key = urldecode($key);
        $rnd = date('YmdHis');

        $ord = '';
        if ($key) {
            $name = "{$path}/{$key}-{$rnd}";
            $ord = "touch {$name}.txt";
            $ord .= " && find {$path} -type f -name \"*.md\" | xargs grep \"{$key}\" -l > {$name}.txt";
            $ord .= " \n zip -r {$name}.zip `cat {$name}.txt` ";
            $ord .= " \n sz {$name}.zip \n";
        }
        $this->assign('order', $ord);
    }

    public function indexGet($path)
    {
        if (empty($path)) {
            $pathT = Input::get('path');
            if (empty($pathT)) $pathT = $this->_root . '/' . date('Y_m_d');
        } else {
            $pathT = urldecode($path);
        }
        $key = Input::get('key');

        $path = realpath($pathT);
        if (strpos($path, $this->_root) !== 0) $this->exit("无权限查看该目录:" . var_export($pathT, true));

        if (is_file($path)) $path = dirname($path);

        if (!is_readable($path)) $this->exit('empty:' . var_export($pathT, true));
        $file = $this->folder($path);
        ksort($file[0]);
        ksort($file[1]);
//        ksort($file);
        $this->assign('allDir', $file);
        $this->assign('path', substr($path, strlen($this->_root)));
        $this->assign('debug', $this->_root);
    }

    public function counterGet()
    {
    }

    public function counterAjax()
    {
        $get = new Get();
        $day = $get->int('type');
        $time = time() - (86400 * $day);
        $method = true;

        $key = $this->config('debug.default.counter');

        if ($time === 0) $time = time();
        $key = "{$key}_counter_" . date('Y_m_d', $time);
        $all = $this->_config->Redis()->hGetAlls($key);
        if (empty($all)) return ['data' => [], 'action' => []];

        $data = [];
        foreach ($all as $hs => $hc) {
            $key = explode('/', $hs, 5);
            $hour = (intval($key[0]) + 1);
            $ca = $method ? "{$key[1]}:/{$key[4]}" : "/{$key[4]}";
            $vm = "{$key[2]}.{$key[2]}";
            if (!isset($data[$vm])) $data[$vm] = ['action' => [], 'data' => []];
            if (!isset($data[$vm]['data'][$hour])) $data[$vm]['data'][$hour] = [];
            $data[$vm]['data'][$hour][$ca] = $hc;
            if (!in_array($ca, $data[$vm]['action'])) $data[$vm]['action'][] = $ca;
            sort($data[$vm]['action']);
        }
        return $data;
    }

    public function o_indexAction()
    {
        if (!is_readable($this->_root)) $this->exit('empty');
        $file = $this->path($this->_root);
        krsort($file);
        $this->assign('allDir', $file);
    }


    /**
     * 读取文件目录所有文件
     * @param string $path
     * @param string $ext 只读取指定文件类型
     * @return array
     */
    private function path(string $path, int $lev = 0)
    {
        $array = array();
        $dir = new \DirectoryIterator($path);
        foreach ($dir as $f) {
            if ($f->isDir()) {
                $name = $f->getFilename();
                if (in_array($name, ['.', '..'])) continue;
                $nPath = "{$path}/{$name}";
                if (is_dir($nPath)) {
                    if ($lev) {
                        $array[$name] = $this->path($nPath, $lev++);
                    } else {
                        $array[$name] = $nPath;
                    }
                }
            }
        }
        return $array;
    }


    /**
     * 仅列出当前目录里的第1级目录
     * @param string $path
     * @return array
     */
    private function folder(string $path)
    {
        $folder = array();
        $file = array();
        $dir = new \DirectoryIterator($path);
        foreach ($dir as $f) {
            $name = $f->getFilename();
            if (in_array($name, ['.', '..'])) continue;
            if ($f->isDir()) {
                $folder[$name] = "{$path}/{$name}";
            } elseif ($f->isFile()) {
                $file[$name] = "{$path}/{$name}";
            }
        }
        return [$folder, $file];
    }


    public function filesAction(string $path, $ext = 'md')
    {
        $path = urldecode($path);
        if (!is_readable($path)) $this->exit('empty');
        if (!$ext) $ext = 'md';
        $file = Input::file($path, $ext);
        $this->assign('path', $path);
        $this->assign('file', $file);
        if ($ext === 'json') {
            $this->setView('debug/json.php');
        }
    }

    public function fileAction($file)
    {
        if (!$file) $file = Input::get('file');
        $path = realpath(urldecode($file));
//        if (stripos($path, $this->_root) !== 0) $this->exit("无权限查看该文件:{$path}");
        if (!is_readable($path)) $this->exit($path . '文件不存在');

//        $this->concat(false);
        $this->css('/public/vui/css/markdown.css');
        $html = Markdown::html(file_get_contents($path), 0);
        $this->assign('html', $html);
    }


    public function delAjax($path)
    {
        $path = urldecode($path);

        if (stripos($path, $this->_root) !== 0) return '非Debug目录禁止删除';

        $unlink = 0;
        D:
        foreach (Input::path($path, true) as $p) {
//            echo "P:{$p}\n";
            $pfile = 0;
            foreach (Input::file($p) as $f) {
//                echo "F:{$p}/{$f}\n";
                unlink("{$p}/{$f}");
                $unlink++;
                $pfile++;
            }
            if ($pfile > 0) rmdir($p);
            else {
                $path = $p;
                goto D;
            }
        }

        return ['success' => 1, 'message' => "删除了{$unlink}个文档"];
    }

    public function error_matchAction($warn)
    {
        $path = $warn ? $this->_warn : $this->_error;
        $dir = new \DirectoryIterator($path);
        $value = [];
        $client = [];
        foreach ($dir as $f) {
            $name = $f->getFilename();
            if ($name === '.' or $name === '..') continue;
            $val = [];
            if ($f->isFile()) {
                $val['fn'] = $f->getPathname();
                $text = file_get_contents($val['fn']);
                if (preg_match('/"HTTP_COOKIE": "(.+)",/', $text, $mch)) {
                    $val['ck'] = $mch[1];
//                    if (in_array($mch[1], $client)) continue;//过滤相同用户
                    $client[] = $mch[1];
                }
                if (preg_match('/"HTTP_USER_AGENT": "(.+)",/', $text, $mch)) {
                    $val['ua'] = $mch[1];
                }

                if (preg_match('/"error": "(.+)"/i', $text, $mch)) {
                    $val['er'] = $mch[1];
                } else if (preg_match('/"message": "(.+)"/i', $text, $mch)) {
                    $val['er'] = $mch[1];
                } else if (preg_match('/"Error": \[[\s.]+"(.+?)"\n/i', $text, $mch)) {
                    $val['er'] = $mch[1];
                }

                if (preg_match('/"file": "(.+)"/i', $text, $mch)) {
                    $val['fl'] = $mch[1];
                }
                if (preg_match('/"line": (\d+),/i', $text, $mch)) {
                    if (isset($val['fl'])) {
                        $val['fl'] = "{$val['fl']}({$mch[1]})";
                    } else {
                        $val['ln'] = $mch[1];
                    }
                }
                if ($warn) {
                    preg_match('/ip=(.+?)\&/i', $text, $url);
                    preg_match('/REAL_IP": "(.+?)",/i', $text, $sev);
                    preg_match('/REMOTE_ADDR": "(.+?)",/i', $text, $rem);
                    $ia = ($url[1] ?? '');
                    $ib = ($sev[1] ?? ($rem[1] ?? ''));
                    if ($ia === $ib) {
                        $val['ip'] = "<em class='red'>{$ia} ~ {$ib}</em>";
                    } else {
                        $val['ip'] = "{$ia} ~ {$ib}";
                    }
                }
            }
            $value[$name] = $val;
        }
        ksort($value);
        $this->assign('value', $value);
        $this->assign('warn', $warn);
    }

    public function testGet()
    {
        $this->setLayout(false);
    }


    public function errorAction()
    {
        $files = [];
        $path = $this->_error;
        if (!is_readable($path)) goto end;
        $file = Input::file($path, 'md');
        foreach ($file as $i => $fil) {
            $time = strtotime(substr($fil, 0, 14));
            $files[$time . ($i + 1000)] = $fil;
        }
        ksort($files);
        end:
        $this->assign('path', $path);
        $this->assign('file', $files);
    }

    public function warnAction($fd)
    {
        $files = $folder = [];
        $path = $this->_warn;
        if (!is_readable($path)) goto end;
        if (empty($fd)) {
            $dir = new \DirectoryIterator($path);
            foreach ($dir as $f) {
                if ($f->isDir()) {
                    $fn = $f->getFilename();
                    if ($fn === '.' or $fn === '..') continue;
                    $folder[] = $fn;
                }
            }
        } else {
            $file = Input::file("{$path}/{$fd}", 'md');
            foreach ($file as $i => $fil) {
                $time = strtotime(substr($fil, 0, 14));
                $files[$time . ($i + 1000)] = "{$fd}/{$fil}";
            }
            ksort($files);
        }

        end:
        $this->assign('folder', $folder);
        $this->assign('path', $path);
        $this->assign('file', $files);
    }

    /**
     * 批量删除
     * @param $error
     * @param $fl
     * @param $warn
     * @return array
     */
    public function error_deleteAjax($error, $fl, $warn)
    {
        $error = urldecode($error);
        if ($error === 'null') $error = null;
        $path = $warn ? $this->_warn : $this->_error;
        $dir = new \DirectoryIterator($path);
        $c = 0;
        foreach ($dir as $f) {
            $name = $f->getFilename();
            if ($name === '.' or $name === '..') continue;
            if ($f->isFile()) {
                $fn = $f->getPathname();
                $text = file_get_contents($fn);
                if (preg_match('/"error": "(.+)"/i', $text, $mch)) {
                    if ($error === $mch[1]) {
                        unlink($fn);
                        $c++;
                    }
                } else if (preg_match('/"message": "(.+)"/i', $text, $mch)) {
                    if ($error === $mch[1]) {
                        unlink($fn);
                        $c++;
                    }
                } else if (preg_match('/[2] => (.+)/i', $text, $mch)) {
                    if ($error === $mch[1]) {
                        unlink($fn);
                        $c++;
                    }
                }
            }
        }

        return ['success' => 1, 'message' => "共删除了{$c}个相同错误信息"];
    }

    public function error_delAjax($file, $warn)
    {
        $file = urldecode($file);
        $path = $warn ? $this->_warn : $this->_error;
        $filename = root($path . "/{$file}");
        if (!is_readable($filename)) return "{$file} not exists.";
        if (stripos($filename, $path) !== 0) return '非Debug目录禁止删除';
        unlink($filename);
    }

    public function error_viewAction($file, $warn)
    {
        $path = $warn ? $this->_warn : $this->_error;
        $file = urldecode($file);
        $filename = root($path . "/{$file}");
        if (!is_readable($filename)) $this->exit("{$file} not exists.");
        $this->css('/public/vui/css/markdown.css');
        $error = file_get_contents($filename);
        $json = json_decode($error, true);
        $debug = '';
        if ($json) {
            $debug = $json['Debug'];
            $error = print_r($json, true);
            $error = substr($error, 7, -2);
        }
        $html = Markdown::html($error, 0);
        $this->assign('file', $file);
        $this->assign('html', $html);
        $this->assign('warn', $warn);
        $this->assign('debug', $debug);
    }


}