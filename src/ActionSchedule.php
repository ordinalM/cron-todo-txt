<?php

namespace OrdinalM\CronTodoTxt;

use DateInterval;
use DateTimeImmutable;
use Exception;
use Throwable;

class ActionSchedule
{
    private const REPEAT_REGEX = '/ (repeat|rec):([^ ]+)/';
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
                ActionSchedule::makeRepeatIntervalFromString($recur);
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
        $todotxt_file->dropLine($task_n)->writeTasks();
        echo "Removed from todo.txt\n";

        return 0;

    }

    private function echoUsage(): void
    {
        echo <<<USAGE
Usage:
    todo-txt $this->command add <task number> <date to schedule to> (<recurrence frequency>)
    todo-txt $this->command process (live)
    todo-txt $this->command ls|list (<search term>)
    todo-txt $this->command edit

USAGE;
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

    /**
     * @throws Exception
     */
    public function addToRepeatFile(DateTimeImmutable $date, ToDoTxtTask $task, ?string $repeat_every): void
    {
        $current_contents = file_get_contents($this->repeat_file);

        // Remove any created and completed dates and done flag before adding to file
        $insert_task = (clone $task)->setCreated(null)->setDone(false)->setCompleted(null);

        if ($repeat_every) {
            // Assert it's a valid interval
            self::makeRepeatIntervalFromString($repeat_every);
            $insert_task->setTag('repeat', $repeat_every);
        }

        $new_line = $date->format('c') . ' ' . $insert_task;
        file_put_contents($this->repeat_file, rtrim($current_contents) . "\n" . $new_line);
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

    public function actionScheduleProcess(array $argv): int
    {
        $is_live = ($argv[0] ?? false) === 'live';
        $this->debug = !$is_live;
        $this->live = $is_live;

        $repeat_file = $this->repeat_file;

        $to_add = [];
        $new_lines = [];
        $changes_made = false;

        foreach (file($repeat_file) as $n => $line) {
            try {
                $line = trim($line);
                if (str_starts_with($line, '#') || !$line) {
                    // Keep comments and blanks
                    $new_lines[] = $line;
                    continue;
                }
                $this->debug('Processing: ' . $line);
                if (!preg_match('/^([^ ]+) (.*)$/', $line, $matches)) {
                    throw new CronToDoTxtException('Invalid repeat line format: ' . $line);
                }
                [, $raw_time_string, $todo] = $matches;
                $timestamp = strtotime($raw_time_string);
                if (!$timestamp) {
                    throw new CronToDoTxtException('Could not parse date from: ' . $line);
                }
                $date = (new DateTimeImmutable())->setTimestamp($timestamp);
                $this->debug('Parsed date ' . $date->format('c'));

                $repeat = self::getRepeatIntervalFromLine($line);
                if ($repeat) {
                    $new_date = $date->add($repeat);
                    $this->debug('Parsed repeat interval, new date would be ' . $new_date->format('c'));
                } else {
                    $new_date = null;
                    $this->debug('Does not repeat');
                }

                if ($date > $this->Now) {
                    $this->debug('Is in future, skipping');
                    $new_lines[] = $line;
                    continue;
                }

                $to_add[] = self::stripRepeatFromToDo($todo);

                // There will always be some sort of change to the schedule file if we reach this point
                $changes_made = true;

                if (!$new_date) {
                    $this->log(sprintf("Dropping line %d: %s", $n, $line));
                    continue;
                }

                $new_line = $new_date->format('c') . ' ' . $todo;
                $this->log(sprintf("Will change line %d to: %s", $n, $new_line));
                $new_lines[] = $new_line;
            } catch (Throwable $throwable) {
                $this->log(sprintf("EXCEPTION: %s on line %d, will comment out: %s", get_class($throwable), $n + 1, $throwable->getMessage()));
                $new_lines[] = '# ' . $line;
                $changes_made = true;
            }
        }

        $this->addNewToDos($to_add);
        $this->writeChangedScheduleFile($changes_made, $new_lines, $repeat_file);

        return 0;
    }

    /**
     * @throws CronToDoTxtException
     */
    private static function getRepeatIntervalFromLine(string $line): ?DateInterval
    {
        $repeat = preg_match(self::REPEAT_REGEX, $line, $matches);
        if (!$repeat) {
            return null;
        }
        $repeat_string = $matches[2];
        try {
            return self::makeRepeatIntervalFromString($repeat_string);
        } catch (Throwable) {
            throw new CronToDoTxtException('Could not parse repeat interval: ' . $repeat_string);
        }
    }

    private static function stripRepeatFromToDo(string $todo): string
    {
        return preg_replace(self::REPEAT_REGEX, '', $todo);
    }

    private function addNewToDos(array $to_add): void
    {
        if (count($to_add) === 0) {
            $this->debug('No tasks to add');

            return;
        }

        if (!$this->live) {
            $this->log('Not live, will not add new tasks');

            return;
        }

        foreach ($to_add as $todo) {
            $this->runToDoTxtAdd($todo);
        }
    }

    private function runToDoTxtAdd(string $todo): void
    {
        $this->log('Adding ' . $todo);
        echo $this->todotxt->runCommand('add', [$todo]) . "\n";
    }

    private function writeChangedScheduleFile(bool $changes_made, array $new_lines, string $repeat_file): void
    {
        if (!$changes_made) {
            $this->debug('No changes to schedule file');

            return;
        }

        $new_file_contents = implode(PHP_EOL, $new_lines);
        $this->debug("New file contents:\n---\n" . $new_file_contents . "\n---\n");

        if (!$this->live) {
            $this->log('Not live, will not write changes');

            return;
        }

        $this->log('Changes made, writing new file to ' . $repeat_file);
        file_put_contents($repeat_file, implode(PHP_EOL, $new_lines));

    }

    private function actionScheduleEdit(): int
    {
        $pipes = [];
        $h_proc = proc_open('editor "' . $this->repeat_file . '"', [STDIN, STDOUT, STDERR], $pipes);

        return proc_close($h_proc);
    }
}
