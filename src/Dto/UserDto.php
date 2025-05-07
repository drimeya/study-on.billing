<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UserDto",
    type: "object",
    properties: [
        new OA\Property(property: "username", type: "string", description: "The user's email address"),
        new OA\Property(property: "password", type: "string", description: "The user's password")
    ]
)]
class UserDto
{
    /**
     * @Assert\NotBlank(message="Name is mandatory")
     * @Assert\Email(message="Invalid email address")
     */
    public string $username;

    /**
     * @Assert\NotBlank(message="Password is mandatory")
     * @Assert\Length(min=6, minMessage="Password must be at least 6 characters long")
     */
    public string $password;

    // Геттеры и сеттеры для свойств
    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }
} 