<?php
require_once __DIR__ . '/../includes/config.php';

$conn = connectDB();
if (!$conn) {
    die("Database connection failed.\n");
}

$sql_path = __DIR__ . '/migrations/2026_08_12_add_face_and_location.sql';
$sql = file_get_contents($sql_path);

if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Migration executed successfully.\n";
} else {
    echo "Migration failed: " . $conn->error . "\n";
}
$conn->close();
