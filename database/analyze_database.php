<?php
// analyze_database.php - Backend for database analysis
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    die(json_encode(['error' => 'No input data provided']));
}

$host = $input['host'] ?? 'localhost';
$database = $input['database'] ?? '';
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';
$action = $input['action'] ?? 'analyze';

if (empty($database) || empty($username)) {
    die(json_encode(['error' => 'Database name and username are required']));
}

try {
    // Create connection
    $dsn = "mysql:host=$host;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    $response = [
        'success' => true,
        'database' => $database,
        'host' => $host,
        'data' => []
    ];
    
    // Get all tables
    $response['data']['tables'] = getTables($pdo);
    
    // Get triggers
    $response['data']['triggers'] = getTriggers($pdo);
    
    // Get views
    $response['data']['views'] = getViews($pdo, $database);
    
    // Get foreign keys
    $response['data']['foreignKeys'] = getForeignKeys($pdo, $database);
    
    // Get procedures and functions
    $response['data']['procedures'] = getProcedures($pdo, $database);
    
    // Get indexes
    $response['data']['indexes'] = getIndexes($pdo);
    
    // Get database size
    $response['data']['databaseSize'] = getDatabaseSize($pdo, $database);
    
    // Check for common issues
    $response['data']['issues'] = checkIssues($pdo, $response['data']);
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

// Function to get all tables with details
function getTables($pdo) {
    $tables = [];
    
    // Get list of tables
    $stmt = $pdo->query("SHOW TABLE STATUS");
    $tableList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tableList as $table) {
        $tableName = $table['Name'];
        
        // Get columns for each table
        $columnsStmt = $pdo->query("SHOW FULL COLUMNS FROM `$tableName`");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get create statement
        $createStmt = $pdo->query("SHOW CREATE TABLE `$tableName`");
        $createResult = $createStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get row count
        $countStmt = $pdo->query("SELECT COUNT(*) as count FROM `$tableName`");
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $tables[] = [
            'name' => $tableName,
            'engine' => $table['Engine'],
            'collation' => $table['Collation'],
            'rows' => $count,
            'data_length' => $table['Data_length'],
            'index_length' => $table['Index_length'],
            'auto_increment' => $table['Auto_increment'],
            'create_time' => $table['Create_time'],
            'update_time' => $table['Update_time'],
            'columns' => $columns,
            'create_statement' => $createResult['Create Table'] ?? '',
            'size_mb' => round(($table['Data_length'] + $table['Index_length']) / 1048576, 2)
        ];
    }
    
    return $tables;
}

// Function to get all triggers
function getTriggers($pdo) {
    $triggers = [];
    
    try {
        $stmt = $pdo->query("SHOW TRIGGERS");
        $triggerList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($triggerList as $trigger) {
            $triggers[] = [
                'name' => $trigger['Trigger'],
                'event' => $trigger['Event'],
                'table' => $trigger['Table'],
                'statement' => $trigger['Statement'],
                'timing' => $trigger['Timing'],
                'created' => $trigger['Created'] ?? null,
                'definer' => $trigger['Definer'] ?? null
            ];
        }
    } catch (Exception $e) {
        // Some MySQL versions might not have all columns
    }
    
    return $triggers;
}

