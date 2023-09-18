<?php

namespace Sharp\Classes\Extras;

use Sharp\Classes\Env\Session;

trait SessionStraw
{
    final protected static function getKey(): string
    {
        return "sharp.session-straw." . self::class;
    }

    final public static function set(mixed $value): void
    {
        Session::getInstance()->set(self::getKey(), $value);
    }

    final public static function get(): mixed
    {
        return Session::getInstance()->try(self::getKey());
    }

    final public static function unset(): void
    {
        Session::getInstance()->unset(self::getKey());
    }

}