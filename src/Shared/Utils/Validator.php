<?php

declare(strict_types=1);

namespace App\Shared\Utils;

use App\Shared\Errors\ValidationException;

class Validator
{
    /**
     * Valida que las claves requeridas existan y no estén vacías.
     *
     * @param array<string, mixed> $data
     * @param array<string> $requiredFields
     * @throws ValidationException
     */
    public static function validateRequired(array $data, array $requiredFields): void
    {
        $errors = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $errors[$field] = "El campo '{$field}' es obligatorio.";
            }
        }

        if (!empty($errors)) {
            throw new ValidationException('Faltan campos obligatorios', $errors);
        }
    }

    public static function validateEmail(string $email): string
    {
        $clean = trim($email);
        if (!filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Correo electrónico inválido', ['email' => 'Formato de correo no válido']);
        }
        return strtolower($clean);
    }
}

