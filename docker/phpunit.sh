#!/bin/bash

# run the PHPUnit tests
docker exec -it expiring_cache_nginx_php ./vendor/bin/phpunit --testdox test