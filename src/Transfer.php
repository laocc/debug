<?php
declare(strict_types=1);

namespace esp\debug;

use esp\error\Error;

class Transfer
{
    private string $_transfer_uri = '/_esp_debug_transfer';
    private string $_transfer_path = '/tmp/_esp_debug_transfer';

    public function __construct(bool $transfer)
    {
        $uri = parse_url(getenv('REQUEST_URI'), PHP_URL_PATH);
        if ($uri === $this->_transfer_uri) {
            $save = $this->accept($transfer);
            exit(getenv('SERVER_ADDR') . ";Length={$save};Time:" . microtime(true));
        }
    }


    /**
     * 保存其他节点发来的日志数据
     * @param bool $transfer
     * @return bool|int|string
     */
    private function accept(bool $transfer)
    {
        $input = file_get_contents("php://input");
        if (empty($input)) return 'null';

        $array = json_decode($input, true);
        if (empty($array['data'])) $array['data'] = 'NULL Data';
        if (is_array($array['data'])) $array['data'] = print_r($array['data'], true);

        //临时中转文件
        if ($transfer) {
            if (!is_readable($this->_transfer_path)) @mkdir($this->_transfer_path, 0740, true);
            $move = $this->_transfer_path . '/' . urlencode(base64_encode($array['filename']));
            return file_put_contents($move, $array['data'], LOCK_EX);
        }

        $this->mk_dir($array['filename']);
        return file_put_contents($array['filename'], $array['data'], LOCK_EX);
    }


    /**
     * 将move里的临时文件移入真实目录
     * 在并发较大时，需要将日志放入临时目录，由后台移到目标目录中
     * 因为在大并发时，创建新目录的速度可能跟不上系统请求速度，有时候发生目录已存在的错误
     * @param bool $show
     * @param string|null $path
     * @throws Error
     */
    public function save(string $path, bool $show = false)
    {
        if (!_CLI) throw new Error('debug->Transfer() 只能运行于CLI环境');

        $time = 0;

        reMove:
        $time++;
        $dir = new \DirectoryIterator($path);
        $array = array();
        foreach ($dir as $i => $f) {
            if ($f->isDot()) continue;
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

                $p = dirname($move);
                if (!is_readable($p)) @mkdir($p, 0740, true);
                else if (!is_dir($p)) @mkdir($p, 0740, true);
                rename("{$path}/{$file}", $move);
            } catch (\Error|\Exception $e) {
                print_r(['moveDebug' => $e]);
            }
        }
        goto reMove;
    }


    private function mk_dir(string $file): void
    {
        if (!$file) {
            return;
        }
        $path = dirname($file);
        try {
            if (!is_dir($path)) {
                @mkdir($path, 0740, true);
                return;
            }
            return;
        } catch (\Error|\Exception $e) {
            return;
        }
    }


}