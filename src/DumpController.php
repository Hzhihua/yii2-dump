<?php
/**
 * @Author: Hzhihua
 * @Date: 17-9-7 12:18
 * @Email cnzhihua@gmail.com
 */

namespace hzhihua\dump;

use Yii;
use yii\di\Instance;
use yii\db\Connection;
use yii\db\TableSchema;
use yii\helpers\Console;
use yii\console\Controller;
use hzhihua\dump\models\Schema;

/**
 * Generate migration file from an existing database
 *
 * ## Configuration in *console/config/main.php*
 * ```
 * return [
 *     'controllerMap' => [
 *         'dump' => [
 *             'class' => 'hzhihua\\dump\\DumpController',
 *             'db' => 'db', // Connection
 *             'templateFile' => '@vendor/hzhihua/yii2-dump/templates/migration.php',
 *             'generatePath' => '@console/migrations',
 *             'table' => 'table1,table2', // select which table will be dump(default filter migration table)
 *             'filter' => 'table3,table4', // table3 and table4 will be filtered when generating migration file
 *             'limit' => '0,1000', // select * from tableName limit 0,1000
 *             // ... ...
 *         ],
 *     ],
 * ];
 * ```
 *
 * ## Default Table Options
 * > ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_unicode_ci
 * it was defined at "@vendor/hzhihua/yii2-dump/src/Migration.php" file
 * 
 * ## Default Limit
 * ```
 * 0,1000
 * ```
 * 
 * ## Default Filter Table
 * > migration
 *
 *
 * ## Commands
 * 
 * Check help
 * ```
 * ./yii help dump
 * ```
 * 
 * print all over the table name without table prefix
 * ```
 * ./yii dump/list
 * ```
 *
 * generate all over the table migration file(default filter migration table)
 * ```
 * ./yii dump
 * or
 * ./yii dump/generate
 * ```
 * 
 * generate all over the table migration file but it only had some data between 0 and 10
 * ```
 * ./yii dump -limit=10
 * ```
 *
 * only generate table1,table2 migration file
 * ```
 * ./yii dump -table=table1,table2
 * ```
 * 
 * only generate table1,table2 migration file and only table1 will be dumped table data
 * ```
 * ./yii dump -table=table1,table2 -data=table1
 * ```
 * 
 * generate all over the migration table file without table1,table2
 * ```
 * ./yii dump -filter=table1,table2
 * ```
 * 
 * print all over the code of migration table file(default filter migration table)
 * ```
 * ./yii dump/create
 * ```
 * 
 * Display the 'createTable' code at the terminal.
 * ```
 * ./yii dump/create
 * ```
 * 
 * Display the 'dropTable' code at the terminal.
 * ```
 * ./yii dump/drop
 * ```
 * 
 * -type params
 * ```
 * ./yii dump -type=0/1/2/3
 * >>> -type=0 generate table migration file,
 * >>> -type=1 generate table data migration file,
 * >>> -type=2 generate add key migration file,
 * >>> -type=3 generate add foreign key migration file
 * ```
 * 
 * Useful commands (for macOS user):
 * ```
 * ./yii dump | pbcopy
 * ./yii dump/drop | pbcopy
 * ```
 * @package hzhihua\dump
 * @property \hzhihua\dump\abstracts\AbstractSchema $schema
 * @property \hzhihua\dump\abstracts\AbstractOutput $output
 * @Author Hzhihua <cnzhihua@gmail.com>
 */
class DumpController extends Controller
{
    /**
     * @var string sparactor table name of actionList
     */
    public $sparactor = ', ';

    /**
     * @var string a migration table name without table prefix
     */
    public $migrationTable = 'migration';

    /**
     * table options for application migrate file
     * **you can use " ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci " for mysql >= 5.7**
     *
     * Default: " ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_unicode_ci "
     *
     * ```
     * create table `user` (
     *     ... ...
     * ) ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_unicode_ci;
     * ```
     *
     * @var string
     */
    public $tableOptions = null;

