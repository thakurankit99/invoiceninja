<?php
/**
 * PostgreSQL Migration Fix for Invoice Ninja
 * 
 * This script fixes the migration issue when running on PostgreSQL by 
 * manually executing the SQL with proper type casting.
 */

// Debug environment variables
echo "DEBUG: Environment variables available to script:\n";
echo "DATABASE_URL: " . (getenv('DATABASE_URL') ?: 'NOT SET') . "\n";
echo "DB_CONNECTION: " . (getenv('DB_CONNECTION') ?: 'NOT SET') . "\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'NOT SET') . "\n";
echo "DB_PORT: " . (getenv('DB_PORT') ?: 'NOT SET') . "\n";
echo "DB_DATABASE: " . (getenv('DB_DATABASE') ?: 'NOT SET') . "\n";
echo "DB_USERNAME: " . (getenv('DB_USERNAME') ?: 'NOT SET') . "\n";
echo "DB_PASSWORD: " . (getenv('DB_PASSWORD') ? '******' : 'NOT SET') . "\n";

// Try to read from .env file directly
$envFile = '/var/www/html/.env';
if (file_exists($envFile)) {
    echo "Reading database configuration from .env file...\n";
    $envContent = file_get_contents($envFile);
    preg_match('/DB_HOST=(.*)/', $envContent, $hostMatches);
    preg_match('/DB_PORT=(.*)/', $envContent, $portMatches);
    preg_match('/DB_DATABASE=(.*)/', $envContent, $dbMatches);
    preg_match('/DB_USERNAME=(.*)/', $envContent, $userMatches);
    preg_match('/DB_PASSWORD=(.*)/', $envContent, $passMatches);
    
    $host = isset($hostMatches[1]) ? trim($hostMatches[1]) : null;
    $port = isset($portMatches[1]) ? trim($portMatches[1]) : null;
    $database = isset($dbMatches[1]) ? trim($dbMatches[1]) : null;
    $username = isset($userMatches[1]) ? trim($userMatches[1]) : null;
    $password = isset($passMatches[1]) ? trim($passMatches[1]) : null;
} else {
    // Fallback to environment variables
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '5432'; // Default PostgreSQL port
    $database = getenv('DB_DATABASE');
    $username = getenv('DB_USERNAME');
    $password = getenv('DB_PASSWORD');
}

// Extract from DATABASE_URL if available and other variables are missing
$databaseUrl = getenv('DATABASE_URL');
if ($databaseUrl && (!$host || !$database)) {
    echo "Extracting connection details from DATABASE_URL...\n";
    
    // Parse URL components
    $parsedUrl = parse_url($databaseUrl);
    
    if ($parsedUrl) {
        $host = $parsedUrl['host'] ?? $host;
        $port = $parsedUrl['port'] ?? $port;
        $username = $parsedUrl['user'] ?? $username;
        $password = $parsedUrl['pass'] ?? $password;
        $path = $parsedUrl['path'] ?? '';
        $database = ltrim($path, '/');
    }
}

// Validate required parameters
if (empty($host) || empty($database)) {
    echo "ERROR: Missing required database connection parameters!\n";
    echo "Host: " . ($host ?: 'NOT SET') . "\n";
    echo "Port: " . ($port ?: 'NOT SET') . "\n";
    echo "Database: " . ($database ?: 'NOT SET') . "\n";
    echo "Username: " . ($username ?: 'NOT SET') . "\n";
    echo "Password: " . ($password ? '******' : 'NOT SET') . "\n";
    exit(1);
}

echo "Connecting to PostgreSQL database: {$database} on {$host}:{$port}\n";

try {
    // Connect to PostgreSQL
    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "Successfully connected to database.\n";
    
    // Check if the companies table exists
    $tablesQuery = $pdo->query("SELECT to_regclass('public.companies') IS NOT NULL as exists");
    $tableExists = $tablesQuery->fetchColumn();
    
    if (!$tableExists) {
        echo "Companies table does not exist yet. No fix needed at this stage.\n";
        exit(0);
    }
    
    // Check if we need to run the fix
    $stmt = $pdo->query("
        SELECT data_type 
        FROM information_schema.columns 
        WHERE table_name = 'companies' 
        AND column_name = 'enabled_expense_tax_rates'
    ");
    
    $dataType = $stmt->fetchColumn();
    
    if ($dataType && strtolower($dataType) !== 'integer') {
        echo "Column needs to be converted (current type: {$dataType}).\n";

        // Execute the proper PostgreSQL type conversion
        $pdo->exec("
            ALTER TABLE companies 
            ALTER COLUMN enabled_expense_tax_rates TYPE integer 
            USING CASE 
                WHEN enabled_expense_tax_rates = 'true' THEN 1
                WHEN enabled_expense_tax_rates = 'false' THEN 0 
                ELSE enabled_expense_tax_rates::integer 
            END,
            ALTER COLUMN enabled_expense_tax_rates SET NOT NULL,
            ALTER COLUMN enabled_expense_tax_rates SET DEFAULT 0
        ");
        
        echo "Successfully converted 'enabled_expense_tax_rates' column to integer type.\n";
        
        // Check if migrations table exists
        $migrationsQuery = $pdo->query("SELECT to_regclass('public.migrations') IS NOT NULL as exists");
        $migrationsExist = $migrationsQuery->fetchColumn();
        
        if ($migrationsExist) {
            // Mark the problematic migration as complete
            $pdo->exec("
                INSERT INTO migrations (migration, batch) 
                VALUES ('2022_07_29_091235_correction_for_companies_table_types', 
                       (SELECT MAX(batch) FROM migrations))
                ON CONFLICT (migration) DO NOTHING
            ");
            
            echo "Migration marked as complete.\n";
        } else {
            echo "Migrations table does not exist yet. Skipping migration marking.\n";
        }
    } else if ($dataType) {
        echo "Column is already the correct type (integer). No changes needed.\n";
    } else {
        echo "Column 'enabled_expense_tax_rates' not found. Skipping fix.\n";
    }
    
    echo "Migration fix completed successfully.\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
} 