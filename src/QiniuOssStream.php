<?php

namespace zhiyicx\Component\QiniuOSS;

use Exception;
use Medz\Component\WrapperInterface\WrapperInterface;

/**
* 七牛OSS Streams.
*/
class QiniuOssStream implements WrapperInterface
{
    /**
     * @var bool Write the buffer on fflush()?
     */
    private $_writeBuffer = false;
    /**
     * @var int Current read/write position
     */
    private $_position = 0;
    /**
     * @var int Total size of the object as returned by oss (Content-length)
     */
    private $_objectSize = 0;
    /**
     * @var string File name to interact with
     */
    private $_objectName = null;
    /**
     * @var string Current read/write buffer
     */
    private $_objectBuffer = null;
    /**
     * @var array Available buckets
     */
    private $_bucketList = [];
    /**
     * @var oss
     */
    private $_oss = null;

    /**
     * Retrieve client for this stream type.
     *
     * @param string $path
     *
     * @return oss
     *
     * @author Seven Du <lovevipdsw@outlook.com>
     * @homepage http://medz.cn
     */
    protected function getOss($path)
    {
        if ($this->_oss === null) {
            $url = explode(':', $path);
            if (empty($url)) {
                throw new Exception("Unable to parse URL $path");
            }
            $this->_oss = QiniuOSS::getWrapperClient($url[0]);
            if (!$this->_oss) {
                throw new Exception("Unknown client for wrapper {$url[0]}");
            }
        }
        return $this->_oss;
    }
    /**
     * Extract object name from URL.
     *
     * @param string $path
     *
     * @return string
     */
    protected function _getNamePart($path)
    {
        $url = parse_url($path);
        if ($url['host']) {
            return !empty($url['path']) ? $url['host'].$url['path'] : $url['host'];
        }
        return '';
    }
    /**
     * Open the stream.
     *
     * @param string $path
     * @param string $mode
     * @param int    $options
     * @param string $opened_path
     *
     * @return bool
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $name = $this->_getNamePart($path);
        // If we open the file for writing, just return true. Create the object
        // on fflush call
        if (strpbrk($mode, 'wax')) {
            $this->_objectName = $name;
            $this->_objectBuffer = null;
            $this->_objectSize = 0;
            $this->_position = 0;
            $this->_writeBuffer = true;
            $this->getOss($path);
            return true;
        } else {
            // Otherwise, just see if the file exists or not
            try {
                $info = $this->getOss($path)->getMetadata($name);
                if ($info) {
                    $this->_objectName = $name;
                    $this->_objectBuffer = null;
                    $this->_objectSize = $info['size'];
                    $this->_position = 0;
                    $this->_writeBuffer = false;
                    return true;
                }
            } catch (Exception $e) {
                return false;
            }
        }
        return false;
    }
    /**
     * Close the stream.
     *
     * @return void
     */
    public function stream_close()
    {
        $this->_objectName = null;
        $this->_objectBuffer = null;
        $this->_objectSize = 0;
        $this->_position = 0;
        $this->_writeBuffer = false;
        unset($this->_oss);
    }
    /**
     * Read from the stream.
     *
     * http://bugs.php.net/21641 - stream_read() is always passed PHP's
     * internal read buffer size (8192) no matter what is passed as $count
     * parameter to fread().
     *
     * @param int $count
     *
     * @return string
     */
    public function stream_read($count)
    {
        if (!$this->_objectName) {
            return '';
        }
        // make sure that count doesn't exceed object size
        if ($count + $this->_position > $this->_objectSize) {
            $count = $this->_objectSize - $this->_position;
        }
        $range_start = $this->_position;
        $range_end = $this->_position + $count;

        if (!$this->_objectBuffer) {
            $this->_objectBuffer = file_get_contents($this->_oss->getUrl($this->_objectName));
        }

        // Only fetch more data from OSS if we haven't fetched any data yet (postion=0)
        // OR, the range end position is greater than the size of the current object
        // buffer AND if the range end position is less than or equal to the object's
        // size returned by OSS
        if (($this->_position == 0) || (($range_end > strlen($this->_objectBuffer)) && ($range_end <= $this->_objectSize))) {
            $options = [
                'range' => $range_start.'-'.$range_end,
            ];
        }
        $data = substr($this->_objectBuffer, $this->_position, $count);
        $this->_position += strlen($data);
        return $data;
    }
    /**
     * Write to the stream.
     *
     * @param string $data
     *
     * @return int
     */
    public function stream_write($data)
    {
        if (!$this->_objectName) {
            return 0;
        }
        $len = strlen($data);
        $this->_objectBuffer .= $data;
        $this->_objectSize += $len;
        // TODO: handle current position for writing!

        list($response, $error) = $this->_oss->getUploadManager()->put
        (
            $this->_oss->getUploadToken(), 
            $this->_objectName, 
            $this->_objectBuffer
        );

        if ($error) {
            return false;
        }

        return $response;
    }