    /**
     * migrate file prefix
     * default: current timestamp
     *
     * Usage:
     * ```php
     * [
     *      'class' => 'hzhihua\dump\DumpController',
     *      'filePrefix' => '123456_789012',
     * ],
     * ```
     *
     * @var null
     */
    public $filePrefix = null;

    /**
     *
     * ```
     * ./yii dump -type=0 # generate table migration file
     * ./yii dump -type=1 # generate table data migration file
     * ./yii dump -type=2 # generate add key migration file
     * ./yii dump -type=3 # generate add foreign key migration file
     * ```
     * @param int $type
     */
    public $type = null;

    /**
     * @var array i18n translation
     */
    public $translations = null;

    /**
     * 命令行参数 -filter=table1,table2 只生成table1,table2 表的Migration文件
     *
     * ```
     * ./yii dump -table=table1,table2 # generate table1,table2 migration file, include key/foreignKey, but not include dump table data
     * ./yii dump -table=table1,table2 -limit # dump table1,table2 all data, include key/foreignKey
     * ./yii dump -table=table1,table2 -data=table1 -limit # generate table1,table2 migration file, include key/foreignKey, but only *table1* will dump all table data
     * ```
     * it will only generate table1 and table2
     *
     * @var string which table will be generated
     * @default all table will be generated
     */
    public $table = null;

    /**
     * params: -data
     * ```
     * ./yii dump -data=table1,table2  # table1,table2 will be dumped all data
     * ./yii dump -data=table1,table2 -limit=0,100 # table1,table2 will be dumped data between 0 and 100
     * ./yii dump -data=table1,table2 -limit=5 # table1,table2 will be dumped data between 0 and 5
     * ./yii dump -data=table1,table2 -limit=5, # table1,table2 will be dumped data offset 5 to all
     * ```
     * @var string select tables that will be dumped some basic data
     */
    public $dumpData = null;

    /**
     * 命令行参数 -filter=table1,table2 只过滤table1,table2 数据库表
     *
     * ```
     * ./yii dump -filter=table1,table2 # it will generate all table migration file, but not include table1,table2
     * ```
     * it will not generte table1 and table2
     * @var null
     * @default filter migration,user table
     */
    public $filter = null;

    /**
     *
     * -limit= dump all data
     * @var string select * from table limit 0,1000
     */
    public $limit = '0,1000';

    /**
     * @inheritdoc
     */
    public $defaultAction = 'generate';

    /**
     * @var Connection|string the DB connection object or the application component ID of the DB connection.
     */
    public $db = 'db';

    /**
     * @var string $schema get schema table data
     */
    public $schema = 'hzhihua\dump\models\Schema';

    /**
     * @var string output table content at terminal or file
     */
    public $output = 'hzhihua\dump\models\Output';

    /**
     * @var string the directory of generating migrate file
     */
    public $generateFilePath = '@console/migrations';

    /**
     * @var string the directory of migrate template file
     */
    public $templateFile = '@vendor/hzhihua/yii2-dump/templates/migration.php';

    /**
     * change the order of applying migration file
     * but the value could not be changed
     * you only can change the key that influence the order of applying migration file
     * eg: configuration below
     * >>> first, generate: m123456_123456_0_table_tableName.php
     * >>> second, generate: m123456_123456_1_tableData_tableName.php
     * >>> third, generate: m123456_123456_2_key_tableName.php
     * >>> fourth, generate: m123456_123456_3_FK_tableName.php
     * so,
     * ```
     * ./yii migrate
     * ```
     * it will first apply the migration file that its name is "m123456_123456_0_table_tableName.php"
     * ... ...
     * the migration file will be apply at last time with the name of "m123456_123456_3_FK_tableName.php"
     * @var array
     */
    public $applyFileOrder = [
        0 => 'table',
        1 => 'tableData',
        2 => 'key',
        3 => 'FK', // foreign key
    ];

