<?php


require_once __DIR__.'/../../IOFrame/Util/TimingMeasurer.php';
$timingMeasurer = new \IOFrame\Util\TimingMeasurer();
echo 'Start time:'.$timingMeasurer->start().EOL;
usleep(250000);
echo 'Time elapsed after usleep(250000): '.$timingMeasurer->timeElapsed().EOL;
$timingMeasurer->waitUntil(microtime(true)+0.200000);
echo 'Time, after another 0.2000000 ns delay:'.$timingMeasurer->timeElapsed().EOL;
$timingMeasurer->waitUntilTimeElapsed(1);
echo 'Time, waited until 1 second elapsed from start:'.$timingMeasurer->timeElapsed().EOL;
usleep(100);
$timingMeasurer->waitUntilIntervalElapsed(1);
echo 'After another usleep(100), waited until 1 second interval from the start:'.$timingMeasurer->timeElapsed().EOL;
usleep(150000);
echo 'Stop time, after another usleep(150000):'.$timingMeasurer->stop().EOL;
echo 'Time elapsed (timer stopped by now): '.$timingMeasurer->timeElapsed().EOL;