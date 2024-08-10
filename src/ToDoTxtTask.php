<?php

namespace OrdinalM\CronTodoTxt;

use JsonSerializable;

class ToDoTxtTask implements JsonSerializable
{
    private const KEY_PRIORITY = 'priority';
    private const KEY_CREATED = 'created';
    private const KEY_COMPLETED = 'completed';
    private const KEY_DONE = 'done';
    private const KEY_TEXT = 'text';
    private ?int $created = null;
    private ?int $completed = null;
    private string $text = '';
    private bool $done = false;
    private ?ToDoTxtPriority $priority = null;

    /**
     * @throws ToDoTxtException
     */
    public static function parseFromString(string $line): self
    {
        if (!preg_match('/^(x )?(\([A-Za-z]\) )?(\d{4}-\d{2}-\d{2} )?(\d{4}-\d{2}-\d{2} )?(.*)$/', trim($line), $matches, PREG_UNMATCHED_AS_NULL)) {
            throw new ToDoTxtException('Could not parse: ' . $line);
        }

        $done = $matches[1] === 'x';
        $date1 = strtotime(trim($matches[3])) ?: null;
        $date2 = strtotime(trim($matches[4])) ?: null;

        $task = (new self())->setText($matches[5])->setDone($done);

        $priority_code = $matches[2] ?: null;
        if ($priority_code) {
            $task->setPriority(new ToDoTxtPriority($priority_code));
        }

        if ($date1 && $date2) {
            $task->setCompleted($date1)->setCreated($date2);
        } elseif ($date1) {
            $task->setCreated($date1);
        }

        return $task;
    }

    public function setPriority(?ToDoTxtPriority $priority): ToDoTxtTask
    {
        $this->priority = $priority;
        return $this;
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
        if ($this->priority) {
            $line[] = $this->priority->display();
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

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        return [
            self::KEY_DONE => $this->done,
            self::KEY_PRIORITY => $this->priority,
            self::KEY_CREATED => $this->created,
            self::KEY_COMPLETED => $this->completed,
            self::KEY_TEXT => $this->text,
        ];
    }

    public function findTags(): array
    {
        if (!preg_match_all('/([a-z]+):([^ ]+)/', $this->text, $matches, PREG_SET_ORDER)) {
            return [];
        }
        $tags = [];
        foreach ($matches as $tag) {
            // Don't add URLs as tags
            $url_scheme = parse_url($tag[0], PHP_URL_SCHEME);
            if (in_array($url_scheme, ['http', 'https', 'ftp'])) {
                continue;
            }
            $tags[$tag[1]] = $tag[2];
        }
        return $tags;
    }
}