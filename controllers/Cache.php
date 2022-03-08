<?php

namespace esp\debug_helps;

use esp\core\Controller;

class Cache extends Controller
{

    public function _init()
    {
        $this->_dispatcher->_debug->disable();
        $this->setViewPath('@' . dirname(__DIR__) . '/views');
    }

    public function indexGet()
    {
        $data = $this->getConfig()->allConfig();
        unset($data['mime'], $data['state']);
        $this->assign('data', $data);
    }

    public function opcacheGet()
    {

    }

    public function resourceAjax()
    {
        $this->_redis->set('resourceRand', time() + mt_rand());
        return ['success' => 1, 'message' => "重置成功"];
    }

    public function flushAjax($all)
    {
        $this->getConfig()->flush(intval($all));
        return ['success' => 1, 'message' => "清空成功"];
    }

}