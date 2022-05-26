#!/usr/bin/env bash
WORK_DIR=$1
if [ ! -n "${WORK_DIR}" ] ;then
    WORK_DIR="."
fi

php bin/console swoole:start -v -d true

echo "starting watch..."
LOCKING=0
fswatch -e ".*" -i "\\.php$" -r ${WORK_DIR} | while read file
do
    if [[ ! ${file} =~ .php$ ]] ;then
        continue
    fi
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