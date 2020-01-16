#!/usr/bin/env bash
WORK_DIR="./"
php bin/console swoole:start -v -d
LOCKING=0
fswatch -e ".*" -i "\.php$" ${WORK_DIR} | while read file
do
    if [ ${LOCKING} -eq 1 ] ;then
        echo "Reloading, skipped."
        continue
    fi
    echo "File ${file} has been modified."
    LOCKING=1
    php bin/console swoole:reload -v
    LOCKING=0
done
exit 0