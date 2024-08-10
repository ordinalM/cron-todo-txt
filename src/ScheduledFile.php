<?php

namespace OrdinalM\CronTodoTxt;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

class ScheduledFile implements IteratorAggregate
{
    /** @var list<ScheduledTask> */
    private array $scheduled_tasks = [];

    /**
     * @throws ToDoTxtException
     */
    public static function createFromFile(string $filename): self
    {
        $scheduled_file = new self();
        foreach (file($filename, FILE_IGNORE_NEW_LINES) as $line) {
            if (str_starts_with($line, '#') || !$line) {
                continue;
            }

            $task = ToDoTxtTask::parseFromString($line);
            $scheduled_file->addScheduledTask(ScheduledTask::createFromTask($task));
        }

        return $scheduled_file;
    }

    public function addScheduledTask(ScheduledTask $scheduled_task): self
    {
        $this->scheduled_tasks[] = $scheduled_task;

        return $this;
    }

    public function writeToFile(string $filename): void
    {
        file_put_contents($filename, implode(PHP_EOL, $this->scheduled_tasks));
    }

    /**
     * @return Traversable<ScheduledTask>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->scheduled_tasks);
    }
}