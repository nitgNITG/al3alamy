<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=salem-mar3y', 'i9505651_mo1', 'W.xYJZROo7haEUnzYmk88');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

