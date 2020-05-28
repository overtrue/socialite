<?php

/*
 * This file is part of the overtrue/socialite.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\Socialite;

use ArrayAccess;
use JsonSerializable;
use Overtrue\Socialite\Contracts\UserInterface;
use Overtrue\Socialite\Traits\HasAttributes;

class User implements ArrayAccess, UserInterface, JsonSerializable, \Serializable
{
    use HasAttributes;

    /**
     * @param array $attributes
     */
    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->getAttribute('id');
    }

    /**
     * @return string
     */
    public function getUsername(): ?string
    {
        return $this->getAttribute('username', $this->getId());
    }

    /**
     * @return string
     */
    public function getNickname(): ?string
    {
        return $this->getAttribute('nickname');
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->getAttribute('name');
    }

    /**
     * @return string
     */
    public function getEmail(): ?string
    {
        return $this->getAttribute('email');
    }

    /**
     * @return string
     */
    public function getAvatar(): ?string
    {
        return $this->getAttribute('avatar');
    }

    /**
     * @param string $token
     *
     * @return $this
     */
    public function setToken(string $token)
    {
        $this->setAttribute('token', $token);

        return $this;
    }

    /**
     * @return \Overtrue\Socialite\AccessToken
     */
    public function getToken(): ?string
    {
        return $this->getAttribute('token');
    }

    /**
     * @return string
     */
    public function getAccessToken(): ?string
    {
        return $this->getToken();
    }

    /**
     * @return string
     */
    public function getRefreshToken(): ?string
    {
        return $this->getAttribute('refresh_token');
    }

    /**
     * @return int|null
     */
    public function getExpiresIn(): ?int
    {
        return $this->getAttribute('expires_in');
    }

    /**
     * @param array $user
     *
     * @return $this
     */
    public function setRaw(array $user)
    {
        $this->setAttribute('raw', $user);

        return $this;
    }

    /**
     * @return array
     */
    public function getRaw(): array
    {
        return $this->getAttribute('raw');
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->attributes;
    }

    public function serialize()
    {
        return serialize($this->attributes);
    }

    /**
     * @see  https://php.net/manual/en/serializable.unserialize.php
     *
     * @param string $serialized <p>
     *                           The string representation of the object.
     *                           </p>
     *
     * @since 5.1.0
     */
    public function unserialize($serialized)
    {
        $this->attributes = unserialize($serialized) ?: [];
    }
}
