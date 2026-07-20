<?php

namespace Database;

class PasswordRepository
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get the password hash for a user by user ID
     *
     * @param int $user_id
     * @return string|null Returns the password hash or null if user not found
     */
    public function getPasswordHashByUserId(int $user_id): ?string
    {
        $stmt = $this->pdo->prepare("SELECT `password_hash` FROM `users` WHERE `user_id` = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $result = $stmt->fetchAll();

        if (count($result) > 0) {
            return $result[0]['password_hash'];
        }

        return null;
    }

    /**
     * Update the password hash for a user
     *
     * @param int $user_id
     * @param string $new_password_hash
     * @return bool Returns true on success
     */
    public function updatePasswordHash(int $user_id, string $new_password_hash): bool
    {
        $stmt = $this->pdo->prepare("UPDATE `users` SET `password_hash` = ? WHERE `user_id` = ?");
        $stmt->execute([$new_password_hash, $user_id]);

        return true;
    }

    /**
     * Verify if the provided password matches the stored password hash
     *
     * @param int $user_id
     * @param string $password
     * @return bool
     */
    public function verifyPassword(int $user_id, string $password): bool
    {
        $stored_hash = $this->getPasswordHashByUserId($user_id);

        if ($stored_hash === null) {
            return false;
        }

        return password_verify($password, $stored_hash);
    }

    /**
     * Change user password with current password verification
     *
     * @param int $user_id
     * @param string $current_password
     * @param string $new_password
     * @return array Returns array with 'success' boolean and 'message' string
     */
    public function changePassword(int $user_id, string $current_password, string $new_password): array
    {
        // Verify current password
        if (!$this->verifyPassword($user_id, $current_password)) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect.'
            ];
        }

        // Hash new password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password
        $this->updatePasswordHash($user_id, $new_password_hash);

        return [
            'success' => true,
            'message' => 'Password changed successfully!'
        ];
    }
}
