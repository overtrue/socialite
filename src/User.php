<?php

namespace Overtrue\Socialite;

use ArrayAccess;
use JsonSerializable;
use Overtrue\Socialite\Contracts\UserInterface;
use Overtrue\Socialite\Traits\HasAttributes;

class User implements ArrayAccess, UserInterface, JsonSerializable, \Serializable
{
    use HasAttributes;

    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    public function getId()
    {
        return $this->getAttribute('id');
    }

    public function getNickname(): ?string
    {
        return $this->getAttribute('nickname');
    }

    public function getName(): ?string
    {
        return $this->getAttribute('name');
    }

    public function getEmail(): ?string
    {
        return $this->getAttribute('email');
    }

    public function getAvatar(): ?string
    {
        return $this->getAttribute('avatar');
    }

    public function setToken(string $token): self
    {
        $this->setAttribute('token', $token);

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->getAttribute('token');
    }

    public function setRefreshToken(?string $refreshToken)
    {
        $this->setAttribute('refresh_token', $refreshToken);

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->getAttribute('refresh_token');
    }

    public function setExpiresIn(int $expiresIn)
    {
        $this->setAttribute('expires_in', $expiresIn);

        return $this;
    }

    public function getExpiresIn(): ?int
    {
        return $this->getAttribute('expires_in');
    }

    public function setRaw(array $user)
    {
        $this->setAttribute('raw', $user);

        return $this;
    }

    public function getRaw(): array
    {
        return $this->getAttribute('raw');
    }

    public function jsonSerialize()
    {
        return $this->attributes;
    }

    public function serialize()
    {
        return serialize($this->attributes);
    }

    public function unserialize($serialized)
    {
        $this->attributes = unserialize($serialized) ?: [];
    }
}
