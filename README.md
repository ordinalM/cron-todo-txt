# cron-todo-txt

todo.txt cron utilities

## Requirements

- Command line PHP 8.1 or higher
- `todo-txt` CLI - see http://todotxt.org/

## Installation

Copy `example-config.yml` to `config.yml` and edit the latter to include:

- the path to your repeat file
- the path to your `todo-txt` if it is not `/usr/bin/todo-txt`

## Usage

Run `bin/process` which has two possible parameters:

- `--debug` prints debug output
- `--live` adds files/makes changes to tasks; if not set, just does a dummy run

You can test everything is working with `bin/process --debug`

### Example crontab entry

```
*/15 * * * * /home/foo/code/cron-todo-txt/bin/process --live >> /home/foo/logs/cron-todo-txt.log 2>&1
```

to run every 15 minutes and output to `/home/foo/logs/cron-todo-txt.log`

## Repeat file format

```
<datetime string> <todo> (repeat|rec:<interval>)
```

Blank lines and ones starting with `#` are ignored.

The datetime string can be anything parsed by PHP's `strtotime`, but must not have any spaces in it. ISO-8601 datetimes are ideal and what it will use when creating recurring tasks - see below. It can include a time as well.

Everything in the `<todo>` will be added as a task with `todo-txt`, including priorities, contexts, and groups.

With no `repeat` or `rec` attribute, the todo will be removed. If there is one it will attempt to parse the value as per <https://www.php.net/manual/en/dateinterval.construct.php> - it will convert case and add "P" at the start if not present, so should pick up simple cases like "5d" (every 5 days) or "1y" (every year). It will then add a new entry in the repeat file

e.g.

```
# This is a comment line

2024-06-13 Write cron-todo-txt module
2024-06-14 Fix bugs in it repeat:1d
```

### In case of error in the file

If the code is unable to parse a date or line it will output an error and also comment out the line concerned (if run with `--live`). It will process any other lines as usual.
