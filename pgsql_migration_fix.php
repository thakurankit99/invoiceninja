<?php
/**
 * PostgreSQL Migration Fix for Invoice Ninja
 * 
 * This script fixes the migration issue when running on PostgreSQL by 
 * manually executing the SQL with proper type casting.
 * 
 * Place this file in the application root and run it after the container starts.
 */

$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$database = getenv('DB_DATABASE');
$username = getenv('DB_USERNAME');
$password = getenv('DB_PASSWORD');

echo "Connecting to PostgreSQL database: {$database} on {$host}:{$port}\n";

try {
    // Connect to PostgreSQL
    $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "Successfully connected to database.\n";
    
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
        
        // Mark the problematic migration as complete
        $pdo->exec("
            INSERT INTO migrations (migration, batch) 
            VALUES ('2022_07_29_091235_correction_for_companies_table_types', 
                   (SELECT MAX(batch) FROM migrations))
            ON CONFLICT (migration) DO NOTHING
        ");
        
        echo "Migration marked as complete.\n";
    } else {
        echo "Column is already the correct type (integer). No changes needed.\n";
    }
    
    echo "Migration fix completed successfully.\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
} 