<?php

namespace OrdinalM\CronTodoTxt;

class ToDoTxtFile
{
    private ?string $to_do_file = null;
    /** @var list<ToDoTxtTask> */
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
    private function assertGoodToDoFile(): void
    {
        if (!$this->to_do_file) {
            throw new ToDoTxtException('todo_file not set');
        }
        if (!file_exists($this->to_do_file)) {
            throw new ToDoTxtException($this->to_do_file . ' does not exist');
        }
        if (!is_readable($this->to_do_file)) {
            throw new ToDoTxtException($this->to_do_file . ' is not readable');
        }
        if (!is_writeable($this->to_do_file)) {
            throw new ToDoTxtException($this->to_do_file . ' is not writeable');
        }
    }

    public function getTask(int $n): ?ToDoTxtTask
    {
        $this->assertHasLoaded();
        $task = $this->tasks[$n - 1] ?? (new ToDoTxtTask());

        return $task->isEmpty() ? null : $task;
    }

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
    public function dropLine(int $n): self
    {
        if ($n <= 0 || $n > count($this->tasks)) {
            throw new ToDoTxtException($n . ' is outside the range of this file');
        }
        $this->tasks[$n - 1]->setText('');

        return $this;
    }

    public function getTasks(): ?array
    {
        return $this->tasks;
    }

    /**
     * @throws ToDoTxtException
     */
    public function writeTasks(): self
    {
        $this->assertGoodToDoFile();
        file_put_contents($this->to_do_file, self::makeFileContentsFromTasks($this->tasks));

        return $this;
    }

    /**
     * @param list<ToDoTxtTask> $tasks
     */
    private static function makeFileContentsFromTasks(array $tasks): string
    {
        return implode(PHP_EOL, array_map(static fn(ToDoTxtTask $task) => (string)$task, $tasks));
    }
}