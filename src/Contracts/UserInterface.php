<?php

namespace Overtrue\Socialite\Contracts;

interface UserInterface
{
    public function getId(): mixed;

    public function getNickname(): ?string;

    public function getName(): ?string;

    public function getEmail(): ?string;

    public function getAvatar(): ?string;

    public function getAccessToken(): ?string;

    public function getRefreshToken(): ?string;

    public function getExpiresIn(): ?int;
}
