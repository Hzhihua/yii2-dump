<?php
/**
 * @Author: Hzhihua
 * @Date: 17-9-7 12:18
 * @Email cnzhihua@gmail.com
 */

namespace hzhihua\dump\models;

use Yii;
use yii\base\View;
use hzhihua\dump\abstracts\AbstractOutput;
use hzhihua\dump\exceptions\CouldNotTouchFileException;
use hzhihua\dump\exceptions\CouldNotMkdirDirectoryException;
use yii\helpers\Console;

/**
 * Class Output
 * @package hzhihua\dump\models
 * @Author Hzhihua <cnzhihua@gmail.com>
 */
class Output extends AbstractOutput
{
    /**
     * enter 换行符号
     */
    const ENTER = "\n";

    /**
     * record start time with float
     * @var array
     */
    protected $startTime = [];

    /**
     * record end time with float
     * @var array
     */
    protected $endTime = [];

    /**
     * @param $content array the parameters (name-value pairs) that will be extracted and made available in the view file.
     * @see \yii\base\View::renderPhpFile()
     * @param $template string template file path
     * @param $outputFile string generate file path
     * @return bool
     * @throws CouldNotMkdirDirectoryException
     * @throws CouldNotTouchFileException
     */
    public function generateFile(array $content, $template, $outputFile)
    {
        if (
            empty($content['safeUp'])   ||
            empty($content['safeDown']) ||
            empty($content['className'])
        ) {
            return false;
        }

        $generatePath = dirname($outputFile);

        if (! is_dir($generatePath)) {
            if (mkdir($generatePath, 0755, true)) {
                throw new CouldNotMkdirDirectoryException($generatePath);
            }
        }

        $migrateContent = (new View())->renderPhpFile($template, $content);

        if (! file_put_contents($outputFile, $migrateContent)) {
            throw new CouldNotTouchFileException($outputFile);
        }

        return true;
    }

    /**
     * printf some thing at the terminal and record start time
     * @param string $string
     * @return int exit code
     */
    public function startPrintf($string)
    {
        if (empty($string)) {
            return '';
        }

        $this->startTime[$string] = microtime(true);

        $this->stdout('/*** Begin '. $string . " ***/" . self::ENTER, 0, Console::FG_YELLOW);

        return 0;
    }

    /**
     * printf some thing at the terminal and record end time
     * @param string $string
     * @return int exit code
     */
    public function endPrintf($string)
    {
        if (empty($string)) {
            return '';
        }
        $this->endTime[$string] = microtime(true);
        $time = $this->endTime[$string] - $this->startTime[$string];

        $this->stdout(
            '/*** End ' . $string . ' ... done (time: ' . sprintf("%.3f", $time) . "s) ***/" . self::ENTER . self::ENTER,
                0,
                Console::FG_GREEN
            );

        return 0;
    }

    /**
     * print conclusion at the terminal
     * @param $handleTable array all table name that had been generate
     * @param $filterTable array all table name that had been filter
     */
    public function conclusion($handleTable, $filterTable)
    {
        $enter = self::ENTER;
        $handleNumber = count($handleTable);
        $filterNumber = count($filterTable);
        $handleTableString = implode($handleTable, ', ');
        $filterTableString = implode($filterTable, ', ');
        $tables = Yii::t('dump', 'Tables');
        $handle = Yii::t('dump', 'Handle');
        $filter = Yii::t('dump', 'Filter');


        $header = <<<HEADER
/**********************************/
/************ Conclusion **********/
/**********************************/

HEADER;

        $footer = <<<FOOTER
        
/************ Conclusion *********/$enter
FOOTER;

        $handle = <<<HANDLE
/*** $handle $handleNumber $tables: */
>>> $handleTableString$enter
HANDLE;

        $filter = <<<FILTER
/*** $filter $filterNumber $tables: */
>>> $filterTableString
FILTER;


        $this->stdout($header, 0, Console::FG_YELLOW);
        $this->stdout($handle, Console::BOLD, Console::FG_YELLOW);
        $this->stdout($filter, Console::BOLD, Console::FG_YELLOW);
        $this->stdout($footer, 0, Console::FG_GREEN);
    }


}