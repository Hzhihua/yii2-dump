<?php
/**
 * Author: Hzhihua
 * Date: 17-9-7
 * Time: 下午12:18
 * Hzhihua <1044144905@qq.com>
 */

namespace hzhihua\dump\models;

use yii\db\Connection;
use yii\db\Query;
use yii\db\TableSchema;
use yii\db\Expression;
use yii\db\ColumnSchema;
use yii\helpers\ArrayHelper;
use hzhihua\dump\abstracts\AbstractSchema;

/**
 * Class Dump
 *
 * @package hzhihua\dump\models
 */
class Schema extends AbstractSchema
{
    /**
     * text ident format
     * 4 space
     */
    public $textIndent = '    ';

    /**
     * cache sql result "SHOW TABLE STATUS"
     * @var null
     */
    protected $_tableStatus = null;

    /**
     * table list
     * @param Connection $db
     * @param string $sparactor "table1, table2, table3"
     * @return string
     */
    public function getTableList(Connection $db, $sparactor = ', ')
    {
        $tableList = '';

        foreach ($db->schema->getTableNames() as $tableName) {
            $tableList .= static::removePrefix($tableName, $this->db->tablePrefix) . $sparactor;
        }

        return rtrim($tableList, $sparactor);
    }

    /**
     * get table safeUp definition with primary key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getTable(TableSchema $table, $indent = 0)
    {
        $safeUp =
            $this->getCreateTable($table->name, $indent)
            . $this->getColumns($table->columns, $indent + 1)
            . $this->getTableOptions($table, $indent)
        ;

        return $safeUp;
    }

    /**
     * get table safeDown definition
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getDropTable(TableSchema $table, $indent = 0)
    {
        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);
        return $this->textIndent($indent) . "\$this->dropTable('{{%$tableName}}');\n";
    }

    /**
     * fetch table data limit(start,end)
     * @param TableSchema $table
     * @param $limit string select data from table limit ...
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getTableData(TableSchema $table, $limit = null, $indent = 0)
    {

        if (null === $limit) {
            return '';
        }

        $data = [];
        $_limit = '';
        $batchInsertSql = '';
        $batchInsertLength = 500;

        if (is_bool($limit)) { // -limit
            $_limit = [null, null]; // select all data
        } elseif (empty($limit)) { // -limit=
            $_limit = [null, null]; // select all data
        } elseif (false !== strpos($limit, ',')) { // -limit=1,10 or -limit=1,
            $_limit = explode(',', $limit);

            if (empty($_limit[1])) { // -limit=1,
                $_limit[1] = null;
            }
        } else { // -limit=1
            $_limit[0] = 0;
            $_limit[1] = $limit;
        }

        $limitCount = $_limit[1] - $_limit[0];

        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        $coulmns = $this->_arrayStyleString($table->columns, 'key', $indent + 1);

        $query = (new Query())
            ->select('*')
            ->from($table->name)
            ->offset($_limit[0])
            ->limit($_limit[1])
        ;

        if ($limitCount > $batchInsertLength) {
            // 批量查询
            foreach ($query->batch(500) as $rows) {
                $data = array_merge($data, $rows);
            }

            if (empty($data)) {
                return '';
            }

            $countData = count($data);
            for ($i = 0; $i < $countData; $i += $batchInsertLength) {
                $insertData = $this->arrayStyleString(array_slice($data, $i, $batchInsertLength), $indent + 1);

                $batchInsertSql .= $this->textIndent($indent);
                $batchInsertSql .= "\$this->batchInsert('{{%$tableName}}', \n$coulmns, \n$insertData\n";
                $batchInsertSql .= $this->textIndent($indent) . ");\n";
            }

        } else {
            $data = $query->all();

            if (empty($data)) {
                return '';
            }

            $insertData = $this->arrayStyleString($data, $indent + 1);

            $batchInsertSql .= $this->textIndent($indent);
            $batchInsertSql .= "\$this->batchInsert('{{%$tableName}}', \n$coulmns, \n$insertData\n";
            $batchInsertSql .= $this->textIndent($indent) . ");\n";
        }


        return $batchInsertSql;

    }

    /**
     * truncate table data
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getDropTableData(TableSchema $table, $indent = 0)
    {
        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);
        return $this->textIndent($indent) . '$this->truncateTable(\'{{%' . $tableName . '}}\');' . "\n";
    }

    /**
     * get column key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getKey(TableSchema $table, $indent = 0)
    {
        $keySql = '';
        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        $data = $this->db->createCommand('SHOW KEYS FROM ' . $table->name)->queryAll();
        $keys = ArrayHelper::map($data, 'Column_name', 'Non_unique', 'Key_name');

        foreach ($keys as $keyName => $value) {
            $columns = null;
            $isUnique = null;
            foreach ($value as $columnName => $nonUnique) {
                $isUnique = $nonUnique ? 0 : 1;
                $columns .= $columnName . ',';
            }
            $columns = rtrim($columns, ',');

            $keySql .= $this->textIndent($indent); // text-indent 缩进

            if ('PRIMARY' === $keyName) { // add primary key
                $keySql .= "\$this->addPrimaryKey('', '{{%$tableName}}', '$columns');";
            } else {
                $keySql .= "\$this->createIndex('$keyName', '{{%$tableName}}', '$columns', $isUnique);";
            }

            $keySql .= "\n";

        }

        return $keySql;
    }

    /**
     * drop column key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getDropKey(TableSchema $table, $indent = 0)
    {
        $keySql = '';
        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        $data = $this->db->createCommand('SHOW KEYS FROM ' . $table->name)->queryAll();
        $keys = ArrayHelper::map($data, 'Column_name', 'Non_unique', 'Key_name');

        foreach ($keys as $keyName => $value) {
            $keySql .= $this->textIndent($indent);

            if ('PRIMARY' === $keyName) { // remove primary key
                $keySql .= "\$this->dropPrimaryKey('', '{{%$tableName}}');";
            } else {
                $keySql .= "\$this->dropIndex('$keyName', '{{%$tableName}}');";
            }

            $keySql .= "\n";

        }

        return $keySql;
    }

    /**
     * add forengin key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getFK(TableSchema $table, $indent = 0)
    {
        if (empty($table->foreignKeys)) {
            return '';
        }

        $definition = '';
        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        foreach ($table->foreignKeys as $fkName => $fk) {
            $refTable = '';
            $refColumns = '';
            $columns = '';
            $fkName = static::removePrefix($fkName, $this->db->tablePrefix);

            foreach ($fk as $k => $v) {
                if (0 === $k) {
                    $refTable = $v;
                } else {
                    $columns = $k;
                    $refColumns = $v;
                }
            }

            $definition .= $this->textIndent($indent) . sprintf(
                    "\$this->addForeignKey(\\Yii::\$app->getDb()->tablePrefix.'%s', '{{%%%s}}', '%s', '{{%%%s}}', '%s');\n",
                    $fkName, // 外健名称
                    $tableName, // 表名
                    $columns, // 列名
                    static::removePrefix($refTable, $this->db->tablePrefix), // 对应外健的表名
                    $refColumns // 对应外健的列名
                );

        }
        return $definition;
    }

    /**
     * drop foreign key
     * @param TableSchema $table
     * @param $indent int text-indent 文本缩进
     * @return string
     */
    public function getDropFK(TableSchema $table, $indent = 0)
    {
        if (empty($table->foreignKeys)) {
            return '';
        }

        $definition = '';
        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        foreach ($table->foreignKeys as $fkName => $fk) {
            $fkName = static::removePrefix($fkName, $this->db->tablePrefix);

            $definition .= $this->textIndent($indent) . sprintf(
                    "\$this->dropForeignKey(\\Yii::\$app->getDb()->tablePrefix.'%s', '{{%%%s}}');\n",
                    $fkName, // 外健名称
                    $tableName // 表名
                );

        }
        return $definition;
    }

