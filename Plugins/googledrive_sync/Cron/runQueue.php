<?php

use FacturaScripts\Core\Base\MiniLog;
use FacturaScripts\Core\CrashReport;
use FacturaScripts\Core\Kernel;
use FacturaScripts\Core\Plugins;
use FacturaScripts\Core\Tools;
use FacturaScripts\Plugins\googledrive_sync\Lib\SyncWorker;
use FacturaScripts\Plugins\googledrive_sync\Model\GoogleDriveQueue;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

if (!defined('FS_FOLDER')) {
    define('FS_FOLDER', dirname(__DIR__, 3));
}

$configFile = FS_FOLDER . '/config.php';
if (!file_exists($configFile)) {
    echo '[GoogleDriveSync] config.php not found. Aborting queue execution.' . PHP_EOL;
    exit(0);
}

require_once $configFile;

@set_time_limit(0);
ignore_user_abort(true);

CrashReport::init();
Kernel::init();
Plugins::init();

$options = getopt('', ['company::', 'limit::', 'release::']);
$companyId = null;
if (isset($options['company']) && $options['company'] !== '') {
    $company = (int)$options['company'];
    if ($company > 0) {
        $companyId = $company;
    }
}

$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 25;
$releaseTtl = isset($options['release']) ? max(60, (int)$options['release']) : 900;

$worker = new SyncWorker();
$released = SyncWorker::releaseStaleJobs($releaseTtl);

$processed = 0;
$errors = 0;
for ($i = 0; $i < $limit; $i++) {
    $job = $worker->processNext($companyId);
    if (!$job instanceof GoogleDriveQueue) {
        break;
    }

    $processed++;
    if ($job->state === 'failed') {
        $errors++;
    }
}

$message = sprintf('[GoogleDriveSync] processed %d job(s), released %d stale, %d error(s)', $processed, $released, $errors);
echo $message . PHP_EOL;

Tools::log()->notice('googledrive-sync-cron-summary', [
    '%processed%' => $processed,
    '%released%' => $released,
    '%errors%' => $errors,
]);

MiniLog::save();

exit($errors > 0 ? 2 : 0);
