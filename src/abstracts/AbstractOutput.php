<?php
/**
 * @Author: Hzhihua
 * @Date: 17-9-7 12:18
 * @Email cnzhihua@gmail.com
 */

namespace hzhihua\dump\abstracts;

use yii\base\Component;
use yii\helpers\Console;

/**
 * Class AbstractOutput
 * @package hzhihua\dump\abstracts
 * @Author Hzhihua <cnzhihua@gmail.com>
 */
abstract class AbstractOutput extends Component
{

    /**
     * @param $content array the parameters (name-value pairs) that will be extracted and made available in the view file.
     * @see \yii\base\View::renderPhpFile()
     * @param $template string template file path
     * @param $outputFile string generate file path
     * @return bool
     */
    abstract public function generateFile(array $content, $template, $outputFile);

    /**
     * printf some thing at the terminal and record start time
     * @param string $string
     * @return int exit code
     */
    abstract public function startPrintf($string);

    /**
     * printf some thing at the terminal and record end time
     * @param string $string
     * @return int exit code
     */
    abstract public function endPrintf($string);

    /**
     * print conclusion at the terminal
     * @param $handleTable array table name that had been generate
     * @param $filterTable array table name that had been filter
     */
    abstract public function conclusion($handleTable, $filterTable);

    /**
     * Prints a string to STDOUT
     *
     * You may optionally format the string with ANSI codes by
     * passing additional parameters using the constants defined in [[\yii\helpers\Console]].
     *
     * Example:
     *
     * ```
     * $this->stdout('This will be red and underlined.', Console::FG_RED, Console::UNDERLINE);
     * ```
     *
     * @param string $string the string to print
     * @return int|bool Number of bytes printed or false on error
     */
    public static function stdout($string)
    {
        $args = func_get_args();
        array_shift($args);

        if (empty($args)) {
            return Console::stdout($string);
        }

        $string = Console::ansiFormat($string, $args);
        return Console::stdout($string);
    }
}