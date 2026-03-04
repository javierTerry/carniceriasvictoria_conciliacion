<?php

/**
 * Unit Test: Multi-Branch Connection Verifier
 * This script verifies that all 3 branch databases are accessible.
 * DO NOT RUN IN PRODUCTION WITHOUT PERMISSION.
 */

// Mocking required parts if necessary or including config
require_once __DIR__ . '/../config/config.php';

function testConnections()
{
    $configs = getBranchesConfig();
    $results = [];

    echo "=== Starting Multi-Branch Connection Test ===\n";

    foreach ($configs as $label => $conf) {
        echo "Testing connection for: $label... ";

        $conn = @mysqli_connect($conf['host'], $conf['user'], $conf['pass'], $conf['db']);

        if (!$conn) {
            echo "FAILED: " . mysqli_connect_error() . "\n";
            $results[$label] = false;
        } else {
            echo "SUCCESS\n";
            $results[$label] = true;
            mysqli_close($conn);
        }
    }

    echo "=== Test Summary ===\n";
    $allPassed = true;
    foreach ($results as $label => $passed) {
        if (!$passed)
            $allPassed = false;
        echo "$label: " . ($passed ? "OK" : "ERROR") . "\n";
    }

    if ($allPassed) {
        echo "\nRESULT: ALL CONNECTIONS VERIFIED.\n";
    } else {
        echo "\nRESULT: SOME CONNECTIONS FAILED. CHECK CONFIG.\n";
    }
}

// Execution block (commented out by default as per request to just create it)
// testConnections();
