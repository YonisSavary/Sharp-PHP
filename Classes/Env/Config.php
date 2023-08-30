<?php

namespace Sharp\Classes\Env;

use Exception;
use Sharp\Classes\Core\Component;
use Sharp\Core\Utils;

class Config
{
    use Component;

    protected ?string $filename = null;
    protected array $content = [];

    public static function getDefaultInstance()
    {
        return new self(Utils::relativePath("sharp.json"));
    }

    public function __construct(string $filename=null)
    {
        if (!$filename)
            return;

        $this->filename = $filename;

        // Warning: this verification is after the previous assignement
        // because we can create a config from nothing then save it in a file

        if (!is_file($filename))
            return;

        $body = file_get_contents($filename);
        $this->content = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    }

    public function set(string $key, mixed $value): void
    {
        $this->content[$key] = $value;
    }

    public function get(string $key, mixed $default=null): mixed
    {
        if (!array_key_exists($key, $this->content))
            return $default;
        return $this->content[$key];
    }

    public function toArray(string $key): array
    {
        return Utils::toArray($this->get($key, []));
    }

    public function try(string $key): mixed
    {
        return $this->get($key, false) ;
    }

    public function save(string $saveAs=null): void
    {
        $path = $saveAs ?? $this->filename;

        if (!$path)
            throw new Exception("Couldn't save a config without file name !");

        file_put_contents($path, json_encode($this->content), JSON_THROW_ON_ERROR);
    }
}