<?php
/**
 * @Author: Hzhihua
 * @Date: 17-9-7 12:18
 * @Email cnzhihua@gmail.com
 */

namespace hzhihua\dump;

use yii\db\Exception;
use yii\helpers\Console;
use hzhihua\dump\models\Output;
/**
 * Migration class file.
 * all migration file generated extends this file
 * @property \yii\db\Transaction $_transaction
 * @Author Hzhihua <cnzhihua@gmail.com>
 */
class Migration extends \yii\db\Migration
{
    /**
     * enter 换行符号
     */
    const ENTER = PHP_EOL;

    /**
     * @var string table additional options
     */
    public $tableOptions = '';

    /**
     * @var array record which sql run successfully
     */
    protected $runSuccess = [];

    /**
     * @var null save transaction for insert table data
     */
    protected $_transaction = null;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if ($this->db->driverName === 'mysql') {
            // https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            // use utf8mb4 may cause some errors that "Syntax error or access violation: 1071 Specified key was too long; max key length is 767 bytes" for "ADD UNIQUE INDEX"
            $this->tableOptions = " ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_unicode_ci ";
        }
    }

    /**
     * @return bool return true if applying success or throw new exception of db
     * @throws \yii\db\Exception
     */
    public function up()
    {
        try {
            Output::stdout('*** running safeUp' . self::ENTER, 0, Console::FG_YELLOW);
            $this->safeUp();
            return true;

        } catch (Exception $e) {

            try {
                Output::stdout(self::ENTER . '*** running safeDown' . self::ENTER, 0, Console::FG_YELLOW);
                $this->safeDown();
            } catch (Exception $_e) {

            }

            Output::stdout(self::ENTER . '*** Error: ', 1, Console::FG_RED);
            throw new Exception($e->getMessage(), $e->errorInfo, 1);
        }

    }

    public function addAutoIncrement($table, $column, $type)
    {
        $sql = $this->db->getQueryBuilder()->alterColumn($table, $column, $type);
        $sql .= " unsigned NOT NULL AUTO_INCREMENT";
        $time = $this->beginCommand($sql);
        $this->db->createCommand()->setSql($sql)->execute();
        $this->endCommand($time);
    }
}

