<?php

namespace Overtrue\Socialite;

use ArrayAccess;
use JsonSerializable;
use Overtrue\Socialite\Contracts\ProviderInterface;
use Overtrue\Socialite\Contracts\UserInterface;
use Overtrue\Socialite\Traits\HasAttributes;

class User implements ArrayAccess, UserInterface, JsonSerializable
{
    use HasAttributes;

    public function __construct(array $attributes, protected ?ProviderInterface $provider = null)
    {
        $this->attributes = $attributes;
    }

    public function getId(): mixed
    {
        return $this->getAttribute('id') ?? $this->getEmail();
    }

    public function getNickname(): ?string
    {
        return $this->getAttribute('nickname') ?? $this->getName();
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

    public function setAccessToken(string $token): self
    {
        $this->setAttribute('access_token', $token);

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->getAttribute('access_token');
    }

    public function setRefreshToken(?string $refreshToken): self
    {
        $this->setAttribute('refresh_token', $refreshToken);

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->getAttribute('refresh_token');
    }

    public function setExpiresIn(int $expiresIn): self
    {
        $this->setAttribute('expires_in', $expiresIn);

        return $this;
    }

    public function getExpiresIn(): ?int
    {
        return $this->getAttribute('expires_in');
    }

    public function setRaw(array $user): self
    {
        $this->setAttribute('raw', $user);

        return $this;
    }

    public function getRaw(): array
    {
        return $this->getAttribute('raw');
    }

    public function setTokenResponse(array $response): static
    {
        $this->setAttribute('token_response', $response);

        return $this;
    }

    public function getTokenResponse()
    {
        return $this->getAttribute('token_response');
    }

    public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    public function __serialize(): array
    {
        return $this->attributes;
    }

    public function __unserialize(array $serialized)
    {
        $this->attributes = $serialized ?: [];
    }

    public function getProvider(): \Overtrue\Socialite\Contracts\ProviderInterface
    {
        return $this->provider;
    }

    public function setProvider(\Overtrue\Socialite\Contracts\ProviderInterface $provider): static
    {
        $this->provider = $provider;

        return $this;
    }
}
