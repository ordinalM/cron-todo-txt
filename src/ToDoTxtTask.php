<?php

namespace OrdinalM\CronTodoTxt;

use DateTimeImmutable;
use JsonSerializable;

class ToDoTxtTask implements JsonSerializable
{
    private const KEY_PRIORITY = 'priority';
    private const KEY_CREATED = 'created';
    private const KEY_COMPLETED = 'completed';
    private const KEY_DONE = 'done';
    private const KEY_TEXT = 'text';
    private const PREFIX_PROJECT = '+';
    private const PREFIX_CONTEXT = '@';
    public const TAG_THRESHOLD = 't';

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

        $done = !empty($matches[1]);
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

    public function findContexts(): array
    {
        return $this->findWordsWithPrefix('@');
    }

    private function findWordsWithPrefix(string $prefix): array
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

    public function hasContext(string $context): bool
    {
        return $this->hasWordWithPrefix($context, self::PREFIX_PROJECT);
    }

    private function hasWordWithPrefix(string $context, string $prefix)
    {
        return str_contains($this->text, $prefix . $context);
    }

    public function findProjects(): array
    {
        return $this->findWordsWithPrefix(self::PREFIX_PROJECT);
    }

    public function addProject(string $project): self
    {
        if ($this->hasProject($project)) {
            return $this;
        }
        $this->appendToText(self::PREFIX_PROJECT . $project);

        return $this;
    }

    public function hasProject(string $project): bool
    {
        return $this->hasWordWithPrefix($project, self::PREFIX_CONTEXT);
    }

    private function appendToText(string $string): self
    {
        $this->text = trim($this->text) . ' ' . $string;

        return $this;
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

    private function toArray(): array
    {
        return [
            self::KEY_DONE => $this->done,
            self::KEY_PRIORITY => $this->priority,
            self::KEY_CREATED => $this->created,
            self::KEY_COMPLETED => $this->completed,
            self::KEY_TEXT => $this->text,
        ];
    }

    public function setTag(string $name, string $value): self
    {
        $tag_text = self::makeTagText($name, $value);
        $existing_tags = $this->findTags();

        // Add new tags at the end of the text
        if (!isset($existing_tags[$name])) {
            return $this->appendToText($tag_text);
        }

        // Replace existing tags with the new value
        $this->text = str_replace(self::makeTagText($name, $existing_tags[$name]), $tag_text, $this->text);

        return $this;
    }

    private static function makeTagText(string $name, string $value): string
    {
        return $name . ':' . $value;
    }

    /**
     * @return array<string, string>
     */
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

    public function getTag(string $name): ?string
    {
        return $this->findTags()[$name] ?? null;
    }

    public function getDateTag(string $name): ?DateTimeImmutable
    {
        $value = $this->getTag($name);
        if (!$value) {
            return null;
        }
        return (new DateTimeImmutable())->setTimestamp(strtotime($value));
    }

    public function deleteTag(string $name): self
    {
        $tags = $this->findTags($name);
        if (!isset($tags[$name])) {
            return $this;
        }
        // Remove the tag's text
        $this->text = str_replace(self::makeTagText($name, $tags[$name]), '', $this->text);

        return $this->cleanTrim();
    }

    private function cleanTrim(): self
    {
        // Strip extraneous spaces due to removal of text
        $this->text = trim(preg_replace('/ +/', ' ', $this->text));

        return $this;
    }
}