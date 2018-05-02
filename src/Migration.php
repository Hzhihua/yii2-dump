<?php
/**
 * @Author: Hzhihua
 * @Date: 17-9-7 12:18
 * @Email cnzhihua@gmail.com
 */

namespace hzhihua\dump;

use Yii;
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
    const ENTER = "\n";

    /**
     * @var string table additional options
     */
    public $tableOptions = '';

    /**
     * @var array record which sql run successfully
     */
    protected $runSuccess = [];

    /**
     * @var \yii\db\Transaction save transaction for insert table data
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
            // you can use " ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci " for mysql >= 5.7

            $controllerMap = Yii::$app->controllerMap;
            foreach ($controllerMap as $key => $value) {
                if (
                    isset($value['tableOptions']) &&
                    isset($value['class']) &&
                    $value['class'] === 'hzhihua\dump\DumpController'
                ) {
                    $tableOptions = sprintf(
                        ' %s ',
                        $controllerMap[$key]['tableOptions']
                    );
                    break;
                }
            }

            isset($tableOptions) || $this->tableOptions = ' ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_unicode_ci ';
        } else {
            throw new Exception('Sorry, we only support mysql database.');
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

        } catch (Exception $e) {

            try {
                Output::stdout(self::ENTER . '*** running safeDown' . self::ENTER, 0, Console::FG_YELLOW);
                $this->safeDown();
            } catch (Exception $_e) {

            }

            Output::stdout(self::ENTER . '*** Error: ', 1, Console::FG_RED);
            throw new Exception($e->getMessage(), $e->errorInfo, 1);
        }

        return null;
    }

    /**
     * 添加自动增长
     * @param string $table 表名{{%tableName}}
     * @param string $column 字段名称
     * @param string $columnType 字段类型
     * @param boolean $property 字段属性
     * @param int $auto_increment 自动增长字段的启始值
     * @return array
     */
    public function addAutoIncrement($table, $column, $columnType, $property = null, $auto_increment = 0)
    {
        $auto_increment = (int) $auto_increment;
        $sql = $this->db->getQueryBuilder()->alterColumn($table, $column, $columnType);

        (null !== $property) && $sql .= " $property ";
        $sql .= "NOT NULL AUTO_INCREMENT";
        (0 !== $auto_increment) && $sql .= ", AUTO_INCREMENT = {$auto_increment}";

        $time = $this->beginCommand($sql);
        $this->db->createCommand()->setSql($sql)->execute();
        $this->endCommand($time);

        return [
            'table_name' => $table,
            'column_name' => $column,
            'column_type' => $columnType,
            'property' => $property,
            'auto_increment' => $auto_increment,
        ];
    }

    /**
     * 删除自动增长
     * @param string $table 表名{{%tableName}}
     * @param string $column 字段名称
     * @param string $columnType 字段类型
     * @param boolean $property 字段属性
     */
    public function dropAutoIncrement($table, $column, $columnType, $property = null)
    {
        $tip = "drop auto_increment on {$table}";
        $sql = $this->db->getQueryBuilder()->alterColumn($table, $column, $columnType);
        (null !== $property) && $sql .= " $property ";
        $sql .= "NOT NULL";

        $time = $this->beginCommand($tip);
        $this->db->createCommand()->setSql($sql)->execute();
        $this->endCommand($time);
    }

    /**
     * 删除主键
     * @param string $name
     * @param string $table
     */
    public function dropPrimaryKey($name, $table)
    {
        $tip = "drop primary key on {$table}";
        $sql =  'ALTER TABLE ' . $this->db->quoteTableName($table)
            . ' DROP PRIMARY KEY';

        $time = $this->beginCommand($tip);
        $this->db->createCommand()->setSql($sql)->execute();
        $this->endCommand($time);
    }
}

