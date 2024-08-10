<?php

namespace OrdinalM\CronTodoTxt;

use DateTimeImmutable;
use Exception;
use Throwable;

class ActionSchedule
{
    private const SCHEDULED_FILE = 'scheduled.txt';
    private readonly DateTimeImmutable $Now;
    private readonly ToDoTxt $todotxt;
    private readonly string $repeat_file;
    private string $command = '';
    private bool $debug;
    private bool $live;

    /**
     * @throws CronToDoTxtException
     */
    public function __construct(bool $debug = false, bool $live = false)
    {
        $this->live = $live;
        $this->debug = $debug;
        $this->Now = new DateTimeImmutable();
        $this->todotxt = new ToDoTxt();
        $this->initRepeatFile();
    }

    /**
     * @throws CronToDoTxtException
     */
    private function initRepeatFile(): void
    {
        if (!$_SERVER['TODO_DIR'] ?? null) {
            throw new CronToDoTxtException('TODO_DIR must be defined - are you running from within todo-txt?');
        }
        $this->repeat_file = $_SERVER['TODO_DIR'] . '/' . self::SCHEDULED_FILE;
        $this->debug('Checking file ' . $this->repeat_file);
        if (!file_exists($this->repeat_file)) {
            touch($this->repeat_file);
            $this->log('Created ' . $this->repeat_file);
        }
        if (!is_writable($this->repeat_file)) {
            throw new CronToDoTxtException('ERROR: File is not writable: ' . $this->repeat_file);
        }
    }

    private function debug(string $message): void
    {
        if (!$this->debug) {
            return;
        }
        $this->log('DEBUG: ' . $message);
    }

    private function log(string $message): void
    {
        echo sprintf("[%s] %s\n", date('c'), trim($message));
    }

    /**
     * @throws CronToDoTxtException
     * @throws ToDoTxtException
     */
    public function action(array $argv): int
    {
        // first arg is the full shell command which we don't need
        array_shift($argv);
        // second arg is the basic action
        $this->command = array_shift($argv);
        // third arg is the sub-action that was chosen by the user
        $sub_action = array_shift($argv);
        switch ($sub_action) {
            case 'add':
                return $this->actionScheduleAdd($argv);
            case 'ls':
            case 'list':
                return $this->actionScheduleList($argv);
            case 'process':
                return $this->actionScheduleProcess($argv);
            case 'edit':
                return $this->actionScheduleEdit();
            case 'pull':
                return $this->actionSchedulePull($argv);
        }
        $this->echoUsage();
        return 0;
    }

    public function actionScheduleAdd(array $argv): int
    {
        try {
            return $this->innerActionScheduleAdd($argv);
        } catch (Throwable $throwable) {
            echo "ERROR: " . $throwable->getMessage();
            return 1;
        }
    }

    /**
     * @throws ToDoTxtException
     * @throws Exception
     */
    private function innerActionScheduleAdd(array $argv): int
    {
        $todotxt_file = ToDoTxtFile::loadFromFile();

        if (count($argv) < 2) {
            $this->echoUsage();
            return 0;
        }

        $task_n = (int)$argv[0];
        $task = $todotxt_file->getTask($task_n);
        if (!$task || $task->isEmpty()) {
            echo "ERROR: no such line $task_n\n";
            exit(1);
        }
        echo sprintf("Will schedule this task:\n%s\n", $task);
        if ($task->isDone()) {
            echo "WARNING: this task has been marked complete - will remove this flag when scheduling.\n";
        }

        $date_raw = $argv[1] ?? '';
        $schedule_date = strtotime($date_raw);
        if (!$schedule_date) {
            echo "ERROR: bad schedule date $date_raw\n";
            return 1;
        }
        echo sprintf("Scheduling until: %s\n", date('c', $schedule_date));

        $recur = $argv[2] ?? null;
        if ($recur) {
            try {
                ScheduledTask::makeRepeatIntervalFromString($recur);
            } catch (Exception) {
                echo "ERROR: could not parse recurrence interval $recur\n";
                return 1;
            }
            echo "Recurring every $recur\n";
        } else {
            echo "Not recurring\n";
        }

        echo 'Confirm? [yN] ';
        $confirm = trim(fgets(STDIN)) === 'y';
        if (!$confirm) {
            echo "Cancelled\n";
            return 0;
        }

        $this->addToRepeatFile((new DateTimeImmutable())->setTimestamp($schedule_date), $task, $recur);
        echo "Added to repeat file\n";
        $todotxt_file->deleteTask($task_n)->writeToFile();
        echo "Removed from todo.txt\n";

        return 0;

    }

    private function echoUsage(): void
    {
        echo <<<USAGE
Usage:
    todo-txt $this->command add <task number> <date to schedule to> (<recurrence frequency>)
    todo-txt $this->command process (live)
    todo-txt $this->command pull (live)
    todo-txt $this->command ls|list (<search term>)
    todo-txt $this->command edit

USAGE;
    }

    /**
     * @throws Exception
     */
    public function addToRepeatFile(DateTimeImmutable $date, ToDoTxtTask $task, ?string $repeat_every): void
    {
        $repeat_file = ScheduledFile::createFromFile($this->repeat_file);

        // Remove any created and completed dates and done flag before adding to file
        $scheduled_task = ScheduledTask::createFromTask($task)->setThreshold($date);

        if ($repeat_every) {
            // Assert it's a valid interval
            ScheduledTask::makeRepeatIntervalFromString($repeat_every);
            $scheduled_task->setRepeat($repeat_every);
        }

        $repeat_file->addScheduledTask($scheduled_task)->writeToFile($this->repeat_file);
    }

