<?php

class Wallet {
    public $id;
    public $uuid;
    public $platform_id;
    public $balance;
    public $created_at;

    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // إنشاء محفظة جديدة
    public function createWallet($platform_id) {
        $query = "INSERT INTO wallets (uuid, platform_id, balance) VALUES (?, ?, 0.00)";
        $stmt = $this->pdo->prepare($query);
        $uuid = $this->generateUUID();
        $stmt->execute([$uuid, $platform_id]);

        return $stmt->rowCount() > 0 ? $uuid : false;
    }

    // استرجاع بيانات المحفظة باستخدام UUID
    public function getWalletByUUID($uuid) {
        $query = "SELECT * FROM wallets WHERE uuid = ?";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$uuid]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // تحديث رصيد المحفظة
    public function updateBalance($uuid, $amount) {
        $query = "UPDATE wallets SET balance = balance + ? WHERE uuid = ?";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$amount, $uuid]);

        return $stmt->rowCount() > 0;
    }

    // التحقق من كفاية الرصيد
    public function checkSufficientBalance($uuid, $amount) {
        $wallet = $this->getWalletByUUID($uuid);
        return $wallet && $wallet['balance'] >= $amount;
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
