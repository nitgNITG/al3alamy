<?php
function validate_token($api_key) {
    global $pdo;

    try {
        $query = "SELECT uuid FROM platforms WHERE api_key = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$api_key]);

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            return $row['uuid']; // إرجاع معرف المنصة
        } else {
            return false;
        }
    } catch (PDOException $e) {
        // التعامل مع الأخطاء في حالة حدوث استثناء
        error_log('Database error: ' . $e->getMessage());
        return false;
    }
}
?>
