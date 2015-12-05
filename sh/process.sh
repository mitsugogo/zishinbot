#!/bin/sh
BASEDIR="/usr/local/mitsugogo/zishinbot"
cd $BASEDIR

LOG="current.log"
PID="current.pid"

start(){
  DATE=`date`
  echo "" >> $LOG
  echo "######## start - $DATE" >> $LOG
  nohup php -d max_execution_time=0 /usr/local/mitsugogo/zishinbot/zishin.php >> $LOG &
  echo $! > $PID
}

stop(){
  DATE=`date`
  echo "" >> $LOG
  echo "######## stop - $DATE" >> $LOG
  kill `cat $PID`;
  rm $PID
}

case "$1" in
  start)
    start
    ;;
  stop)
    stop
    ;;
  restart)
    stop
    start
    ;;
  *)
    echo "Usage: s2quartz {start|stop|restart}"
    exit 1
esac
