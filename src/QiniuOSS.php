<?php

namespace zhiyicx\Component\QiniuOSS;

use Qiniu\Auth;

/**
* Qiniu  OSS
*/
class QiniuOSS extends QiniuClient
{

    protected static $_wrapperClients = [];

    protected static $bucket;

    public static function getBucket()
    {
        return static::$bucket;
    }

    public function setBucket($bucket)
    {
        static::$bucket = $bucket;

        return $this;
    }
}
