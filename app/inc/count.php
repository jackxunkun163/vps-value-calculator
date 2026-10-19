<?php
/* 计数器：data/count.json 存储当日与累计计算次数，跨日自动重置 */

function vps_count_file()
{
    return VPS_DATA_DIR . '/count.json';
}

function vps_read_counts()
{
    vps_ensure_dirs();
    $today = date('Y-m-d');
    $data = null;
    if (is_file(vps_count_file())) {
        $data = json_decode((string)@file_get_contents(vps_count_file()), true);
    }
    if (!is_array($data) || !isset($data['date'], $data['today'], $data['total'])) {
        $data = ['date' => $today, 'today' => 0, 'total' => 0];
    }
    if ($data['date'] !== $today) {
        $data = ['date' => $today, 'today' => 0, 'total' => (int)$data['total']];
    }
    return $data;
}

function vps_bump_counts()
{
    vps_ensure_dirs();
    $fh = fopen(vps_count_file(), 'c+');
    if (!$fh) {
        return vps_read_counts();
    }
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    $today = date('Y-m-d');
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data) || !isset($data['date'], $data['today'], $data['total'])) {
        $data = ['date' => $today, 'today' => 0, 'total' => 0];
    }
    if ($data['date'] !== $today) {
        $data['date'] = $today;
        $data['today'] = 0;
    }
    $data['today'] = (int)$data['today'] + 1;
    $data['total'] = (int)$data['total'] + 1;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $data;
}