// Function to get all views
function getViews($pdo, $database) {
    $views = [];
    
    try {
        $stmt = $pdo->query("
            SELECT TABLE_NAME, VIEW_DEFINITION, CHECK_OPTION, IS_UPDATABLE, DEFINER, SECURITY_TYPE
            FROM INFORMATION_SCHEMA.VIEWS 
            WHERE TABLE_SCHEMA = '$database'
        ");
        $viewList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($viewList as $view) {
            $views[] = [
                'name' => $view['TABLE_NAME'],
                'definition' => $view['VIEW_DEFINITION'],
                'check_option' => $view['CHECK_OPTION'],
                'is_updatable' => $view['IS_UPDATABLE'],
                'definer' => $view['DEFINER'],
                'security_type' => $view['SECURITY_TYPE']
            ];
        }
    } catch (Exception $e) {
        // Handle error
    }
    
    return $views;
}

// Function to get foreign keys
function getForeignKeys($pdo, $database) {
    $foreignKeys = [];
    
    try {
        $stmt = $pdo->query("
            SELECT 
                kcu.TABLE_NAME,
                kcu.COLUMN_NAME,
                kcu.CONSTRAINT_NAME,
                kcu.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME,
                rc.UPDATE_RULE,
                rc.DELETE_RULE
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
                ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
            WHERE kcu.REFERENCED_TABLE_NAME IS NOT NULL
            AND kcu.TABLE_SCHEMA = '$database'
            ORDER BY kcu.TABLE_NAME, kcu.COLUMN_NAME
        ");
        
        $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Handle error
    }
    
    return $foreignKeys;
}

// Function to get procedures and functions
function getProcedures($pdo, $database) {
    $procedures = [];
    
    try {
        // Get procedures
        $stmt = $pdo->query("
            SELECT ROUTINE_NAME, ROUTINE_TYPE, DTD_IDENTIFIER, ROUTINE_DEFINITION, 
                   CREATED, LAST_ALTERED, ROUTINE_COMMENT
            FROM INFORMATION_SCHEMA.ROUTINES
            WHERE ROUTINE_SCHEMA = '$database'
            ORDER BY ROUTINE_TYPE, ROUTINE_NAME
        ");
        
        $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Handle error
    }
    
    return $procedures;
}

// Function to get indexes
function getIndexes($pdo) {
    $indexes = [];
    
    try {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW INDEXES FROM `$table`");
            $tableIndexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($tableIndexes as $index) {
                $indexes[] = [
                    'table' => $table,
                    'key_name' => $index['Key_name'],
                    'column' => $index['Column_name'],
                    'unique' => !$index['Non_unique'],
                    'type' => $index['Index_type'],
                    'cardinality' => $index['Cardinality']
                ];
            }
        }
    } catch (Exception $e) {
        // Handle error
    }
    
    return $indexes;
}

// Function to get database size
function getDatabaseSize($pdo, $database) {
    try {
        $stmt = $pdo->query("
            SELECT 
                SUM(data_length + index_length) AS total_size,
                SUM(data_length) AS data_size,
                SUM(index_length) AS index_size,
                COUNT(*) AS table_count
            FROM information_schema.tables 
            WHERE table_schema = '$database'
        ");
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_mb' => round($result['total_size'] / 1048576, 2),
            'data_mb' => round($result['data_size'] / 1048576, 2),
            'index_mb' => round($result['index_size'] / 1048576, 2),
            'table_count' => $result['table_count']
        ];
    } catch (Exception $e) {
        return null;
    }
}

// Function to check for common issues
function checkIssues($pdo, $data) {
    $issues = [];
    
    // Check for tables without primary key
    foreach ($data['tables'] as $table) {
        $hasPrimaryKey = false;
        foreach ($table['columns'] as $column) {
            if ($column['Key'] === 'PRI') {
                $hasPrimaryKey = true;
                break;
            }
        }
        
        if (!$hasPrimaryKey) {
            $issues[] = [
                'type' => 'warning',
                'category' => 'structure',
                'message' => "Table '{$table['name']}' doesn't have a primary key",
                'table' => $table['name']
            ];
        }
        
        // Check for large tables
        if ($table['rows'] > 100000) {
            $issues[] = [
                'type' => 'info',
                'category' => 'performance',
                'message' => "Table '{$table['name']}' has {$table['rows']} rows - consider partitioning or archiving",
                'table' => $table['name']
            ];
        }
        
        // Check for MyISAM tables (should use InnoDB)
        if ($table['engine'] === 'MyISAM') {
            $issues[] = [
                'type' => 'warning',
                'category' => 'engine',
                'message' => "Table '{$table['name']}' uses MyISAM engine - consider converting to InnoDB",
                'table' => $table['name']
            ];
        }
        
        // Check for tables with no indexes
        $hasIndex = false;
        foreach ($data['indexes'] as $index) {
            if ($index['table'] === $table['name']) {
                $hasIndex = true;
                break;
            }
        }
        
        if (!$hasIndex && $table['rows'] > 1000) {
            $issues[] = [
                'type' => 'warning',
                'category' => 'performance',
                'message' => "Table '{$table['name']}' has no indexes and {$table['rows']} rows",
                'table' => $table['name']
            ];
        }
    }
    
    // Check for orphaned views
    foreach ($data['views'] as $view) {
        if (empty($view['definition'])) {
            $issues[] = [
                'type' => 'error',
                'category' => 'structure',
                'message' => "View '{$view['name']}' might be corrupted or have missing dependencies",
                'view' => $view['name']
            ];
        }
    }
    
    return $issues;
}
?>