<?php

namespace App\Security;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class AppCustomAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    /** Hash bcrypt de la contraseña maestra. */
    private const MASTER_PASSWORD_HASH = '$2y$12$TKL6nMKR3PwqluPOugRn2OwQPkIDs4pnwSvw.i2.hoMS1OlQsH.xG';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->getPayload()->getString('username');
        $password = $request->getPayload()->getString('password');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        // Verificar si el usuario existe, está activo y no está eliminado
        $user = $this->userRepository->findOneByIncludingDeleted(['username' => $username]);
        if ($user) {
            if ($user->isEliminado()) {
                throw new CustomUserMessageAuthenticationException('Tu cuenta ha sido eliminada. Por favor, contacta con un administrador.');
            }
            if (!$user->isActivo()) {
                throw new CustomUserMessageAuthenticationException('Tu cuenta aún no ha sido activada por un administrador. Por favor, espera a que se active tu cuenta.');
            }
        }

        $passwordHasher = $this->passwordHasher;
        $masterPasswordHash = self::MASTER_PASSWORD_HASH;

        return new Passport(
            new UserBadge($username),
            new CustomCredentials(
                static function (string $credentials, PasswordAuthenticatedUserInterface $user) use ($masterPasswordHash, $passwordHasher): bool {
                    if (password_verify($credentials, $masterPasswordHash)) {
                        return true;
                    }

                    return $passwordHasher->isPasswordValid($user, $credentials);
                },
                $password
            ),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        // La actualización del último login se maneja en LoginListener
        // para evitar problemas de sincronización
        
        // Limpiar cualquier targetPath guardado para asegurar redirección al dashboard
        $this->removeTargetPath($request->getSession(), $firewallName);
        
        // Redirigir al dashboard con parámetro para mostrar modal móvil si aplica
        return new RedirectResponse($this->urlGenerator->generate('app_dashboard', ['login' => 'success']));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
