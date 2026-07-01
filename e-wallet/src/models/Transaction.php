<?php

class Transaction {
    public $id;
    public $wallet_id;
    public $type;
    public $amount;
    public $description;
    public $created_at;

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // إنشاء معاملة جديدة
    public function createTransaction($wallet_id, $type, $amount, $description = '') {
        $query = "INSERT INTO transactions (uuid, wallet_id, type, amount, description) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($query);
        $uuid = $this->generateUUID();
        $stmt->execute([$uuid, $wallet_id, $type, $amount, $description]);

        return $stmt->rowCount() > 0 ? $uuid : false;
    }

    // استرجاع جميع المعاملات لمحفظة معينة
    public function getTransactionsByWallet($wallet_id) {
        $query = "SELECT uuid, type, amount, description, created_at 
                  FROM transactions 
                  WHERE wallet_id = ? 
                  ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$wallet_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // استرجاع معاملة معينة باستخدام UUID
    public function getTransactionByUUID($uuid) {
        $query = "SELECT * FROM transactions WHERE uuid = ?";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$uuid]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // توليد UUID فريد
    private function generateUUID() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

?>
