<?php

namespace App\Dto;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    schema: 'UserDto',
    type: 'object',
    properties: [
        new OA\Property(property: 'username', type: 'string', description: 'Email пользователя'),
        new OA\Property(property: 'password', type: 'string', description: 'Пароль пользователя'),
    ]
)]
class UserDto
{
    #[Assert\NotBlank(message: 'Email обязателен')]
    #[Assert\Email(message: 'Некорректный email')]
    public string $username = '';

    #[Assert\NotBlank(message: 'Пароль обязателен')]
    #[Assert\Length(min: 6, minMessage: 'Пароль должен содержать не менее 6 символов')]
    public string $password = '';
}
