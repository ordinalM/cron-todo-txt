<?php

namespace OrdinalM\CronTodoTxt;

use Symfony\Component\Yaml\Yaml;

class Config
{
    public const KEY_REPEAT_FILE = 'repeat_file';
    public const KEY_TODOTXT_PATH = 'todotxt_path';
    private array $config = [self::KEY_REPEAT_FILE => './repeat.txt', self::KEY_TODOTXT_PATH => '/usr/bin/todo-txt'];

    public function __construct()
    {
        $this->config = array_merge($this->config, Yaml::parseFile(__DIR__ . '/../config.yml'));
    }

    public function getValue(string $key): mixed
    {
        return $this->config[$key] ?? null;
    }
}