    /**
     * return string of array style
     * eg: "[['1'], ['2'], ['3']]"
     * @param array $array 二维数组
     * @param int $indent int text-indent 文本缩进
     * @return string
     */
    public function arrayStyleString(array $array, $indent = 0)
    {
        $string = '';
        $string .= $this->textIndent($indent) . '[';
        $string .= "\n";

        foreach ($array as $key => $value) {
            $string .= $this->_arrayStyleString($value, 'value', $indent + 1) . ",\n";
        }

        $string .= $this->textIndent($indent) . ']';

        return $string;
    }

    /**
     * return string of array style
     * eg: "['1', '2', '3']"
     * @param array $array 一维数组
     * @param string $type
     * @param int $indent int text-indent 文本缩进
     * @return string
     */
    public function _arrayStyleString(array $array, $type = "value", $indent = 0)
    {
        $string = '';
        $string .= '[';

        if ('key' === $type) { // get column name
            foreach ($array as $key => $value) {
                $string .= '\'' . $key . '\', ';
            }
        } else {  // get rows data
            foreach ($array as $key => $value) {
                if (null === $value) {
                    $string .= 'NULL, ';
                } elseif (is_int($value)) {
                    $string .= $value . ', ';
                }else {
                    $string .= '\'' . $value . '\', ';
                }
            }
        }

        $string = rtrim($string, ', ');
        $string .= ']';

        return $this->textIndent($indent) . $string;
    }

