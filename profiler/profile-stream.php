<?php
declare(strict_types=1);
namespace Smackcoders\AIOACC;

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

define('STREAM_WRAPPER_INJECTION_STRING_ALT', '<?php declare(ticks=1,strict_types=1);?>');
define('STREAM_WRAPPER_INJECTION_STRING',     '<?php declare(ticks=1);               ?>'); //add spaces to match
define('STREAM_WRAPPER_INJECTION_LENGTH', 40);

class FileStreamWrapper
{
    /**
     * @var resource
     */
    public $context;

    /**
     * @var resource
     */
    private $handle;

    /**
     * @var string
     */
    const protocol = 'file';

    private $is_php_file;

    public static function init()
    {
        $result = stream_wrapper_unregister(self::protocol);
        if (false === $result) {
            throw new UnexpectedValueException('Failed to unregister');
        }
        stream_wrapper_register(self::protocol, FileStreamWrapper::class, 0);
    }

    private static function restore()
    {
        $result = stream_wrapper_restore(self::protocol);
        if (false === $result) {
            throw new UnexpectedValueException('Failed to restore');
        }
    }

    public function stream_open($path, $mode, $options, &$opened_path): bool
    {
        $throw_error = $options & STREAM_REPORT_ERRORS;
        if (isset($this->handle)) {
            if ($throw_error)
                throw new UnexpectedValueException('Handle congruency');
            else return false;
        }
        $use_include_path = ($options & STREAM_USE_PATH) != 0;

        $context = $this->context;
        if (null === $context) {
            $context = stream_context_get_default();
        }
        self::restore();
        $handle = fopen($path, $mode, $use_include_path, $context);
        self::init();

        if (false === $handle) {
            return false;
        }
        $meta = stream_get_meta_data($handle);
        if (!isset($meta['uri'])) {
            if ($throw_error)
                throw new UnexpectedValueException('Uri not in meta data');
            else return false;
        }

        $opened_path = $meta['uri'];

        $this->handle = $handle;
        $this->is_php_file = (substr($path, -4) == '.php');

        return true;
    }

    private function doWrapper($function)
    {
        self::restore();
        $result = $function();
        self::init();
        return $result;
    }

    /**
     * @return array
     */
    public function stream_stat(): array
    {
        return $this->doWrapper(function () {
            $array = fstat($this->handle);
            if ($this->is_php_file) {
                $array['size'] += STREAM_WRAPPER_INJECTION_LENGTH;
                $array[7] += STREAM_WRAPPER_INJECTION_LENGTH;
            }
            return $array;
        });
    }

    /**
     * @param $count
     *
     * @return string
     */
    public function stream_read(int $count): string
    {
        return $this->doWrapper(function () use ($count) {
            $result  = '';
            $wrap_it = false;
            if (ftell($this->handle) == 0 && $this->is_php_file) {
                $count  -= STREAM_WRAPPER_INJECTION_LENGTH;
                $wrap_it = true;
            }
            $file = fread($this->handle, $count);
        
            // discern injection string type to use
            if ($wrap_it) {
                // look for statement: "declare(strict_types=1);" 
                $pattern = '/declare\s*+\(\s*+strict_types\s*+\=\s*+1\s*+\)\s*+;/i';
                if (preg_match($pattern, $file, $matches)) {
                    // replace statement with same amount of spaces, to keep file stream the same size
                    $replace_with = str_repeat(' ', strlen($matches[0]));
                  //$file = preg_replace($pattern, $replace_with, $file, 1);
                    $file = str_replace($matches[0], $replace_with, $file);
                    // use alternate injection string
                    $result = STREAM_WRAPPER_INJECTION_STRING_ALT;
                } else {
                    // use default injection string
                    $result = STREAM_WRAPPER_INJECTION_STRING;
                }
            }
            return $result . $file;
        });
    }

    function stream_write($data)
    {
        return $this->doWrapper(function () use ($data) {
            return fwrite($this->handle, $data);
        });
    }

    public function stream_set_option(int $option, int $arg1, int $arg2)
    {
        return $this->doWrapper(function () use ($option, $arg1, $arg2) {
            $result = false;
            switch ($option) {
                case STREAM_OPTION_BLOCKING:
                    $result = stream_set_blocking($this->handle, $arg1);
                case STREAM_OPTION_READ_TIMEOUT:
                    $result = stream_set_timeout($this->handle, $arg1, $arg2);
                case STREAM_OPTION_WRITE_BUFFER:
                    $result = stream_set_write_buffer($this->handle, $arg2);
                default:
                    $result = false;
            }
            return $result;
        });
    }

    public function stream_eof(): bool
    {
        return $this->doWrapper(function () {
            return feof($this->handle);
        });
    }

    public function url_stat($uri, $flags)
    {
        return $this->doWrapper(function () use ($uri, $flags) {
            $path = $uri;

            if (!$path) {
                return false;
            }

            // Suppress warnings if requested or if the file or directory does not
            // exist. This is consistent with PHPs plain filesystem stream wrapper.
            return ($flags & STREAM_URL_STAT_QUIET || !file_exists($path)) ? @stat($path) : stat($path);
        });
    }

    function stream_tell()
    {
        return $this->doWrapper(function () {
            $result = ftell($this->handle);
            if ($this->is_php_file && $result >= 5)
                $result += STREAM_WRAPPER_INJECTION_LENGTH;
            return $result;
        });
    }

    function stream_seek($offset, $whence)
    {
        return $this->doWrapper(function () use ($offset, $whence) {
            return fseek($this->handle, $offset, $whence);
        });
    }

    function stream_flush()
    {
        return $this->doWrapper(function () {
            return fflush($this->handle);
        });
    }

    function unlink($path)
    {
        return $this->doWrapper(function () use ($path) {
            return unlink($path);
        });
    }

    function rename($path_from, $path_to)
    {
        return $this->doWrapper(function () use ($path_from, $path_to) {
            return rename($path_from, $path_to);
        });
    }

    function mkdir($path, $mode, $options)
    {
        return $this->doWrapper(function () use ($path, $mode, $options) {
            return mkdir($path, $mode, $options);
        });
    }

    function rmdir($path, $options)
    {
        return $this->doWrapper(function () use ($path, $options) {
            return rmdir($path, $options);
        });
    }

    function dir_opendir(string $path, int $options)
    {
        return $this->doWrapper(function () use ($path, $options) {
            return $this->handle = opendir($path);
        });
    }

    function dir_closedir()
    {
        return $this->doWrapper(function () {
            return closedir($this->handle);
        });
    }

    function dir_readdir()
    {
        return $this->doWrapper(function () {
            return readdir($this->handle);
        });
    }

    function dir_rewinddir()
    {
        return $this->doWrapper(function () {
            return rewind($this->handle);
        });
    }

    public function stream_metadata(string $path,int $options): bool {
        return true;
    }

    public function stream_lock(int $options): bool {
        return true;
    }
}
