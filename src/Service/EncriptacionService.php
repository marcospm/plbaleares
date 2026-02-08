<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class EncriptacionService
{
    private const CIPHER_METHOD = 'AES-256-CBC';
    private const IV_LENGTH = 16; // 128 bits para AES

    public function __construct(
        private ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * Obtiene la clave de encriptación desde las variables de entorno
     */
    private function getEncryptionKey(): string
    {
        try {
            $key = $this->parameterBag->get('kernel.secret');
        } catch (\Exception $e) {
            // Fallback si no se puede obtener del parameterBag
            $key = $_ENV['APP_SECRET'] ?? 'default_secret_key_change_in_production';
        }
        
        // Usar el secret de Symfony como base, pero derivar una clave de 32 bytes para AES-256
        $derivedKey = hash('sha256', $key . '_chat_encryption_key', true);
        
        return $derivedKey;
    }

    /**
     * Encripta un texto
     */
    public function encriptar(string $texto): string
    {
        if (empty($texto)) {
            return $texto;
        }

        $key = $this->getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);
        
        $encrypted = openssl_encrypt(
            $texto,
            self::CIPHER_METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Error al encriptar el mensaje');
        }

        // Combinar IV y texto encriptado, luego codificar en base64
        $combined = $iv . $encrypted;
        return base64_encode($combined);
    }

    /**
     * Desencripta un texto
     */
    public function desencriptar(string $textoEncriptado): string
    {
        if (empty($textoEncriptado)) {
            return $textoEncriptado;
        }

        // Intentar detectar si el texto ya está encriptado
        // Si no es base64 válido o no tiene el formato esperado, asumir que no está encriptado
        $decoded = @base64_decode($textoEncriptado, true);
        if ($decoded === false || strlen($decoded) < self::IV_LENGTH) {
            // Probablemente es un mensaje antiguo sin encriptar
            return $textoEncriptado;
        }

        $key = $this->getEncryptionKey();
        $iv = substr($decoded, 0, self::IV_LENGTH);
        $encrypted = substr($decoded, self::IV_LENGTH);

        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER_METHOD,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            // Si falla la desencriptación, devolver el texto original (puede ser un mensaje antiguo)
            return $textoEncriptado;
        }

        return $decrypted;
    }
}
