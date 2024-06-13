<?php

namespace OrdinalM\CronTodoTxt;

use DateInterval;
use DateTimeImmutable;
use Throwable;

class CronToDoTxt
{
    private const REPEAT_REGEX = '/ (repeat|rec):([^ ]+)/';
    private DateTimeImmutable $Now;
    private Config $config;

    public function __construct(private readonly bool $debug = false, private readonly bool $live = false)
    {
        $this->Now = new DateTimeImmutable();
        $this->config = new Config();
    }

    public function processRepeats(): void
    {
        $repeat_file = $this->config->getValue(Config::KEY_REPEAT_FILE);
        if (!file_exists($repeat_file)) {
            $this->log('ERROR: File not found: ' . $repeat_file);
            return;
        }
        if (!is_writable($repeat_file)) {
            $this->log('ERROR: File is not writable: ' . $repeat_file);
            return;
        }

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

                $repeat = self::getRepeatInterval($line);
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

                if (!$new_date) {
                    $this->log(sprintf("Dropping line %d: %s", $n, $line));
                    $changes_made = true;
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

        if (count($to_add) === 0) {
            $this->debug('No tasks to add');
        }

        if (!$this->live) {
            $this->log('Not live, will not change anything');

            return;
        }

        foreach ($to_add as $todo) {
            $this->runToDoTxtAdd($todo);
        }

        if (!$changes_made) {
            return;
        }

        $this->log('Changes made, writing new file to ' . $repeat_file);
        file_put_contents($repeat_file, implode(PHP_EOL, $new_lines));
    }

    private function log(string $message): void
    {
        echo sprintf("[%s] %s\n", date('c'), trim($message));
    }

    private function debug(string $message): void
    {
        if (!$this->debug) {
            return;
        }
        $this->log('DEBUG: ' . $message);
    }

    /**
     * @throws CronToDoTxtException
     */
    private static function getRepeatInterval(string $line): ?DateInterval
    {
        $repeat = preg_match(self::REPEAT_REGEX, $line, $matches);
        if (!$repeat) {
            return null;
        }
        $repeat_string = strtoupper($matches[2]);
        try {
            // In case I forget to add a P at the start
            if (!str_starts_with($repeat_string, 'P')) {
                $repeat_string = 'P' . $repeat_string;
            }
            return new DateInterval($repeat_string);
        } catch (Throwable) {
            throw new CronToDoTxtException('Could not parse repeat interval: ' . $repeat_string);
        }
    }

    private static function stripRepeatFromToDo(string $todo): string
    {
        return preg_replace(self::REPEAT_REGEX, '', $todo);
    }

    private function runToDoTxtAdd(string $todo): void
    {
        $todotxt_command = sprintf("/usr/bin/todo-txt add '%s'", str_replace("'", "\'", $todo));
        $this->log('Running ' . $todotxt_command);
        exec($todotxt_command, $output);
        echo implode(PHP_EOL, $output) . PHP_EOL;
    }
}