    public function actionScheduleList(array $argv): int
    {
        try {
            $search = $argv[0] ?? null;
            $scheduled = file($this->repeat_file, FILE_IGNORE_NEW_LINES);
            if ($search) {
                $scheduled = array_filter($scheduled, static fn(string $line) => str_contains($line, $search));
            }
            echo implode("\n", $scheduled) . "\n---\n$this->repeat_file\n";
            return 1;
        } catch (Throwable $throwable) {
            echo "ERROR: " . $throwable->getMessage();
            return 1;
        }
    }

    /**
     * @throws CronToDoTxtException
     * @throws ToDoTxtException
     * @throws Exception
     */
    public function actionScheduleProcess(array $argv): int
    {
        $this->setLiveDebugFlags($argv);

        $repeat_file = ScheduledFile::createFromFile($this->repeat_file);

        $to_add = [];
        $new_scheduled_file = new ScheduledFile();
        $changes_made = false;

        foreach ($repeat_file as $n => $scheduled_task) {
            $this->debug('Processing: ' . $scheduled_task);
            $threshold_date = $scheduled_task->getThreshold();
            $this->debug('Parsed date ' . $threshold_date->format('c'));

            $repeat = $scheduled_task->getRepeat();
            if ($repeat) {
                $new_date = $threshold_date->add($repeat);
                $this->debug('Parsed repeat interval, new date would be ' . $new_date->format('c'));
            } else {
                $new_date = null;
                $this->debug('Does not repeat');
            }

            if ($threshold_date > $this->Now) {
                $this->debug('Is in future, skipping');
                $new_scheduled_file->addScheduledTask($scheduled_task);
                continue;
            }

            $to_add[] = $scheduled_task->makeInsertableTask();

            // There will always be some sort of change to the schedule file if we reach this point
            $changes_made = true;

            if (!$new_date) {
                $this->log(sprintf("Dropping line %d: %s", $n, $scheduled_task));
                continue;
            }

            $new_scheduled_task = (clone $scheduled_task)->setThreshold($new_date);
            $this->log(sprintf("Will change line %d to:\n%s", $n, $new_scheduled_task));
            $new_scheduled_file->addScheduledTask($new_scheduled_task);
        }

        $this->addNewToDos($to_add);
        if ($changes_made) {
            $this->writeChangedScheduleFile($new_scheduled_file);
        }
        return 0;
    }

    private function setLiveDebugFlags(array $argv): void
    {
        $is_live = ($argv[0] ?? false) === 'live';
        $this->debug = !$is_live;
        $this->live = $is_live;
    }

    /**
     * @param list<ToDoTxtTask> $to_add
     */
    private function addNewToDos(array $to_add): void
    {
        if (count($to_add) === 0) {
            $this->debug('No tasks to add');

            return;
        }

        $this->debug("Tasks to add:\n" . implode("\n", $to_add));

        if (!$this->live) {
            $this->log('Not live, will not add new tasks');

            return;
        }

        foreach ($to_add as $todo) {
            $this->runToDoTxtAdd($todo);
        }
    }

    private function runToDoTxtAdd(ToDoTxtTask $todo): void
    {
        $this->log('Adding: ' . $todo);
        echo $this->todotxt->runCommand('add', [$todo]) . "\n";
    }

    private function writeChangedScheduleFile(ScheduledFile $scheduled_file): void
    {
        if (!$this->live) {
            $this->log('Not live, will not write changes');

            return;
        }

        $this->log('Changes made, writing new file to ' . $this->repeat_file);
        $scheduled_file->writeToFile($this->repeat_file);
        $this->log("$this->repeat_file now:\n" . file_get_contents($this->repeat_file));
    }

    private function actionScheduleEdit(): int
    {
        $pipes = [];
        $h_proc = proc_open('editor "' . $this->repeat_file . '"', [STDIN, STDOUT, STDERR], $pipes);

        return proc_close($h_proc);
    }

    /**
     * @throws ToDoTxtException
     */
    private function actionSchedulePull(array $argv): int
    {
        $this->setLiveDebugFlags($argv);

        // Load existing files
        $todo_file = ToDoTxtFile::loadFromFile();
        $scheduled = ScheduledFile::createFromFile($this->repeat_file);
        $changes_made = false;

        foreach ($todo_file as $n => $task) {
            $threshold = $task->getDateTag(ToDoTxtTask::TAG_THRESHOLD);
            if (!$threshold) {
                continue;
            }
            $this->debug(sprintf("Found threshold tag %s in line %d:\n%s", $threshold->format('c'), $n + 1, $task));
            if ($threshold <= $this->Now) {
                $this->debug('In the past, ignoring');
                continue;
            }
            $changes_made = true;
            $scheduled_task = ScheduledTask::createFromTask($task);
            $this->log(sprintf("Will remove task %d and insert into scheduled list:\n%s", $n, $scheduled_task));
            $todo_file->deleteTask($n + 1);
            $scheduled->addScheduledTask($scheduled_task);
        }

        if (!$changes_made) {
            $this->log('No changes made');

            return 0;
        }

        $this->debug("New scheduled file:\n" . $scheduled);
        $this->debug("New todo file:\n" . $todo_file);
        if (!$this->live) {
            $this->log('Not live, will not make changes');

            return 0;
        }

        $this->log('Writing changes');
        $scheduled->writeToFile($this->repeat_file);
        $todo_file->writeToFile();

        return 0;
    }
}