    /**
     * 记录所有已经 过滤 的数据库表，不带表前缀
     * collect all over the table of filter without table prefix
     * eg: ['table1', 'table2']
     *
     * @var array
     */
    protected $filters = [];

    /**
     * 记录所有已经 生成 的数据库表，不带表前缀
     * collect all over the table of generation without table prefix
     * eg: ['table1', 'table2']
     *
     * @var array
     */
    protected $generations = [];

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), [
            'db',
            'table',  // database table
            'filter', // filter database table
            'limit', // select data from table limit ...
            'type',
            'dumpData',
            'migrationTable',
            'templateFile' => 'templateFile',
            'generateFilePath' => 'generateFilePath',
        ]);
    }

    /**
     * @inheritdoc
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'table' => 'table',
            'filter' => 'filter',
            'limit' => 'limit',
            'type' => 'type',
            'data' => 'dumpData', // which table will be dump data
            'templateFile' => 'templateFile',
            'migrationTable' => 'migrationTable',
            'generateFilePath' => 'generateFilePath',
        ]);
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $schema = $this->schema;
        $this->schema = Instance::ensure($this->schema, Schema::class);
        $this->db = Instance::ensure($this->db, Connection::class);
        $this->schema->db = isset($schema['db']) ? Instance::ensure($schema['db'], Connection::class) : $this->db;
        $this->output = Instance::ensure($this->output, 'hzhihua\dump\Models\Output');

        Yii::$app->i18n->translations['dump*'] =
            $this->translations ?: [
                'class' => 'yii\i18n\PhpMessageSource',
                'basePath' => __DIR__ . '/../messages',
                'fileMap' => [
                    'dump' => 'main.php',
                ],
            ];

        return true;
    }

    /**
     * print all over the table name without table prefix
     *
     * @return int the status of the action execution
     */
    public function actionList()
    {
        $tableList = $this->schema->getTableList($this->db, $this->sparactor) . "\n";
        return $this->output->stdout($tableList, 0, Console::FG_YELLOW);
    }

    /**
     * generate migration file -type = 0 generate table, 1 generate table data, 2 generate add key, 3 generate add foreign key
     * @return int the status of the action execution
     */
    public function actionGenerate()
    {
        $this->output->startPrintf('Process');
        $tableOptions = $this->getOptions($this->table);
        $filterOptions = $this->getOptions($this->filter);
        $info = Yii::t('dump', 'Generate Migration File');

        foreach ($this->db->getSchema()->getTableSchemas() as $table) {
            // filter some table  -filter=...  -table=...
            if ($this->filterTable($table->name, $filterOptions, $tableOptions)) {
                continue;
            }

            switch ($this->type) {
                case '0':
                    $this->generateFile($table, 'table', $info, 2);
                    break;

                case '1':
                    $this->generateFile($table, 'tableData', $info, 2);
                    break;

                case '2':
                    $this->generateFile($table, 'key', $info, 2);
                    break;

                case '3':
                    $this->generateFile($table, 'FK', $info, 2);
                    break;

                default:
                    $this->generateFile($table, 'table', $info, 2);
                    $this->generateFile($table, 'tableData', $info, 2);
                    $this->generateFile($table, 'key', $info, 2);
                    $this->generateFile($table, 'FK', $info, 2);
                    break;
            }

        }

        $this->output->conclusion($this->generations, $this->filters);
        $this->output->endPrintf('Process');
        return 0;
    }

    /**
     * print the 'createTable' code.
     *
     * @return int the status of the action execution
     */
    public function actionCreate()
    {
        $this->output->startPrintf('Process');
        $tableOptions = $this->getOptions($this->table);
        $filterOptions = $this->getOptions($this->filter);

        $createTableInfo = Yii::t('dump', 'Create Table');
        $insertDataInfo = Yii::t('dump', 'Insert Data Of Table');
        $addKeyInfo = Yii::t('dump', 'Add Key Of Table');
        $addFKInfo = Yii::t('dump', 'Add Foreign Key Of Table');

        foreach ($this->db->getSchema()->getTableSchemas() as $table) {
            // filter some table  -filter=...  -table=...
            if ($this->filterTable($table->name, $filterOptions, $tableOptions)) {
                continue;
            }

            switch ($this->type) {
                case '0':
                    $this->printf($table, 'table', $createTableInfo);
                    break;

                case '1':
                    $this->printf($table, 'tableData', $insertDataInfo);
                    break;

                case '2':
                    $this->printf($table, 'key', $addKeyInfo);
                    break;

                case '3':
                    $this->printf($table, 'FK', $addFKInfo); // foreign key
                    break;

                default:
                    $this->printf($table, 'table', $createTableInfo);
                    $this->printf($table, 'tableData', $insertDataInfo);
                    $this->printf($table, 'key', $addKeyInfo);
                    $this->printf($table, 'FK', $addFKInfo); // foreign key
                    break;
            }

        }

        $this->output->conclusion($this->generations, $this->filters);
        $this->output->endPrintf('Process');
        return 0;
    }

    /**
     * print the 'dropTable' code.
     *
     * @return integer the status of the action execution
     */
    public function actionDrop()
    {
        $this->output->startPrintf('Process');
        $tableOptions = $this->getOptions($this->table);
        $filterOptions = $this->getOptions($this->filter);

        $dropTableInfo = Yii::t('dump', 'Drop Table');
        $dropDataInfo = Yii::t('dump', 'Drop Data Of Table');
        $dropKeyInfo = Yii::t('dump', 'Drop Key Of Table');
        $dropFKInfo = Yii::t('dump', 'Drop Foreign Key Of Table');

        foreach ($this->db->getSchema()->getTableSchemas() as $table) {
            // filter some table  -filter=...  -table=...
            if ($this->filterTable($table->name, $filterOptions, $tableOptions)) {
                continue;
            }

            switch ($this->type) {
                case '0':
                    $this->printf($table, 'dropTable', $dropTableInfo);
                    break;

                case '1':
                    $this->printf($table, 'dropTableData', $dropDataInfo);
                    break;

                case '2':
                    $this->printf($table, 'dropKey', $dropKeyInfo);
                    break;

                case '3':
                    $this->printf($table, 'dropFK', $dropFKInfo); // foreign key
                    break;

                default:
                    $this->printf($table, 'dropTable', $dropTableInfo);
                    $this->printf($table, 'dropTableData', $dropDataInfo);
                    $this->printf($table, 'dropKey', $dropKeyInfo);
                    $this->printf($table, 'dropFK', $dropFKInfo); // foreign key
                    break;
            }

        }

        $this->output->conclusion($this->generations, $this->filters);
        $this->output->endPrintf('Process');
        return 0;
    }

    /**
     * Unified generate file interface
     * @param TableSchema $table
     * @param string $functionName abstract function name
     * @see \hzhihua\dump\abstracts\AbstractSchema abstract function name
     * @param null $tip
     * @param int $indent text-indent 文本缩进
     * @return bool
     */
    public function generateFile(TableSchema $table, $functionName, $tip = null, $indent = 0)
    {
        $params = $this->getParams($table, $functionName, $indent);

        if (
            empty($params['safeUp'])   ||
            empty($params['safeDown']) ||
            empty($params['className'])
        ) {
            return false;
        }

        $template = Yii::getAlias($this->templateFile);
        $outputFile = Yii::getAlias($this->generateFilePath) . DIRECTORY_SEPARATOR . $params['className'] . '.php';

        if (! empty($tip)) {
            $this->output->startPrintf($tip); // record start time
            $this->output->stdout($outputFile . "\n");
            $rst = $this->output->generateFile($params, $template, $outputFile);
            $this->output->endPrintf($tip); // record end time
        } else {
            $this->output->stdout($outputFile);
            $rst = $this->output->generateFile($params, $template, $outputFile);
        }

        return $rst;
    }

    /**
     * check
     * ```
     * ./yii dump -limit # generate all table file and dump all data beside migration table
     * ./yii dump -table=table1,table2 -limit # dump and generate all data of table1,table2
     * ./yii dump -table=table1,table2 -data=table1 -limit # generate table1,table2 migration file and dump all data of table1
     * ./yii dump -filter=table1,table2 -limit # generate migration file beside table1,table2 and dump all data beside table1,table2
     * ./yii dump -filter=table1,table2 -data=table3 -limit # generate migration file beside table1,table2 and dump all data of table3
     * ```
     * @param $tableName
     * @return bool|string
     */
    public function checkLimit($tableName)
    {
        if (null !== $this->limit) {
            if (! empty($this->dumpData)) {
                $dumpData = $this->getOptions($this->dumpData);
                if (! in_array($tableName, $dumpData)) {
                    return false;
                }
            }
            return $this->limit;
        }

        return false;
    }

    /**
     * Unified printf interface
     * @param TableSchema $table
     * @param $functionName string $functionName abstract function name
     * @see \hzhihua\dump\abstracts\AbstractSchema abstract function name
     * @param null $tip
     * @param int $indent text-indent 文本缩进
     * @return bool|int
     */
    public function printf(TableSchema $table, $functionName, $tip = null, $indent = 0)
    {
        $params = [];
        $params[] = &$table;
        $params[] = $indent;
        $functionName = ucfirst($functionName);
        $functionName = 'get' . $functionName;

        if ('getTableData' === $functionName) {
            if ($limit = $this->checkLimit($table->name)) {
                array_pop($params);
                $params[] = $limit;
                $params[] = $indent;
            } else {
                return 0;
            }
        }

        if (! $data = call_user_func_array([$this->schema, $functionName], $params)) {
            return 0;
        }

        if (! empty($tip)) {
            $tip .= ': ' . $this->schema->removePrefix($table->name, $this->db->tablePrefix);
            $this->output->startPrintf($tip); // record start time
            $this->output->stdout($data);
            $this->output->endPrintf($tip); // record end time
        } else {
            $this->output->stdout($data);
        }


        return 0;
    }

    /**
     * change order of applying file
     * 改变"./yii migrate"应用migration文件的顺序
     *
     * @param $functionName
     * @return false|int
     */
    public function changeOrderOfApplyingFile($functionName)
    {
        return array_search($functionName, $this->applyFileOrder);
    }

    /**
     * Get relevant data with array style for generating file function
     * @param TableSchema $table
     * @param string $functionName abstract function name
     * @see \hzhihua\dump\abstracts\AbstractSchema abstract function name
     * @param int $indent text-indent 文本缩进
     * @return array
     */
    public function getParams(TableSchema $table, $functionName, $indent = 0)
    {
        $params = [];
        $params[] = &$table;
        $params[] = $indent;
        $ucFunctionName = ucfirst($functionName);
        $get = 'get' . $ucFunctionName;
        $drop = 'getDrop' . $ucFunctionName;

        $order = $this->changeOrderOfApplyingFile($functionName);

        if ('getTableData' === $get) {
            if ($limit = $this->checkLimit($table->name)) {
                array_pop($params);
                $params[] = $limit;
                $params[] = $indent;
            } else {
                return [];
            }
        }

        $safeUp = call_user_func_array([$this->schema, $get], $params);
        if (3 === count($params)) {
            unset($params[1]); // unser $this->limit
        }
        $safeDown = call_user_func_array([$this->schema, $drop], $params);
        $tableName = $this->schema->removePrefix($table->name, $this->db->tablePrefix);

        return [
            'safeUp' => $safeUp ? "\n" . $safeUp . "\n" : '',
            'safeDown' => $safeDown ? "\n" . $safeDown . "\n" : '',
            'className' => static::getClassName($order . '_' . $functionName . '_' . $tableName, $this->filePrefix),
        ];
        
    }

    /**
     * ```
     * ./yii dump -table=table1,table2
     * ```
     * >>> return ['tablePrefix_table1', 'tablePrefix_table2'];
     *
     * ```
     * ./yii dump -filter=table1,table2
     * ```
     * >>> return ['tablePrefix_table1', 'tablePrefix_table2'];
     *
     * add tablePrefix for options
     * @param $options string options params  eg: $options=table1,table2
     * @return array
     */
    public function getOptions($options)
    {
        if (empty($options)) {
            return [];
        }

        $tablePrefixOptionsArray = []; // add table prefix
        $optionsArray = explode(',', $options);

        foreach ($optionsArray as $key => $tableName) {
            $tablePrefixOptionsArray[$key] = $this->schema->addPrefix($tableName, $this->db->tablePrefix);
        }

        return $tablePrefixOptionsArray;
    }

    /**
     * which table will not be generated
     * ```
     * ./yii dump -table=table,table1 -filter=table2,table3
     * ```
     * @param $tableName string with prefix table name
     * @param $filterTable array -filter params
     * @param $optionsTable array -table params
     * @return boolean false not filter | true filter
     */
    public function filterTable($tableName, array $filterTable = [], array $optionsTable = [])
    {

        if (empty($filterTable) && empty($optionsTable)) {
            // filter migration table
            if ($tableName !== $this->schema->addPrefix($this->migrationTable, $this->db->tablePrefix)) {

                $tableName = $this->schema->removePrefix($tableName, $this->db->tablePrefix);
                if (! in_array($tableName, $this->generations) ) {
                    // 记录所有已经 生成 的数据库表
                    $this->generations[] = $tableName;
                }
                return false;
            }

        } elseif (! empty($filterTable) && empty($optionsTable)) {
            if (! in_array($tableName, $filterTable)) {

                $tableName = $this->schema->removePrefix($tableName, $this->db->tablePrefix);
                if (! in_array($tableName, $this->generations) ) {
                    // 记录所有已经 生成 的数据库表
                    $this->generations[] = $tableName;
                }
                return false;
            }

        } elseif (empty($filterTable) && ! empty($optionsTable)) {
            if (in_array($tableName, $optionsTable)) {

                $tableName = $this->schema->removePrefix($tableName, $this->db->tablePrefix);
                if (! in_array($tableName, $this->generations) ) {
                    // 记录所有已经 生成 的数据库表
                    $this->generations[] = $tableName;
                }
                return false;
            }

        } else { // ! empty($filterTable) && ! empty($optionsTable)
            if (in_array($tableName, $optionsTable) && ! in_array($tableName, $filterTable)) {

                $tableName = $this->schema->removePrefix($tableName, $this->db->tablePrefix);
                if (! in_array($tableName, $this->generations) ) {
                    // 记录所有已经 生成 的数据库表
                    $this->generations[] = $tableName;
                }

                return false;
            }
        }

        // 记录所有已经 过滤 的数据库表
        $tableName = $this->schema->removePrefix($tableName, $this->db->tablePrefix);
        if (! in_array($tableName, $this->filters) ) {
            $this->filters[] = $tableName;
        }

        return true; // filter
    }

    /**
     * @param string $classDesc class name description
     * @return string
     */
    public static function getClassName($classDesc, $filePrefix = null)
    {
        if ($filePrefix) {
            return sprintf(
                "m%s_%s",
                $filePrefix,
                $classDesc
            );
        }

        $time = $_SERVER['REQUEST_TIME'];

        // 文件名必须以"m123456_123456_"开头
        return sprintf(
            "m%s_%s_%s",
            date('ymd', $time),
            date('His', $time),
            $classDesc
        );
    }

}