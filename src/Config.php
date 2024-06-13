<?php

namespace OrdinalM\CronTodoTxt;

use Symfony\Component\Yaml\Yaml;

class Config
{
    private array $config = [];
    public const KEY_REPEAT_FILE = 'repeat_file';

    public function __construct()
    {
        $this->config = Yaml::parseFile(__DIR__ . '/../config.yml');
    }

    public function getValue(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }
}