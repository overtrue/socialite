<?php

namespace Overtrue\Socialite\Contracts;

interface UserInterface
{
    public function getId(): ?string;

    public function getNickname(): ?string;

    public function getName(): ?string;

    public function getEmail(): ?string;

    public function getAvatar(): ?string;

    public function getToken(): ?string;

    public function getRefreshToken(): ?string;

    public function getExpiresIn(): ?int;
}