    /**
     * End of the stream?
     *
     * @return bool
     */
    public function stream_eof()
    {
        if (!$this->_objectName) {
            return true;
        }
        return $this->_position >= $this->_objectSize;
    }
    /**
     * What is the current read/write position of the stream.
     *
     * @return int
     */
    public function stream_tell()
    {
        return $this->_position;
    }
    public function stream_lock($operation)
    {
        return false;
    }
    /**
     * Enter description here...
     *
     * @param int $option
     * @param int $arg1
     * @param int $arg2
     *
     * @return bool
     */
    public function stream_set_option($option, $arg1, $arg2)
    {
        return false;
    }
    /**
     * Enter description here...
     *
     * @param int $cast_as
     *
     * @return resource
     */
    public function stream_cast($cast_as)
    {
    }
    /**
     * Update the read/write position of the stream.
     *
     * @param int $offset
     * @param int $whence
     *
     * @return bool
     */
    public function stream_seek($offset, $whence = SEEK_SET)
    {
        if (!$this->_objectName) {
            return false;
        // Set position to current location plus $offset
        } elseif ($whence === SEEK_CUR) {
            $offset += $this->_position;
        // Set position to end-of-file plus $offset
        } elseif ($whence === SEEK_END) {
            $offset += $this->_objectSize;
        }
        if ($offset >= 0 && $offset <= $this->_objectSize) {
            $this->_position = $offset;
            return true;
        }
        return false;
    }
    /**
     * Flush current cached stream data to storage.
     *
     * @return bool
     */
    public function stream_flush()
    {
        // If the stream wasn't opened for writing, just return false
        if (!$this->_writeBuffer) {
            return false;
        }

        list($response, $error) = $this->_oss->getUploadManager()->put
        (
            $this->_oss->getUploadToken(), 
            $this->_objectName, 
            $this->_objectBuffer
        );

        if ($error) {
            return false;
        }

        return $response;
    }
    /**
     * Returns data array of stream variables.
     *
     * @return array
     */
    public function stream_stat()
    {
        if (!$this->_objectName) {
            return [];
        }
        $stat = [];
        $stat['dev'] = 0;
        $stat['ino'] = 0;
        $stat['mode'] = 0777;
        $stat['nlink'] = 0;
        $stat['uid'] = 0;
        $stat['gid'] = 0;
        $stat['rdev'] = 0;
        $stat['size'] = 0;
        $stat['atime'] = 0;
        $stat['mtime'] = 0;
        $stat['ctime'] = 0;
        $stat['blksize'] = 0;
        $stat['blocks'] = 0;
        if (($slash = strstr($this->_objectName, '/')) === false || $slash == strlen($this->_objectName) - 1) {
            /* bucket */
            $stat['mode'] |= 040000;
        } else {
            $stat['mode'] |= 0100000;
        }
        $info = $this->_oss->getBucketManager()->stat
        (
            $this->_oss->getBucket(), 
            $this->_objectName
        )[0];

        if (!empty($info)) {
            $stat['size'] = $info['fsize'];
            $stat['atime'] = time();
            $stat['mtime'] = floor($info['putTime'] / 10000000);
        }

        return $stat;
    }
    /**
     * Attempt to delete the item.
     *
     * @param string $path
     *
     * @return bool
     */
    public function unlink($path)
    {
        return $this->getOss($path)->getBucketManager()->delete
        (
            $this->getOss($path)->getBucket(), 
            $this->_getNamePart($path)
        );
    }
    /**
     * Attempt to rename the item.
     *
     * @param string $path_from
     * @param string $path_to
     *
     * @return bool False
     */
    public function rename($path_from, $path_to)
    {
        $response = $this->getOss($path_from)->getBucketManager()->rename
        (
            $this->getOss($path_from)->getBucket(),
            $path_from, 
            $path_to
        );
        var_dump($response);
        return is_null($response);
    }
    /**
     * Create a new directory.
     *
     * @param string $path
     * @param int    $mode
     * @param int    $options
     *
     * @return bool
     */
    public function mkdir($path, $mode, $options)
    {
        return false;
    }
    /**
     * Remove a directory.
     *
     * @param string $path
     * @param int    $options
     *
     * @return bool
     */
    public function rmdir($path, $options)
    {
        return false;
    }
    /**
     * Return the next filename in the directory.
     *
     * @return string
     */
    public function dir_readdir()
    {
        $object = current($this->_bucketList);
        if ($object !== false) {
            next($this->_bucketList);
        }
        return $object;
    }
    /**
     * Attempt to open a directory.
     *
     * @param string $path
     * @param int    $options
     *
     * @return bool
     */
    public function dir_opendir($path, $options)
    {
        $dirName = $this->_getNamePart($path).'/';
        if (preg_match('@^([a-z0-9+.]|-)+://$@', $path) || $dirName == '/') {

            //  Open directory file
            $iterms = $this->getOss($path)->listContents($dirName);
        } else {
            $prefix = '';
            $marker = '';
            $limit = 3;
            list($iterms, $marker, $err) = $this->getOss($path)->getBucketManager()->listFiles
            (
                $this->_oss->getBucket(), 
                $prefix, 
                $marker, 
                $limit
            );
        }

        if ($iterms) {

            $this->_bucketList = $iterms;
        }

        return $this->_bucketList !== false;
    }
    /**
     * Return array of URL variables.
     *
     * @param string $path
     * @param int    $flags
     *
     * @return array
     */
    public function url_stat($path, $flags)
    {
        $stat = [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => 0777,
            'nlink'   => 0,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => 0,
            'atime'   => 0,
            'mtime'   => 0,
            'ctime'   => 0,
            'blksize' => 0,
            'blocks'  => 0,
        ];
        $name = $this->_getNamePart($path);
        try {
            $info = $this->getOss($path)->getMetadata($name);
            if ($info) {
                $stat['size'] = $info['size'];
                $stat['atime'] = time();
                $stat['mtime'] = $info['timestamp'];
                $stat['mode'] |= 0100000;
            }
        } catch (Exception $e) {
            $stat['mode'] |= 040000;
        }
        return $stat;
    }
    /**
     * Reset the directory pointer.
     *
     * @return bool True
     */
    public function dir_rewinddir()
    {
        reset($this->_bucketList);
        return true;
    }
    /**
     * Close a directory.
     *
     * @return bool True
     */
    public function dir_closedir()
    {
        $this->_bucketList = [];
        return true;
    }

} // END class AliyunOssStream implements WrapperInterface
