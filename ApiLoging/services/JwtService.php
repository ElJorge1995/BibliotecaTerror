<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Servicio encargado de la generación y verificación de tokens JWT (JSON Web Tokens).
 * Utiliza la librería firebase/php-jwt para asegurar la integridad de las sesiones.
 */
class JwtService
{
    /**
     * Genera un token JWT para un usuario.
     * Incluye datos de perfil básicos en el payload para evitar consultas
     * constantes a la BD, y un session id (`sid`) que el middleware compara
     * contra users.current_session_id para garantizar una única sesión activa.
     *
     * @param array $user Datos del usuario (id, email, role, etc.)
     * @param string $sid Session id (64 hex chars). Obligatorio.
     * @return string Token JWT codificado
     */
    public static function generate(array $user, string $sid): string
    {
        if ($sid === '') {
            throw new RuntimeException('sid required');
        }

        $now = time();
        $ttl = (int) (getenv('JWT_TTL_SECONDS') ?: 86400);
        $secret = getenv('JWT_SECRET') ?: 'change-this-secret';
        $issuer = getenv('JWT_ISSUER') ?: 'reglado-auth';

        $payload = [
            'iss' => $issuer,
            'iat' => $now,
            'exp' => $now + $ttl,
            'sub' => (int) $user['id'],
            'sid' => $sid,
            'email' => $user['email'],
            'username' => $user['username'] ?? null,
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'phone' => $user['phone'] ?? null,
            'name' => $user['name'],
            'role' => $user['role'],
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    /**
     * Verifica la validez de un token JWT.
     *
     * Además de la firma y la expiración (que valida la librería), comprueba
     * que el `iss` coincida con el emisor configurado: si el secret se
     * compartiera por error con otro servicio, sus tokens no servirían aquí.
     *
     * @param string $token Token a verificar
     * @return array Payload decodificado
     * @throws Exception Si el token es inválido, ha expirado o tiene mal `iss`
     */
    public static function verify(string $token): array
    {
        $secret = getenv('JWT_SECRET') ?: 'change-this-secret';
        $expectedIssuer = getenv('JWT_ISSUER') ?: 'reglado-auth';

        $decoded = (array) JWT::decode($token, new Key($secret, 'HS256'));

        $issuer = (string) ($decoded['iss'] ?? '');
        if ($issuer !== $expectedIssuer) {
            throw new RuntimeException('jwt issuer mismatch');
        }

        return $decoded;
    }
}