    /**
     * @param int $indent text-indent number
     * @return string
     */
    public function textIndent($indent)
    {
        return str_repeat($this->textIndent, $indent);
    }

    /**
     * @param $tableName string
     * @param int $indent the number of text indent
     * @return string
     */
    public function getCreateTable($tableName, $indent = 0)
    {
        $tableName = static::removePrefix($tableName, $this->db->tablePrefix);
        $stdout = $this->textIndent($indent) . "\$this->createTable('{{%$tableName}}', [\n";
        return $stdout;
    }

    /**
     * Returns the columns definition.
     * @param $columns array
     * @param int $indent the number of text indent
     * @return string
     */
    public function getColumns(array $columns, $indent = 0)
    {
        $definition = '';
        foreach ($columns as $column) {
            $tmp = sprintf("'%s' => \$this->%s%s,\n",
                $column->name, static::getSchemaType($column), static::other($column));

            if (null !== $column->enumValues) {
                $tmp = static::replaceEnumColumn($tmp);
            }
            $definition .= $this->textIndent($indent) . $tmp; // 处理缩进问题
        }
        return $definition;
    }

    /**
     * Returns the primary key definition.
     * @param array $pk
     * @param array $columns
     * @param int $indent the number of text indent
     * @return string the primary key definition
     */
    public function getPrimaryKey(array $pk, array $columns, $indent = 0)
    {
        if (empty($pk)) {
            return '';
        }

        // Composite primary keys
        if (2 <= count($pk)) {
            $compositePk = implode(', ', $pk);
            return $this->textIndent($indent) . "'PRIMARY KEY ($compositePk)',\n";
        }
        // Primary key not an auto-increment
        $flag = false;
        foreach ($columns as $column) {
            if ($column->autoIncrement) {
                $flag = true;
            }
        }
        if (false === $flag) {
            return $this->textIndent($indent) . sprintf("'PRIMARY KEY (%s)',\n", $pk[0]);
        }
        return '';
    }

    /**
     * @param TableSchema $table
     * @param int $indent the number of text indent
     * @return string
     */
    public function getTableOptions(TableSchema $table, $indent = 0)
    {
        $tableComment = $this->getTableComment($table, $indent);
        $tableOptions = $this->textIndent($indent) . '], $this->tableOptions);' . "\n";

        if (! empty($tableComment)) {
            $tableOptions .= "\n" . $tableComment . "\n";
        }

        return $tableOptions;
    }

