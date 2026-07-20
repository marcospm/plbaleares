<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PasswordResetService
{
    private const TOKEN_BYTES = 16;

    private const TOKEN_TTL = '+2 hours';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function findUserByEmail(string $email): ?User
    {
        return $this->userRepository->findActiveByEmail($email);
    }

    /**
     * Crea un token nuevo o reutiliza uno vigente (evita invalidar enlaces por doble envío).
     */
    public function createOrReuseToken(User $user): string
    {
        $userId = $user->getId();
        if ($userId === null) {
            throw new \RuntimeException('Usuario sin identificador.');
        }

        $connection = $this->entityManager->getConnection();
        $row = $connection->fetchAssociative(
            'SELECT reset_password_token, reset_password_expires_at FROM user WHERE id = ? AND eliminado = 0',
            [$userId],
            [ParameterType::INTEGER]
        );

        if (\is_array($row)) {
            $existingToken = $row['reset_password_token'] ?? null;
            $expiresRaw = $row['reset_password_expires_at'] ?? null;

            if (\is_string($existingToken) && $existingToken !== '' && \is_string($expiresRaw)) {
                $expiresAt = new \DateTimeImmutable($expiresRaw);
                if ($expiresAt->getTimestamp() > time()) {
                    return $existingToken;
                }
            }
        }

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $expiresAt = (new \DateTimeImmutable(self::TOKEN_TTL))->format('Y-m-d H:i:s');

        $updated = $connection->executeStatement(
            'UPDATE user SET reset_password_token = ?, reset_password_expires_at = ? WHERE id = ? AND eliminado = 0',
            [$token, $expiresAt, $userId],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]
        );

        if ($updated !== 1) {
            $this->logger->error('No se pudo guardar el token de recuperacion', ['user_id' => $userId]);
            throw new \RuntimeException('No se pudo guardar el token de recuperacion.');
        }

        $storedToken = $connection->fetchOne(
            'SELECT reset_password_token FROM user WHERE id = ?',
            [$userId],
            [ParameterType::INTEGER]
        );

        if (!\is_string($storedToken) || !hash_equals($token, $storedToken)) {
            $this->logger->error('El token guardado no coincide con el generado', ['user_id' => $userId]);
            throw new \RuntimeException('Error al verificar el token de recuperacion.');
        }

        $this->entityManager->clear();
        $this->userRepository->invalidateUserCache($user);

        return $token;
    }

    public function findUserByToken(string $token): ?User
    {
        $userId = $this->entityManager->getConnection()->fetchOne(
            'SELECT id FROM user WHERE reset_password_token = ? AND eliminado = 0 LIMIT 1',
            [$token],
            [ParameterType::STRING]
        );

        if (!\is_numeric($userId)) {
            $this->logger->warning('Token de recuperacion no encontrado en BD', [
                'token_length' => strlen($token),
            ]);

            return null;
        }

        return $this->userRepository->find((int) $userId);
    }

    public function isExpired(User $user): bool
    {
        $expiresAt = $user->getResetPasswordExpiresAt();
        if (!$expiresAt) {
            return true;
        }

        return $expiresAt->getTimestamp() < time();
    }

    public function clearToken(User $user): void
    {
        $userId = $user->getId();
        if ($userId === null) {
            return;
        }

        $this->entityManager->getConnection()->executeStatement(
            'UPDATE user SET reset_password_token = NULL, reset_password_expires_at = NULL WHERE id = ?',
            [$userId],
            [ParameterType::INTEGER]
        );

        $this->entityManager->clear();
        $this->userRepository->invalidateUserCache($user);
    }
}
