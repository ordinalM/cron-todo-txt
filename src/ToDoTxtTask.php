<?php

namespace OrdinalM\CronTodoTxt;

class ToDoTxtTask
{
    private ?int $created = null;
    private ?int $completed = null;
    private string $text = '';
    private bool $done = false;

    /**
     * @throws ToDoTxtException
     */
    public static function parseFromString(string $line): self
    {
        if (!preg_match('/^(x )?(\d{4}-\d{2}-\d{2} )?(\d{4}-\d{2}-\d{2} )?(.*)$/', trim($line), $matches)) {
            throw new ToDoTxtException('Could not parse: ' . $line);
        }

        $done = $matches[1] !== '';
        $date1 = strtotime(trim($matches[2])) ?: null;
        $date2 = strtotime(trim($matches[3])) ?: null;

        $task = (new self())->setText($matches[4])->setDone($done);
        if ($date2) {
            $task->setCompleted($date1)->setCreated($date2);
        } elseif ($date1) {
            $task->setCreated($date1);
        }

        return $task;
    }

    public function __toString(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $line = [];
        if ($this->done) {
            $line[] = 'x';
        }
        if ($this->completed) {
            $line[] = self::makeDateString($this->completed);
            // Must have a created date if it has a completed date per spec
            $line[] = self::makeDateString($this->created);
        } elseif ($this->created) {
            $line[] = self::makeDateString($this->created);
        }
        $line[] = $this->text;

        return implode(' ', $line);
    }

    public function isEmpty(): bool
    {
        return $this->text === '';
    }

    private static function makeDateString(int $date): string
    {
        return date('Y-m-d', $date);
    }

    public function getContexts(): array
    {
        return $this->getWordsWithPrefix('@');
    }

    private function getWordsWithPrefix(string $prefix): array
    {
        $prefix_words = [];
        $todo_words = explode(" ", $this->text);
        foreach ($todo_words as $component) {
            if (!str_starts_with($component, $prefix)) {
                continue;
            }
            $prefix_words[] = substr($component, strlen($prefix));
        }

        return array_unique($prefix_words);
    }

    public function getProjects(): array
    {
        return $this->getWordsWithPrefix('+');
    }

    public function getCreated(): ?int
    {
        return $this->created;
    }

    public function setCreated(?int $created): ToDoTxtTask
    {
        $this->created = $created;
        return $this;
    }

    public function getCompleted(): ?int
    {
        return $this->completed;
    }

    public function setCompleted(?int $completed): ToDoTxtTask
    {
        $this->completed = $completed;
        return $this;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function setDone(bool $done): ToDoTxtTask
    {
        $this->done = $done;
        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): ToDoTxtTask
    {
        $this->text = $text;
        return $this;
    }

    public function markDone(?int $completed = null): self
    {
        if ($this->done) {
            return $this;
        }

        if (!$completed) {
            $completed = time();
        }
        $this->completed = $completed;
        if (!$this->created) {
            $this->created = $completed;
        }
        $this->done = true;

        return $this;
    }

    public function markNotDone(): self
    {
        if (!$this->done) {
            return $this;
        }

        $this->completed = null;
        $this->done = false;

        return $this;
    }
}