<?php
/**
 * @Author: Hzhihua
 * @Date: 17-9-7 12:18
 * @Email cnzhihua@gmail.com
 */

namespace hzhihua\dump\exceptions;


use yii\console\Exception;

/**
 * Class CouldNotMkdirDirectoryException
 * @package hzhihua\dump\exceptions
 * @Author Hzhihua <cnzhihua@gmail.com>
 */
class CouldNotMkdirDirectoryException extends Exception
{
    /**
     * Construct the exception.
     *
     * @param string $directory the path of directory that could not be found
     * @param int $code the Exception code.
     * @param \Exception $previous the previous exception used for the exception chaining.
     */
    public function __construct($directory, $code = 1, \Exception $previous = null)
    {
        parent::__construct("Could not mkdir directory: \"$directory\".", $code, $previous);
    }
}