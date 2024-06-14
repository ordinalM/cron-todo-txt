# cron-todo-txt

todo.txt cron utilities

## Requirements

- Command line PHP 8.1 or higher
- `todo-txt` CLI - see http://todotxt.org/
- `composer` - see https://getcomposer.org/

## Installation

- Run `composer install` from the project root.
- Install `bin/schedule` as an action for `todo-txt` - see https://github.com/todotxt/todo.txt-cli/wiki/Creating-and-Installing-Add-ons. It is recommended that you symlink to the file in the project from your actions directory e.g.

```shell
mkdir -p ~/.todo.actions.d
cd ~/.todo.actions.d
ln -s /path/to/this/project/bin/schedule
```

## Usage

### Scheduling a task

Add the task to your `todo.txt` as normal and then use
```
todo-txt schedule add <n> <date to schedule to> (<optional repeat>)
```
e.g.
```shell
todo-txt schedule add 1 tomorrow 1w
```

### Listing scheduled tasks
```
todo-txt schedule ls|list (<optional search term>)
```

### Processing the schedule

Run `todo-txt schedule process` to add any scheduled tasks that should be added right now. It has one possible parameter, `live` - if this is set it will actually make the changes, otherwise it will just print debug output which is perfectly safe.

#### Example crontab entry

```
*/15 * * * * /usr/bin/todo-txt schedule process live >> /home/foo/logs/cron-todo-txt.log 2>&1
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

If the code is unable to parse a date or line it will output an error and also comment out the line concerned (if run with `live`). It will process any other lines as usual.

## TODO

- [x] Convert to have functions running as todo-txt addons
- [ ] Proper tests
- [ ] Structs for the scheduled file
- [ ] Split out the `ToDoTxt*` classes into a separate module for handling `todo-txt` files in PHP.
- [ ] A command for users to delete repeated tasks
- [ ] A command to add repeated tasks directly, not just from the existing task list
