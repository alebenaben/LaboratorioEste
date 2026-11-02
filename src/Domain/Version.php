<?php
declare(strict_types=1);

namespace App\Domain;

/** Versiones del sistema instalado (CU-52). La más nueva primero. */
final class Version
{
    public const LISTA = [
        ['v' => 83, 'fecha' => '2026-11-02', 't' => 'Sistema instalado en el servidor: una sola base PostgreSQL compartida por todos los usuarios, permisos y claves controlados en el servidor (claves cifradas con Argon2id y cambio obligatorio de la clave inicial), facturas con CAE de ARCA (web service WSFEv1), mails automáticos desde sistema@integraldeleste.com.ar con el PDF adjunto y copias de seguridad automáticas fuera del servidor.'],
    ];

    public static function actual(): array
    {
        return self::LISTA[0];
    }
}
