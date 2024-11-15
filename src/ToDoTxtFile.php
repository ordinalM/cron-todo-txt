<?php

namespace OrdinalM\CronTodoTxt;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

class ToDoTxtFile implements IteratorAggregate
{
    private ?string $to_do_file = null;
    /** @var list<ToDoTxtTask|null> */
    private ?array $tasks = null;

    /**
     * @throws ToDoTxtException
     */
    public static function loadFromFile(?string $file = null): self
    {
        $todo_file = new self();
        if (empty($file)) {
            $todo_file->setToDoFile($_SERVER['TODO_FILE'] ?? null);
        } else {
            $todo_file->setToDoFile($file);
        }
        $todo_file->loadTasks();

        return $todo_file;
    }

    public function setToDoFile(?string $to_do_file): ToDoTxtFile
    {
        $this->to_do_file = $to_do_file;
        return $this;
    }

    /**
     * @throws ToDoTxtException
     */
    public function loadTasks(): self
    {
        $this->assertGoodToDoFile();
        $todo_data = file($this->to_do_file);
        $this->tasks = [];
        foreach ($todo_data as $line) {
            $this->tasks[] = ToDoTxtTask::parseFromString($line);
        }

        return $this;
    }

    /**
     * @throws ToDoTxtException
     */
    private function assertGoodToDoFile(string $filename = null): void
    {
        if (!$filename) {
            $filename = $this->to_do_file;
        }
        if (!$filename) {
            throw new ToDoTxtException('todo_file not set');
        }
        if (!file_exists($filename)) {
            throw new ToDoTxtException($this->to_do_file . ' does not exist');
        }
        if (!is_readable($filename)) {
            throw new ToDoTxtException($this->to_do_file . ' is not readable');
        }
        if (!is_writeable($filename)) {
            throw new ToDoTxtException($this->to_do_file . ' is not writeable');
        }
    }

    /**
     * @throws ToDoTxtException
     */
    public function getTask(int $n): ?ToDoTxtTask
    {
        $this->assertHasLoaded();
        $task = $this->tasks[$n - 1] ?? (new ToDoTxtTask());

        return $task->isEmpty() ? null : $task;
    }

    /**
     * @throws ToDoTxtException
     */
    private function assertHasLoaded(): void
    {
        if ($this->tasks) {
            return;
        }
        throw new ToDoTxtException('Tasks not yet loaded');
    }

    /**
     * @throws ToDoTxtException
     */
    public function deleteTask(int $n): self
    {
        if ($n <= 0 || $n > count($this->tasks)) {
            throw new ToDoTxtException($n . ' is outside the range of this file');
        }
        $this->tasks[$n - 1] = new ToDoTxtTask();

        return $this;
    }

    public function getTasks(): ?array
    {
        return $this->tasks;
    }

    /**
     * @throws ToDoTxtException
     */
    public function writeToFile(string $filename = null): self
    {
        if (!$filename) {
            $filename = $this->to_do_file;
        }
        $this->assertGoodToDoFile($filename);
        file_put_contents($filename, $this->__toString());

        return $this;
    }

    public function __toString(): string
    {
        return implode(PHP_EOL, array_map(static fn(ToDoTxtTask $task) => (string)$task, $this->tasks)) . PHP_EOL;
    }

    /**
     * @return Traversable<ToDoTxtTask>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->tasks);
    }

    private function launchEditor(): int
    {
        $pipes = [];
        $h_proc = proc_open('editor "' . $this->to_do_file . '"', [STDIN, STDOUT, STDERR], $pipes);

        return proc_close($h_proc);
    }
}