<?php

namespace OrdinalM\CronTodoTxt;

use JsonSerializable;

class ToDoTxtPriority implements JsonSerializable
{
    /**
     * @throws ToDoTxtException
     */
    public function __construct(private string $priority_code)
    {
        $this->priority_code = strtoupper($this->priority_code);
        $this->priority_code = trim($this->priority_code, '() ');
        $this->assertValidPriorityCode();
    }

    /**
     * @throws ToDoTxtException
     */
    private function assertValidPriorityCode(): void
    {
        if (preg_match('/^[A-Z]/', $this->priority_code)) {
            return;
        }

        throw new ToDoTxtException('Invalid priority code "' . $this->priority_code . '"');
    }

    public function __toString(): string
    {
        return $this->priority_code;
    }

    public function display(): string
    {
        return '(' . $this->priority_code . ')';
    }

    public function jsonSerialize(): ?string
    {
        return $this->priority_code;
    }
}