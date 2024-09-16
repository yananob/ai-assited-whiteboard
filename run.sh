#!/bin/bash
set -eu

echo "Running PHPStan..."
./vendor/bin/phpstan analyze -c phpstan.neon

php ./main.php
