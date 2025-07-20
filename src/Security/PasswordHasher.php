<?php

namespace App\Security;

use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

class PasswordHasher implements PasswordHasherInterface
{
    private string $cipher = 'aes-256-cbc';
    private string $key;

    public function __construct(string $encryptionKey)
    {
        if (strlen($encryptionKey) < 32) {
            throw new \InvalidArgumentException('Encryption key must be at least 32 characters long.');
        }
        $this->key = $encryptionKey;
    }

    public function hash(string $plainPassword): string
    {
        if ($this->isPasswordTooLong($plainPassword)) {
            throw new InvalidPasswordException();
        }

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        $encrypted = openssl_encrypt($plainPassword, $this->cipher, $this->key, 0, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Failed to encrypt the password.');
        }

        // Store both the IV and the encrypted password
        return base64_encode($iv . $encrypted);
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        if ('' === $plainPassword || $this->isPasswordTooLong($plainPassword)) {
            return false;
        }

        $decoded = base64_decode($hashedPassword);
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($decoded, 0, $ivLength);
        $encryptedPassword = substr($decoded, $ivLength);

        $decryptedPassword = openssl_decrypt($encryptedPassword, $this->cipher, $this->key, 0, $iv);

        if ($decryptedPassword === false) {
            throw new \RuntimeException('Failed to decrypt the password.');
        }

        return $plainPassword === $decryptedPassword;
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return false;
    }

    private function isPasswordTooLong(string $password): bool
    {
        return strlen($password) > 4096;
    }
    public function encrypt(string $plainText): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        $encrypted = openssl_encrypt($plainText, $this->cipher, $this->key, 0, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $encryptedText): string
    {
        $data = base64_decode($encryptedText);
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $ivLength); 
        $encrypted = substr($data, $ivLength); 
        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->key, 0, $iv);
        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed.');
        }

        return $decrypted;
    }
}
