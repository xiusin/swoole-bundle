#!/usr/bin/env bash
WORK_DIR=$1
if [ ! -n "${WORK_DIR}" ] ;then
    WORK_DIR="."
fi

php bin/console swoole:start -v -d true

WORK_DIR="${WORK_DIR}/src/"

echo "starting watch ${WORK_DIR}..."
LOCKING=0
event=""
fswatch -x -l 2 -e ".*" -i "\\.php$" -r ${WORK_DIR} | while read file event
do
    if [ "${event}" = "PlatformSpecific" ]; then
        continue
    fi

    if [[ ! ${file} =~ .php$ ]] ;then
        continue
    fi
    if [ ${LOCKING} -eq 1 ] ;then
        echo "Reloading, skipped."
        continue
    fi
    echo "File ${file} has been modified. E:[${event}]"
    LOCKING=1
    php bin/console swoole:reload -v
    LOCKING=0
done
exit 0