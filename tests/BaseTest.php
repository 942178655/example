<?php

include_once "../vendor/autoload.php";


use zhiyicx\Component\QiniuOSS\QiniuOSS;

/**
* BaseTest
*/
class BaseTest extends QiniuOSS
{
    const BUCKET = '28youth';
    const DOMAIN = 'omt6lyv55.bkt.clouddn.com';
    const ACCESS_KEY = 'UEwud1dLTn5Qn0swqpvKhhvUt2GJBvNvg6agr9sA';
    const SECRET_KEY = 'T4IwE_3uPW_bHRoa_L1up8sS58krW7vKxCogo6hl';

    public function __construct()
    {
        parent::__construct(self::ACCESS_KEY, self::SECRET_KEY, self::BUCKET, self::DOMAIN);
    }

    public function testopen()
    {
        $f = fopen('oss://text/txt1.txt', 'r');

        // return  fstat($f); //获取信息
        return $f;
    }

    public function testRead()
    {
        return  fread($this->testopen(), 1000);
    }

    public function testPut()
    {
        // return fwrite($this->testopen(), fread(fopen('D:\txt2.txt', 'r'), 1000));
        return fwrite(fopen('oss://t2.txt', 'w'), file_get_contents('D:\txt2.txt'));
    }

    /* 底层正则不明白 */
    public function testOpendir()
    {
        $list =  opendir('oss://text');

        return $list;
    }

    /* 有点问题 */
    public function testRename()
    {
        $o = rename('oss://text/t1.txt', 'oss://text/t3.txt');

        return $o;
    }
}

$oss = new BaseTest();
$oss->registerStreamWrapper('oss');
echo "<pre>";
print_r($oss->testPut());
