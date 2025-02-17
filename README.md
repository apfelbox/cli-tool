CLI Tool
========

A small CLI tool that implements a suite of different helpers.


Installation
------------

1. Check out the repository
2. Run `composer install --optimize-autoloader --classmap-authoritative`
3. Symlink the `bin/tool` to `/usr/local/bin`.



Usage
-----

Just call the command you wish to use.

Here is an overview of all available commands:

### `check-dns`

Pass a file path to a TXT file containing one domain per line, to check the DNS for all domains in this file.
