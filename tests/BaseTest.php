<?php

namespace zhiyicx\Component\QiniuOSS\Tests;

use zhiyicx\Component\QiniuOSS\QiniuOssStream;

/**
 * 基础测试.
 *
 **/
class BaseTest extends \PHPUnit_Framework_TestCase
{
    const OSS_BUCKET = '28youth';
    const OSS_ACCESS_KEY_ID = 'UEwud1dLTn5Qn0swqpvKhhvUt2GJBvNvg6agr9sA';
    const OSS_ACCESS_KEY_SECRET = 'T4IwE_3uPW_bHRoa_L1up8sS58krW7vKxCogo6hl';

    protected $oss;

    public function __construct()
    {
        $this->oss = new QiniuOssStream();
    }


 
}


$test = new BaseTest();

print_r($test);