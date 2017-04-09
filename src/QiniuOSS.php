<?php

namespace zhiyicx\Component\QiniuOSS;

use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;

/**
* Qiniu  OSS
*/
class QiniuOSS
{

    protected $accessKey;
    protected $secretKey;
    protected $bucket;
    protected $domain;

    protected $authManager;
    protected $uploadManager;
    protected $bucketManager;

    protected static $_wrapperClients = [];

    /**
     * QiniuAdapter constructor.
     *
     * @param string $accessKey
     * @param string $secretKey
     * @param string $bucket
     * @param string $domain
     */
    public function __construct($accessKey, $secretKey, $bucket, $domain)
    {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->bucket = $bucket;
        $this->domain = $domain;
    }

    /**
     * @return Qiniu/bucket
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * Get resource url.
     *
     * @param  string $path
     *
     * @return string
     */
    public function getUrl($path)
    {
        return $this->normalizeHost($this->domain).$path;
    }

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function read($path)
    {
        $contents = file_get_contents($this->getUrl($path));

        return compact('contents', 'path');
    }

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function readStream($path)
    {
        if (ini_get('allow_url_fopen')) {
            $stream = fopen($this->normalizeHost($this->domain).$path, 'r');

            return compact('stream', 'path');
        }

        return false;
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     *
     * @return array
     */
    public function listContents($directory = '', $recursive = false)
    {
        $list = [];

        $result = $this->getBucketManager()->listFiles($this->bucket, $directory);

        foreach ($result[0] as $files) {
            $list[] = $this->normalizeFileInfo($files);
        }

        return $list;
    }

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMetadata($path)
    {
        $result = $this->getBucketManager()->stat($this->bucket, $path);
        $result[0]['key'] = $path;

        return $this->normalizeFileInfo($result[0]);
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getMimetype($path)
    {
        $response = $this->getBucketManager()->stat($this->bucket, $path);

        if (empty($response[0]['mimeType'])) {
            return false;
        }

        return ['mimetype' => $response[0]['mimeType']];
    }

    /**
     * Get the timestamp of a file.
     *
     * @param string $path
     *
     * @return array|false
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @param \Qiniu\Storage\BucketManager $manager
     *
     * @return $this
     */
    public function setBucketManager(BucketManager $manager)
    {
        $this->bucketManager = $manager;

        return $this;
    }

    /**
     * @param \Qiniu\Storage\UploadManager $manager
     *
     * @return $this
     */
    public function setUploadManager(UploadManager $manager)
    {
        $this->uploadManager = $manager;

        return $this;
    }

    /**
     * @param \Qiniu\Auth $manager
     *
     * @return $this
     */
    public function setAuthManager(Auth $manager)
    {
        $this->authManager = $manager;

        return $this;
    }

    /**
     * @return \Qiniu\Storage\BucketManager
     */
    public function getBucketManager()
    {
        return $this->bucketManager ?: $this->bucketManager = new BucketManager($this->getAuthManager());
    }

    /**
     * @return \Qiniu\Auth
     */
    public function getAuthManager()
    {
        return $this->authManager ?: $this->authManager = new Auth($this->accessKey, $this->secretKey);
    }

    /**
     * @return \Qiniu\UploadToken
     */
    public function getUploadToken()
    {
        return $this->getAuthManager()->uploadToken($this->bucket);
    }

    /**
     * @return \Qiniu\Storage\UploadManager
     */
    public function getUploadManager()
    {
        return $this->uploadManager ?: $this->uploadManager = new UploadManager();
    }

    /**
     * @param array $stats
     *
     * @return array
     */
    protected function normalizeFileInfo(array $stats)
    {
        return [
            'type' => 'file',
            'path' => $stats['key'],
            'timestamp' => floor($stats['putTime'] / 10000000),
            'size' => $stats['fsize'],
        ];
    }

    /**
     * @param  string $domain
     *
     * @return string
     */
    protected function normalizeHost($domain)
    {
        if (0 !== stripos($domain, 'https://') && 0 !== stripos($domain, 'http://')) {
            $domain = "http://{$domain}";
        }

        return rtrim($domain, '/').'/';
    }

    /**
     * Register this object as stream wrapper client.
     *
     * @param string $name
     *
     * @return oss
     */
    public function registerAsClient($name)
    {
        self::$_wrapperClients[$name] = $this;
        return $this;
    }
    /**
     * Unregister this object as stream wrapper client.
     *
     * @param string $name
     *
     * @return oss
     */
    public function unregisterAsClient($name)
    {
        unset(self::$_wrapperClients[$name]);
        return $this;
    }
    /**
     * Get wrapper client for stream type.
     *
     * @param string $name
     *
     * @return oss
     */
    public static function getWrapperClient($name)
    {
        return self::$_wrapperClients[$name];
    }
    /**
     * Register this object as stream wrapper.
     *
     * @param string $name
     *
     * @return oss
     */
    public function registerStreamWrapper($name = 'oss')
    {
        stream_register_wrapper($name, 'zhiyicx\\Component\\QiniuOSS\\QiniuOssStream');
        $this->registerAsClient($name);
    }
    /**
     * Unregister this object as stream wrapper.
     *
     * @param string $name
     *
     * @return oss
     */
    public function unregisterStreamWrapper($name = 'oss')
    {
        stream_wrapper_unregister($name);
        $this->unregisterAsClient($name);
    }
} // END
