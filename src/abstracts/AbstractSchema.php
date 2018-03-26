<?php
/**
 * @Author: Hzhihua
 * @Date: 17-9-7 12:18
 * @Email cnzhihua@gmail.com
 */

namespace hzhihua\dump\abstracts;

use Yii;
use yii\base\Component;
use yii\db\Connection;
use yii\db\TableSchema;

/**
 * Class abstractDump
 * @property Connection $db
 * @package hzhihua\dump\Models
 * @Author Hzhihua <cnzhihua@gmail.com>
 */
abstract class AbstractSchema extends Component
{
    /**
     * @var Connection|string the DB connection object or the application component ID of the DB connection.
     */
    public $db = 'db';

    /**
     * table list
     * @param Connection $db
     * @param string $sparactor "table1, table2, table3"
     * @return string
     */
    abstract public function getTableList(Connection $db, $sparactor = ', ');

    /**
     * get table safeUp definition
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    abstract public function getTable(TableSchema $table, $indent = 0);

    /**
     * get table safeDown definition
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    abstract public function getDropTable(TableSchema $table, $indent = 0);

    /**
     * fetch table data limit(start,end)
     * @param TableSchema $table
     * @param $limit string select data from table limit ...
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    abstract public function getTableData(TableSchema $table, $limit = null, $indent = 0);

    /**
     * truncate table data
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    abstract public function getDropTableData(TableSchema $table, $indent = 0);

    /**
     * get column key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    abstract public function getKey(TableSchema $table, $indent = 0);

    /**
     * drop column key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    abstract public function getDropKey(TableSchema $table, $indent = 0);

    /**
     * add forengin key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    abstract public function getFK(TableSchema $table, $indent = 0);

    /**
     * drop foreign key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    abstract public function getDropFK(TableSchema $table, $indent = 0);


    /**
     * add prefix for $string
     * @param $string string
     * @param $prefix string
     * @return string
     */
    public static function addPrefix($string, $prefix)
    {
        if (empty($prefix)) {
            return $string;
        }

        return strtolower($prefix) . $string;
    }

    /**
     * remove prefix for $string
     * @param $string string
     * @param $prefix string
     * @return string
     */
    public static function removePrefix($string, $prefix = null)
    {
        if (empty($prefix)) {
            $prefix = Yii::$app->controller->db->tablePrefix ?: null;
        }

        if (strpos($string, $prefix) === false) {
            return $string;
        }

        $position = strpos($string, $prefix) + strlen($prefix);

        return substr($string, $position);
    }
}