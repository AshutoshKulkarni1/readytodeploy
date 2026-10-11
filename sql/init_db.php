<?php
/**
 * Database Auto-Initialization Script
 *
 * Safe for Railway / Docker / Local startup:
 * 1. Waits for MySQL to be reachable (with retry backoff).
 * 2. Checks if database schema already exists.
 * 3. Applies schema and seed data if this is a fresh database.
 */

require_once __DIR__ . '/../src/php/config/db.php';

echo "==> [DB INIT] Checking database connection (Host: " . DB_HOST . ":" . DB_PORT . ", DB: " . DB_NAME . ")...\n";

$maxRetries = 15;
$retryDelay = 2; // seconds
$pdo = null;

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    try {
        $pdo = getDB();
        // Run a lightweight test query
        $pdo->query("SELECT 1");
        echo "==> [DB INIT] Successfully connected to MySQL.\n";
        break;
    } catch (Throwable $e) {
        echo "==> [DB INIT] Attempt $attempt/$maxRetries: MySQL not ready yet ({$e->getMessage()}). Waiting {$retryDelay}s...\n";
        if ($attempt === $maxRetries) {
            echo "==> [DB INIT] Warning: Could not connect to database after $maxRetries attempts.\n";
            exit(1);
        }
        sleep($retryDelay);
    }
}

try {
    // 1. Check if admins table exists
    $tableExists = false;
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'admins'")->fetch();
        if (!empty($check)) {
            $tableExists = true;
        }
    } catch (Throwable $e) {
        $tableExists = false;
    }

    if (!$tableExists) {
        echo "==> [DB INIT] Fresh database detected. Running schema migration...\n";

        $schemaFile = file_exists(__DIR__ . '/init/01-schema.sql')
            ? __DIR__ . '/init/01-schema.sql'
            : __DIR__ . '/schema.sql';

        if (file_exists($schemaFile)) {
            $schemaSql = file_get_contents($schemaFile);
            // Clean database-specific directives to respect current DB connection
            $schemaSql = preg_replace('/CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+[^;]+;/i', '', $schemaSql);
            $schemaSql = preg_replace('/USE\s+[^;]+;/i', '', $schemaSql);

            $pdo->exec($schemaSql);
            echo "==> [DB INIT] Schema created successfully.\n";
        } else {
            echo "==> [DB INIT] Schema file not found, skipping.\n";
        }
    } else {
        echo "==> [DB INIT] Schema tables already present.\n";
    }

    // 2. Check if seed data is needed (if colleges table is empty)
    $hasColleges = false;
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM colleges")->fetchColumn();
        if ($count > 0) {
            $hasColleges = true;
        }
    } catch (Throwable $e) {
        $hasColleges = false;
    }

    if (!$hasColleges) {
        echo "==> [DB INIT] Seeding default colleges, courses, batches, and users...\n";
        $seedFile = file_exists(__DIR__ . '/init/02-seed.sql')
            ? __DIR__ . '/init/02-seed.sql'
            : __DIR__ . '/seed_data.sql';

        if (file_exists($seedFile)) {
            $seedSql = file_get_contents($seedFile);
            $seedSql = preg_replace('/USE\s+[^;]+;/i', '', $seedSql);
            $pdo->exec($seedSql);
            echo "==> [DB INIT] Seed data imported successfully.\n";
        }
    } else {
        echo "==> [DB INIT] Data already seeded.\n";
    }

    echo "==> [DB INIT] Database initialization complete.\n";
    exit(0);

} catch (Throwable $e) {
    echo "==> [DB INIT] Error during database initialization: " . $e->getMessage() . "\n";
    // Do not crash the entire container if migration has a partial error
    exit(0);
}
