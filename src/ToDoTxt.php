<?php

namespace OrdinalM\CronTodoTxt;

class ToDoTxt
{
    private string $todo_full_sh;

    public function __construct()
    {
        $this->todo_full_sh = $_SERVER['TODO_FULL_SH'] ?? '/usr/bin/todo-txt';
    }

    public function runCommand(string $command, array $values = []): string
    {
        $cli_values = array_map(static fn(string $value) => "'" . str_replace("'", "\'", $value) . "'", $values);
        exec($this->todo_full_sh . ' ' . $command . ' ' . implode(' ', $cli_values), $output);

        return implode(PHP_EOL, $output);
    }
}