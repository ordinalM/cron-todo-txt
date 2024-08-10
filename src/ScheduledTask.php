<?php

namespace OrdinalM\CronTodoTxt;

use DateInterval;
use DateTimeImmutable;
use Exception;

class ScheduledTask
{
    private const TAG_REPEAT_INTERVAL = 'repeat';
    private ToDoTxtTask $task;

    public static function createFromTask(ToDoTxtTask $task): self
    {
        return (new self())->setTask(
            (clone $task)->setCreated(null)->setCompleted(null)->setDone(false)
        );
    }

    public function __toString(): string
    {
        return (string)$this->task;
    }

    public function setRepeat(?string $repeat_interval): self
    {
        $this->task->setTag(self::TAG_REPEAT_INTERVAL, $repeat_interval);

        return $this;
    }

    public function setThreshold(DateTimeImmutable $date): self
    {
        $this->task->setTag(ToDoTxtTask::TAG_THRESHOLD, $date->format('c'));

        return $this;
    }

    /**
     * @throws CronToDoTxtException
     */
    public function getThreshold(): DateTimeImmutable
    {
        $threshold_raw = $this->task->findTags()[ToDoTxtTask::TAG_THRESHOLD] ?? null;
        if (!$threshold_raw) {
            throw new CronToDoTxtException('Cannot find a threshold tag in ' . $this->task);
        }

        return (new DateTimeImmutable())->setTimestamp(strtotime($threshold_raw));
    }

    public function getTask(): ToDoTxtTask
    {
        return $this->task;
    }

    public function setTask(ToDoTxtTask $task): self
    {
        $this->task = $task;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function getRepeat(): ?DateInterval
    {
        $repeat = $this->task->getTag(self::TAG_REPEAT_INTERVAL);
        if (!$repeat) {
            return null;
        }

        return self::makeRepeatIntervalFromString($repeat);
    }

    /**
     * @throws Exception
     */
    public static function makeRepeatIntervalFromString(string $repeat_string): DateInterval
    {
        $repeat_string = strtoupper($repeat_string);
        // In case I forget to add a P at the start
        if (!str_starts_with($repeat_string, 'P')) {
            $repeat_string = 'P' . $repeat_string;
        }

        return new DateInterval($repeat_string);
    }

    public function makeInsertableTask(): ToDoTxtTask
    {
        // Clear the threshold and repeat tags
        return (clone $this->task)->deleteTag(ToDoTxtTask::TAG_THRESHOLD)->deleteTag(self::TAG_REPEAT_INTERVAL);
    }
}