    /**
     * get table comment
     * @param TableSchema $table
     * @param int $indent the number of text indent
     * @return string
     */
    public function getTableComment(TableSchema $table, $indent = 0)
    {

        if (null === $this->_tableStatus) {
            try {
                // 不知 “SHOW TABLE STATUS” sql语句在其他数据库中是否会执行成功，所以用try catch捕获异常
                $this->_tableStatus = $this->db->createCommand('SHOW TABLE STATUS')->queryAll();
            } catch (\Exception $e) {
                return '';
            }
        }

        $tableName = static::removePrefix($table->name, $this->db->tablePrefix);

        foreach ($this->_tableStatus as $value) {
            if ($table->name === $value['Name'] && ! empty($value['Comment'])) {
                return $this->textIndent($indent) . "\$this->addCommentOnTable('{{%$tableName}}', '{$value['Comment']}');";
            }
        }

        return '';
    }

    /**
     * Returns the schema type.
     * @param ColumnSchema $column
     * @return string the schema type
     */
    public static function getSchemaType($column)
    {
        // primary key
        if ($column->isPrimaryKey && $column->autoIncrement) {
            if ('bigint' === $column->type) {
                return 'bigInteger()';
            } elseif('smallint' === $column->type) {
                return 'smallInteger()';
            }
            return 'integer()';
//            if ('bigint' === $column->type) {
//                return 'bigPrimaryKey()';
//            }
//            return 'primaryKey()';
        }

        // boolean
        if ('tinyint(1)' === $column->dbType) {
            return 'boolean()';
        }

        // smallint
        if ('smallint' === $column->type) {
            if (null === $column->size) {
                return 'smallInteger()';
            }
            return 'smallInteger';
        }

        // bigint
        if ('bigint' === $column->type) {
            if (null === $column->size) {
                return 'bigInteger()';
            }
            return 'bigInteger';
        }

        // enum
        if (null !== $column->enumValues) {
            // https://github.com/yiisoft/yii2/issues/9797
            $enumValues = array_map('addslashes', $column->enumValues);
            return "enum(['".implode('\', \'', $enumValues)."'])";
        }

        // others
        if (null === $column->size && 0 >= $column->scale) {
            return $column->type.'()';
        }

        return $column->type;
    }

    /**
     * Returns the other definition.
     * @param ColumnSchema $column
     * @return string the other definition
     */
    public static function other(ColumnSchema $column)
    {
        $definition = '';

        // size
        if (null !== $column->scale && 0 < $column->scale) {
            $definition .= "($column->precision,$column->scale)";

        } elseif (null !== $column->size && ! $column->autoIncrement && 'tinyint(1)' !== $column->dbType) {
            $definition .= "($column->size)";

        } elseif (null !== $column->size && ! $column->isPrimaryKey && $column->unsigned) {
            $definition .= "($column->size)";
        }

        // unsigned
        if ($column->unsigned) {
            $definition .= '->unsigned()';
        }

        // null
        if ($column->allowNull) {
            $definition .= '->null()';

        } elseif (! $column->autoIncrement) {
            $definition .= '->notNull()';
        }

        // default value
        if ($column->defaultValue instanceof Expression) {
            $definition .= "->defaultExpression('$column->defaultValue')";

        } elseif (is_int($column->defaultValue)) {
            $definition .= "->defaultValue($column->defaultValue)";

        } elseif (is_bool($column->defaultValue)) {
            $definition .= '->defaultValue('.var_export($column->defaultValue, true).')';

        } elseif (is_string($column->defaultValue)) {
            $definition .= "->defaultValue('".addslashes($column->defaultValue)."')";
        }

        // comment
        if (null !== $column->comment && '' !== $column->comment) {
            $definition .= "->comment('".addslashes($column->comment)."')";
        }

        // append

        return $definition;
    }

    /**
     * @param string $tmp temporary definition
     * @return string
     */
    public static function replaceEnumColumn($tmp)
    {
        return preg_replace("/,\n/", "\",\n", strtr($tmp, [
            '()' => '',
            '])' => ')',
            '),' => ',',
            '$this->enum([' => '"ENUM (',
            '->notNull' => ' NOT NULL',
            '->null' => ' DEFAULT NULL',
            '->defaultValue(' => ' DEFAULT ',
            '->comment(' => ' COMMENT ',
        ]));
    }

}